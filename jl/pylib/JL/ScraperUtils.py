# General helpers for writing scrapers
#
#

from optparse import OptionParser
import sys
import socket
import traceback
#import httplib
import os
import re
from datetime import datetime
import time
import random
#import cookielib
import urlparse

# NOTE: would be nicer to migrate from urllib2 to requests for our http traffic,
# but it's a little fiddly because:
#  1) need to research how to hook in to provide rate throttling (and caching, as per JL_USE_CACHE=1)
#  2) we'd use sessions to differentiate between cookie-preserving and non-cookie-preserving, and
#     there doesn't seem to be a way in requests to ignore cookies in sessions, short of writing
#     our own dummy cookiejar implementation (not too hard, but research required).
#
# So, for now, sticking with crufty old urllib2 and all the stuff we've built on top of it.
import urllib2
import contextlib
import ukmedia
import DB
import ArticleDB
import Byline
import Publication
import Journo
import Misc

import feedparser
import lxml.html

from urllib2helpers import CollectingRedirectHandler, ThrottlingProcessor, CacheHandler





def build_uber_opener(cookiejar=None):
    """ build a super url opener which handles redirects, throttling, caching, cookies... """

    handlers = []

    throttle_delay = float( os.getenv( 'JL_FETCH_INTERVAL' ,'1' ) )

    if os.getenv('JL_USE_CACHE','false').lower() not in ('0', 'false','off'):
        handlers.append(CacheHandler(".jlcache"))

    # throttling handler should be after caching handler, so
    # cached requests return without being throttled.
    handlers.append(ThrottlingProcessor(throttle_delay))

    if cookiejar is not None:
        handlers.append(urllib2.HTTPCookieProcessor(cookiejar))

    # redirect handler needs to be last, as it adds "redirects"
    # member to the final response object
    handlers.append(CollectingRedirectHandler())

    return urllib2.build_opener(*handlers)


# install a default url handler which throttles and handles JL_FETCH_INTERVAL and JL_USE_CACHE
#cookiejar = cookielib.LWPCookieJar()
default_opener = build_uber_opener(cookiejar=None)
urllib2.install_opener(default_opener)



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


canonical_url_pats = [
    re.compile(r'<link\s+[^>]*rel\s*=\s*"canonical"[^>]*href\s*=\s*"(.*?)"', re.IGNORECASE),
    re.compile(r'<link\s+[^>]*href\s*=\s*"(.*?)"[^>]*rel\s*=\s*"canonical"', re.IGNORECASE),
    # opengraph url property (makes huge "og:" namespace assumptions but hey :-)
    re.compile(r'<meta\s+property\s*=\s*"og:url"\s+content="(.*?)"\s*/>', re.IGNORECASE),
    ]

def extract_canonical_url(html, base_url):
    """ scan html for canonical page url. Returns url or None
 
    supports rel=canonical and og:url
    """

    # TODO: this should handle badly/un-encoded URLS
    # (eg http://www.express.co.uk/blogs/post/268/blog/2012/03/18/308764/No-spring-in-the-blackcaps-step)
    # but to do that we really need to know the character encoding of the
    # page.
    # SO, rel-canonical extraction should really be done by the scraper
    # itself, as part of extract(). Silly to change it now while we've
    # still got lots of custom scrapers, but once we ditch 'em in favour of
    # a single generic scraper, that scraper should do rel-canonical
    # processing...

    # TODO: handle malformed <head>
    # eg "http://www.theeastafrican.co.ke/business/Kenya+allows+public+online+access+to+govt+data/-/2560/1197916/-/hh4v0e/-/"
    # (missing opening <head> tag)

    m = re.compile(r'<head[^>]*>(.*?)</head\s*>',re.DOTALL|re.IGNORECASE).search(html)
    if not m:
        return None     # some sites have _really_ bad html ;-)
    head_html = m.group(1)

    for pat in canonical_url_pats:
        m = pat.search(head_html)
        if m is not None:
            url = m.group(1).strip()
            o = urlparse.urlparse(url)
            if o[0]=='' or o[1]=='':    # relative url?
                url = urlparse.urljoin(base_url,url)
            return url
    return None


