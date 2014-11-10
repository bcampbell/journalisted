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

func main() {
	var debug string
	flag.StringVar(&debug, "d", "", "log debug info to stderr (h=headline, c=content, a=authors d=dates all=hcad)")
	flag.Parse()

	if len(flag.Args()) != 1 {
		fmt.Println("Usage: ", os.Args[0], "<article url>")
		os.Exit(1)
	}

	// connect to db
	connStr := "postgres://jl@localhost/jl?sslmode=disable"
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

	foo := []string{artURL}
	found, err := findArticles(tx, foo)
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
		os.Exit(1)
	}

	if len(found) > 0 {
		fmt.Fprintf(os.Stderr, "Article already in db id: %v\n", found)
		os.Exit(1)
	}

	client := &http.Client{} //Timeout: 1 * time.Second}
	rawHTML, err := grabHTML(client, artURL)

	art, err := arts.ExtractHTML(rawHTML, artURL)
	if err != nil {
		panic(err)
	}

	//	writeYaml(os.Stdout, art)
	//	fmt.Printf("%q\n", art)

	// load article
	artID, err := insertArticle(tx, art, "")
	if err != nil {
		fmt.Fprintf(os.Stderr, "ERROR: %s - Rolling back\n", err)

		err = tx.Rollback()
		if err != nil {
			fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
			os.Exit(1)
		}
	} else {
		fmt.Printf("[A%d]\n", artID)
		err = tx.Commit()
		if err != nil {
			fmt.Fprintf(os.Stderr, "ERROR: %s\n", err)
			os.Exit(1)
		}
	}
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
