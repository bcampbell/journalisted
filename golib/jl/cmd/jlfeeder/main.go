package main

import (
	"database/sql"
	"flag"
	"fmt"
	"github.com/bcampbell/htmlutil"
	"github.com/bcampbell/journalisted/golib/jl"
	"github.com/lib/pq"
	"golang.org/x/net/html"
	"io/ioutil"
	"log"
	"os"
	"regexp"
	"semprini/scrapeomat/slurp"
	"strings"
	"time"
)

var opts = struct {
	dbURI         string
	verbose       bool
	forceRescrape bool
	serverBase    string
	sinceIDFile   string
}{}

var infoLog *log.Logger
var warnLog *log.Logger
var errLog *log.Logger

// cheesy wordcount (TODO: improve!)
var wordPat *regexp.Regexp = regexp.MustCompile(`\w+`)

func main() {
	flag.StringVar(&opts.dbURI, "db", "", `Database URI eg "postgres://jl@localhost/jl" (if unset, uses $JL_DB_URI)`)
	flag.StringVar(&opts.serverBase, "server", "http://localhost:12345", `base URL of slurp server`)
	flag.BoolVar(&opts.verbose, "v", false, `verbose`)
	flag.BoolVar(&opts.forceRescrape, "force", false, `force rescrape`)
	flag.StringVar(&opts.sinceIDFile, "sinceid", "", `file to track since_id with ""=none`)
	flag.Parse()

	if opts.verbose {
		infoLog = log.New(os.Stderr, "", 0)
	} else {
		infoLog = log.New(ioutil.Discard, "", 0)
	}
	warnLog = log.New(os.Stderr, "", 0)
	errLog = log.New(os.Stderr, "", 0)

	err := doIt()
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

func doIt() error {

	var sinceID int
	var err error
	if opts.sinceIDFile != "" {
		sinceID, err = getSinceID(opts.sinceIDFile)
		if err != nil {
			return err
		}
		infoLog.Printf("%s: since_id is %d\n", opts.sinceIDFile, sinceID)
	}

	db, err := openDB(opts.dbURI)
	if err != nil {
		return err
	}
	stats := loadStats{}

	defer func() {
		db.Close()

		if opts.sinceIDFile != "" && stats.HighID != 0 {
			putSinceID(opts.sinceIDFile, stats.HighID)
			infoLog.Printf("%s: new since_id is %d\n", opts.sinceIDFile, sinceID)
		}

	}()

	client := slurp.NewSlurper(opts.serverBase)

	// grab and process in batches
	for {
		filt := &slurp.Filter{
			SinceID:  sinceID,
			Count:    30,
			PubCodes: []string{"guardian", "dailymail"},
		}
		infoLog.Printf("slurp %v...\n", filt)
		incoming := client.Slurp(filt)
		arts := []*slurp.Article{}
		for msg := range incoming {
			if msg.Error != "" {
				errLog.Printf(msg.Error)
			} else if msg.Article != nil {
				//infoLog.Printf("bing %s\n", msg.Article.CanonicalURL)
				if msg.Article.ID > sinceID {
					sinceID = msg.Article.ID
				}
				//fmt.Printf("%s (%s)\n", art.Title, art.Permalink)
				arts = append(arts, msg.Article)
			} else {
				warnLog.Printf("empty message\n")
			}
		}
		infoLog.Printf("batch received (%d arts)\n", len(arts))

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

			sinceID = stats.HighID
		}
		break
		// end of articles?
		if len(arts) < filt.Count {
			break
		}

		// FORCE BREAK FOR NOW!
		break
	}
	return nil
}

type loadStats struct {
	NewCnt   int
	Reloaded int
	Skipped  int
	ErrCnt   int
	// highest article ID encountered so far
	HighID int
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
		// log it
		bylineBits := make([]string, len(art.Authors))
		for i, j := range art.Authors {
			bylineBits[i] = j.Ref
		}
		infoLog.Printf("%d: new [a%d] %s (%s)\n", raw.ID, art.ID, art.Permalink, strings.Join(bylineBits, ","))

		if rescrape {
			stats.Reloaded++
			//infoLog.Printf("reloaded %s %s\n", art.Title, art.Permalink)
		} else {
			stats.NewCnt++
			//infoLog.Printf("new %s %s\n", art.Title, art.Permalink)
		}

		// record highest serverside ID encountered so far
		if raw.ID > stats.HighID {
			stats.HighID = raw.ID
		}
	}
	return nil
}

// convertArt converts a slurped article into jl form
func convertArt(src *slurp.Article) (*jl.Article, []*jl.UnresolvedJourno) {
	now := time.Now()

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

	wordCnt := sql.NullInt64{0, false}
	rawTxt := ""
	if src.Content != "" {
		// parse and render to raw text
		doc, err := html.Parse(strings.NewReader(src.Content))
		if err == nil {
			rawTxt = htmlutil.RenderNode(doc)
		}
	}

	if rawTxt != "" {
		// count words
		cnt := len(wordPat.FindAllString(rawTxt, -1))
		if cnt > 0 {
			wordCnt = sql.NullInt64{int64(cnt), true}
		}

		// extract tags
		//tags := jl.ExtractTags(rawText)
	}

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

		WordCount: wordCnt,
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
