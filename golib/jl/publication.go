package jl

import (
	"database/sql"
	"github.com/bcampbell/arts/arts"
	"regexp"
	"strings"
)

type Publication struct {
	ID         int
	ShortName  string
	PrettyName string
	SortName   string
	HomeURL    string
	Domains    []string
}

func findPublication(tx *sql.Tx, domain string) (int, error) {
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

// TODO: use JL publication instead of arts.Publication
func createPublication(tx *sql.Tx, pub *arts.Publication) (int, error) {
	prettyName := pub.Name
	if prettyName == "" {
		prettyName = strippedDomain(pub.Domain)
	}
	shortName := genShortName(prettyName)

	// strip leading "the"s for more natural sort order
	sortName := strings.ToLower(prettyName)
	sortName = stripThePat.ReplaceAllLiteralString(prettyName, "")

	homeURL := "http://" + pub.Domain

	var pubID int
	err := tx.QueryRow(`INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, $1,$2,$3,$4) RETURNING id`, shortName, prettyName, sortName, homeURL).Scan(&pubID)
	if err != nil {
		return 0, err
	}

	_, err = tx.Exec(`INSERT INTO pub_domain (pub_id,domain) VALUES ($1, $2)`, pubID, pub.Domain)
	if err != nil {
		return 0, err
	}

	_, err = tx.Exec(`INSERT INTO pub_alias (pub_id,alias) VALUES ($1, $2)`, pubID, prettyName)
	if err != nil {
		return 0, err
	}

	return pubID, nil
}

func createPublication(tx *sql.Tx, pub *arts.Publication) (int, error) {
	prettyName := pub.Name
	if prettyName == "" {
		prettyName = strippedDomain(pub.Domain)
	}
	shortName := genShortName(prettyName)

	// strip leading "the"s for more natural sort order
	sortName := strings.ToLower(prettyName)
	sortName = stripThePat.ReplaceAllLiteralString(prettyName, "")

	homeURL := "http://" + pub.Domain

	var pubID int
	err := tx.QueryRow(`INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, $1,$2,$3,$4) RETURNING id`, shortName, prettyName, sortName, homeURL).Scan(&pubID)
	if err != nil {
		return 0, err
	}

	_, err = tx.Exec(`INSERT INTO pub_domain (pub_id,domain) VALUES ($1, $2)`, pubID, pub.Domain)
	if err != nil {
		return 0, err
	}

	_, err = tx.Exec(`INSERT INTO pub_alias (pub_id,alias) VALUES ($1, $2)`, pubID, prettyName)
	if err != nil {
		return 0, err
	}

	return pubID, nil
}
