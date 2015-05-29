package jl

import (
	"database/sql"
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
