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
import urlparse
import cookielib
import contextlib
import ConfigParser
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils

cj = cookielib.LWPCookieJar()


def FindArticles(sesh):
    """ get current active articles by scanning each section page """

    start_page="http://www.ft.com/"

    art_url_pat = re.compile(r"(.*/\\d{4}/\\d{2}.*/[^/]{4,}/$)|(.*/[-0-9a-f]{8,}.html$)", re.I)
    navsel="nav.nav-ftcom a"
    nav_blacklist = []
    domain_whitelist = ('www.ft.com','blogs.ft.com')
    article_blacklist = []

    urls = ScraperUtils.GenericFindArtLinks(start_page,domain_whitelist,navsel,nav_blacklist,art_url_pat,sesh=sesh)
    arts = []
    for url in urls:
        good = True
        for blacklisted in article_blacklist:
            if blacklisted in url:
                good = False
        if good:
            arts.append(ContextFromURL(url))

    return arts



# file with our FT subscription details
FT_CONFIG_FILE = '../conf/ft.conf'

def Prep(sesh):
    """ Prepare for scraping (by logging into FT with our user account) """ 

    Login(sesh)


def Login(sesh):

    assert sesh is not None

    config = ConfigParser.ConfigParser()
    config.read( FT_CONFIG_FILE )
    email = config.defaults()[ 'email' ]
    pwd = config.defaults()[ 'password' ]


    # log on!
    login_url="https://accounts.ft.com/login?location=http://www.ft.com/home"
    #login_url = "http://jl.dev/poop"
    postdata = urllib.urlencode({'email':email, 'password': pwd, 'rememberMe':'true', 'Sign In': ''})

    ukmedia.DBUG2( "Logging in to ft.com (as '%s')\n" % (email,) )    
    with contextlib.closing(sesh.open(login_url,postdata)) as resp:
        # print("CODE: %d\n",resp.code)
        #for code,url in resp.redirects:
        #    ukmedia.DBUG2( " -> %s %s\n" % (code,url))
        dest = resp.geturl()
        #ukmedia.DBUG2( "ended up at: %s\n" % ( dest,))
    if 'accounts.ft.com' in dest:
        raise Exception, "Login failed - incorrect login details"
    ukmedia.DBUG2("logged in\n")

def Extract(html, context, **kw):
    o = urlparse.urlparse( context['srcurl'] )
    if o[1] == 'blogs.ft.com':
        return Extract_blog( html, context ) 
    else:
        if '<div class="ft-story-header">' in html:
            return Extract_article_old_cms(html, context)
        else:
            return Extract_article_new_cms(html, context)

def Extract_article_new_cms( html, context ):
    """ extract fn for FT main articles """

    art = context
    doc = lxml.html.fromstring(html)

    drm = doc.cssselect('#DRMUpsell')
    if len(drm) > 0:
        ukmedia.DBUG("WARN skip truncated article (Not logged in) - %s\n" % (art['srcurl'],))
        return None


    storyheader = doc.cssselect('.fullstoryHeader')[0]
    byline_txt = u''
    foo = storyheader.cssselect('.byline')
    if len(foo)>0:
        byline_txt = unicode(foo[0].text_content()).strip()
    title_txt = unicode(storyheader.cssselect('h1')[0].text_content()).strip()
    #pubdate_txt = storyheader.cssselect('.lastUpdated')[0].text_content()
    pubdate_txt = storyheader.cssselect('.time')[0].text_content()

#    bod = doc.cssselect('.fullstoryBody')[0]
    bod = doc.cssselect('#storyContent')[0]
    for cruft in bod.cssselect('.insideArticleShare, .shareArt, .promobox, insideArticleRelatedTopics'):
        cruft.drop_tree()
    content = unicode(lxml.html.tostring(bod))
    content = ukmedia.SanitiseHTML(content)

    art['title'] = title_txt
    art['byline'] = byline_txt
    art['pubdate'] = ukmedia.ParseDateTime(pubdate_txt)
    art['content'] = content
    art['srcorgname'] = u'ft'

    return art


def Extract_article_old_cms( html, context ):
    """ extract fn for FT main articles in old cms system"""
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






def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcorgname'] = u'ft'
    context['lastseen'] = datetime.now()

    return context




if __name__ == "__main__":
    # create a url opener which remembers cookies (as well as throttling and all the other uber-opener stuff)
    opener = ScraperUtils.build_uber_opener(cookiejar=cj)

    # NOTE: login requires Referer!
    opener.addheaders = [
        ('User-Agent', "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:43.0) Gecko/20100101 Firefox/43.0"),
        ('Accept', "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"),
#        ('Accept-Encoding', "gzip, deflate"),
        ('Accept-Language', "en-US,en;q=0.5"),
        ('Referer', "https://accounts.ft.com/login?location=http%3A%2F%2Fwww.ft.com%2Fhome%2Fasia" ),
    ]

    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50, prep=Prep, sesh=opener )
#    ukmedia.FetchURL("http://jl.dev/bob-smith", sesh=opener)