def tidy_url(url):
    """ Apply some general rules to clean up urls which have obvious cruft in them """

    # remove silly rss indicators...

    tidy_pats = [
        re.compile(r"[?]rss=yes$", re.I),
        re.compile(r"[?]r=rss$", re.I),
        ]

    for pat in tidy_pats:
        url = pat.sub('',url)
    return url





def scraper_main( find_articles, context_from_url, extract, max_errors=20, prep=None, sesh=None ):
    """ a commandline frontend and loop for scrapers to use """

    usage = """usage: %prog [options] <urls>

    If no <urls> are provided, the scraper will scan the site for
    articles and try to scrape them all.

    If you need cookies (eg for paywall login) pass in a custom url opener via sesh.

    """

    parser = OptionParser(usage=usage)
    parser.add_option("-t", "--test", action="store_true", dest="test", help="test with a dry run - don't commit to the database")
    parser.add_option("-f", "--force", action="store_true", dest="force_rescrape", help="force rescrape of article if already in DB")
    parser.add_option("-m", "--max_errors", type="int", default=max_errors, help="set num of errors allowed before quitting (default %d)" % (max_errors,))
    parser.add_option('-j', '--expected_journo', dest="expected_journo", help="journo ref to help resolve ambiguous cases (eg 'fred-bloggs-1')")
    parser.add_option('-d', '--discover', action="store_true", dest="discover", help="discover articles, dump list to stdout and exit")
    parser.add_option('-r', '--raw', action="store_true", dest="raw", help="just fetch article, dump the raw data to stdout and exit")
 
    (opts, args) = parser.parse_args()

    if sesh is None:
        sesh = default_opener

    # scraper might need to do a login
    if prep is not None:
        prep(sesh)

    if len(args) == 0:
        # do a full scraper run: discover articles and scrape 'em.
        # expect lots of errors for a lot of sites.

        found = find_articles(sesh=sesh)
    else:
        # urls passed in as params
        found = []
        for url in args:
            found.append(context_from_url(url))

    if opts.discover:
        for a in found:
            print( a['permalink']);
    else:
        scrape_articles(found, extract, opts, sesh=sesh)



