#
#
#

from optparse import OptionParser
import sys
import socket
import traceback
import urllib2
import httplib
import os
from datetime import datetime
import time
import random

import ukmedia, DB, ArticleDB
import feedparser


def unique_articles( arts ):
    """ removes dupes from list of articles """
    # (order preserving)
    seen = {}
    result = []
    for art in arts:
        id = art['permalink']
        if id in seen:
            continue
        seen[id] = 1
        result.append(art)
    return result





def scraper_main( find_articles, context_from_url, extract, max_errors=20, prep=None ):


    parser = OptionParser()
    parser.add_option( "-d", "--dryrun", action="store_true", dest="dry_run", help="don't touch the database" )
    parser.add_option( "-f", "--force", action="store_true", dest="force_rescrape", help="force rescrape of article if already in DB" )
 
    (opts, args) = parser.parse_args()

    # scraper might need to do a login
    if prep is not None:
        prep()

    if len(args) == 0:
        # do a full scraper run: discover articles and scrape 'em.
        # expect lots of errors for a lot of sites.

        found = find_articles()
    else:
        # urls passed in as params
        found = []
        for url in args:
            found.append(context_from_url(url))

    scrape_articles(found, extract, max_errors, opts)




def scrape_articles( found, extract, max_errors, opts):
    """Scrape list of articles, return error counts.

    found -- list of article contexts to scrape
    extract -- extract function
    max_errors -- tolerated number of errors before bailing
    opts -- dry_run, force_rescrape etc...
    """

    extralogging = False


    # remove dupes (eg often articles appear in more than one RSS feed)
    found = unique_articles(found)

    # randomise the order of articles, so that if the scraper does abort
    # due to too many errors, successive runs should be able to pick up
    # all the scrapable articles.
    random.shuffle(found)
    assert(len(found)>0)
    ukmedia.DBUG2("%d articles to scrape\n" % (len(found)))

    if opts.dry_run:
        store = ArticleDB.ArticleDB( dryrun=True, reallyverbose=True )  # testing
    else:
        store = ArticleDB.ArticleDB()


    failcount = 0
    abortcount = 0
    newcount = 0
    had_count = 0
    rescrape_count = 0

    for context in found:

        conn = DB.conn()
        srcid = context['srcid']
        if not srcid:
            ukmedia.DBUG2( u"WARNING: missing srcid! '%s' ( %s )\n" % (getattr(context,'title',''), context['srcurl'] ) )
            continue

        try:
            article_id = store.ArticleExists( srcid )
            if article_id:
                if extralogging:
                    ukmedia.DBUG( u"already got %s [a%s] (attributed to: %s)\n" % (context['srcurl'], article_id,GetAttrLogStr(conn,article_id) ) )
                if not opts.force_rescrape:
                    had_count += 1
                    continue;   # skip it - we've already got it

            #ukmedia.DBUG2( u"fetching %s\n" % (context['srcurl']) )
            html = ukmedia.FetchURL( context['srcurl'] )
 
            # some extra, last minute context :-)
            context[ 'lastscraped' ] = datetime.now()

            art = extract( html, context )


            if art:
                if article_id:
                    # rescraping existing article
                    art['id'] = article_id
                    article_id = store.upsert( art )
                    rescrape_count += 1
                else:
                    #
                    article_id = store.upsert( art )
                    newcount += 1


        except Exception, err:
            # always just bail out upon ctrl-c
            if isinstance( err, KeyboardInterrupt ):
                raise

            failcount = failcount+1
            # TODO: phase out NonFatal! just get scraper to print out a warning message instead
            if isinstance( err, ukmedia.NonFatal ):
                continue

            report = traceback.format_exc()

            if 'title' in context:
                msg = u"FAILED (%s): '%s' (%s)" % (err, context['title'], context['srcurl'])
            else:
                msg = u"FAILED (%s): (%s)" % (err,context['srcurl'])
            ukmedia.DBUG( msg + "\n" )
            ukmedia.DBUG2( report + "\n" )
            ukmedia.DBUG2( '-'*60 + "\n" )

            abortcount = abortcount + 1
            if abortcount >= max_errors:
                print >>sys.stderr, "Too many errors - ABORTING"
                raise
            #else just continue with next article

    ukmedia.DBUG("%d new, %d already had, %d rescraped, %d failed\n" % (newcount, had_count, rescrape_count, failcount))


