#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - columnists require separate scrape path (no rss feeds!)?
#
# Notes:
# - www.dailymail.co.uk and www.mailonsunday.co.uk are interchangable
#

import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,NavigableString,Tag
from JL import ukmedia,ScraperUtils


# page to read the list of rss feeds from
rss_feed_page = "http://www.dailymail.co.uk/home/rssMenu.html"


# page which lists columnists and their latest rants
#columnistmainpage = 'http://www.dailymail.co.uk/pages/live/columnists/dailymail.html'


def FindColumnistArticles():
    """Dailymail doesn't seem to have an RSS feed for it's columnists,
    so we'll just grep for links on the columnist page.
    TODO: could follow archive links for more articles..."""

    ukmedia.DBUG2("Searching Columnist page for articles\n")
    foundarticles = []
    html = ukmedia.FetchURL( columnistmainpage )
    soup = BeautifulSoup( html )

    srcorgname = u'dailymail'
    lastseen = datetime.now()

    for h in soup.findAll( 'h3' ):
        url = TidyURL( 'http://www.dailymail.co.uk' + h.a['href'] )

        context = {
            'srcid': CalcSrcID( url ),
            'srcurl': url,
            'permalink': url,
            'srcorgname' : srcorgname,
            'lastseen': lastseen,
            }
        foundarticles.append( context )

    ukmedia.DBUG2("found %d columnist articles\n" % (len(foundarticles)) )
    return foundarticles




def FindRSSFeeds( rssurl ):
    # TODO: can handle "Live mag" and "You mag" with a little more work
    blacklist = ( 'Pictures', 'Coffee Break', 'Live mag', 'You mag' )

    feeds = {}

    html = ukmedia.FetchURL( rssurl )

    soup = BeautifulSoup( html )
    t = soup.find( 'table', {'class':'rss-feeds-table'} )
    for a in t.findAll( 'a', {'href':re.compile('.rss$') } ):
        n = a.string
        if not n in blacklist:
            url = 'http://www.dailymail.co.uk' + a['href']
            feeds[ n ] = url

    return feeds




def Extract( html, context ):
    """ Extract dailymail article """

    art = context

    soup = BeautifulSoup( html )
    # quite possible that they still _really_ use windows-1252 despite
    # claiming iso-8859-1...
    # soup = BeautifulSoup( html, fromEncoding='windows-1252' )

    maindiv = soup.find( 'div', {'class': re.compile(ur'\bartItem\b') } )

    # kill printPage div and everything after it
    # (ie everything after article text)
    printdiv = maindiv.find( 'div', {'class': 'printPage'} )
    for cruft in printdiv.findAllNext():
        cruft.extract()
    printdiv.extract()


    titletxt = u''
    # headline can be split across multiple h1s... (but only want ones
    # near the top)
    for e in maindiv.findAll( 'h1' ):
        if e not in maindiv.contents[0:5]:
            continue
        titletxt = titletxt + u' ' + e.renderContents(None)
        e.extract()
    art['title'] =  ukmedia.FromHTML( titletxt )

    # description/abstract in <h[23]>s near the top
    desctxt = u''
    for e in maindiv.findAll( re.compile( 'h[23]') ):
        if e not in maindiv.contents[0:5]:
            continue
        desctxt = desctxt + e.renderContents(None)
        e.extract()
    desctxt = ukmedia.FromHTML( desctxt )

    # pull out previewLinks - links to comments
    previewlinks = maindiv.find( 'ul', {'class': 'previewLinks' } )
    if previewlinks:
        previewlinks.extract();

    # next part is byline and pubdate
    # we assume pubdate always there and last thing...
    bylinetxt = u''
    e = maindiv.contents[0]
    while 1:
        s = u''
        if isinstance( e, NavigableString ):
#            print "&str& '%s'" % ( e )
            s = unicode(e)
        else:
            s = e.renderContents( None )
