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
    """ scrape a list of rss feeds from the nytimes site """
    rss_page = "http://www.nytimes.com/services/xml/rss/index.html"

    html = ukmedia.FetchURL( rss_page )
    soup = BeautifulSoup( html )

    feeds = []
    div = soup.find('div',{'id':'rss-toggler'} )
    for a in div.findAll('a'):
        url = urlparse.urljoin( rss_page, a['href'] )

        if 'termsconditions.html' in url:
            continue
        if 'javascript:void(0);' in url:
            continue

        title = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        feeds.append( (title,url) )

    return feeds


 
def FindArticles():
    """ get a set of articles to scrape from the bbc rss feeds """
    feeds = FindFeeds()
    articles = ScraperUtils.FindArticlesFromRSS( feeds, u'nytimes', ScrubFunc )
    return articles


def CalcSrcID( url ):
    """ Extract unique srcid from url. Returns None if this scraper doesn't handle it."""
    o = urlparse.urlparse(url)
    if not o[1].lower().endswith( 'nytimes.com' ):
        return None
    return url


def ScrubFunc( context, entry ):
    """ per-article callback for processing RSS feeds """

    # nytimes has special rss url which redirect to real article...
    # ...luckily the guid has proper link (marked as non-permalink)
    url = entry.guid
    context = ContextFromURL( url )
    if not context['srcid']:
        return None # suppress it
    return context


def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    context = {}
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )

#    context['srcorgname'] = u'guardian'
#        context['srcurl'] = url + '?page=all'
    context['srcurl'] = url
    context['lastseen'] = datetime.now()
    return context


    # http://www.nytimes.com/2010/09/03/nyregion/03poll.html?pagewanted=all
def Extract( html, context ):
    if '<meta name="PT" content="Blogs" />' in html:
        return Extract_hentry( html, context )
    else:
        return Extract_main_newspaper( html, context )



def Extract_main_newspaper( html, context ):
    art = context
    soup = BeautifulSoup( html )

    art['srcorgname'] = u'nytimes'

    art_div = soup.find( 'div', {'id':'article'} )
#    h1 = soup.find( 'h1', {'class':'articleHeadline'} )

    hdl = art_div.find( 'nyt_headline' )
    art['title'] = ukmedia.FromHTMLOneLine( hdl.renderContents(None) )
    byl = art_div.find( 'nyt_byline' )
    if byl:
        art['byline'] = ukmedia.FromHTMLOneLine( byl.renderContents(None) )
    else:
        art['byline'] = u''

    dateline = art_div.find( 'h6', {'class':'dateline'} )
    art['pubdate'] = ukmedia.ParseDateTime( dateline.renderContents( None ) )

    art['content'] = u''
    for content_div in art_div.findAll( 'div', {'class':'articleBody'} ):
        art['content'] = art['content'] + u"\n" + ukmedia.SanitiseHTML( content_div.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] );

    return art


def Extract_hentry( html, context ):
    art = context
    soup = BeautifulSoup( html )

    art['srcorgname'] = u'nytimes'

    hentry = soup.find( 'div', {'class':re.compile(r'\bhentry\b')} )

    title = hentry.find( None, {'class':re.compile(r'\bentry-title\b')} )
    art['title'] = ukmedia.FromHTMLOneLine( title.renderContents(None) )

    # nyt-specific...
    byl = hentry.find( None, {'class':re.compile(r'\bbyline\b')} )
    if byl:
        art['byline'] = ukmedia.FromHTMLOneLine( byl.renderContents(None) )
    else:
        art['byline'] = u''

    # TODO: should handle "updated" too
    pubdate = hentry.find( None, {'class':re.compile(r'\published\b')} )
    art['pubdate'] = ukmedia.ParseDateTime( pubdate['title'] )

    content = hentry.find( None, {'class':re.compile(r'\entry-content\b')} )
    art['content'] = ukmedia.SanitiseHTML( content.renderContents(None) )

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