# Assorted scraping stuff
#

def FindArticlesFromRSS( rssfeeds, srcorgname, mungefunc, maxerrors=5 ):
    """ Get a list of current articles from a set of RSS feeds """

    socket.setdefaulttimeout( ukmedia.defaulttimeout )  # in seconds

    ukmedia.DBUG2( "*** %s ***: reading rss feeds...\n" % (srcorgname) )

    # ugly, but temporary. Flaw in dictionaries is that we can't have two feeds with the same name. big problem. So we're switching to lists instead.
    # TODO: change all scrapers to use lists of tuples instead of dicts, then kill this code!
    if isinstance( rssfeeds, dict):
        d = rssfeeds
        rssfeeds = []
        # it's a dictionary {name:url} (phasing this out!!!)
        for feedname, feedurl in d.iteritems():
            rssfeeds.append( (feedname,feedurl) )

    foundarticles = []
    errcnt = 0
    for f in rssfeeds:
        try:
            feedname = f[0]
            feedurl = f[1]
            foundarticles = foundarticles + ReadFeed( feedname, feedurl, srcorgname, mungefunc )
        except (Exception), e:
            msg = u"ERROR fetching feed '%s' (%s): %s" % (feedname,feedurl,e.__class__)
            ukmedia.DBUG( msg + "\n" )
#            print >>sys.stderr, '-'*60
#            print >>sys.stderr, traceback.format_exc()
#            print >>sys.stderr, '-'*60

            errcnt = errcnt + 1
            if errcnt >= maxerrors:
                print >>sys.stderr, "Too many RSS errors - ABORTING"
                raise


    return foundarticles



def ReadFeed( feedname, feedurl, srcorgname, mungefunc=None ):
    """fetch a list of articles from an RSS feed"""

    foundarticles = []
    ukmedia.DBUG2( "feed '%s' (%s)\n" % (feedname,feedurl) )

    if ukmedia.USE_CACHE:
        ukmedia.FetchURL(feedurl, ukmedia.defaulttimeout, "rssCache" )
        r = feedparser.parse( os.path.join( "rssCache", ukmedia.GetCacheFilename(feedurl) ) )
    else:
        r = feedparser.parse( feedurl )
        
    #debug:     print r.version;

    lastseen = datetime.now()
    for entry in r.entries:
        #Each item is a dictionary mapping properties to values

        if not hasattr( entry, 'link' ):
            # fix to cope with bad telegraph rss feeds with empty <item/>
            ukmedia.DBUG2( "UHOH - missing link attr (feed '%s' - %s)\n" % (feedname,feedurl) )
            continue

        url = entry.link        # will be guid if no link attr
        title = entry.title
        if hasattr(entry, 'summary'):
            desc = entry.summary
        else:
            desc = u''

        pubdate = None
        if hasattr(entry, 'updated_parsed'):
            if entry.updated_parsed:
                pubdate = datetime.fromtimestamp(time.mktime(entry.updated_parsed))

#       print "New desc: ",desc.encode('latin-1','replace')

        title = ukmedia.DescapeHTML( title )
        desc = ukmedia.FromHTML( desc )
            

        context = {
            'srcid': url,
            'srcurl': url,
            'permalink': url,
            'description': desc,
            'title' :title,
            'srcorgname' : srcorgname,  # kill this! Might not know it until article has been scraped!
            'lastseen': lastseen,
            'feedname': feedname #gtb
            }

        if pubdate:
            context['pubdate'] = pubdate

        if mungefunc:
            context = mungefunc( context, entry )
            # mungefunc can suppress by returning None.
            if not context:
                continue
            if not context.get( 'srcid', None ):
                ukmedia.DBUG2( "WARNING: missing/null srcid! ('%s')\n" % (context['srcurl']) )

        foundarticles.append( context )

    return foundarticles


def ShouldSkip( conn, srcid ):
    """ returns True if an article has a skip order on it (ie don't scrape!) """
    skip = False
    c = conn.cursor()
    c.execute( "SELECT * FROM error_articlescrape WHERE srcid=%s",(srcid) )
    row = c.fetchone()
    if row:
        if row['action'] == 's':
            skip = True
    c.close()
    return skip