#            print "&tag&: '%s'" %(e.name)
        n = e.nextSibling
        e.extract()
        e = n
        bylinetxt = bylinetxt + s
        if u'Last updated at' in s:
            break;

    bylinetxt = ukmedia.FromHTML( bylinetxt )

    # split out the date part...
    # eg "Last updated at 2:42 PM on 22nd May 2008"
    pat = re.compile( ur"(.*?)\s*Last updated at (.* (?:on )?.* \d{4})", re.IGNORECASE|re.DOTALL )
    m = pat.match( bylinetxt )

    art['byline'] = m.group(1)
    art['byline'] = u' '.join( art['byline'].split() )
    pubdatetxt = m.group(2)
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )


    if( art['byline'] ) == u'':
        # columnists have no bylines, but might have a "More From ..." bit in <div class="columnist-archive"
        columnistdiv = maindiv.find( 'div', {'class':'columnist-archive'} )
        if columnistdiv:
            h3 = columnistdiv.h3
            morefrompat = re.compile( ur'More from\s+(.*?)\s*[.]{3}', re.IGNORECASE )
            m = morefrompat.search( h3.renderContents(None) )
            art['byline'] = ukmedia.FromHTML( m.group(1) )


    # now extract article text

    # cruft removal
    for cruft in maindiv.findAll( 'img' ):
        cruft.extract()
    for cruft in maindiv.findAll( 'p', {'class':'imageCaption'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'p', {'class':'scrollText'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'span', {'class':re.compile('^clickTo.*$') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'clear'} ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('^related.*$') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('^thinFloat') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'columnist-archive' } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':'floatRHS' } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'a', {'class':re.compile('^lightbox') } ):
        cruft.extract()
    for cruft in maindiv.findAll( 'div', {'class':re.compile('ArtInlineReadLinks') } ):
        cruft.extract()

    contenttxt = maindiv.prettify(None)
    contenttxt = contenttxt.replace( u'<o:p>', u'' )
    contenttxt = contenttxt.replace( u'</o:p>', u'' )
    contenttxt = ukmedia.SanitiseHTML( contenttxt )
    art['content'] = contenttxt

    if desctxt == u'':
        desctxt = ukmedia.FirstPara( contenttxt )

    desctxt = u' '.join( desctxt.split() )
    art['description'] = desctxt

    return art







def ScrubFunc( context, entry ):
    """mungefunc for ukmedia.FindArticlesFromRSS()"""

    # most dailymail RSS feeds go through feedburner, but luckily the original url is still there...
    url = context[ 'srcurl' ]
    url = TidyURL(url)
    if url.find('feedburner') != -1:
        url = entry.feedburner_origlink


    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    return context


tidypat = re.compile( "^(.*?[.]html)(?:[?].*)?$" )

def TidyURL( url ):
    return tidypat.sub( r'\1', url )

# old style URLs:
# http://www.dailymail.co.uk/pages/live/articles/news/news.html?in_article_id=564447
# new style (from late may 2008):
# http://www.dailymail.co.uk/news/article-564447/Tories-ready-govern-moments-notice-insists-bullish-Cameron.html
#
# notes:
# - article id is same (hooray!)
# - old urls are redirected to new ones
# - text after article id ignored (redirected to canonical url)
#    Canonical url form appears to be:
#    http://www.dailymail.co.uk/news/article-564447/index.html
idpats = [
    re.compile( r"\bin_article_id=(\d+)" ),
    re.compile( r"/article-(\d+)/.*[.]html" )
    ]

def CalcSrcID( url ):
    """ Generate a unique srcid from a url """


    o = urlparse.urlparse( url )
    # blogs are handled by blogs.py
    if o[1] not in ( 'www.dailymail.co.uk', 'www.mailonsunday.co.uk' ):
        return None

    for pat in idpats:
        m = pat.search( url )
        if m:
            return 'dailymail_' + m.group(1)
    return None


def ContextFromURL( url ):
    """Set up for scraping a single article from a bare url"""
    url = TidyURL(url)
    context = {
        'srcurl': url,
        'permalink': url,
        'srcid': CalcSrcID( url ),
        'srcorgname': u'dailymail', 
        'lastseen': datetime.now(),
    }
    return context


def FindArticles():
    """Look for recent articles"""

    rssfeeds = FindRSSFeeds( rss_feed_page )

    found = ukmedia.FindArticlesFromRSS( rssfeeds, u'dailymail', ScrubFunc )
    # extra articles not from RSS feeds...
#    found = found + FindColumnistArticles()
    return found


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

