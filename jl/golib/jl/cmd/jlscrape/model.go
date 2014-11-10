package main

import (
	"database/sql"
	"fmt"
	"github.com/bcampbell/arts/arts"
	"regexp"
	"strings"
	"time"
)

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

// return id of new article
func insertArticle(tx *sql.Tx, art *arts.Article, expectedRef string) (int, error) {
	var artID int

	now := time.Now()
	firstSeen := now
	lastSeen := now
	lastScraped := now
	lastCommentCheck := now
	pubDate := art.Published

	// find or create publication
	pubID, err := findPublication(tx, art.Publication.Domain)
	if err == sql.ErrNoRows {
		// not found - create a new one
		pubID, err = createPublication(tx, &art.Publication)
		fmt.Printf("new publication %d\n", pubID)
	}

	if err != nil {
		return 0, err
	}

	permalink := art.CanonicalURL
	if permalink == "" {
		permalink = art.URLs[0]
	}
	srcURL := permalink

	// TODO: need to be a bit more picky about dates...

	err = tx.QueryRow(`INSERT INTO article(title, byline, description, lastscraped, pubdate, firstseen, lastseen, permalink, srcurl, srcorg, wordcount, last_comment_check ) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12) RETURNING id`,
		art.Headline,
		"",
		"", // TODO: grab first para
		lastScraped,
		pubDate,
		firstSeen,
		lastSeen,
		permalink,
		srcURL,
		pubID,
		sql.NullInt64{0, false}, // TODO: wordcount
		lastCommentCheck).Scan(&artID)
	if err != nil {
		return 0, err
	}

	// URLs
	for _, u := range art.URLs {
		_, err := tx.Exec(`INSERT INTO article_url (article_id, url) VALUES ($1,$2)`, artID, u)
		if err != nil {
			return 0, err
		}
	}

	fmt.Printf("new article %d\n", artID)
	// insert content
	if art.Content != "" {
		_, err := tx.Exec(`INSERT INTO article_content (article_id, content,scraped) VALUES ( $1,$2,$3 )`, artID, art.Content, lastScraped)
		if err != nil {
			return 0, err
		}
	}

	// queue it for xapian indexing
	tx.Exec(`DELETE FROM article_needs_indexing WHERE article_id=$1`, artID)
	tx.Exec(`INSERT INTO article_needs_indexing (article_id) VALUES ($1)`, artID)

	// if there was a scraper error entry for this article, delete it now
	//tx.Exec( "DELETE FROM error_articlescrape WHERE srcid=%s", srcid )

	// TODO: images into article_image table?

	// commentlinks
	// TODO: tags

	// find/create journos
	journoIDs := []int{}
	for _, author := range art.Authors {
		j, err := resolveJourno(tx, &author, pubID, expectedRef)
		if err == ErrAmbiguousJourno {
			// LOG WARNING HERE
			continue
		} else if err != nil {
			return 0, err
		}

		var journoID int
		if j == nil {
			// create a new journo
			journoID, err = createJourno(tx, &author)
			if err != nil {
				return 0, err
			}
			fmt.Printf("Created new journo %d\n", journoID)
		} else {
			journoID = j.ID
		}
		journoIDs = append(journoIDs, journoID)

	}

	// link journos to article
	for _, jid := range journoIDs {
		// journo_attr
		_, err := tx.Exec("INSERT INTO journo_attr (journo_id,article_id) VALUES ($1,$2)", jid, artID)
		if err != nil {
			return 0, err
		}

		// TODO: apply journo activation policy

		// clear the html cache for that journos page
		cacheName := fmt.Sprintf("j%s", jid)
		_, err = tx.Exec("DELETE FROM htmlcache WHERE name=$1", cacheName)
		if err != nil {
			return 0, err
		}
	}

	return artID, nil
}

func pgMarkers(start, cnt int) []string {
	out := make([]string, cnt)
	for i := 0; i < cnt; i++ {
		out[i] = fmt.Sprintf("$%d", i+start)
	}
	return out
}

func pgMarkerList(start, cnt int) string {
	out := pgMarkers(start, cnt)
	return strings.Join(out, ",")
}

func findArticles(tx *sql.Tx, urls []string) ([]int, error) {
	out := []int{}
	sql := `SELECT DISTINCT article_id FROM article_url WHERE url IN (` + pgMarkerList(1, len(urls)) + `)`

	params := make([]interface{}, len(urls))
	for i := 0; i < len(urls); i++ {
		params[i] = urls[i]
	}

	rows, err := tx.Query(sql, params...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	for rows.Next() {
		var artID int
		if err := rows.Scan(&artID); err != nil {
			return nil, err
		}
		out = append(out, artID)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	return out, nil
}
