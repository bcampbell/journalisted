#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# paywall notes:
#
# 10 articles before cutoff.
#
# - in browser, tracked by cookie
# - but also restricts scraper without cookies, presumably based on IP address
#   or something. Appears to be a short timeout (<1 hour).
# - more restrictive outside the UK? unsure, but suspect not.
#
# All the other data is available, so when we hit the paywall limit we just
# stop including the actual article content... not great, but hey.
#

import re
from datetime import datetime, timedelta, date
import sys
import os
import urlparse
import urllib
import urllib2
import ConfigParser
import lxml.html

import site
site.addsitedir("../pylib")
from JL import ukmedia, ScraperUtils


def Extract( html, context, **kw ):

    art = context
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])


    print( lxml.html.tostring(doc) )
    return None



    article = doc.cssselect('[itemtype*="schema.org/Article"], [itemtype*="schema.org/NewsArticle"], [itemtype*="schema.org/Review"]')[0]

    h1 = article.cssselect('[itemprop~="headline"]')[0]

    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    art['byline'] = u''
    authors = article.cssselect('[itemprop~="author"]')
    if len(authors)>0:
        parts = [ukmedia.FromHTMLOneLine(a[0].text_content()) for a in authors]
        art['byline'] = u', '.join(parts)


    pubdatetxt = u''
    pubdates = article.cssselect('header .article-date time[itemprop~="datePublished"]')
    if len(pubdates)>0:
        art['pubdate'] = ukmedia.ParseDateTime(pubdates[0].get('datetime'))
    body_div = article.cssselect('[itemprop*="articleBody"], [itemprop*="reviewBody"]')[0]

    # cruft removal
    for cruft in body_div.cssselect('.block-share, aside'):
        cruft.drop_tree()

    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )
    art['srcorgname'] = u'telegraph'

    return art






def TidyURL( url ):
    """ Tidy up URL - trim off any extra cruft (eg rss tracking stuff) """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url








def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    url = TidyURL( url )
    context = {}
    context['permalink'] = url
    context['srcorgname'] = u'telegraph'
    context['srcurl'] = url
    context['lastseen'] = datetime.now()
    return context





def FindArticles():
    """ get current active articles by scanning each section page """


    start_page = "http://www.telegraph.co.uk"
    art_url_pat = re.compile(r"^.*://.*/.*/[^/]+-[^/]+/?$", re.I)
    navsel = ".header-nav-primary a, .header-nav-local a"
    nav_blacklist = []
    domain_whitelist = ('www.telegraph.co.uk',)
    article_blacklist = [re.compile(pat,re.I) for pat in [ r'/page-\d+', r'/authors/' ] ]

    urls = ScraperUtils.GenericFindArtLinks(start_page,domain_whitelist,navsel,nav_blacklist,art_url_pat)
    arts = []
    for url in urls:
        good = True
        for blacklisted in article_blacklist:
            if blacklisted.search(url):
                good = False
        if good:
            arts.append(ContextFromURL(url))

    return arts





if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=150 )



