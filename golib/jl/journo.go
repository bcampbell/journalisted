package jl

import (
	"database/sql"
	"errors"
	"fmt"
	"github.com/dotcypress/phonetics"
	"github.com/lib/pq"
	"regexp"
	"strings"
	"time"
)

//var ErrNoMatchingJourno = errors.New("No matching journo")
var ErrAmbiguousJourno = errors.New("Ambiguous journo")

type Journo struct {
	ID          int
	Ref         string
	Prettyname  string
	Lastname    string
	Firstname   string
	Created     time.Time
	Status      string
	Oneliner    string
	LastSimilar pq.NullTime
	// Modified flag to indicate journo changed - handled by triggers in DB
	Modified           bool
	FirstnameMetaphone string
	LastnameMetaphone  string
	AdminNotes         string
	AdminTags          string
	Fake               bool
}

type UnresolvedJourno struct {
	Name string
	//	Email     string
	//	Twitter   string
	//	RelAuthor string
}

// create a brand new journo from a name
func CreateJourno(tx *sql.Tx, name string) (*Journo, error) {
	// hit the DB to generate a unique ref
	ref, err := uniqRef(tx, baseRef(name))
	if err != nil {
		return nil, err
	}

	//
	j := &Journo{}
	j.Status = "i"
	j.Ref = ref
	j.Prettyname = name
	j.Firstname, j.Lastname = splitName(name)
	j.FirstnameMetaphone = phonetics.EncodeMetaphone(j.Firstname)
	j.LastnameMetaphone = phonetics.EncodeMetaphone(j.Lastname)

	err = insertJourno(tx, j)
	if err != nil {
		return nil, err
	}
	// TODO: future: fill out journo_alias table, and also rel-author links etc to help resolution...
	return j, nil
}

// updates j.ID and j.Created
func insertJourno(tx *sql.Tx, j *Journo) error {

	err := tx.QueryRow(`INSERT INTO journo (id,ref,prettyname,lastname,firstname,created,status,oneliner,last_similar,firstname_metaphone,lastname_metaphone,admin_notes,admin_tags,fake) VALUES (DEFAULT,$1,$2,$3,$4,NOW(),$5,$6,$7,$8,$9,$10,$11,$12) RETURNING id,created`,
		j.Ref,
		j.Prettyname,
		j.Lastname,
		j.Firstname,
		j.Status,
		j.Oneliner,
		j.LastSimilar,
		j.FirstnameMetaphone,
		j.LastnameMetaphone,
		j.AdminNotes,
		j.AdminTags,
		j.Fake).Scan(&j.ID, &j.Created)
	if err != nil {
		return fmt.Errorf("insertJourno failed: %s", err)
	}

	return nil
}

var refCleanser = regexp.MustCompile(`[^-a-z ]`)

// generate a journo ref from a name
func baseRef(name string) string {
	ref := strings.ToLower(toASCII(name))
	ref = refCleanser.ReplaceAllLiteralString(ref, " ")
	ref = strings.TrimSpace(ref)

	ref = strings.Join(strings.Fields(ref), "-")
	return ref
}

// get firstname and lastname
// TODO: cope with prefixes (Mr,Dr) and suffixs (BSc)
func splitName(n string) (string, string) {

	parts := strings.Fields(n)
	if len(parts) == 0 {
		return "", ""
	}
	if len(parts) == 1 {
		return parts[0], ""
	}
	return parts[0], parts[len(parts)-1]
}

// return an unused journo ref suitable for a new journo to use
func uniqRef(tx *sql.Tx, baseRef string) (string, error) {
	var ref string
	var suffix int
	for {
		if suffix == 0 {
			ref = baseRef
		} else {
			ref = fmt.Sprintf("%s-%d", baseRef, suffix)
		}

		var id int
		err := tx.QueryRow("SELECT id FROM journo WHERE ref=$1", ref).Scan(&id)
		if err == sql.ErrNoRows {
			// it's available!
			break
		}
		suffix++
	}
	return ref, nil
}

