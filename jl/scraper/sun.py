#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# KNOWN ISSUES:
#
# - the sun sometimes embed multiple stories on the same webpage,
#   for now we just process only the "main" story and discard the substory.
# - we miss subheadings for the occasional article
#   (they sometimes skip the "article" class we look for...)
# - pages with flash video leave some cruft in the content text
#    ("You need Flash Player 8 or higher..." etc)
#

import re
import urllib2
import sys
import traceback
from datetime import date,datetime
import urlparse
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ScraperUtils

from pprint import pprint

# current url format:
# http://www.thesun.co.uk/sol/homepage/news/2471744/Browns-Nailed-Plotters.html
srcidpat_slugstyle = re.compile( '/(\\d+)/[^/]+.html$' );

# prev url format:
# http://www.thesun.co.uk/sol/homepage/news/royals/article862982.ece
# http://www.thescottishsun.co.uk/scotsol/homepage/news/article2438517.ece
srcidpat_ecestyle = re.compile( '/(article\\d+[.]ece)$' )

# Old url format, no longer used (vignette storyserver cms, I think)
# http://www.thesun.co.uk/article/0,,2007400986,00.html
srcidpat_oldstyle = re.compile( '/(article/[^/]+[.]html)$' )


# names of columnists and indicators in urls, for last resort bylines
columnist_lookups = [
    {'url': '/columnists/fergus_shanahan/', 'name':u'Fergus Shanahan' },
    {'url': '/columnists/ally_ross/', 'name': u'Ally Ross' },
    {'url': '/columnists/jane_moore/', 'name': u'Jane Moore' },
    {'url': '/columnists/blunkett/', 'name': u'David Blunkett' },
    {'url': '/columnists/kelvin_mackenzie/', 'name': u'Kelvin MacKenzie' },
    {'url': '/columnists/john_gaunt/', 'name': u'John Gaunt' },
    {'url': '/columnists/lorraine_kelly/', 'name': u'Lorraine Kelly' },
    {'url': '/columnists/clarkson/', 'name': u'Jeremy Clarkson' },
    {'url': '/columnists/kavanagh/', 'name': u'Trevor Kavanagh' },
]


def CalcSrcID( url ):
    """Extract a unique srcid from url"""

    o = urlparse.urlparse( url )
    if not (o[1].endswith('thesun.co.uk') or o[1].endswith('thescottishsun.co.uk') ):
        return None

    for blacklisted in ( '/mystic_meg/', '/virals/', '/video/' ):
        if blacklisted in o[2]:
            return None

    m = srcidpat_slugstyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    m = srcidpat_ecestyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    m = srcidpat_oldstyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    return None




def FindSections():
    """ get list of sections from nav popup """

    page_url = "http://www.thesun.co.uk"
    html = ukmedia.FetchURL(page_url)
    doc = lxml.html.fromstring(html)
    doc.make_links_absolute(page_url)

    sections = set()
    links = doc.cssselect('#nav-extension-container .nav-extension-category a')
    for l in links:
        url = l.get('href')

        blacklisted_sections = ['www.page3.com','/video/','/fun/competitions/']
        if [foo for foo in blacklisted_sections if foo in url]:
            continue
        sections.add(url)

    return sections



def FindArticles():
    sections = FindSections()
    ukmedia.DBUG2( "scanned navigation menu, found %d sections\n" %(len(sections),) )

    article_urls = set()
    for section_url in sections:
        article_urls.update( ReapArticles( section_url ) )

    foundarticles =[]
    for url in article_urls:
        context = ContextFromURL( url )
        if context is not None:
            foundarticles.append( context )

    ukmedia.DBUG2( "Found %d articles\n" % ( len(foundarticles) ) )
    return foundarticles



def ReapArticles( page_url ):
    """ find all article links on a page """

    article_urls = set()
    #    ukmedia.DBUG2( "scanning for article links on %s\n" %(page_url,) )
    try:
        html = ukmedia.FetchURL( page_url ) 
    except urllib2.HTTPError, e:
        # bound to be some 404s...
        ukmedia.DBUG( "SKIP '%s' (%d error)\n" %(page_url, e.code) )
        return article_urls

    html = ukmedia.FetchURL(page_url)
    doc = lxml.html.fromstring(html)
    doc.make_links_absolute(page_url)


    for a in doc.cssselect('a'):
        url = a.get('href')
        if url is None:
            continue
        url = urlparse.urljoin( page_url, url )
        url = ''.join( url.split() )
        url = re.sub( '#(.*?)$', '', url)

        #title = a.text_content()
        srcid = CalcSrcID( url )
        #print url,":",srcid
        if srcid is not None:
            article_urls.add(url)

    ukmedia.DBUG2( "scanned %s, found %d articles\n" % ( page_url, len(article_urls) ) );
    return article_urls




def Extract(html, context, **kw):
    art = context

    # we know it's utf-8 (lxml.html seems to get it wrong sometimes)
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])
    doc.make_links_absolute(art['srcurl'])

    main_div = doc.cssselect('#articlebody')[0]

    h1 = main_div.cssselect('h1')[0]
    art['title'] = u' '.join(unicode(h1.text_content()).split())

    bylines = main_div.cssselect('.display-byline')
    if bylines:
        art['byline'] = u' '.join(unicode(bylines[0].text_content()).split())
    else:
        art['byline'] = u''

    pubdate_txt = doc.cssselect('meta[property="article:published_time"]')[0].get('content')
    art['pubdate'] = ukmedia.ParseDateTime(pubdate_txt)

    content_div = main_div.cssselect('#bodyText')[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(content_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )


    return art




tidyurl_pat = re.compile( "(http:[/][/].*[/]article\\d+[.]ece)([?].*)?" )
def TidyURL( url ):
    """Sun urls can have params (eg to say they came from an rss feed)."""
    url = tidyurl_pat.sub( "\\1", url )
    return url



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    url = TidyURL(url)
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'sun'
    context['lastseen'] = datetime.now()
    return context



if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=150 )

