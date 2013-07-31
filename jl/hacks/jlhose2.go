package main

// $ curl localhost:9999/articles -H "Last-Event-ID: 33895"

import (
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	//_ "github.com/bmizerany/pq"
	"github.com/bcampbell/eventsource"
	_ "github.com/lib/pq"
	"log"
	"net"
	"net/http"
	"strconv"
	"time"
)

// articleEvent encodes article data we want to stream out as events
type articleEvent struct {
	// internal article id
	id int
	// Permalink is the canonical URL
	Permalink string
	// TODO: VITAL TO INCLUDE ALTERNATE URLS FOR ARTICLE!
	// Title is the article headline
	Title       string
	Lastscraped time.Time
	Pubdate     time.Time
	Content     string
	// TODO: more fields!
	//  - journalisted url
	//  - publication info:  pretty name, home url etc..
}

func (art *articleEvent) Id() string {
	// TODO: currently just using internal article id as event id.
	// not too bad in practice - should always be ascending.
	return strconv.Itoa(art.id)
	// alternative:
	//	return strconv.Itoa(Lastscraped.Unix()) + "_" + strconv.Itoa(art.id)
}

func (art *articleEvent) Event() string {
	return "article"
}

func (art *articleEvent) Data() string {
	out, _ := json.Marshal(art)
	return string(out)
}

func fetchArticles(db *sql.DB, lastEventId string, batchSize int) ([]*articleEvent, error) {

	lowId := 0
	var err error
	if lastEventId != "" {
		lowId, err = strconv.Atoi(lastEventId)
		if err != nil {
			return nil, err
		}
	}

	rows, err := db.Query(`
        SELECT a.id,a.permalink,a.title,a.pubdate,a.lastscraped,c.content
            FROM article a LEFT JOIN article_content c ON a.id=c.article_id
            WHERE a.id>$1
            ORDER BY lastscraped ASC
            LIMIT $2
        `, lowId, batchSize)

	arts := make([]*articleEvent, 0, batchSize)
	for rows.Next() {
		var art articleEvent
		err = rows.Scan(&art.id, &art.Permalink, &art.Title, &art.Pubdate, &art.Lastscraped, &art.Content)
		if err != nil {
			return nil, err
		}
		arts = append(arts, &art)
	}
	return arts, nil
}

func main() {
	var port = flag.Int("port", 9999, "port to run server on")
	var batchSize = flag.Int("batchsize", 100, "max number of articles to send at once")
	var interval = flag.Int("interval", 60, "deplay between batches, in seconds")
	var dbstring = flag.String("db", "user=jl dbname=jl host=/var/run/postgresql sslmode=disable", "connection string for database")
	flag.Parse()

	db, err := sql.Open("postgres", *dbstring)
	if err != nil {
		panic(err)
	}
	defer db.Close()

	http.HandleFunc("/articles", handler(db, *batchSize, *interval))
	l, err := net.Listen("tcp", fmt.Sprintf(":%d", *port))
	if err != nil {
		return
	}
	defer l.Close()
	log.Printf("Listening on port %d", *port)
	http.Serve(l, nil)
}

func handler(db *sql.DB, batchSize int, sleepTimeSecs int) http.HandlerFunc {
	return func(w http.ResponseWriter, req *http.Request) {
		h := w.Header()
		h.Set("Content-Type", "text/event-stream; charset=utf-8")
		h.Set("Cache-Control", "no-cache, no-store, must-revalidate")
		h.Set("Connection", "keep-alive")
		/*
			if srv.AllowCORS {
				h.Set("Access-Control-Allow-Origin", "*")
			}
		*/
		lastEventId := req.Header.Get("Last-Event-ID")

		log.Printf("%s connected (lastEventId='%s')", req.RemoteAddr, lastEventId)

		flusher := w.(http.Flusher)
		notifier := w.(http.CloseNotifier)
		flusher.Flush()
		enc := eventsource.NewEncoder(w)

		wakeup := make(chan bool, 1)
		for {
			arts, err := fetchArticles(db, lastEventId, batchSize)
			if err != nil {
				panic(err)
			}
			log.Printf("%s sending batch (%d articles)", req.RemoteAddr, len(arts))
			for _, art := range arts {
				err := enc.Encode(art)
				if err != nil {
					panic(err)
				}
				log.Printf("%s send %s: %s", req.RemoteAddr, art.Id(), art.Title)
				lastEventId = art.Id()
			}
			flusher.Flush()

			// wait for
			go func() {
				time.Sleep(time.Duration(sleepTimeSecs) * time.Second)
				wakeup <- true
			}()

			select {
			case <-notifier.CloseNotify():
				log.Printf("%s disconnected", req.RemoteAddr)
				return
			case <-wakeup:
			}
		}
	}
}