func FindJournoByName(tx *sql.Tx, name string) ([]*Journo, error) {
	// TODO: use journo_alias table to do lookup!
	// KLUDGE ALERT: we're using refs to look up journos. This sucks, but
	// we're stuck with it until we transition over to a properly-populated journo_alias table

	// check first 20 possible refs.
	r := baseRef(name)
	refs := []interface{}{r}
	for i := 1; i < 20; i++ {
		refs = append(refs, fmt.Sprintf("%s-%d", r, i))
	}

	sql := `SELECT id,ref,prettyname,lastname,firstname,created,status,oneliner,last_similar,modified,firstname_metaphone, lastname_metaphone, admin_notes, admin_tags,fake FROM journo WHERE ref IN (` + pgMarkerList(1, len(refs)) + `)`
	rows, err := tx.Query(sql, refs...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []*Journo{}
	for rows.Next() {
		var j Journo
		if err := rows.Scan(&j.ID, &j.Ref, &j.Prettyname, &j.Lastname, &j.Firstname, &j.Created, &j.Status, &j.Oneliner, &j.LastSimilar, &j.Modified, &j.FirstnameMetaphone, &j.LastnameMetaphone, &j.AdminNotes, &j.AdminTags, &j.Fake); err != nil {
			return nil, err
		}
		out = append(out, &j)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	return out, nil
}

func ResolveJourno(tx *sql.Tx, details *UnresolvedJourno, pubID int, expectedRef string) ([]*Journo, error) {

	// TODO: if author has unique identifier (rel-author, email, twitter etc), use that to look them up first

	//fmt.Printf("resolveJourno(%s)\n", name)
	candidates, err := FindJournoByName(tx, details.Name)
	if err != nil {
		return nil, err
	}

	//fmt.Printf(" => %d candidates with matching name\n", len(candidates))
	if len(candidates) == 0 {
		return nil, nil
	}

	// if we expect a specific journo, bypass further checks
	if expectedRef != "" {
		for _, c := range candidates {
			if expectedRef == c.Ref {
				//fmt.Printf(" => got expectedRef\n")
				return []*Journo{c}, nil // gotcha
			}
		}
	}

	if pubID != 0 {
		candidates, err = filterJournosByPubID(tx, candidates, pubID)
		if err != nil {
			return nil, err
		}
	}
	//fmt.Printf("matching: %v\n", matching)

	return candidates, nil
}

// filterJournosByPubID takes a list of journos and a publication ID
// and returns a list of the journos who have previous articles
// with that publication.
func filterJournosByPubID(tx *sql.Tx, journos []*Journo, pubID int) ([]*Journo, error) {
	params := []interface{}{}
	params = append(params, pubID)
	for _, j := range journos {
		params = append(params, j.ID)
	}
	//fmt.Printf("params: %v\n", params)
	sql := `SELECT DISTINCT attr.journo_id
        FROM ( journo_attr attr INNER JOIN article a ON a.id=attr.article_id )
        WHERE a.srcorg=$1
           AND attr.journo_id IN (` + pgMarkerList(2, len(journos)) + `)`
	rows, err := tx.Query(sql, params...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	matching := map[int]struct{}{}
	for rows.Next() {
		var id int
		if err := rows.Scan(&id); err != nil {
			return nil, err
		}
		matching[id] = struct{}{}
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}

	//
	out := []*Journo{}
	for _, j := range journos {
		if _, got := matching[j.ID]; got {
			out = append(out, j)
		}
	}
	return out, nil
}

func JournoUpdateActivation(tx *sql.Tx, journoID int) error {
	const activationThreshold = 2

	var cnt int
	err := tx.QueryRow(`SELECT COUNT(*) FROM journo_attr ja INNER JOIN article a ON (a.id=ja.article_id AND a.status='a') WHERE ja.journo_id=$1`, journoID).Scan(&cnt)
	if err != nil {
		return err
	}
	if cnt >= activationThreshold {
		_, err = tx.Exec(`UPDATE journo SET status='a' WHERE id=$1 AND status='i'`, journoID)
		if err != nil {
			return err
		}
	}
	return nil
}
