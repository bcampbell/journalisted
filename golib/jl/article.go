package jl

import (
	"database/sql"
	"errors"
	"fmt"
	"github.com/lib/pq"
	"strings"
	"time"
)

var ErrNoArticle = errors.New("article not found")

// Article is the in-memory representation of an article in the db
type Article struct {
	// ID is the unique article ID in the db.
	// An ID of 0 indicates the article data is not yet in the db.
	ID int
	// Title is the headline of the article
	Title string
	// Byline is the raw byline (deprecated)
	Byline string
	// Description is the short summary of the article,
	// often the first paragraph or 50 words or whatever.
	Description string
	// Content is the main text of the article (formatted
	// with a subset of HTML)
	Content string
	// PubDate is the date of publication, if known
	PubDate   pq.NullTime
	FirstSeen time.Time // not null
	LastSeen  time.Time // not null
	// Permalink is the canonical URL
	Permalink string

	SrcURL string
	// URLs is a list of all known urls for the article
	URLs        []string
	Publication *Publication // SrcOrg      int
	//    SrcID   string

	//
	LastScraped pq.NullTime
	WordCount   sql.NullInt64

	// Status can be:
	// 'a' active
	// 'h' hidden
	// 'd' duplicate (deprecated)
	// others?
	Status rune

	// Authors is the list of journos credited with writing this article
	Authors []*Journo
	/*
	   // do we really need these in the struct?
	   TotalBlogLinks int
	   TotalComments int
	   NeedsIndexing bool
	   LastCommentCheck
	   LastSimilar
	*/

	Tags []Tag
}

// InsertArticle loads a new article into the database.
// Upon success, art.ID is set to the newly-minted id.
// Requires the Publication has already been resolved.
// Journos must also be resolved.
func InsertArticle(tx *sql.Tx, art *Article) error {
	var artID int

	// some sanity checks:
	if art.ID != 0 {
		return fmt.Errorf("Article ID already set")
	}
	if art.Publication == nil {
		return fmt.Errorf("Publication not set")
	}
	if art.Publication.ID == 0 {
		return fmt.Errorf("Publication ID not set")
	}

	for _, journo := range art.Authors {
		if journo.ID == 0 {
			return fmt.Errorf("journo missing ID")
		}
	}

	err := tx.QueryRow(`INSERT INTO article(title, byline, description, lastscraped, pubdate, firstseen, lastseen, permalink, srcurl, srcorg, wordcount, last_comment_check ) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12) RETURNING id`,
		art.Title,
		art.Byline,
		art.Description,
		art.LastScraped,
		art.PubDate,
		art.FirstSeen,
		art.LastSeen,
		art.Permalink,
		art.SrcURL,
		art.Publication.ID,
		art.WordCount,
		pq.NullTime{},
	).Scan(&artID)
	if err != nil {
		return err
	}

	// URLs
	for _, u := range art.URLs {
		_, err := tx.Exec(`INSERT INTO article_url (article_id, url) VALUES ($1,$2)`, artID, u)
		if err != nil {
			return err
		}
	}

	// insert content
	if art.Content != "" {
		_, err := tx.Exec(`INSERT INTO article_content (article_id, content,scraped) VALUES ( $1,$2,$3 )`, artID, art.Content, art.LastScraped)
		if err != nil {
			return err
		}
	}

	// link authors
	for _, journo := range art.Authors {
		// journo_attr
		_, err = tx.Exec("INSERT INTO journo_attr (journo_id,article_id) VALUES ($1,$2)", journo.ID, artID)
		if err != nil {
			return err
		}
	}

	// apply journo activation policy
	for _, journo := range art.Authors {
		err = JournoUpdateActivation(tx, journo.ID)
		if err != nil {
			return err
		}
	}

	// Tags
	for _, t := range art.Tags {
		_, err := tx.Exec(`INSERT INTO article_tag (article_id, tag, freq) VALUES ($1,$2,$3)`, artID, t.Name, t.Freq)
		if err != nil {
			return err
		}
	}
	// queue article for xapian indexing
	tx.Exec(`DELETE FROM article_needs_indexing WHERE article_id=$1`, artID)
	tx.Exec(`INSERT INTO article_needs_indexing (article_id) VALUES ($1)`, artID)

	// if there was a scraper error entry for this article, delete it now
	//tx.Exec( "DELETE FROM error_articlescrape WHERE srcid=%s", srcid )

	// TODO: images into article_image table?

	// commentlinks
	// TODO: wordcount

	art.ID = artID
	return nil
}

func UpdateArticle(tx *sql.Tx, art *Article) error {
	panic("Not implemented!!!")
	panic("TODO: sanity checks")
	_, err := tx.Exec(`UPDATE article SET (title, byline, description, lastscraped, pubdate, lastseen, permalink, srcurl, srcorg, wordcount) = ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) WHERE id=$11`,
		art.Title,
		art.Byline,
		art.Description,
		art.LastScraped,
		art.PubDate,
		art.LastSeen,
		art.Permalink,
		art.SrcURL,
		art.Publication.ID,
		art.WordCount,
		art.ID)
	if err != nil {
		return err
	}

	// URLs
	_, err = tx.Exec(`DELETE FROM article_url WHERE article_id=$1`, art.ID)
	if err != nil {
		return err
	}
	for _, u := range art.URLs {
		_, err = tx.Exec(`INSERT INTO article_url (article_id, url) VALUES ($1,$2)`, art.ID, u)
		if err != nil {
			return err
		}
	}

	// replace content
	_, err = tx.Exec(`DELETE FROM article_content WHERE article_id=$1`, art.ID)
	if err != nil {
		return err
	}
	if art.Content != "" {
		_, err = tx.Exec(`INSERT INTO article_content (article_id, content,scraped) VALUES ( $1,$2,$3 )`,
			art.ID,
			art.Content,
			art.LastScraped)
		if err != nil {
			return err
		}
	}

	panic("TODO: update tags")

	panic("TODO: update journo links")
	// queue it for xapian indexing
	tx.Exec(`DELETE FROM article_needs_indexing WHERE article_id=$1`, art.ID)
	tx.Exec(`INSERT INTO article_needs_indexing (article_id) VALUES ($1)`, art.ID)

	// TODO:
	// article_image
	// article_commentlink

	return nil
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

// FindArticle returns the id of the article matching any of the given urls
// If no article is found, ErrNoArticle is returned.
// If there are mulitple matches (shouldn't happen!) an error is returned
func FindArticle(tx *sql.Tx, urls []string) (int, error) {
	ids, err := findArticles(tx, urls)
	if err != nil {
		return 0, err
	}

	n := len(ids)
	switch {
	case n > 1:
		return 0, fmt.Errorf("Multiple matching articles, id: %v", urls)
	case n == 1:
		return ids[0], nil // yay.
	default: // n==0
		return 0, ErrNoArticle
	}
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
