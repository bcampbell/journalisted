#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the guardian and observer, including commentisfree and blogs
#

import re
from datetime import date,datetime,timedelta
import time
import sys
import urlparse
import lxml.html

import site
site.addsitedir("../pylib")
from JL import DB,ScraperUtils,ukmedia



def Extract( html, context, **kw ):

    art = context
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])


    #article = doc.cssselect('[itemtype="http://schema.org/NewsArticle"]')[0]
    article = doc.cssselect('article#article')[0]

    h1 = article.cssselect('[itemprop="headline"]')[0]

    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    art['byline'] = u''
    authors = article.cssselect('[itemprop="author"]')
    if len(authors)>0:
        parts = [ukmedia.FromHTMLOneLine(a[0].text_content()) for a in authors]
        art['byline'] = u', '.join(parts)


    pubdatetxt = u''
    pubdates = article.cssselect('time[itemprop~="datePublished"]')
    if len(pubdates)>0:
        art['pubdate'] = ukmedia.ParseDateTime(pubdates[0].get('datetime'))

    body_div = article.cssselect('[itemprop~="articleBody"], [itemprop~="reviewBody"]')[0]

    # cruft removal
    for cruft in body_div.cssselect('.block-share, aside'):
        cruft.drop_tree()

    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )
    art['srcorgname'] = u'guardian'

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
    context['srcorgname'] = u'guardian'
    context['srcurl'] = url
    context['lastseen'] = datetime.now()
    return context





def FindArticles(sesh):
    """ get current active articles by scanning each section page """


    start_page = "http://www.theguardian.com"
    art_url_pat = re.compile(r"^.*/\d{4}/[a-zA-Z]{3}/\d{1,2}/[^/]+-[^/]+$", re.I)
    navsel = 'nav[role="navigation"] a'
    nav_blacklist = ["/contributors/","/crosswords/"]
    domain_whitelist = ('www.theguardian.com',)
    article_blacklist = ['/picture/','/gallery','/live/','/video/','/audio/','/ng-interactive/']

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





if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=150 )

