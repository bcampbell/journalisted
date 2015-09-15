package main

import (
	"database/sql"
	"fmt"
	"github.com/bcampbell/journalisted/golib/jl"
	//	"github.com/lib/pq"
	//	"time"
	"errors"
	//	"strings"
)

// TODO: handle updating publication with new info?

var ErrAmbiguousJourno = errors.New("Ambiguous Journo")

func stash(tx *sql.Tx, art *jl.Article, authors []*jl.UnresolvedJourno, expectedJournoRef string, logPrefix string) error {

	var err error
	// sanity checks
	if art.Publication == nil {
		return fmt.Errorf("missing publication")
	}

	if art.Publication.ID == 0 {
		// find or create publication
		// TODO: better publication resolution
		err = jl.ResolvePublication(tx, art.Publication)
		if err == jl.ErrNoPub {
			// not found - create a new one
			err = jl.InsertPublication(tx, art.Publication)
			if err != nil {
				return err
			}
			infoLog.Printf("%snew publication [%d] %s\n", logPrefix, art.Publication.ID, art.Publication.ShortName)
		}
		if err != nil {
			return err
		}
	}

	//create/update article
	if art.ID == 0 {
		err = jl.InsertArticle(tx, art)
		if err != nil {
			return fmt.Errorf("InsertArticle failed: %s\n", err)
		}
	} else {
		panic("uhoh") // TODO!
		err = jl.UpdateArticle(tx, art)
		if err != nil {
			return err
		}
	}

	// find/create journos

	journos := []*jl.Journo{}

	// de-dupe (just in case!)
	authors = jl.UniqUnresolvedJournos(authors)
	for _, author := range authors {
		j, err := sussJourno(tx, author, art.Publication.ID, expectedJournoRef, logPrefix)
		if err == ErrAmbiguousJourno {
			// TODO: need a better mechanism to notify ambiguous journos!
			// maybe a new database table?
			warnLog.Printf("%sAmbiguous Journo (%s) \n", logPrefix, author.Name)
			continue
		} else if err != nil {
			warnLog.Printf("%s%s (%s) \n", logPrefix, err, author.Name)
			//
			continue
		}
		journos = append(journos, j)
	}
	art.Authors = journos

	// zap old journo links (TODO: skip for new articles)
	_, err = tx.Exec("DELETE FROM journo_attr WHERE article_id=$1", art.ID)
	if err != nil {
		return err
	}

	// link journos to article
	for _, j := range art.Authors {
		// journo_attr
		_, err = tx.Exec("INSERT INTO journo_attr (journo_id,article_id) VALUES ($1,$2)", j.ID, art.ID)
		if err != nil {
			return fmt.Errorf("failed to attribute journo (j%d to a%d): %s", j.ID, art.ID, err)
		}

		// apply journo activation policy
		err = jl.JournoUpdateActivation(tx, j.ID)
		if err != nil {
			return err
		}

		// clear the html cache for that journos page
		/*
			cacheName := fmt.Sprintf("j%s", jid)
			_, err = tx.Exec("DELETE FROM htmlcache WHERE name=$1", cacheName)
			if err != nil {
				return 0, err
			}
		*/
	}

	return nil
}

// find or create a journo
func sussJourno(tx *sql.Tx, author *jl.UnresolvedJourno, pubID int, expectedJournoRef string, logPrefix string) (*jl.Journo, error) {

	prospects, err := jl.ResolveJourno(tx, author, pubID, expectedJournoRef)
	if err != nil {
		return nil, err
	}

	n := len(prospects)
	switch {
	case n == 1:
		return prospects[0], nil
	case n > 1:
		return nil, ErrAmbiguousJourno
	}
	// n == 0:
	newJourno, err := jl.CreateJourno(tx, author.Name)
	if err != nil {
		return nil, err
	}
	infoLog.Printf("%snew journo [j%d] %s\n", logPrefix, newJourno.ID, newJourno.Prettyname)
	return newJourno, nil
}
