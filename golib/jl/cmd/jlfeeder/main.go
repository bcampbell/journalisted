package main

import (
	"database/sql"
	"flag"
	"fmt"
	"github.com/bcampbell/htmlutil"
	"github.com/bcampbell/journalisted/golib/jl"
	"github.com/lib/pq"
	"golang.org/x/net/html"
	"io"
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
	flag.StringVar(&opts.sinceIDFile, "sinceid", "", `file to track since_id with (""=none)`)
	flag.Parse()

	if opts.verbose {
		infoLog = log.New(os.Stdout, "", 0)
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

		infoLog.Println(stats.summary())
		if opts.sinceIDFile != "" && stats.HighID != 0 {
			putSinceID(opts.sinceIDFile, stats.HighID)
			infoLog.Printf("%s: new since_id is %d\n", opts.sinceIDFile, sinceID)
		}

	}()

	client := slurp.NewSlurper(opts.serverBase)
	lastID := sinceID

	// grab and process in batches
	for {
		filt := &slurp.Filter{
			SinceID:  sinceID,
			Count:    2000,
			PubCodes: []string{},
		}
		arts := []*slurp.Article{}
		infoLog.Printf("slurp %v...\n", filt)
		stream := client.Slurp2(filt)
		for {
			wireArt, err := stream.Next()
			if err == io.EOF {
				break
			} else if err != nil {
				return err
			}
			if wireArt.ID > lastID {
				lastID = wireArt.ID
			}
			arts = append(arts, wireArt)
			receivedCnt += 1
		}
		infoLog.Printf("batch received (%d arts)\n", len(arts))

		for _, art := range arts {
			tx, err := db.Begin()
			if err != nil {
				return err
			}
			err = loadArt(tx, art, &stats)
			if err != nil {
				tx.Rollback()
				// TODO: check error count against threshold here!
				continue
			}
			tx.Commit()

		}
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
	// highest article ID encountered so far
	HighID int
}

func (stats *loadStats) summary() string {

	return fmt.Sprintf("%d new, %d skipped, %d errors", stats.NewCnt, stats.Skipped, stats.ErrCnt)
}

// convert and load an article into the database
func loadArt(tx *sql.Tx, rawArt *slurp.Article, stats *loadStats) error {
	art, authors := convertArt(rawArt)
	// already got the article?
	foundID, err := jl.FindArticle(tx, art.URLs)

	if err != nil && err != jl.ErrNoArticle {
		errLog.Printf(err.Error())
		stats.ErrCnt++
		return err
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
			// bump the since_id
			if rawArt.ID > stats.HighID {
				stats.HighID = rawArt.ID
			}
			return nil
		}
	}

	logPrefix := fmt.Sprintf("%d: ", rawArt.ID)

	err = stash(tx, art, authors, "", logPrefix)
	if err != nil {
		errLog.Printf("%sError: %s\n", logPrefix, err.Error())
		stats.ErrCnt++
		return err
	}
	// log it
	bylineBits := make([]string, len(art.Authors))
	for i, j := range art.Authors {
		bylineBits[i] = j.Ref
	}
	infoLog.Printf("%snew [a%d] %s (%s)\n", logPrefix, art.ID, art.Permalink, strings.Join(bylineBits, ","))

	if rescrape {
		stats.Reloaded++
		//infoLog.Printf("reloaded %s %s\n", art.Title, art.Permalink)
	} else {
		stats.NewCnt++
		//infoLog.Printf("new %s %s\n", art.Title, art.Permalink)
	}

	// record highest serverside ID encountered so far
	if rawArt.ID > stats.HighID {
		stats.HighID = rawArt.ID
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

	pub := jl.NewPublication(src.Publication.Domain, src.Publication.Name)

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

	tags := []jl.Tag{}
	if rawTxt != "" {
		// count words
		cnt := len(wordPat.FindAllString(rawTxt, -1))
		if cnt > 0 {
			wordCnt = sql.NullInt64{int64(cnt), true}
		}

		tags = jl.ExtractTagsFromText(rawTxt)
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
		Tags:      tags,
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
