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

    try:
        art_div = doc.cssselect('article')[0]
    except IndexError as e:
        if '/motors/reviews/' in context['srcurl']:
            ukmedia.DBUG2("SKIP old-format motor review %s\n" %(context['srcurl'],))
            return None
        else:
            raise

    headlinetxt = unicode(art_div.cssselect('header h1')[0].text_content())
    headlinetxt = u' '.join(headlinetxt.split())
    art['title'] = headlinetxt

    art['byline'] = u''
    authors = art_div.cssselect('header .author')
    if len(authors)>0:
        art['byline'] = ukmedia.FromHTMLOneLine(authors[0].text_content())

    pubdatetxt = art_div.cssselect('.published')[0].text_content().strip()
    art['pubdate'] = ukmedia.ParseDateTime(pubdatetxt)

    content_div = art_div.cssselect('.KonaBody')[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(content_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )

    if '/scotland-on-sunday/' in art['srcurl']:
        art['srcorgname'] = u'scotlandonsunday'
    else:
        art['srcorgname'] = u'scotsman'

    return art





art_url_pat = re.compile('.*/([a-z0-9_]+-){1,}[0-9]+$', re.I)

def FindArticles():
    sections = set( ("http://www.scotsman.com/",))
    sections_seen = set(sections)
    err_404_cnt = 0

    arts = set()

    while len(sections)>0:
        section_url = sections.pop()
        try:
            html = ukmedia.FetchURL(section_url)
        except urllib2.HTTPError as e:
            # allow a few 404s
            if e.code == 404:
                ukmedia.DBUG("ERR fetching %s (404)\n" %(section_url,))
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


        # check nav bars forsections to scan
        for navlink in doc.cssselect('#level1nav a, #navigationTier a'):
            url = navlink.get('href')
            if url in sections_seen:
                continue
            o = urlparse.urlparse( url )
            if o.hostname not in ('www.scotsman.com',):
                continue

            blacklisted_sections = ()
            if [foo for foo in blacklisted_sections if foo in url]:
                continue

            # section is new and looks ok - queue for scanning
            #ukmedia.DBUG( "Queue section %s\n" % (url,))
            sections.add(url)
            sections_seen.add(url)

        # now scan this section page for article links
        section_arts = set()
        for a in doc.cssselect('body a'):
            url = a.get('href',None)
            if url is None:
                continue
            if art_url_pat.search(url) is None:
                continue
            o = urlparse.urlparse( url )
            if o.hostname not in ('www.scotsman.com',):
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
    #arts = FindArticles()
    #print( "Found %d article links\n"%(len(arts),))
    #for art in arts:
    #    print art['srcurl']
    #    ukmedia.FetchURL(art['srcurl'])

    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200 )

