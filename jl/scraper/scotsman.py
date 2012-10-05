#!/usr/bin/env python
#
# Scraper for The Scotsman and Scotland on Sunday
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# NOTES:
# Same article urls work on both thescotsman.scotsman.com and
# scotlandonsunday.scotsman.com.
# Extract fn should also handle Edinburgh Evening News and other papers on the same site
#


import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2
import lxml.html


import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup,Comment
from JL import ukmedia, ScraperUtils


def Extract(html, context, **kw):
    art = context

    doc = lxml.html.fromstring(html)
    doc.make_links_absolute(context['srcurl'])

    main_div = doc.cssselect('.editorialSection')[0]

    headlinetxt = unicode(main_div.cssselect('.mainHeadline')[0].text_content())
    headlinetxt = u' '.join(headlinetxt.split())
    art['title'] = headlinetxt

    byline = main_div.cssselect('.byline')[0]

    pubdatetxt = byline.cssselect('.pubDate')[0].text_content().strip()
    pubdate = ukmedia.ParseDateTime(pubdatetxt)
    art['pubdate'] = pubdate

    content_div = main_div.cssselect('.KonaBody')[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(content_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )


    bylinetxt = unicode(byline.text_content())
    bylinetxt = re.compile(r'\s*Published.*', re.I).sub(u'',bylinetxt)
    bylinetxt = u' '.join(bylinetxt.split())


    if bylinetxt == u'':
        # opinion section often has "blahblahblah writes Fred Bloggs" paras after byline but before content. sigh.
        foo = u' '.join([p.text_content() for p in main_div.cssselect('.editorialSectionLeft > p')]).strip()
        m = re.compile(r'writes\s+((?:[a-z]{2,}\s+){1,2}[a-z]{2,})\s*$',re.I).search(foo)
        if m:
            bylinetxt = m.group(1)

    art['byline'] = bylinetxt

    if '/scotland-on-sunday/' in art['srcurl']:
        art['srcorgname'] = u'scotlandonsunday'
    else:
        art['srcorgname'] = u'scotsman'

    return art




def FindSections():
    # use nav bar to get a list of section pages
    page_url = "http://www.scotsman.com"
    html = ukmedia.FetchURL(page_url)
    doc = lxml.html.fromstring(html)
    doc.make_links_absolute(page_url)

    sections = set()
    links = doc.cssselect('#navSearchBar a')
    for l in links:

        url = l.get('href')
        # TODO: overly restrictive, but hey. Could allow other sections.
        if '/the-scotsman/' in url or '/scotland-on-sunday/' in url:
            sections.add(url)
    return sections


def FindArticles():
    sections = FindSections()
    arts = set()

    art_url_pat = re.compile('.*/([a-z0-9_]+-){1,}[0-9]+$', re.I)

    # scan the sections for articles
    err_404_cnt = 0
    for section_url in sections:
        try:
            html = ukmedia.FetchURL(section_url)
        except urllib2.HTTPError, e:
            # allow a few 404s
            if e.code == 404:
                ukmedia.DBUG2("ERR fetching %s (404)\n" %(section_url,))
                err_404_cnt += 1
                if err_404_cnt < 5:
                    continue
            raise
        
        try:
            doc = lxml.html.fromstring(html)
            doc.make_links_absolute(section_url)
        except lxml.etree.XMLSyntaxError as e:
            ukmedia.DBUG("ERROR parsing %s: %s\n" %(section_url, e))
            continue

        section_arts = set()
        for a in doc.cssselect('#mainContent a'):
            url = a.get('href',None)
            if art_url_pat.search(url) is None:
                continue

            if url is None:
                continue
            section_arts.add(url)

        ukmedia.DBUG("%s: found %d articles\n" % (section_url,len(section_arts)) )
        arts.update(section_arts)

    return [ContextFromURL(url) for url in arts]







def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    o = urlparse.urlparse( url )
    if '/scotland-on-sunday/' in o[2]:
        context['srcorgname'] = u'scotlandonsunday'
    else:
        context['srcorgname'] = u'scotsman'

    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200 )

