#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News
#

import re
from datetime import datetime
import sys
import urlparse
import lxml.html
import urllib2

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils






def Extract( html, context, **kw ):
    art = context
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])

    og_type = doc.cssselect('head meta[property="og:type"]')
    if len(og_type)>0:
        foo = og_type[0].get('content')
        if foo != u'article':
            ukmedia.DBUG2("SKIP og:type '%s' %s\n" % (foo,art['srcurl']))
            return None

    article = doc.cssselect('.story-body')[0]

    h1 = article.cssselect('h1')[0]
    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    art['byline'] = u''
    authors = article.cssselect('.byline')
    if len(authors)>0:
        parts = [ukmedia.FromHTMLOneLine(a[0].text_content()) for a in authors]
        art['byline'] = u', '.join(parts)

    dt = article.cssselect('.date')[0].get('data-seconds')
    art['pubdate'] = datetime.utcfromtimestamp(int(dt))


    foo = article.cssselect('[property="articleBody"]')
    if len(foo)==0:
        foo = article.cssselect('.map-body')
    body_div = foo[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))

    art['srcorgname']=u'bbcnews'
    return art

def FindArticles(sesh):
    """ get current active articles by scanning each section page """


    start_page = "http://www.bbc.co.uk/news"
    art_url_pat = re.compile(r".*/[^/]*\d{4}[^/]*/?$", re.I)
    navsel = '.navigation-wide-list a'
    nav_blacklist = []
    domain_whitelist = ('www.bbc.co.uk','www.bbc.com')
#    article_blacklist = ['/picture/','/gallery','/live/','/video/','/audio/','/ng-interactive/']
    article_blacklist = []

    urls = ScraperUtils.GenericFindArtLinks(start_page,domain_whitelist,navsel,nav_blacklist,art_url_pat)
    arts = []
    for url in urls:
        good = True
        for blacklisted in article_blacklist:
            if blacklisted in url:
                good = False
        if good:
            arts.append(ContextFromURL(url))

    return arts


def TidyURL( url ):
    """ Tidy up URL - trim off any extra cruft (eg rss tracking stuff) """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""

    url = TidyURL(url)

    context = {}
    context['permalink'] = url
    context['srcurl'] = url
    context['srcorgname'] = u'bbcnews'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract )

