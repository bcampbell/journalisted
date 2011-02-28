#!/usr/bin/env python
#
# Scraper for Financial Times
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# NOTES:
# This scraper handles both blogs and news articles
# Login form is a separate page, in a iframe:
# http://media.ft.com/h/subs2.html
#
# TODO:
# - how to handle NY times articles on FT site?
# - handle ft Lex articles?
#

import sys
import re
from datetime import datetime
import sys
import urllib
import urllib2
import cookielib
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils



def FetchRSSFeeds( masterpage='http://www.ft.com/servicestools/newstracking/rss' ):
    feeds = {}

    feedpat = re.compile( "http://.*" )
    f = urllib2.urlopen( masterpage )
    html = f.read()
    f.close()

    soup = BeautifulSoup( html )

    c = soup.find( 'div', id='content' )
    for a in c.findAll('a', href=feedpat):
        url = a['href'] #urlparse.urljoin( masterpage, a['href'] )
        o = urlparse.urlparse(url)
        if o[1] not in ('www.ft.com','blogs.ft.com' ):
            continue

        #a.img.extract()
        name = ukmedia.FromHTML(a.renderContents(None))

        # their blog RSS links used to be wrong
#        if o[1] == 'blogs.ft.com':
#            url = url.replace( '/rss.xml', '/feed/' )

        feeds[ name ] = url

    return feeds



# Storage for cookies we receive in this session, required
# to access subscription content.
# We won't bother saving it to disk - just throw it away after
# this run of the scraper.
cookiejar = cookielib.LWPCookieJar()


# file with our FT subscription details
FT_CONFIG_FILE = '../conf/ft.conf'

def Prep():
    """ Prepare for scraping (by logging into FT with our user account) """ 
    global cookiejar
    # install Cookie handling - all further urllib2 activity
    # should include cookies from now on. 
    opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookiejar))
    urllib2.install_opener(opener)
    Login()



def Login():
    global cookiejar

    # parse config file to get our username/password
    foo = open( FT_CONFIG_FILE, 'rt' ).read()
    postvals = {}
    for field in ('username', 'password'):
        m = re.search( "^\\s*" + field + "\\s*=\\s*(.*?)\\s*(#.*)?$", foo, re.MULTILINE )
        if m:
            postvals[field] = m.group(1)


    # log on!
    url="https://registration.ft.com/registration/barrier"
    txdata = urllib.urlencode( postvals )
    ukmedia.DBUG2( "Logging in to ft.com (as '%s')\n" % (postvals['username']) )    
    req = urllib2.Request(url, txdata)
    handle = urllib2.urlopen(req)

    # check for incorrect-login response
    html = handle.read()
    if html.find( "<h1 class=\"highLight\">Incorrect login details</h1>" ) != -1:
        raise Exception, "Login failed - incorrect login details"

    # cookiejar should now contain our credential cookies.

#   print 'These are the cookies we have received so far :'
#   for index, cookie in enumerate(cookiejar):
#       print index, '  :  ', cookie


def Extract( html, context ):
    o = urlparse.urlparse( context['srcurl'] )
    if o[1] == 'blogs.ft.com':
        return Extract_blog( html, context ) 
    else:
        return Extract_article( html, context ) 


