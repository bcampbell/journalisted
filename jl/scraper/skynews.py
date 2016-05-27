#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for Sky News
#
# NOTE: just the blogs, for now, but sky news articles seem to have bylines so
# we should really cover them too...

import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils


blog_feeds = [
    # this one seems like enough - could get all the individual ones, but probably no point going to so much effort.
    ( 'sky news blogs', 'http://blogs.news.sky.com/rss/editors.xml' )
]


# eg:
# "http://blogs.news.sky.com/boultonandco/Post:def40def-fafd-45da-af67-d821c63074f4"
blog_srcid_pat = re.compile( r"/(Post:[-0-9a-fA-F]+)$" )

def CalcSrcID( url ):
    """ Extract unique srcid from url. Returns None if this scraper doesn't handle it."""

    o = urlparse.urlparse(url)
    if o[1] != 'blogs.news.sky.com':
        return None

    m = blog_srcid_pat.search(url)
    if m:
        return "skynews_blog_" + m.group(1)

    return None



def Extract(html, context, **kw):
    o = urlparse.urlparse(context['srcurl'])
    if o[1] == 'blogs.news.sky.com':
        return Extract_blog( html, context )
    return None


def Extract_blog( html, context ):
    """Parse the html of a blog post page"""

    art = context
    art['srcorgname'] = u'skynews';

    soup = BeautifulSoup( html )

    postbody_div = soup.find( 'div', {'id':'blogPostBody'} )

    title_div = postbody_div.find('div',{'id':'blogPostTitle'} )
    art['title'] = ukmedia.FromHTMLOneLine( title_div.h4.renderContents( None ) )

    info_div = postbody_div.find( 'div', {'class':'blogPostInfo'} )

    author = info_div.find( 'span', {'class':'blogPostAuthor'} )
    art['byline'] = ukmedia.FromHTMLOneLine( author.renderContents( None ) )
    d = info_div.find( 'span', {'id':'articleDate'} )
    art['pubdate'] = ukmedia.ParseDateTime( d.renderContents( None ) )


    title_div.extract()
    info_div.extract()


    # images
    art['images'] = []
    for img in postbody_div.findAll( 'img' ):
        img_caption = u''
        img_url = img['src']
        img_credit = u''
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )

    # strip out non-text content
    for img in postbody_div.findAll( 'img' ):
        img.extract()
    for cruft in postbody_div.findAll( 'div', {'class':'articleToolsRecommend'} ):
        cruft.extract()
    for cruft in postbody_div.findAll( 'div', {'class':'blogTagsComponent'} ):
        cruft.extract()

    art['content'] = ukmedia.SanitiseHTML( postbody_div.renderContents( None ) )
    art['description'] = ukmedia.FirstPara( art['content'] )

    # don't bother about comments - they're all added in via javascript

    return art



def ScrubFunc( context, entry ):
    """ per-article callback for processing RSS feeds """
    srcid = CalcSrcID( context['srcurl'] )
    if not srcid:
        return None # suppress it
    context['srcid'] = srcid
    return context


def FindArticles(sesh):
    """ get a set of articles to scrape from the rss feeds """

    articles = ScraperUtils.FindArticlesFromRSS( blog_feeds, u'skynews', ScrubFunc )
    return articles


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    # NOTE: urls from the rss feed have a couple of extra components which
    # we _could_ strip out here...
    context = {}
    context['permalink'] = url
    context['srcurl'] = url
    context['srcid'] = CalcSrcID( url )
#    context['srcorgname'] = u'skynews'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract )

