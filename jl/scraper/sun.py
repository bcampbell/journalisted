#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

import re
from datetime import date,datetime
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ScraperUtils


base_url = 'http://www.thesun.co.uk'


def FindArticles(sesh):
    """ get current active articles by scanning each section page """
    urls = ScraperUtils.GenericFindArtLinks(
            start_page = base_url,
            domain_whitelist = "www.thesun.co.uk",
            nav_sel = "#sun-menu a, .sub-nav__container a",
            art_url_pat = re.compile(r'/\d{5,}/[^/]+/?$'),
            article_blacklist = ["/football/team/",]
        )

    return [ ContextFromURL(u) for u in urls ]


def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    context = {}
    context['permalink'] = url
    context['srcorgname'] = u'sun'
    context['srcurl'] = url
    context['lastseen'] = datetime.now()
    return context


def Extract( html, context, **kw ):
    art = context
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])

    article = doc.cssselect('article.article')[0]
    h1 = doc.cssselect('header .article__headline')[0]
    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    art['byline'] = u''
    authors = doc.cssselect('.article__meta .article__author-name')
    if len(authors)>0:
        parts = [ukmedia.FromHTMLOneLine(a.text_content()) for a in authors]
        art['byline'] = u', '.join(parts)


    pubdates = doc.cssselect('.article__meta .article__published')
    if len(pubdates)>0:
        art['pubdate'] = ukmedia.ParseDateTime(pubdates[0].text_content())

    body_div = article.cssselect('.article__content')[0]

    # cruft removal
    for cruft in body_div.cssselect('.rail-video-index'):
        cruft.drop_tree()

    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )
    art['srcorgname'] = u'sun'

    return art



if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

