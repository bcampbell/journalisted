package main

import (
	"database/sql"
	"errors"
	"fmt"
	"github.com/bcampbell/arts/arts"
	"github.com/dotcypress/phonetics"
	"github.com/lib/pq"
	"regexp"
	"strings"
	"time"
)

//var ErrNoMatchingJourno = errors.New("No matching journo")
var ErrAmbiguousJourno = errors.New("Ambiguous journo")

type Journo struct {
	ID                 int
	Ref                string
	Prettyname         string
	Lastname           string
	Firstname          string
	Created            time.Time
	Status             string
	Oneliner           string
	LastSimilar        pq.NullTime
	Modified           bool
	FirstnameMetaphone string
	LastnameMetaphone  string
	AdminNotes         string
	AdminTags          string
	Fake               bool
}

var refCleanser = regexp.MustCompile(`[^-a-z ]`)

// generate a journo ref from a name
// eg
func baseRef(name string) string {
	ref := strings.ToLower(toASCII(name))
	ref = refCleanser.ReplaceAllLiteralString(ref, " ")
	ref = strings.TrimSpace(ref)

	ref = strings.Join(strings.Fields(ref), "-")
	return ref
}

// get firstname and lastname
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

func createJourno(tx *sql.Tx, journo *arts.Author) (int, error) {
	ref, err := uniqRef(tx, baseRef(journo.Name))
	if err != nil {
		return 0, err
	}

	prettyName := journo.Name
	firstName, lastName := splitName(journo.Name)
	firstNameMetaphone := phonetics.EncodeMetaphone(firstName)
	lastNameMetaphone := phonetics.EncodeMetaphone(lastName)

	var journoID int
	err = tx.QueryRow(`INSERT INTO journo (id,ref,prettyname,firstname,lastname,firstname_metaphone,lastname_metaphone,created) VALUES (DEFAULT,$1,$2,$3,$4,$5,$6,NOW()) RETURNING id`,
		ref,
		prettyName,
		firstName,
		lastName,
		firstNameMetaphone,
		lastNameMetaphone).Scan(&journoID)
	if err != nil {
		return 0, err
	}

	// TODO: future: fill out journo_alias table, and also rel-author links etc to help resolution...

	return journoID, nil
}

func findJournoByName(tx *sql.Tx, name string) ([]*Journo, error) {
	// TODO: use journo_alias table to do lookup!
	// KLUDGE ALERT: we're using refs to look up journos. This sucks, but
	// we're stuck with it until we transition over to a properly-populated journo_alias table

	// check first 20 possible refs.
	r := baseRef(name)
	refs := []interface{}{r}
	for i := 1; i < 20; i++ {
		refs = append(refs, fmt.Sprintf("%s-%d", r, i))
	}

	// TODO: probably no reason fill out most of these fields...
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

func resolveJourno(tx *sql.Tx, author *arts.Author, pubID int, expectedRef string) (*Journo, error) {

	// TODO: if author has unique identifier (rel-author, email, twitter etc), use that to look them up first

	fmt.Printf("resolveJourno(%s)\n", author.Name)
	candidates, err := findJournoByName(tx, author.Name)
	if err != nil {
		return nil, err
	}

	fmt.Printf(" => %d candidates with matching name\n", len(candidates))
	if len(candidates) == 0 {
		return nil, nil
	}

	// if we expect a specific journo, bypass further checks
	if expectedRef != "" {
		for _, c := range candidates {
			if expectedRef == c.Ref {
				fmt.Printf(" => got expectedRef\n")
				return c, nil // gotcha
			}
		}
	}

	// any candidates written for this publication before?
	params := []interface{}{pubID}
	for _, c := range candidates {
		params = append(params, c.ID)
	}
	fmt.Printf("params: %v\n", params)
	sql := "SELECT DISTINCT attr.journo_id FROM ( journo_attr attr INNER JOIN article a ON a.id=attr.article_id ) WHERE a.srcorg=$1 AND attr.journo_id IN (" + pgMarkerList(2, len(candidates)) + ")"
	rows, err := tx.Query(sql, params...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	matching := []int{}
	for rows.Next() {
		var id int
		if err := rows.Scan(&id); err != nil {
			return nil, err
		}
		matching = append(matching, id)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	fmt.Printf("matching: %v\n", matching)

	if len(matching) == 0 {
		fmt.Printf("No journo found for %s\n", author.Name)
		return nil, nil
	}

	if len(matching) > 1 {
		return nil, ErrAmbiguousJourno
	}

	for _, c := range candidates {
		if c.ID == matching[0] {
			return c, nil // gotcha!
		}
	}

	// shouldn't get this far
	return nil, nil
}