def scrape_articles( found, extract, opts, sesh = None):
    """Scrape list of articles, return error counts.

    found -- list of article contexts to scrape
    extract -- extract function
    opts:
        max_errors -- tolerated number of errors before bailing
        test
        force_rescrape
        etc...
    sesh -- url opener (None for default)
    """

    if sesh is None:
        sesh = default_opener


    extralogging = False
    max_errors = getattr(opts,'max_errors',0)
    expected_journo = getattr(opts,'expected_journo',None)

    # remove dupes (eg often articles appear in more than one RSS feed)
    found = unique_articles(found)

    # randomise the order of articles, so that if the scraper does abort
    # due to too many errors, successive runs should be able to pick up
    # all the scrapable articles.
    random.shuffle(found)
    #assert(len(found)>0)
    ukmedia.DBUG2("%d articles to scrape\n" % (len(found)))

    if opts.test:
        ukmedia.DBUG("DRY RUN\n")

    store = ArticleDB.ArticleDB()


    failcount = 0
    abortcount = 0
    newcount = 0
    had_count = 0
    rescrape_count = 0

    for context in found:

        try:
            known_urls = set((context['srcurl'], context['permalink']))
            got = store.find_article(known_urls)
            if len(got) > 0:
                if extralogging:
                    for article_id in got:
                        ukmedia.DBUG( u"already got %s [a%s] (attributed to: %s)\n" % (context['srcurl'], article_id,GetAttrLogStr(article_id)))
                if not opts.force_rescrape:
                    had_count += 1
                    continue;   # skip it - we've already got it
                else:
                    assert(len(got) == 1)


            #ukmedia.DBUG2( u"fetching %s\n" % (context['srcurl']) )
            #with sesh.open(context['srcurl']) as resp:
            with contextlib.closing(sesh.open(context['srcurl'])) as resp:
                # is the server sending an charset encoding?
                kwargs = {}
                content_type = resp.info().getheader('Content-Type','')
                m = re.compile(r';\s*charset\s*=\s*([^;]*)', re.I).search(content_type)
                if m:
                    kwargs['encoding'] = m.group(1)

                # grab the content
                html = resp.read()

                if opts.raw:
                    # just dump instead of processing
                    print(html)
                    continue

                # add any URLs we were redirected via...
                for code,url in resp.redirects:
                    known_urls.add(url)
                    if code==301:    # permanant redirect
                        context['permalink'] = url

            # check html for a rel="canonical" link:
            canonical_url = extract_canonical_url(html, context['permalink'])
            if canonical_url is not None:
                known_urls.add(canonical_url)
                context['permalink'] = canonical_url

            # strip off "?rss=yes" etc from permalink
            tidied_url = tidy_url(context['permalink'])
            if tidied_url != context['permalink']:
                context['permalink'] = tidied_url
                known_urls.add(tidied_url)

            context['urls'] = known_urls

            # check that all urls are OK (eg express.co.uk have a habit of publishing borked ones for blogs)
            for url in known_urls:
                url.encode('utf-8') # will raise an exception if dud

            # repeat url-based existence check with the urls we now have
            # TODO: if so, add any new urls... maybe rescrape and update article? 
            article_id = None
            got = store.find_article(known_urls)
            if len(got) > 0:
                if extralogging:
                    for article_id in got:
                        ukmedia.DBUG( u"already got %s [a%s] (attributed to: %s)\n" % (context['srcurl'], article_id,GetAttrLogStr(article_id)))
                if not opts.force_rescrape:
                    had_count += 1
                    continue;   # skip it - we've already got it
                else:
                    assert(len(got) == 1)
                    article_id = got[0]

            # some extra, last minute context :-)
            context[ 'lastscraped' ] = datetime.now()

            art = extract(html, context, **kwargs)

            if art:
                # set the srcorg id for the article
                if 'srcorgname' in art and art['srcorgname'] is not None:
                    srcorg = Misc.GetOrgID( art[ 'srcorgname' ] )
                else:
                    # no publication specified - look up using domain name
                    o = urlparse.urlparse(art['permalink'])
                    domain = o[1].lower()
                    srcorg = Publication.find_or_create(domain)
                art['srcorg'] = srcorg


                # resolve bylined authors to journo ids
                authors = Byline.CrackByline(art['byline'])
                attributed = []
                for author in authors:
                    attributed.append(Journo.find_or_create(author, art, expected_journo))
                art['journos'] = attributed

                if opts.test:
                    ukmedia.PrettyDump( art )

                if article_id:
                    # rescraping existing article
                    art['id'] = article_id
                    article_id = store.upsert( art )
                    rescrape_count += 1
                else:
                    #
                    article_id = store.upsert( art )
                    newcount += 1



                if opts.test:
                    DB.conn().rollback()
                else:
                    DB.conn().commit()


        except Exception, err:
            DB.conn().rollback()

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
            if abortcount > max_errors:
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
        except urllib2.HTTPError as e:
            ukmedia.DBUG( "HTTPError fetching feed '%s' (%s): code %s\n" % (feedname,feedurl,e.code))
            errcnt += 1
            if errcnt >= maxerrors:
                print >>sys.stderr, "Too many RSS errors - ABORTING"
                raise
        except urllib2.URLError as e:
            ukmedia.DBUG( "URLError fetching feed '%s' (%s): %s\n" % (feedname,feedurl,e.reason))
            errcnt += 1
            if errcnt >= maxerrors:
                print >>sys.stderr, "Too many RSS errors - ABORTING"
                raise
        except Exception as e:
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



