package jl

import (
	"database/sql"
	"fmt"
	"github.com/lib/pq"
	"strings"
	"time"
)

type Article struct {
	ID          int // 0 = no ID assigned (ie not yet in DB)
	Title       string
	Byline      string
	Description string
	Content     string
	PubDate     pq.NullTime
	FirstSeen   time.Time // not null
	LastSeen    time.Time // not null
	Permalink   string
	SrcURL      string
	URLs        []string
	Publication *Publication // SrcOrg      int
	//    SrcID   string
	LastScraped pq.NullTime
	WordCount   sql.NullInt64
	Status      rune

	/*
	   // do we really need these in the struct?
	   TotalBlogLinks int
	   TotalComments int
	   NeedsIndexing bool
	   LastCommentCheck
	   LastSimilar
	*/
}

// sets the id of the article
func InsertArticle(tx *sql.Tx, art *Article, pubID int) error {
	var artID int

	// TODO: find/create publication if publication id is 0

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
		// TODO: wordcount
		sql.NullInt64{0, false}).Scan(&artID)
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

	// queue it for xapian indexing
	tx.Exec(`DELETE FROM article_needs_indexing WHERE article_id=$1`, artID)
	tx.Exec(`INSERT INTO article_needs_indexing (article_id) VALUES ($1)`, artID)

	// if there was a scraper error entry for this article, delete it now
	//tx.Exec( "DELETE FROM error_articlescrape WHERE srcid=%s", srcid )

	// TODO: images into article_image table?

	// commentlinks
	// TODO: tags

	art.ID = artID
	return nil
}

func UpdateArticle(tx *sql.Tx, artID int, art *Article) error {
	_, err := tx.Exec(`UPDATE article SET (title, byline, description, lastscraped, pubdate, lastseen, permalink, srcurl, srcorg, wordcount) = ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) WHERE id=$12`,
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
		artID)
	if err != nil {
		return err
	}

	// URLs
	_, err = tx.Exec(`DELETE FROM article_url WHERE article_id=$1`, artID)
	if err != nil {
		return err
	}
	for _, u := range art.URLs {
		_, err = tx.Exec(`INSERT INTO article_url (article_id, url) VALUES ($1,$2)`, artID, u)
		if err != nil {
			return err
		}
	}

	// replace content
	_, err = tx.Exec(`DELETE FROM article_content WHERE article_id=$1`, artID)
	if err != nil {
		return err
	}
	if art.Content != "" {
		_, err = tx.Exec(`INSERT INTO article_content (article_id, content,scraped) VALUES ( $1,$2,$3 )`,
			artID,
			art.Content,
			art.LastScraped)
		if err != nil {
			return err
		}
	}

	// queue it for xapian indexing
	tx.Exec(`DELETE FROM article_needs_indexing WHERE article_id=$1`, artID)
	tx.Exec(`INSERT INTO article_needs_indexing (article_id) VALUES ($1)`, artID)

	// TODO:
	// article_image
	// article_commentlink
	// article_tag

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

func FindArticles(tx *sql.Tx, urls []string) ([]int, error) {
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
