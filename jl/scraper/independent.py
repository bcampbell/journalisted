#!/usr/bin/env python
#
# Copyright (c) 2013 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

import re
import urllib   # for urlencode
import ConfigParser
import sys
import traceback
from datetime import date,datetime
import urlparse
import lxml.html

import site
site.addsitedir("../pylib")
from JL import ukmedia,ScraperUtils

from pprint import pprint



def FindArticles():
    start_page = "http://www.independent.co.uk"
    #.../slug-id.html

    # eg http://www.independent.co.uk/news/education/education-news/term-time-holidays-number-of-pupils-taking-unauthorised-time-off-school-soars-a7038521.html
    art_url_pat = re.compile(r"^.*/[^/]+-[^/]+[.]html$", re.I)
    navsel = "#masthead nav a"
    nav_blacklist = ['/biography/',]
    domain_whitelist = ('www.independent.co.uk',)
    article_blacklist = ['/biography/','/voices/iv-drip/', '/voices/debate/']

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






def pretty_join(names):
    if len(names)>2:
        return u", ".join(names[:-1]) + u" and " + names[-1]
    else:
        return u" and ".join(names)




def Extract(html, context, **kw):

    art = context

    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])
    doc.make_links_absolute(art['srcurl'])

    article = doc.cssselect('[itemtype="http://schema.org/NewsArticle"], [itemtype="http://schema.org/Review"]')[0]

    h1 = article.cssselect('[itemprop="headline"]')[0]

    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    art['byline'] = u''
    authors = article.cssselect('[itemprop="author"]')
    if len(authors)>0:
        parts = [ukmedia.FromHTMLOneLine(a[0].text_content()) for a in authors]
        art['byline'] = u', '.join(parts)


    pubdatetxt = u''
    pubdates = article.cssselect('header time')
    if len(pubdates)>0:
        art['pubdate'] = ukmedia.ParseDateTime(pubdates[0].get('datetime'))

    body_div = article.cssselect('[itemprop~="articleBody"], [itemprop~="reviewBody"]')[0]

    # cruft removal
    for cruft in body_div.cssselect('.inline-pipes-list, #gigya-share-btns-2'):
        cruft.drop_tree()

    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )
    art['srcorgname'] = u'independent'


    return art




def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
#    url = TidyURL(url)
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcorgname'] = u'independent'
    context['lastseen'] = datetime.now()
    return context

if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=150 )


