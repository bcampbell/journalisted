#!/usr/bin/env python
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
from BeautifulSoup import BeautifulSoup,NavigableString,Tag,Comment
from JL import ukmedia,ScraperUtils


import lxml.html


# page which lists columnists and their latest rants
#columnistmainpage = 'http://www.dailymail.co.uk/pages/live/columnists/dailymail.html'


columnistmainpage = "http://www.dailymail.co.uk/debate/columnists/index.html"

columnistnames = None

def GetColumnistNames():
    """ Scrape a list of the columnist names from the columnist page (cached)"""
    global columnistnames
    if not columnistnames:
        columnistnames = []
        html = ukmedia.FetchURL( columnistmainpage )
        soup = BeautifulSoup( html )
        for a in soup.findAll( 'a', {'class':'author'} ):
            n = a.renderContents(None).strip()
            if not n in columnistnames:
                columnistnames.append( n )
    return columnistnames


def FindRSSFeeds():

#    blacklist = ( 'Pictures', 'Coffee Break', 'Live mag', 'You mag' )
    blacklist = ()
    feeds = []

    # page to read the list of rss feeds from
    rss_feed_page = "http://www.dailymail.co.uk/home/rssMenu.html"
    html = ukmedia.FetchURL( rss_feed_page )
    assert html.strip() != ''
    soup = BeautifulSoup( html )

    # look for rss icons, step back to find the links.

    

    for btn in soup.findAll( 'span', {'class':"rss-btn rss"} ):
        a = btn.find('a')
        if a:
            feed_url = urlparse.urljoin( rss_feed_page, a['href'] )
            # could get a more human-readable name, but relative url is good enough
            feed_name = a['href']
            feeds.append( (feed_name,feed_url) )

    assert len(feeds) > 120         # 168 feeds at time of writing

    return feeds



def Extract( html, context, **kw ):
    """ Extract dailymail article """

    art = context

    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])
    doc.make_links_absolute(art['srcurl'])

    main_div = doc.cssselect('.article-text')[0]

    h1 = main_div.cssselect('h1')[0]
    art['title'] = u' '.join(unicode(h1.text_content()).split())


    art['byline'] = u''
    authors = main_div.cssselect('.author-section .author')
    if authors:
        art['byline'] = u" and ".join([unicode(author.text_content()) for author in authors])
        art['byline'] = re.compile(r'\s*\bfor (mailonline|the daily mail|daily mail australia|thisismoney[.]co[.]uk)\b',re.I).sub(u'',art['byline'])

    when = doc.cssselect('.article-timestamp-published')
    if not when:
        when = doc.cssselect('.article-timestamp-updated')
    pubdate_txt = when[0].text_content()
    art['pubdate'] = ukmedia.ParseDateTime(pubdate_txt)


    # cull assorted cruft (including any div with an id)
    for cruft in main_div.cssselect('h1, .author-section, .byline-section, .relatedItems, .shareArticles, div[id]'):
        cruft.drop_tree()

    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(main_div))).strip()
    art['description'] = ukmedia.FirstPara( art['content'] )
    return art









def ScrubFunc( context, entry ):
    """mungefunc for ScraperUtils.FindArticlesFromRSS()"""

    # most dailymail RSS feeds go through feedburner, but luckily the original url is still there...
    url = context[ 'srcurl' ]
    url = TidyURL(url)
    if url.find('feedburner') != -1:
        url = entry.feedburner_origlink

    if '/video/' in url:
        return None

    context['srcurl'] = url
    context['permalink'] = url
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


def ContextFromURL( url ):
    """Set up for scraping a single article from a bare url"""
    url = TidyURL(url)
    context = {
        'srcurl': url,
        'permalink': url,
        'srcorgname': 'dailymail', 
        'lastseen': datetime.now(),
    }
    return context


def FindArticles():
    """Look for recent articles"""

    rssfeeds = FindRSSFeeds()

    found = ScraperUtils.FindArticlesFromRSS( rssfeeds, u'dailymail', ScrubFunc )
    return found


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