def ReadFeed( feedname, feedurl, srcorgname, mungefunc=None, sesh=None ):
    """fetch a list of articles from an RSS feed"""

    foundarticles = []
    ukmedia.DBUG2( "feed '%s' (%s)\n" % (feedname,feedurl) )

    if sesh is None:
        sesh = default_opener

    stream = sesh.open(feedurl)
    r = feedparser.parse(stream)

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
        desc = ukmedia.truncate_words(desc,50)
            

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

        foundarticles.append( context )

    return foundarticles






def GetAttrLogStr(article_id ):
    """ return a list of attributed journos for logging eg "[a1234 fred blogs], [a4321 bob roberts]" """
    c = DB.conn().cursor()
    sql = """
SELECT j.id,j.ref,j.prettyname
    FROM ( JOURNO j INNER JOIN journo_attr attr ON attr.journo_id=j.id )
    WHERE attr.article_id=%s
"""
    c.execute( sql, (article_id,) )
    rows = c.fetchall()
    return ", ".join( [ "[j%d %s]" % (int(row['id']),row['prettyname']) for row in rows ] )



def GenericFindArtLinks(start_page, domain_whitelist, nav_sel, nav_blacklist=[], art_url_pat=None, article_blacklist=[],sesh=None):
    """ find article links by iterating through navigation links
    
    start_page              - url of inital location
    nav_sel                  - css selector string to match nav links (eg "#siteheader nav a")
    domain_whitelist        - accepted domains (reject anything else)
    nav_blacklist           - reject any sections with urls containing any of these strings (eg ['/topic/',] )
    art_url_pat             - regex string to match article URLs
    article_blacklist       - (optional) list of substrings or regexes of dud urls
    sesh                    - (optional) the url opener to use to fetch pages
    """

    assert art_url_pat is not None

    if article_blacklist is None:
        article_blacklist = []

    sections = set( (start_page,))
    sections_seen = set(sections)
    http_err_cnt = 0
    fetch_cnt = 0

    arts = set()

    while len(sections)>0:
        section_url = sections.pop()

        try:
            fetch_cnt += 1
            #ukmedia.DBUG2("fetch %s\n" % (section_url,))
            html = ukmedia.FetchURL(section_url,sesh=sesh)
        except urllib2.HTTPError as e:
            # allow a few http errors...
            if e.code in (404,500,302):
                ukmedia.DBUG("ERR fetching %s (%d)\n" %(section_url,e.code))
                http_err_cnt += 1
                if http_err_cnt < 5:
                    continue
            raise

        try:
            doc = lxml.html.fromstring(html)
            doc.make_links_absolute(section_url)
        except lxml.etree.XMLSyntaxError as e:
            ukmedia.DBUG("ERROR parsing %s: %s\n" %(section_url, e))
            continue


        # check nav bars for sections to scan
        for navlink in doc.cssselect(nav_sel):
            url = navlink.get('href')
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue
            # strip fragment
            url = urlparse.urlunparse((o[0],o[1],o[2],o[3],o[4],''))

            if url in sections_seen:
                continue

            sections_seen.add(url)

            if [foo for foo in nav_blacklist if foo in url]:
                ukmedia.DBUG2("IGNORE %s\n" %(url, ))
                continue

            # section is new and looks ok - queue for scanning
            #ukmedia.DBUG( "Queue section %s\n" % (url,))
            sections.add(url)

        # now scan this section page for article links
        section_arts = set()
        for a in doc.cssselect('body a'):
            url = a.get('href',None)
            if url is None:
                continue
            if art_url_pat.search(url) is None:
                continue
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue

            section_arts.add(url)

        ukmedia.DBUG2("%s: found %d articles\n" % (section_url,len(section_arts) ) )
        arts.update(section_arts)


    # apply article blacklist to filter out duds
    out = []
    for url in arts:
        good = True
        for pat in article_blacklist:
            try:
                # assume regex...
                if pat.search(url):
                    good = False
            except AttributeError:
                # ...oops. treat as plain string
                if pat in url:
                    good = False
        if good:
            out.append(url)

    ukmedia.DBUG("crawl finished: %d articles (from %d fetches)\n" % (len(out),fetch_cnt,) )
    return out

