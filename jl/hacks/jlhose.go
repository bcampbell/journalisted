package main

// $ curl localhost:9999/articles -H "Last-Event-ID: 33895"

import (
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	"github.com/donovanhide/eventsource"
	"github.com/golang/glog"
	"github.com/gorilla/mux"
	_ "github.com/lib/pq"
	"net"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"time"
)

var port = flag.Int("port", 9999, "port to run server on")
var dbstring = flag.String("db", "user=jl dbname=jl host=/var/run/postgresql sslmode=disable", "connection string for database")
var timeout = flag.Duration("timeout", 10*time.Second, "timeout for a client disconnection")
var interval = flag.Duration("interval", 5*time.Second, "delay between monitor polls")

type article struct {
	id, channel string
}

// articleEvent encodes article data we want to stream out as events
type articleEvent struct {
	// internal article id
	id int
	// Permalink is the canonical URL
	Permalink string
	// Title is the article headline
	Lastscraped time.Time
	Pubdate     time.Time
	Title       string `json:"title"`
	Title       string
	LastScraped time.Time
	Published   time.Time
	Source      string
	Urls        []string
	Journalists []string
	Content     string `json:"text"`
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

type articleRepository struct {
	*sql.DB
}

func (repo *articleRepository) streamArticles(channel, lastEventId string, stream chan *article) (last *article) {
	tx, err := repo.Begin()
	if err != nil {
		glog.Error(err)
		return
	}
	defer tx.Rollback()
	glog.V(2).Infoln("Declaring cursor", lastEventId, channel)
	_, err = tx.Exec(`DECLARE cur SCROLL CURSOR FOR
			SELECT a.id,
			o.shortname
            FROM article a
            LEFT OUTER JOIN organisation o
			ON a.srcorg=o.id
            WHERE a.id>$1
            AND ($2='articles' OR o.shortname=$2)
            ORDER BY lastscraped ASC;`, lastEventId, channel)
	glog.V(2).Infoln("Declared cursor", lastEventId, channel)
	if err != nil {
		glog.Error(err)
		return
	}
	last = new(article)
	for finished := false; !finished; {
		glog.V(2).Infoln("Fetching next 100..")
		rows, err := tx.Query(`FETCH FORWARD 100 FROM cur;`)
		if err != nil {
			glog.Error(err)
			return
		}
		finished = true
		for rows.Next() {
			finished = false
			var art article
			if err = rows.Scan(&art.id, &art.channel); err != nil {
				glog.Error(err)
				return
			}
			select {
			case <-time.After(*timeout):
				glog.Warning("Timeout on cursor pump")
				return
			case stream <- &art:
				last.channel = art.channel
				last.id = art.id
			}
		}
	}
	return
}

func (repo *articleRepository) Get(channel, lastEventId string) eventsource.Event {
	// Maybe this should be an inner join for articles with no content?
	row := repo.QueryRow(`	SELECT a.id,
							MAX(a.permalink),
							MAX(a.title),
							MAX(a.pubdate),
							MAX(a.lastscraped),
							COALESCE(MAX(o.prettyname),''),
							COALESCE(string_agg(u.url,' '),''),
							COALESCE(string_agg(j.prettyname,','),''),
							COALESCE(MAX(c.content),'') 
							FROM article a 
							LEFT OUTER JOIN article_content c 
							ON a.id=c.article_id
							LEFT OUTER JOIN article_url u
							ON a.id=u.article_id
							LEFT OUTER JOIN journo_attr attr
							ON a.id = attr.article_id
							LEFT OUTER JOIN journo j
							ON attr.journo_id=j.id
							LEFT OUTER JOIN organisation o
							ON a.srcorg=o.id
							WHERE a.id=$1
							GROUP BY a.id`, lastEventId)
	var art articleEvent
	var urls, journalists string
	if err := row.Scan(&art.id, &art.Permalink, &art.Title, &art.Published, &art.LastScraped, &art.Source, &urls, &journalists, &art.Content); err != nil {
		glog.Error(err)
		return nil
	}
	art.Urls = strings.Split(urls, " ")
	art.Journalists = strings.Split(journalists, ",")
	glog.V(2).Infof("Got Channel: %s from Last-Event-ID: %s", channel, lastEventId)
	return &art
}

func (repo *articleRepository) Replay(channel, lastEventId string) (ids chan string) {
	stream := make(chan *article)
	ids = make(chan string)
	glog.Infof("Replaying Channel: %s from Last-Event-Id: %s", channel, lastEventId)
	go func() {
		for art := range stream {
			ids <- art.id
		}
		close(ids)
	}()
	go func() {
		defer close(stream)
		last := repo.streamArticles(channel, lastEventId, stream)
		glog.Infof("Finished Replaying Channel: %s from Last-Event-ID: %s To: %s", channel, lastEventId, last.id)
	}()
	return
}

func (repo *articleRepository) Monitor(srv *eventsource.Server) {
	stream := make(chan *article)
	defer close(stream)
	var lastEventId string
	if err := repo.QueryRow("SELECT MAX(id) FROM article").Scan(&lastEventId); err != nil {
		glog.Fatal(err)
	}
	glog.Infof("Monitoring from Id: %s onwards ", lastEventId)
	go func() {
		for art := range stream {
			glog.Infof("Publishing Id: %s Channel: %s", art.id, art.channel)
			srv.Publish([]string{"articles", art.channel}, repo.Get(art.channel, art.id))
		}
	}()
	for {
		glog.V(2).Infoln("Polling...")
		if last := repo.streamArticles("articles", lastEventId, stream); last.id != "" {
			lastEventId = last.id
		}
		time.Sleep(*interval)
	}

}

func main() {
	flag.Parse()
	db, err := sql.Open("postgres", *dbstring)
	if err != nil {
		glog.Fatal(err)
	}
	if err = db.Ping(); err != nil {
		glog.Fatal(err)
	}
	repo := &articleRepository{db}
	srv := eventsource.NewServer()
	srv.Register("articles", repo)
	channels := make(map[string]struct{})
	var lock sync.Mutex
	router := mux.NewRouter().StrictSlash(false)
	router.HandleFunc("/", srv.Handler("articles"))
	router.HandleFunc("/{channel}/", func(resp http.ResponseWriter, req *http.Request) {
		channel := mux.Vars(req)["channel"]
		lock.Lock()
		if _, ok := channels[channel]; !ok {
			channels[channel] = struct{}{}
			srv.Register(channel, repo)
		}
		lock.Unlock()
		srv.Handler(mux.Vars(req)["channel"])(resp, req)
	})
	http.Handle("/", router)
	l, err := net.Listen("tcp", fmt.Sprintf(":%d", *port))
	if err != nil {
		glog.Fatal(err)
	}
	defer l.Close()
	glog.Infof("Listening on port %d", *port)
	go repo.Monitor(srv)
	http.Serve(l, nil)
}
