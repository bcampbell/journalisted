#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for Mirror and Sunday Mirror
#

import re
from datetime import datetime
import time
import string
import sys
import urlparse
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia, ScraperUtils




def Extract(html, context, **kw):
    url = context['srcurl']
   
    o = urlparse.urlparse(url)
    if re.match('(www[.])?mirror[.]co[.]uk',o.hostname):
        return Extract_MainSite( html, context )
    if re.match('(www[.])?mirrorfootball[.]co[.]uk',o.hostname):
        # TODO: mirrorfootball
        return None
    if re.match( r'/(blogs|fashion)[.]mirror[.]co[.]uk', o.hostname ):
        return Extract_Blog( html, context )


def Extract_MainSite( html, context ):
    art = context

    # we know it's utf-8 (lxml.html seems to get it wrong sometimes)
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])

    article_div = doc.cssselect('.article-page .article')[0]
    #print lxml.html.tostring(article_div)
    #return None


    header_div = article_div.cssselect('.article-header')[0]

    h1 = header_div.cssselect('h1')[0]
    art['title'] = ukmedia.FromHTMLOneLine(unicode(lxml.html.tostring(h1)))

    for li in header_div.cssselect('ul.tools li'):
        txt = li.text_content()
        if re.search(r'\d{4}',txt):
            # it's a date
            art['pubdate'] = ukmedia.ParseDateTime(txt)
        elif 'By' in txt:
            # it's a byline (should use rel=author)
            art['byline'] = u' '.join(txt.split())

    body_div = article_div.cssselect('.body')[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(body_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )
    art['srcorgname'] = u'mirror'   # to avoid clash with sundaymirror in pub_domain table (TODO: merge both into "Mirror Online")

    return art







def Extract_Blog( html, context ):
    """extract article from a mirror.co.uk page"""

    art = context
    soup = BeautifulSoup( html )

    #maindiv = soup.find( 'div', { 'class': 'art-body' } )

    h1 = soup.find( 'h1', { 'class':'asset-name' } )
    art['title'] = ukmedia.FromHTML( h1.renderContents( None ) )

    body = soup.find( 'div', { 'class': 'asset-body' } )
    for cruft in body.findAll( 'span', {'class':re.compile("mt-enclosure")} ):
        cruft.extract()
    for cruft in body.findAll( 'img' ):
        cruft.extract()
    for cruft in body.findAll( 'object' ):
        cruft.extract()



    art['content'] = body.renderContents( None )
    #art['content'] = ukmedia.SanitiseHTML( art['content'] )

    art['description'] = ukmedia.FirstPara( art['content'] )

    # meta contains byline and date and permalink...
    # eg: "By Ann Gripper on Jul 21, 08 10:00 AM  in Golf"
    meta = soup.find( 'div', { 'class': 'asset-meta' } )
    metatxt = ukmedia.FromHTML( meta.renderContents( None ) )
    metatxt = u' '.join( metatxt.split() )
    metapat = re.compile( r"\s*(.*?)\s*on\s+(.*?(AM|PM))\s*" )
    m = metapat.search( metatxt )
    art['byline'] = m.group(1)
    art['pubdate'] = ukmedia.ParseDateTime( m.group(2) )

    return art




# to get unique id out of url
srcid_patterns = [


    # new-style:
    #  http://www.mirror.co.uk/news/top-stories/2008/07/24/exclusive-anne-darwin-vows-to-flee-to-panama-and-1million-fortune-when-out-of-jail-115875-20668758/
    # old-style (mirror):
    #  http://www.mirror.co.uk/news/topstories/2008/02/29/prince-harry-to-be-withdrawn-from-afghanistan-89520-20335665/
    # old-style (sunday mirror):
    #  http://www.sundaymirror.co.uk/news/sunday/2008/02/24/commons-speaker-michael-martin-in-new-expenses-scandal-98487-20329121/
    re.compile( "-([-0-9]+)(/([?].*)?)?$" ),

    # really old style:
    re.compile( "%26(objectid=[0-9]+)%26" ),

    # blogs:
    # http://blogs.mirror.co.uk/maguire/2008/07/beauty-and-the-beast.html
    # "http://fashion.mirror.co.uk/2008/04/sun-and-sandal.html"
    re.compile( "((blogs|fashion).mirror.co.uk/.*[.]html)" )
    ]



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['lastseen'] = datetime.now()
    return context





def FindArticles():

    home_url = "http://www.mirror.co.uk"
    html = ukmedia.FetchURL(home_url) 
    doc = lxml.html.document_fromstring( html )
    doc.make_links_absolute(home_url)

    sections = set()

    for a in doc.cssselect('nav.nav-main nav a'):
        url = a.get('href')
        sections.add(url)

    article_urls = set()
    for section_url in sections:
        article_urls.update(ReapArticles(section_url))
  
    return [ContextFromURL(url) for url in article_urls]




def ReapArticles(page_url):
    """ find all article links on a page """

    art_pats = (
        re.compile('https?://(www.)?mirror.co.uk/.*-[0-9]{4,}$', re.I),
        re.compile('https?://(www.)?mirrorfootball.co.uk/.*[0-9]{4,}[.]html$', re.I)
    )

    article_urls = set()
    html = ukmedia.FetchURL( page_url ) 

    doc = lxml.html.document_fromstring( html )
    doc.make_links_absolute(page_url)

    for a in doc.cssselect('a'):
        url = a.get('href')
        if url is None:
            continue
        url = re.sub( '#(.*?)$', '', url)

        for pat in art_pats:
            if pat.match(url):
                article_urls.add(url)
                break

    ukmedia.DBUG2( "scanned %s, found %d articles\n" % ( page_url, len(article_urls) ) );
    return article_urls



if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

