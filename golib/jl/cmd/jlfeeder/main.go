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

	// grab and process in batches
	for {
		filt := &slurp.Filter{
			SinceID:  sinceID,
			Count:    10,
			PubCodes: []string{"guardian"},
		}
		incoming := client.Slurp(filt)
		arts := []*jl.Article{}
		for msg := range incoming {
			if msg.Error != "" {
				fmt.Printf("ERROR: %s\n", msg.Error)
			} else if msg.Article != nil {
				if msg.Article.ID > sinceID {
					sinceID = msg.Article.ID
				}
				art := convertArt(msg.Article)
				//fmt.Printf("%s (%s)\n", art.Title, art.Permalink)
				arts = append(arts, art)
			} else {
				fmt.Printf("WARN empty message...\n")
			}
		}

		// add them
		for _, art := range arts {
			fmt.Printf("%s %s\n", art.Title, art.Permalink)
		}

		if len(arts) < filt.Count {
			break
		}

	}
	return nil
}

func processArticle(tx *sql.Tx, art *jl.Article, expectedJourno string) (int, error) {

	// recheck database in case scraping yielded new urls
	found, err := jl.FindArticles(tx, art.URLs)
	if err != nil {
		return 0, err
	}
	// TODO: should merge in newly-discovered URLs?

	if len(found) > 1 {
		// not quite sure if there's anything we can do here...
		// TODO: maybe article_url table should have unique constraint upon url?
		return 0, fmt.Errorf("Multiple matching articles in db, id: %v", found)
	}

	if len(found) > 0 && !opts.forceRescrape {
		return 0, fmt.Errorf("already got [a%d]", found[0])
	}

	// find or create publication
	// TODO: handle updating publication with new info?

	/*
			pubID, err := jl.FindPublication(tx, art.Publication.Domains[0])
			if err == sql.ErrNoRows {
				// not found - create a new one
				pubID, err = jl.CreatePublication(tx, art.Publication)
				//fmt.Printf("new publication [%d] %s\n", pubID, art.Publication.Domain)
			}
			if err != nil {
				return 0, err
			}

		//create/update article
		var artID int
		if len(found) == 0 {
			artID, err = jl.InsertArticle(tx, art, pubID)
		} else {
			artID = found[0]
			err = jl.UpdateArticle(tx, artID, art, pubID)
		}
		if err != nil {
			return 0, err
		}

		// find/create journos
		journoIDs := []int{}
		for _, author := range jl.Authors {
			j, err := jl.ResolveJourno(tx, &author, pubID, expectedJourno)
			if err == jl.ErrAmbiguousJourno {
				// LOG WARNING HERE
				continue
			} else if err != nil {
				return 0, err
			}

			var journoID int
			if j == nil {
				// create a new journo
				journoID, err = jl.CreateJourno(tx, &author)
				if err != nil {
					return 0, err
				}
				fmt.Printf("new journo [j%d] %s\n", journoID, author.Name)
			} else {
				journoID = j.ID
			}
			journoIDs = append(journoIDs, journoID)

		}

		// link journos to article
		if len(found) > 0 {
			_, err = tx.Exec("DELETE FROM journo_attr WHERE article_id=$1", artID)
			if err != nil {
				return 0, err
			}
		}
		for _, jid := range journoIDs {
			// journo_attr
			_, err = tx.Exec("INSERT INTO journo_attr (journo_id,article_id) VALUES ($1,$2)", jid, artID)
			if err != nil {
				return 0, err
			}

			// apply journo activation policy
			err = journoUpdateActivation(tx, jid)
			if err != nil {
				return 0, err
			}

			// clear the html cache for that journos page
			cacheName := fmt.Sprintf("j%s", jid)
			_, err = tx.Exec("DELETE FROM htmlcache WHERE name=$1", cacheName)
			if err != nil {
				return 0, err
			}
		}

		// log it
		bylineBits := make([]string, len(journoIDs))
		for i, jid := range journoIDs {
			bylineBits[i] = fmt.Sprintf("[j%d]", jid)
		}
		fmt.Printf("new [a%d] \"%s\" (%s)\n", artID, art.Headline, strings.Join(bylineBits, ","))
		return artID, nil
	*/
	return 0, fmt.Errorf("NOT IMPLEMENTED YET!")
}

func convertArt(src *slurp.Article) *jl.Article {

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
	return art

}
