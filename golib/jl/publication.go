package jl

import (
	"database/sql"
	"errors"
	"fmt"
	"regexp"
	"strings"
)

type Publication struct {
	ID        int
	ShortName string
	// PrettyName is the canonical human-readable name for the publication
	PrettyName string
	// SortName used for presentation in lists.
	// Usually remove leading "The" for more natural ordering.
	SortName string
	HomeURL  string
	Domains  []string
}

var ErrNoPub = errors.New("Publication not found")

func ResolvePublication(tx *sql.Tx, pub *Publication) error {
	if pub.ID != 0 {
		return fmt.Errorf("publication already resolved")
	}

	// try shortname first
	if pub.ShortName != "" {
		var pubID int
		err := tx.QueryRow(`SELECT id FROM organisation WHERE shortname=$1`, pub.ShortName).Scan(&pubID)
		if err != sql.ErrNoRows {
			if err != nil {
				return err
			}
			// got it!
			pub.ID = pubID
			return nil
		}
	}

	// try domain names (both with and without www. prefix)
	domains := []interface{}{}
	for _, d := range pub.Domains {
		a := strings.TrimPrefix(d, "www.")
		domains = append(domains, a, "www."+a)
	}

	if len(domains) > 0 {
		var pubID int
		s := `SELECT pub_id FROM pub_domain WHERE domain IN(` + pgMarkerList(1, len(domains)) + `)`
		err := tx.QueryRow(s, domains...).Scan(pubID)
		if err != sql.ErrNoRows {
			if err != nil {
				return err
			}
			// got it!
			pub.ID = pubID
			return nil
		}
	}

	// TODO: try prettyname against pub_alias table
	return ErrNoPub
}

// returns sql.ErrNoRows if not found
func FindPublication(tx *sql.Tx, domain string) (int, error) {
	// TODO (maybe) match both www. and non www. versions?
	//domain = strings.ToLower(domain)
	var pubID int
	err := tx.QueryRow(`SELECT pub_id FROM pub_domain WHERE domain=$1`, domain).Scan(&pubID)
	return pubID, err
}

var shortNameSanitisePat = regexp.MustCompile(`[^-a-z.0-9]`)
var stripThePat = regexp.MustCompile(`(?i)^the\s+`)
var stripWWWPat = regexp.MustCompile(`(?i)^www[0-9]?[.]`)

func genShortName(prettyName string) string {
	// zap accented chars
	n := toASCII(prettyName)
	n = strings.ToLower(n)
	return shortNameSanitisePat.ReplaceAllLiteralString(n, "")
}

func strippedDomain(d string) string {
	return stripWWWPat.ReplaceAllString(d, "")
}

func CreatePublication(tx *sql.Tx, domain, name string) (*Publication, error) {
	pub := &Publication{}
	pub.Domains = []string{domain}
	if name == "" {
		name = strippedDomain(domain)
	}
	pub.PrettyName = name
	pub.ShortName = genShortName(pub.PrettyName)

	// strip leading "the"s for more natural sort order
	pub.SortName = stripThePat.ReplaceAllLiteralString(strings.ToLower(pub.PrettyName), "")

	pub.HomeURL = "http://" + domain

	err := tx.QueryRow(`INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, $1,$2,$3,$4) RETURNING id`,
		pub.ShortName,
		pub.PrettyName,
		pub.SortName,
		pub.HomeURL).Scan(&pub.ID)
	if err != nil {
		return nil, err
	}

	for _, domain := range pub.Domains {
		_, err = tx.Exec(`INSERT INTO pub_domain (pub_id,domain) VALUES ($1, $2)`, pub.ID, domain)
		if err != nil {
			return nil, err
		}
	}

	_, err = tx.Exec(`INSERT INTO pub_alias (pub_id,alias) VALUES ($1, $2)`, pub.ID, pub.PrettyName)
	if err != nil {
		return nil, err
	}

	return pub, nil
}

func InsertPublication(tx *sql.Tx, pub *Publication) error {
	// sanity checks
	if pub.ID != 0 {
		return fmt.Errorf("won't reinsert existing publication")
	}
	if len(pub.Domains) == 0 {
		return fmt.Errorf("publication lacks domain(s)")
	}
	if pub.ShortName == "" {
		return fmt.Errorf("publication lacks shortname")
	}
	if pub.PrettyName == "" {
		return fmt.Errorf("publication lacks prettyname")
	}
	if pub.SortName == "" {
		return fmt.Errorf("publication lacks sortname")
	}
	if pub.HomeURL == "" {
		return fmt.Errorf("publication lacks homeurl")
	}

	err := tx.QueryRow(`INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, $1,$2,$3,$4) RETURNING id`,
		pub.ShortName,
		pub.PrettyName,
		pub.SortName,
		pub.HomeURL).Scan(&pub.ID)
	if err != nil {
		return err
	}

	for _, domain := range pub.Domains {
		_, err = tx.Exec(`INSERT INTO pub_domain (pub_id,domain) VALUES ($1, $2)`, pub.ID, domain)
		if err != nil {
			return err
		}
	}

	_, err = tx.Exec(`INSERT INTO pub_alias (pub_id,alias) VALUES ($1, $2)`, pub.ID, pub.PrettyName)
	if err != nil {
		return err
	}

	return nil
}
