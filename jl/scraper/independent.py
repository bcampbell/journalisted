#!/usr/bin/env python
#
# Copyright (c) 2013 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

import re
import urllib   # for urlencode
import urllib2
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
    art_url_pat = re.compile('.*/([a-z0-9_]+-){1,}([a-z0-9_]+)-[0-9]+.html$', re.I)
    navsel = "#navigation a"
    nav_blacklist = ['/biography/',]
    domain_whitelist = ('www.independent.co.uk',)
    article_blacklist = ['/biography/','/voices/iv-drip/', '/voices/debate/']

    urls = GenericFindArtLinks(start_page,domain_whitelist,navsel,nav_blacklist,art_url_pat)
    arts = []
    for url in urls:
        good = True
        for blacklisted in article_blacklist:
            if blacklisted in url:
                good = False
        if good:
            arts.append(ContextFromURL(url))

    return arts


def GenericFindArtLinks(start_page, domain_whitelist, navsel, blacklisted_sections, art_url_pat):
    sections = set( (start_page,))
    sections_seen = set(sections)
    http_err_cnt = 0
    fetch_cnt = 0

    arts = set()

    while len(sections)>0:
        section_url = sections.pop()

        try:
            fetch_cnt += 1
            html = ukmedia.FetchURL(section_url)
        except urllib2.HTTPError as e:
            # allow a few http errors...
            if e.code in (404,500):
                ukmedia.DBUG("ERR fetching %s (%d)\n" %(section_url,e.code))
                http_err_cnt += 1
                if http_err_cnt < 5:
                    continue
            raise

        try:
            doc = lxml.html.fromstring(html)
            doc.make_links_absolute(section_url)
        except lxml.etree.XMLSyntaxError as e:
            ukmedia.DBUG("ERROR parsing %s: %s\n" %(section_url, e))
            continue


        # check nav bars for sections to scan
        for navlink in doc.cssselect(navsel):
            url = navlink.get('href')
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue
            # strip fragment
            url = urlparse.urlunparse((o[0],o[1],o[2],o[3],o[4],''))

            if url in sections_seen:
                continue

            sections_seen.add(url)

            if [foo for foo in blacklisted_sections if foo in url]:
                ukmedia.DBUG2("IGNORE %s\n" %(url, ))
                continue

            # section is new and looks ok - queue for scanning
            #ukmedia.DBUG( "Queue section %s\n" % (url,))
            sections.add(url)

        # now scan this section page for article links
        section_arts = set()
        for a in doc.cssselect('body a'):
            url = a.get('href',None)
            if url is None:
                continue
            if art_url_pat.search(url) is None:
                continue
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue

            section_arts.add(url)

        ukmedia.DBUG("%s: found %d articles\n" % (section_url,len(section_arts) ) )
        arts.update(section_arts)

    ukmedia.DBUG("crawl finished: %d articles (from %d fetches)\n" % (len(arts),fetch_cnt,) )

    return list(arts)





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

    main_div = doc.cssselect('#main')[0]

    h1s = main_div.cssselect('h1.title')
    if not h1s:
        # eg /voices/ section
        h1s = doc.cssselect('#top h1.title')
    art['title'] = u' '.join(unicode(h1s[0].text_content()).split())

    bylines = main_div.cssselect('.articleByTimeLocation .byline .authorName')
    if not bylines:
        # eg /voices/ section
        bylines = doc.cssselect('#top .articleByline .author a')

    if bylines:
        art['byline'] = pretty_join([unicode(b.text_content()).strip() for b in bylines])
    else:
        art['byline'] = u''

    pubdate_txt = doc.cssselect('meta[property="article:published_time"]')[0].get('content')
    art['pubdate'] = ukmedia.ParseDateTime(pubdate_txt)

    content_divs = main_div.cssselect('.articleContent')
    txt = u" ".join([unicode(lxml.html.tostring(div)) for div in content_divs])
    art['content'] = ukmedia.SanitiseHTML(txt)

    art['description'] = ukmedia.FirstPara( art['content'] )


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