def LogScraperError( conn, context, report ):
    c = conn.cursor()
    srcid = context['srcid']

    title = getattr(context, 'title', u'' )

    c.execute( "SELECT * FROM error_articlescrape WHERE srcid=%s", srcid )
    row = c.fetchone()
    if row:
        # article has an existing error entry...
        c.execute( "UPDATE error_articlescrape SET report=%s, attempts=attempts+1,lastattempt=NOW() WHERE srcid=%s", (report,srcid) )
    else:
        # log the error
        params = ( srcid, '', title, context['srcurl'], 1, report, ' ' )
        c.execute( "INSERT INTO error_articlescrape (srcid,scraper,title,srcurl,attempts,report,action,firstattempt,lastattempt) VALUES( %s,%s,%s,%s,%s,%s,%s,NOW(),NOW() )", params )
    c.commit()
    c.close()


def GetAttrLogStr( conn,article_id ):
    """ return a list of attributed journos for logging eg "[a1234 fred blogs], [a4321 bob roberts]" """
    c = conn.cursor()
    sql = """
SELECT j.id,j.ref,j.prettyname
    FROM ( JOURNO j INNER JOIN journo_attr attr ON attr.journo_id=j.id )
    WHERE attr.article_id=%s
"""
    c.execute( sql, article_id )
    rows = c.fetchall()
    return ", ".join( [ "[j%d %s]" % (int(row['id']),row['prettyname']) for row in rows ] )



# TODO: kill this (still used by scrape-tool for now)
def ProcessArticles( foundarticles, store, extractfn, maxerrors=10, extralogging=False, forcerescrape=False ):
    """Download, scrape and load a list of articles

    Each entry in foundarticles must have at least:
        srcurl, srcorgname, srcid
    Assumes entire article can be grabbed from srcurl.

    extractfn - function to extract an article from the html
    extralogging - enables extra debug output (eg when article already is in DB, etc)
    forcerescrape - if True, rescrape articles already in DB
    """
    failcount = 0
    abortcount = 0
    newcount = 0

    for context in foundarticles:

        conn = DB.conn()
        srcid = context['srcid']
        if not srcid:
            ukmedia.DBUG2( u"WARNING: missing srcid! '%s' ( %s )\n" % (getattr(context,'title',''), context['srcurl'] ) )
            continue


        if ShouldSkip( conn, srcid ):
            ukmedia.DBUG2( u"s for skip: %s (%s)\n" % (getattr(context,'title',''), context['srcurl'] ) )
            continue

        try:
            article_id = store.ArticleExists( srcid )
            if article_id:
                if extralogging:
                    ukmedia.DBUG( u"already got %s [a%s] (attributed to: %s)\n" % (context['srcurl'], article_id,GetAttrLogStr(conn,article_id) ) )
                if not forcerescrape:
                    continue;   # skip it - we've already got it

            #ukmedia.DBUG2( u"fetching %s\n" % (context['srcurl']) )
            html = ukmedia.FetchURL( context['srcurl'] )
 
            # some extra, last minute context :-)
            context[ 'lastscraped' ] = datetime.now()

            art = extractfn( html, context )


            if art:
                if article_id:  # rescraping existing article?
                    art['id'] = article_id

                article_id = store.Add( art )
                newcount = newcount + 1


        except Exception, err:
            # always just bail out upon ctrl-c
            if isinstance( err, KeyboardInterrupt ):
                raise

            failcount = failcount+1
            # TODO: phase out NonFatal! just get scraper to print out a warning message instead
            if isinstance( err, ukmedia.NonFatal ):
                continue

            report = traceback.format_exc()

            if 'title' in context:
                msg = u"FAILED (%s): '%s' (%s)" % (err, context['title'], context['srcurl'])
            else:
                msg = u"FAILED (%s): (%s)" % (err,context['srcurl'])
            ukmedia.DBUG( msg + "\n" )
            ukmedia.DBUG2( report + "\n" )
            ukmedia.DBUG2( '-'*60 + "\n" )

            if not store.dryrun:    # UGH.
                LogScraperError( conn, context, report )

            abortcount = abortcount + 1
            if abortcount >= maxerrors:
                print >>sys.stderr, "Too many errors - ABORTING"
                raise
            #else just skip this one and go on to the next...

    ukmedia.DBUG( "%s: %d new, %d failed\n" % (sys.argv[0], newcount, failcount ) )
    return (newcount,failcount)

