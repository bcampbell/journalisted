package main

import (
	"code.google.com/p/go.text/unicode/norm"
	"database/sql"
	"flag"
	"fmt"
	"github.com/bcampbell/arts/arts"
	_ "github.com/lib/pq"
	"io/ioutil"
	"net/http"
	"os"
	"strings"
)

func toASCII(txt string) string {
	// convert to NFKD form
	// eg, from wikipedia:
	// "U+00C5" (the Swedish letter "Å") is expanded into "U+0041 U+030A" (Latin letter "A" and combining ring above "°")
	n := norm.NFKD.String(txt)

	// strip out non-ascii chars (eg combining ring above "°", leaving just "A")
	n = strings.Map(
		func(r rune) rune {
			if r > 128 {
				r = -1
			}
			return r
		}, n)
	return n
}

var opts = struct {
	verbose        bool
	test           bool
	forceRescrape  bool
	expectedJourno string
	dbURI          string // eg "postgres://jl@localhost/jl?sslmode=disable"
}{}

func main() {
	flag.StringVar(&opts.dbURI, "db", "", `Database URI eg "postgres://jl@localhost/jl" (if unset, uses $JL_DB_URI)`)
	flag.BoolVar(&opts.verbose, "v", false, "info to stderr ")
	flag.BoolVar(&opts.test, "t", false, "test only - don't commit to db")
	flag.BoolVar(&opts.forceRescrape, "f", false, "force rescrape of articles already in db")
	flag.StringVar(&opts.expectedJourno, "j", "", "journo ref to help resolve ambiguous cases eg: fred-bloggs-1")
	flag.Parse()

	if len(flag.Args()) != 1 {
		fmt.Println("Usage: ", os.Args[0], "<article url>")
		os.Exit(1)
	}

	// connect to db
	connStr := opts.dbURI
	if connStr == "" {
		connStr = os.Getenv("JL_DB_URI")
	}
	if connStr == "" {
		fmt.Fprintf(os.Stderr, "ERROR: no db uri (use -db flag or JL_DB_URI environment var\n")
		os.Exit(1)
	}

	db, err := sql.Open("postgres", connStr)
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
		os.Exit(1)
	}
	defer db.Close()

	artURL := flag.Arg(0)

	tx, err := db.Begin()
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
		os.Exit(1)
	}

	if !opts.forceRescrape {
		// already got the article?
		foo := []string{artURL}
		found, err := findArticles(tx, foo)
		if err != nil {
			fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
			os.Exit(1)
		}

		if len(found) > 0 {
			fmt.Fprintf(os.Stderr, "already got [a%d]\n", found[0])
			os.Exit(0)
		}
	}

	// Download
	client := &http.Client{} //Timeout: 1 * time.Second}
	rawHTML, err := grabHTML(client, artURL)
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR grabbing article: %s\n", err)
		os.Exit(1)
	}

	// extract
	art, err := arts.ExtractHTML(rawHTML, artURL)
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR extracting article: %s\n", err)
		os.Exit(1)
	}

	// load/update article in db
	_, insertErr := processArticle(tx, art, opts.expectedJourno)
	if insertErr != nil {
		fmt.Fprintf(os.Stderr, "ERROR: %s\n", insertErr)
	}

	if insertErr == nil && !opts.test {
		err = tx.Commit()
		if err != nil {
			fmt.Fprintf(os.Stderr, "ERROR commit failed: %s\n", err)
			os.Exit(1)
		}
	} else {
		fmt.Printf("rolling back\n")
		err = tx.Rollback()
		if err != nil {
			fmt.Fprintf(os.Stderr, "ERROR rollback failed: %s\n", err)
			os.Exit(1)
		}
	}

	os.Exit(0)
}

func grabHTML(client *http.Client, artURL string) ([]byte, error) {
	request, err := http.NewRequest("GET", artURL, nil)
	if err != nil {
		return nil, err
	}
	response, err := client.Do(request)
	if err != nil {
		return nil, err
	}
	defer response.Body.Close()

	rawHTML, err := ioutil.ReadAll(response.Body)
	if err != nil {
		return nil, err
	}

	if response.StatusCode != 200 {
		return nil, fmt.Errorf("HTTP error: %s", response.Status)
	}
	return rawHTML, nil
}

func processArticle(tx *sql.Tx, art *arts.Article, expectedJourno string) (int, error) {

	// recheck database in case scraping yielded new urls
	found, err := findArticles(tx, art.URLs)
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
	pubID, err := findPublication(tx, art.Publication.Domain)
	if err == sql.ErrNoRows {
		// not found - create a new one
		pubID, err = createPublication(tx, &art.Publication)
		fmt.Printf("new publication [%d] %s\n", pubID, art.Publication.Domain)
	}
	if err != nil {
		return 0, err
	}

	//create/update article
	var artID int
	if len(found) == 0 {
		artID, err = insertArticle(tx, art, pubID)
	} else {
		artID = found[0]
		err = updateArticle(tx, artID, art, pubID)
	}
	if err != nil {
		return 0, err
	}

	// find/create journos
	journoIDs := []int{}
	for _, author := range art.Authors {
		j, err := resolveJourno(tx, &author, pubID, expectedJourno)
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
}
