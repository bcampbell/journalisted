#!/usr/bin/env python
#
# Scraper for The Herald (http://www.theherald.co.uk)
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# TODO:
# - could get journo email addresses from bylines

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2
import lxml.html

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils



def Extract(html, context, **kw):

    # BIG BIG CAVEAT:
    # Herald only serves up the first part of article text.
    # the rest is fetched via javascript. sigh.

    art = context
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])

    title = doc.cssselect('.sht-body .article-title')[0]
    art['title'] = unicode( title.text_content() ).strip()

    try:
        byline = doc.cssselect('#article-byline')[0]
        art['byline'] = unicode(byline.text_content())
    except IndexError:
        art['byline'] = u''

    pubdate = doc.cssselect('.sht-body .section-date-author time')[0]
    art['pubdate'] = ukmedia.ParseDateTime(pubdate.get('content'))



    abstract = doc.cssselect('#article-abstract')[0]
    content = doc.cssselect('#article-content')[0]
    for cruft in content.cssselect('.field'):
        cruft.drop_tree()

    art['content'] = unicode(lxml.html.tostring(abstract)) + unicode(lxml.html.tostring(content))
    art['content'] = ukmedia.SanitiseHTML(art['content'])
    art['description'] = ukmedia.FirstPara( art['content'] )
    return art




# NEW URLS (as of sept 2009):
#  http://www.heraldscotland.com/news/politics/macaskill-denies-brother-s-role-in-libya-oil-interests-1.918391
#  http://www.heraldscotland.com/comment/iain-macwhirter/how-do-we-prevent-google-from-turning-into-hal-1.918020
#  http://www.heraldscotland.com/sport/more-scottish-football/fletcher-desperate-to-make-world-cup-dream-come-true-1.918563?localLinksEnabled=false


def CalcSrcID( url ):
    """ extract unique srcid from url """
    url = TidyURL( url.lower() )
    o = urlparse.urlparse( url )
    if o[1].endswith( 'heraldscotland.com' ):
        # NEW url
        new_idpat = re.compile( r"(?:.*?)(\d+)$" )
        m = new_idpat.match( o[2] )
        if m:
            return 'heraldscotland_' + m.group(1)
    return None


def TidyURL( url ):
    """ Tidy up URL - trim off params, query, fragment... """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    url = TidyURL( url )
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    if context['srcid'] == None:
        return None
    context['srcorgname'] = u'herald'
    context['lastseen'] = datetime.now()
    return context


def ScrubFunc( context, entry ):
    url = TidyURL( context['srcurl'] )
    context['permalink'] = url
    context['srcurl'] = url
    context['srcid'] = CalcSrcID( url )
    return context

def FindArticles(sesh):
    """Gather articles to scrape from the herald website. """
    feeds = FindRSSFeeds()
    found = ScraperUtils.FindArticlesFromRSS( feeds, u'herald', ScrubFunc, maxerrors=20 )
    return found



def FindRSSFeeds():
    rss_page = 'http://www.heraldscotland.com/services/rss' 
    html = ukmedia.FetchURL( rss_page )
    soup = BeautifulSoup.BeautifulSoup( html )
    feeds = []
    for a in soup.findAll( 'a', {'class':'rss-link'} ):
        a.img.extract() # kill the rss icon
        feed_name = a.renderContents( None )
        feed_url = urlparse.urljoin( rss_page, a['href'] )
        feeds.append( (feed_name,feed_url) )

    ukmedia.DBUG2( "Scanned '%s', found %d RSS feeds\n" %( rss_page, len(feeds) ) )
    return feeds

if __name__ == "__main__":
    # loads of 400 errors in testing, for some reason...
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200 )