def Extract_article( html, context ):
    """ extract fn for FT main articles """
    art = context
    soup = BeautifulSoup( html )

    headerdiv = soup.find( 'div', {'class':'ft-story-header'} )
    
    h = headerdiv.find( ['h1','h2','h3' ] )
    headline = h.renderContents( None )
    headline = ukmedia.FromHTML( headline )

    art['title'] = headline


    # Check for reuters/NYT stories
    footerp = soup.find( 'p', {'class':'ft-story-footer'} )
    if footerp:
        txt = footerp.renderContents(None)
        if u'Reuters Limited' in txt:
            ukmedia.DBUG2( "IGNORE: Reuters item '%s' (%s)\n" % (headline, art['srcurl']) )
            return None
        if u'The New York Times Company' in txt:
            ukmedia.DBUG2( "IGNORE: NYT item '%s' (%s)\n" % (headline, art['srcurl']) )
            return None

    # "Published: February 10 2008 22:05 | Last updated: February 10 2008 22:05"
    # "Published: Mar 07, 2008"
    datepat = re.compile( ur"Published:\s+(.*)\s*[|]?", re.UNICODE )

    byline = u''
    pubdate = None
    for p in headerdiv.findAll( 'p' ):
        txt = p.renderContents( None )
        if u'By Reuters' in txt:
            ukmedia.DBUG2( "IGNORE: Reuters-bylined item '%s' (%s)\n" % (headline, art['srcurl']) )
            return None

        m = datepat.match( txt )
        if m:
            # it's the datestamp
            pubdate = ukmedia.ParseDateTime( m.group(1) ) 
        else:
            if byline != u'':
                raise Exception, "uh-oh..."
            byline = ukmedia.FromHTML( txt )

    byline = u' '.join( byline.split() )

    art['byline'] = byline
    art['pubdate'] = pubdate



    bodydiv = soup.find( 'div', {'class':'ft-story-body'} )

    for cruft in bodydiv.findAll( 'script' ):
        cruft.extract()

    content = bodydiv.renderContents( None )
    content = ukmedia.SanitiseHTML( content )
    content = content.strip()

    # some really screwed up pages have an extra body element wrapped around
    # the article text... sigh...
    # (in those cases, the previous code should through up empty content)
    if content == u'':
        bs = soup.findAll('body')
        if len(bs) > 1:
            ukmedia.DBUG2( "Extra body div!\n" );
            content = bs[-1].renderContents( None )
            content = ukmedia.SanitiseHTML( content )

    art['content'] = content

    art['description'] = ukmedia.FirstPara( content )



    if soup.find( 'div', {'id':'DRMUpsell'} ):
        pagetitle = soup.title.renderContents(None)
        if u'/ Lex /' in pagetitle:
            ukmedia.DBUG2( "IGNORE: Lex Premium-subscriber-only '%s' (%s')\n" % (headline, art['srcurl']) )
            return None
        else:
            raise Exception, "Uh-oh... we're being shortchanged... (login failed?)"

    return art


def Extract_blog( html, context ):
    """ extract fn for FT blog entries
    
    basically hAtom, but missing proper author and date bits... sigh...
    """

    art = context
    soup = BeautifulSoup( html )

    # standard hAtom...

    hentry = soup.find( True, {'class':re.compile(r'\bhentry\b')} )

    entry_title = hentry.find( True, {'class': re.compile(r'\bentry-title\b') } )
    art['title'] = ukmedia.FromHTMLOneLine( entry_title.renderContents(None) )

    entry_contents = hentry.findAll( True, {'class': re.compile( r'\bentry-content\b' ) } )
    art['content'] = ukmedia.SanitiseHTML( u''.join([ foo.renderContents(None) for foo in entry_contents ]) )
    art['description'] = ukmedia.FirstPara( art['content'] )



    # now we depart from hAtom for date and byline...

    entry_date = hentry.find( True, { 'class': re.compile(r'\bentry-date\b') } )
    # eg "December 29, 2010 4:35 pm "
    art['pubdate'] = ukmedia.ParseDateTime( entry_date.renderContents(None) )

    byline = u''
    author_byline = hentry.find( True, {'class': re.compile(r'\bauthor_byline\b') } )
    if author_byline is not None:
        byline = ukmedia.FromHTMLOneLine( author_byline.renderContents(None) )
    else:

        specialcases = { '/material-world/':u'Vanessa Friedman',
            '/gavyndavies/': u'Gavyn Davies',
            '/martin-wolf-exchange/': u'Martin Wolf',
        }
        h1 = ukmedia.FromHTMLOneLine( soup.h1.renderContents( None) )
        m = re.search( r"\s*(.*)'s blog", h1 )
        if m:
            byline = m.group(1)
        else:
            for k,v in specialcases.iteritems():
                print k
                if k in art['permalink']:
                    byline = v
                    break

    art['byline'] = byline

    # comments are added in using javascript (hosted on 'inferno')

    return art



def Extract_blog_OLD( html, context ):
    """ extract fn for FT blog entries """
    art = context
    soup = BeautifulSoup( html )

    postdiv = soup.find( 'div', {'class':re.compile(r'\bpost\b')} )

    h3 = postdiv.find( 'h3', {'class': re.compile('entry_header')} )
    headline = h3.renderContents(None)
    headline = ukmedia.FromHTML( headline )

    # try and pull a byline from somewhere...
    byline = u''
    authorspan = postdiv.find( 'span', {'class':'author_title'} )
    if authorspan:
        byline = authorspan.renderContents(None)
        byline = ukmedia.FromHTML( byline )


    if byline == u'':
        # sometimes an "About ...." box in sidebar
        sidebar = soup.find( 'div', {'id':'sidebar'} )
        if sidebar:
            foo = sidebar.find( 'p', {'class':'author_info'} )
            if foo:
                if foo.find( 'strong' ):
                    byline = foo.strong.renderContents(None)
                    byline = ukmedia.FromHTML(byline)
                    byline = byline.replace( u'About ', u'' )

    # special case
    if byline == u'Dear Lucy':
        byline = u'Lucy Kellaway'

    # if still no joy, try getting alt tag from blog title image
    if byline == u'':
        titleimage = soup.find( 'div', id="title_image" )
        if titleimage:
            byline = titleimage.a.img['alt']

    # force to single line
    byline = u' '.join( byline.split() )


    dateh2 = postdiv.find( 'h2', {'class': re.compile('date_header') } )
    datetxt = dateh2.renderContents( None ).strip()
    pubdate = ukmedia.ParseDateTime( datetxt )

    entrydiv = postdiv.find( 'div', {'class':'entry'} )
    metap = entrydiv.find( 'p', {'class':'postmetadata'} )
    metap.extract()

    for cruft in entrydiv.findAll( 'span', {'id': re.compile('more-')} ):
        cruft.extract()

    content = entrydiv.renderContents(None)
    desc = ukmedia.FirstPara( content )

    art['title'] = headline
    art['byline'] = byline
    art['content'] = content
    art['description'] = desc
    art['pubdate'] = pubdate

    return art


def ScrubFunc( context, entry ):

    url = context['srcurl']
    o = urlparse.urlparse( url )

    if o[1] == 'traxfer.ft.com':
        url = entry.guid
        o = urlparse.urlparse(url)
    elif o[1] == 'feeds.feedburner.com':
        # some of the FT feeds (the blogs?) redirect to feedburner.
        # Luckily, the feedburner feeds have a special entry
        # which contains the original link
        # (we also have to do this in dailymail.py)
        url = entry.feedburner_origlink
        o = urlparse.urlparse( url )

    if url == 'http://www.ft.com/dbpodcast':
        return None

    # don't scrape alphaville yet...
    if o[1] == 'ftalphaville.ft.com':
        return None

    # scrub off ",dwp_uuid=...." part from url...
    url = re.sub( ",dwp_uuid=[0-9a-fA-F\\-]+","",url )

    context['srcurl'] = url
    context['permalink'] = url

    context['srcid'] = CalcSrcID( url )
    return context


def FindArticles():
    """ get a set of articles to scrape from the rss feeds """
    rssfeeds = FetchRSSFeeds()
    # ft seems to have a bunch of dud feeds.
    return ScraperUtils.FindArticlesFromRSS( rssfeeds, u'ft', ScrubFunc, maxerrors=10 )

# pattern to extract unique id from FT article urls
# eg
# "http://www.ft.com/cms/s/8ca13fba-d80d-11dc-98f7-0000779fd2ac.html"
# "http://www.ft.com/cms/s/0/6be90c0c-e0ab-11dc-b0d7-0000779fd2ac,dwp_uuid=89fe9472-9c7f-11da-8762-0000779e2340.html"
art_idpat = re.compile( "/([^/]+)(,dwp_uuid=[0-9a-fA-F\\-]+)?[.]html" )
# blog urls look like this:
# http://blogs.ft.com/brusselsblog/2008/02/hanging-by-a-thhtml/
blog_idpat = re.compile( "blogs.ft.com/(.*)$" )

def CalcSrcID( url ):
    """ generate a unique srcid from an ft url """
    o = urlparse.urlparse( url )
    if not re.match( "(\w+[.])?ft[.]com$", o[1] ):
        return None

    m = art_idpat.search( url )
    if m:
        return 'ft_' + m.group(1)
    m = blog_idpat.search( url )
    if m:
        return 'ftblog_' + m.group(1)

    return None


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'ft'
    context['lastseen'] = datetime.now()

    # to clean the url...
    context = ScrubFunc( context, None )
    return context





if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50, prep=Prep )

