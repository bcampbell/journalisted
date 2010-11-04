#!/usr/bin/env python

import re
import urllib2
import sys
import traceback
from datetime import date,datetime
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ScraperUtils


def FindFeeds():
    """ scrape a list of rss feeds from the latimes site """
    rss_page = "http://www.latimes.com/services/site/la-rssinfopage,0,5039586.htmlstory"

    html = ukmedia.FetchURL( rss_page )
    soup = BeautifulSoup( html )

    feeds = []
    div = soup.find('div',{'id':'story-body'} )
    for td in div.table.findAll('td', {'class':'rssTitleCell'} ):
        a = td.a
        url = urlparse.urljoin( rss_page, a['href'] )

        title = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        feeds.append( (title,url) )

    return feeds


 
def FindArticles():
    """ get a set of articles to scrape from the bbc rss feeds """
    feeds = FindFeeds()
    articles = ScraperUtils.FindArticlesFromRSS( feeds, None, ScrubFunc, maxerrors=20 )
    return articles


def CalcSrcID( url ):
    """ Extract unique srcid from url. Returns None if this scraper doesn't handle it."""
    o = urlparse.urlparse(url)
    if not o[1].lower().endswith( 'latimes.com' ):
        return None
    return url


def ScrubFunc( context, entry ):
    """ per-article callback for processing RSS feeds """

    # NOTE: real url not in feeds. They mostly go through feedburner (albeit on the latimes domain)
    url = context['srcurl']
    if hasattr( entry, 'feedburner_origlink' ):
        url = entry.feedburner_origlink


    context = ContextFromURL( url )
    if not context['srcid']:
        return None # suppress it
    return context


def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    # strip query and fragment
    o = urlparse.urlparse(url)
    url = urlparse.urlunparse( (o[0], o[1], o[2], o[3],None,None) )

    context = {}
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )

    context['srcurl'] = url
    context['lastseen'] = datetime.now()
    return context


def Extract( html, context ):

    art = context
    soup = BeautifulSoup( html )

    art['srcorgname'] = u'latimes'

    art_div = soup.find( 'div', {'class':'story'} )
    h1 = art_div.h1
    art['title'] = ukmedia.FromHTMLOneLine( h1.renderContents(None) )

    byl = art_div.find( 'span', {'class':'byline'} )
    if byl:
        art['byline'] = ukmedia.FromHTMLOneLine( byl.renderContents(None) )
    else:
        art['byline'] = u''

    dateline = art_div.find( 'span', {'class':'dateString'} )
    art['pubdate'] = ukmedia.ParseDateTime( dateline.renderContents( None ) )



    art['content'] = u''
    content_div = art_div.find('div', {'id': 'story-body-text'} )
    if content_div is None:
        content_div = art_div.find('div', {'id': 'story-body'} )

    for cruft in content_div.findAll( 'div', {'id': 'article-promo'} ):
        cruft.extract()

    art['content'] = ukmedia.SanitiseHTML( content_div.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] );

    return art



if __name__ == "__main__":

#    arts = FindArticles()
#    for a in arts:
#        print a['srcurl']

#    feeds = FindFeeds()
#    for title,url in feeds:
#        print title, url
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

