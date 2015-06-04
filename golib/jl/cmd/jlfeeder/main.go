package main

import (
	"database/sql"
	"flag"
	"fmt"
	"github.com/bcampbell/journalisted/golib/jl"
	"github.com/lib/pq"
	"io/ioutil"
	"log"
	"os"
	"semprini/scrapeomat/slurp"
	"time"
)

var opts = struct {
	dbURI         string
	verbose       bool
	forceRescrape bool
}{}

var infoLog *log.Logger
var warnLog *log.Logger
var errLog *log.Logger

func main() {
	flag.StringVar(&opts.dbURI, "db", "", `Database URI eg "postgres://jl@localhost/jl" (if unset, uses $JL_DB_URI)`)
	flag.BoolVar(&opts.verbose, "v", false, `verbose`)
	flag.BoolVar(&opts.forceRescrape, "force", false, `force rescrape`)
	flag.Parse()

	if opts.verbose {
		infoLog = log.New(os.Stderr, "", 0)
	} else {
		infoLog = log.New(ioutil.Discard, "", 0)
	}
	warnLog = log.New(os.Stderr, "", 0)
	errLog = log.New(os.Stderr, "", 0)

	err := doIt("http://localhost:12345", 0)
	if err != nil {
		panic(err)
	}
}

// connect to db
func openDB(connStr string) (*sql.DB, error) {
	if connStr == "" {
		connStr = os.Getenv("JL_DB_URI")
	}
	if connStr == "" {
		return nil, fmt.Errorf("no db uri (use -db flag or JL_DB_URI environment var)")
	}
	db, err := sql.Open("postgres", connStr)
	if err != nil {
		return nil, err
	}
	return db, nil
}

func doIt(location string, sinceID int) error {
	db, err := openDB(opts.dbURI)
	if err != nil {
		return err
	}
	defer db.Close()

	client := slurp.NewSlurper(location)

	stats := loadStats{}
	// grab and process in batches
	for {
		filt := &slurp.Filter{
			SinceID:  sinceID,
			Count:    10,
			PubCodes: []string{"herald"},
		}
		incoming := client.Slurp(filt)
		arts := []*slurp.Article{}
		for msg := range incoming {
			if msg.Error != "" {
				fmt.Printf("ERROR: %s\n", msg.Error)
			} else if msg.Article != nil {
				if msg.Article.ID > sinceID {
					sinceID = msg.Article.ID
				}
				//fmt.Printf("%s (%s)\n", art.Title, art.Permalink)
				arts = append(arts, msg.Article)
			} else {
				fmt.Printf("WARN empty message...\n")
			}
		}

		if len(arts) > 0 {
			// load the batch into the db
			tx, err := db.Begin()
			if err != nil {
				return err
			}
			err = loadBatch(tx, arts, &stats)
			if err != nil {
				tx.Rollback()
				return err
			}
			// TODO: commit!
			tx.Rollback()
		}
		break
		// end of articles?
		if len(arts) < filt.Count {
			break
		}

	}
	return nil
}

type loadStats struct {
	NewCnt   int
	Reloaded int
	Skipped  int
	ErrCnt   int
}

// load a batch of articles into the database
func loadBatch(tx *sql.Tx, rawArts []*slurp.Article, stats *loadStats) error {
	for _, raw := range rawArts {
		art, authors := convertArt(raw)
		// already got the article?
		foundID, err := jl.FindArticle(tx, art.URLs)

		if err != nil && err != jl.ErrNoArticle {
			errLog.Printf(err.Error())
			stats.ErrCnt++
			// TODO: implement abort here if too many errors?
			continue
		}

		rescrape := false
		if err == nil {
			// article was found
			if opts.forceRescrape {
				rescrape = true
				art.ID = foundID
			} else {
				// skip this one. already got it.
				// TODO: possible that we've got new URLs to add...
				stats.Skipped++
				continue
			}
		}

		err = stash(tx, art, authors, "")
		if err != nil {
			errLog.Printf(err.Error())
			stats.ErrCnt++
			continue
		}

		if rescrape {
			stats.Reloaded++
			infoLog.Printf("reloaded %s %s\n", art.Title, art.Permalink)
		} else {
			stats.NewCnt++
			infoLog.Printf("new %s %s\n", art.Title, art.Permalink)
		}
	}
	return nil
}

// convertArt converts a slurped article into jl form
func convertArt(src *slurp.Article) (*jl.Article, []*jl.UnresolvedJourno) {

	bestURL := src.CanonicalURL
	if bestURL == "" {
		bestURL = src.URLs[0]
	}

	pub := &jl.Publication{
		ID:         0, // unresolved
		ShortName:  src.Publication.Code,
		PrettyName: src.Publication.Name,
		Domains:    []string{src.Publication.Domain},
	}

	var pubDate pq.NullTime
	t, err := time.Parse(time.RFC3339, src.Published)
	if err == nil {
		pubDate.Time = t
		pubDate.Valid = true
	} // else pubDate is NULL

	now := time.Now()

	art := &jl.Article{
		ID:          0, // note: we generate our own IDs at the JL end
		Title:       src.Headline,
		Byline:      "",
		Description: "",
		Content:     src.Content,
		PubDate:     pubDate,
		FirstSeen:   now,
		LastSeen:    now,
		Permalink:   bestURL,
		SrcURL:      bestURL,
		URLs:        make([]string, len(src.URLs)),
		Publication: pub,
		LastScraped: pq.NullTime{now, true},

		// TODO: WORDCOUNT!!!!
		WordCount: sql.NullInt64{0, false},
		Status:    'a',
	}
	copy(art.URLs, src.URLs)

	// authors
	authors := []*jl.UnresolvedJourno{}
	for _, a := range src.Authors {
		u := &jl.UnresolvedJourno{
			Name: a.Name,
			// TODO: Email, rel-author, twitter, etc..
		}
		authors = append(authors, u)
	}
	return art, authors
}
