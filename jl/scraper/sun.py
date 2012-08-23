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


def FindArticles():
    homepage = "http://www.thesun.co.uk"
    html = ukmedia.FetchURL( homepage )
    soup = BeautifulSoup( html )

    # go through the naviation menu looking for urls of sections
    sections = set()
    nav = soup.find( 'div',{'id':'LeftNavigation'} )
    for a in nav.findAll( 'a' ):
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        url = urlparse.urljoin( homepage, a['href'] )

        # filter out undesirables...
        accepted = True
        o = urlparse.urlparse( url )
        if o[1] not in ('www.thesun.co.uk', 'thesun.co.uk' ):
            accepted = False
        for blacklisted in ( '/mystic_meg/', '/virals/' ):
            if blacklisted in o[2]:
                accepted = False

        if accepted:
            sections.add( url )

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

    soup = BeautifulSoup( html )


    for a in soup.findAll( 'a' ):
        url = a.get('href')
        if url is None:
            continue
        url = urlparse.urljoin( page_url, url )
        url = ''.join( url.split() )
        url = re.sub( '#(.*?)$', '', url)

        title = a.string
        srcid = CalcSrcID( url )
        #print url,":",srcid
        if srcid is not None:
            article_urls.add(url)

    ukmedia.DBUG2( "scanned %s, found %d articles\n" % ( page_url, len(article_urls) ) );
    return article_urls






def Extract(html, context, **kw):
    art = context
    # sun _claims_ to be iso-8859-1, but they're talking crap.
    page_encoding = 'windows-1252'
    soup = BeautifulSoup( html, fromEncoding=page_encoding)

    # main column is column2 div - we can exclude a lot of nav cruft by starting here.
    col2 = soup.find( 'div', {'id':"column2"} )

    # sigh.... the sun sometimes embed multiple stories on the same page...
    # For now we'll just discard the sub-story. Unhappy about this, but
    # it just makes things too complicated.
    # TODO: something better.
    col3 = col2.find('div', { 'id':re.compile("column3") } )
    if col3:
        col3.extract()


    # get headline
    h1 = col2.h1
    if not h1:
        # their html is so messed up that sometimes BeautifulSoup mistakenly
        # closes the column2 div before the main article. If that is the
        # case, just use the whole soup instead...
        col2 = soup
        # need to skip the h1 banner at top of page
        artmodule = soup.find( text=re.compile(".*BEGIN: Module - Main Article.*"))
        if artmodule:
            h1 = artmodule.findNext('h1')
        else:
            #sigh... sometimes they have "roottag" at the start of the article.
            # What is "roottag"? good question...
            roottag = soup.find( 'roottag' )
            if roottag:
                h1 = roottag.findPrevious( 'h1' )

    if h1 is not None and 'small' in h1['class']:
        h1 = None

    if h1:
        titletxt = h1.renderContents(None).strip()
    else:
        # sometimes there is no <h1> headline - it can be replaced by a gif
        # try meta tag
        m = soup.find('meta[name="og:title"]')
        if m:
            titletxt = unicode(m['content'],page_encoding)
        else:
            # no headline. get it from page title.  
            foo = soup.title.renderContents( None );
            m = re.search( r"\s*(.*?)\s*[|].*", foo )
            titletxt = m.group(1)

    titletxt = ukmedia.FromHTML( titletxt )
    titletxt = u' '.join( titletxt.split() )
    art['title'] = titletxt

    # ignore some known pages
    ignore_titles = [ "Contact us", "HAVE YOUR SAY" ]
        #   "Your stars for the month ahead"?
    if art['title'] in ignore_titles:
        ukmedia.DBUG2( "IGNORE '%s' (%s)\n" % (art['title'], art['srcurl']) );
        return None


    if html.find("BEGIN ROO vxFlashPlayer embed") != -1:
        ukmedia.DBUG2( "IGNORE video page '%s' (%s)\n" % (art['title'], art['srcurl']) );
        return None

    # 'author' class paras for author, email link and date...
    bylinetxt = u''
    datetxt = u''
    # get page date (it's in format "Friday, December 14, 2007")
    #pagedatetxt = soup.find( 'p', {'id':"masthead-date"}).string.strip()

    for author in soup.findAll( ['p','div'], { 'class': re.compile( r'\bauthor\b|\bdisplay-byline\b' ) } ):
        txt = author.renderContents( None ).strip()

        if txt == '':
            continue
        if txt.find( 'Email the author' ) != -1:
            continue        # ignore email links

        m = re.compile( r'^(?:Published:|Last Updated:)\s+(.*)$',re.I).search(txt)
        if m is not None:
            # it's a date (eg '11 Dec 2007' or 'Today')
            datetxt = m.group(1)
        else:
            # "By Bob Smith" or "BOB SMITH"
            if re.compile('^by\s',re.I).search(txt) or re.compile(r'^[A-Z]{3,}\s+[A-Z]{3,}(\s+[A-Z]{3,})?\s*$').match(txt):
                if bylinetxt != u'':
                    raise Exception, "Uhoh - multiple bylines..."
                bylinetxt = ukmedia.FromHTMLOneLine(txt)


    if datetxt == u'':
        foo = soup.find('div',{'class':re.compile(r'\bpublished-date-text\b')})
        if foo:
            txt = ukmedia.FromHTMLOneLine(foo.renderContents(None))
            m = re.compile( r'^(?:Published:|Last Updated:)\s+(.*)$',re.I).search(txt)
            if m is not None:
                datetxt = m.group(1)

    if datetxt == u'Today':
        art['pubdate'] = datetime( d.year, d.month, d.day )
    else:
        if datetxt != '':
            try:
                art['pubdate' ] = ukmedia.ParseDateTime( datetxt )
            except Exception:
                # there is some javascript we can look at if all else fails...
                m = re.compile(r'publication_date : "(\d{8})"').search(html)
                if m:
                    art['pubdate']=datetime.strptime(m.group(1),"%Y%m%d")
        else:
            # there is some javascript we can look at if all else fails...
            m = re.compile(r'publication_date : "(\d{8})"').search(html)
            if m:
                art['pubdate']=datetime.strptime(m.group(1),"%Y%m%d")

    if bylinetxt == u'':
        # columnists have an <img> with alt='...'
        for author in soup.findAll( ['p','div'], { 'class': re.compile( r'\bauthor\b|\bdisplay-byline\b' ) } ):
            img = author.find('img')
            if img and 'alt' in img:
                bylinetxt = img['alt'].strip()

    if bylinetxt == u'':
        # look for byline in javascript
        m = re.compile(r'var byLine = "(.*?)";').search(html)
        if m:
            bylinetxt = unicode(m.group(1), page_encoding)
            bylinetxt = ukmedia.FromHTMLOneLine(bylinetxt)

    if bylinetxt == u'':
        # some special cases where no byline is given
        for c in columnist_lookups:
            if c['url'] in art['srcurl']:
                bylinetxt = c['name']


    bylinetxt = bylinetxt.replace('<br/>',' ')
    art['byline'] = bylinetxt.strip()


    bodyText = soup.find( 'div', {'id':'bodyText'} )
    for cruft in bodyText.findAll( 'p', {'class':re.compile('advertising') } ):
        cruft.extract()
    contenttxt = bodyText.renderContents(None)
    contenttxt = ukmedia.SanitiseHTML( contenttxt );
    art['content'] = contenttxt
    art['description'] = ukmedia.FirstPara( contenttxt )


    # pprint( art)
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
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

