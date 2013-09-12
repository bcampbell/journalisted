#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# NOTES:
# express has rss feeds, but they seem a little a) chaotic and b) rubbish
#

# TODO:
# use bylineomatic on health, food others?

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2
import lxml.html
from pprint import pprint

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils



base_url = "http://www.express.co.uk/"


def FindRSSFeeds():
    rss_feed_page = base_url + "feeds"
    html = ukmedia.FetchURL( rss_feed_page )

    found_feeds = []
 
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=base_url)
    doc.make_links_absolute()
    for a in doc.cssselect('#rsslist a'):
        name = " ".join(a.text_content().strip().split())
        url = a.get('href')
        o = urlparse.urlparse(url)
        if '/rss/' not in o.path:
            continue
        found_feeds.append((name,url))
    return found_feeds



def ScrubFunc( context, entry ):
    """ sanitise rss entry """
    url = context['srcurl']
    o = urlparse.urlparse(context['srcurl'])
    if o.hostname != "www.express.co.uk":
        if 'feedsportal.com' in o.hostname:
            context['srcurl'] = entry.guid
        elif 'feedproxy.google.com' in o.hostname:
            context['srcurl'] = entry.guid
        else:
            ukmedia.DBUG2("IGNORE %s\n" %(url,))
            return None

    return context


def FindArticles():
    """Look for recent articles"""

    rssfeeds = FindRSSFeeds()
    found = ScraperUtils.FindArticlesFromRSS( rssfeeds, u'express', ScrubFunc)
    return found



def Extract(html, context, **kw):
    art = context

    # some missing articles just return a 302 and send us back to section
    if art['permalink'] == "http://www.express.co.uk/comment/columnists":
        ukmedia.DBUG2("BAD redirect to /comment/columnists - article removed? %s\n" %(art['srcurl'],));
        return None

    enc = 'utf-8'

    uni = html.decode(enc).encode(enc)


    parser = lxml.html.HTMLParser(encoding=enc)
    doc = lxml.html.document_fromstring(html, parser, base_url=base_url)

    art_element = doc.cssselect('#singleArticle')[0]

    headline = art_element.cssselect('header h1')[0].text_content()
    headline = u" ".join(headline.strip().split()) 
    art['title'] = headline

    byline = unicode(art_element.cssselect('.publish-info .author')[0].text_content())
    byline = u" ".join(byline.strip().split()) 
    dt = art_element.cssselect('.publish-info time')[0]
    art['pubdate'] = ukmedia.ParseDateTime(dt.get('datetime'))
    art['byline']=byline
    art['srcorgname']=u'express';

    content = u''
    for p in art_element.cssselect('section.text-description'):
        content = content + unicode(lxml.html.tostring(p))

    content = ukmedia.SanitiseHTML(content)
    art['content'] = content

    return art


def TidyURL( url ):
    """ Tidy up URL - trim off params, query, fragment... """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url


def ContextFromURL( url ):
    """Set up for scraping a single article from a bare url"""
    url = TidyURL(url)
    context = {
        'srcurl': url,
        'permalink': url,
        'srcorgname': u'express', 
        'lastseen': datetime.now(),
    }
    return context




if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200 )


