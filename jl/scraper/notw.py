#!/usr/bin/env python
#
# Scraper for NewsOfTheWorld
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# NOTW have their content split into two: their own website, and on
# notw.typepad.com (blogs.newsoftheworld.co.uk).
#
# notw rss feeds look pretty useless, so we do a shallow crawl for links.
# the typepad rss feeds would probably be OK...
#


import sys
import re
from datetime import datetime
import sys
import urllib2
import urlparse
import traceback

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup,Tag,Comment
from JL import ukmedia, ScraperUtils

notw_bloggers = (
    u'Ian Kirby',
    u'Jamie Lyons',
    u'Fraser Nelson',
    u'Sophy Ridge' )

def ArticlesFromSoup( soup ):

    blacklist = ( '/celebgallery/', '/yourscore/' )

    found = []
    for a in soup.findAll('a'):
        if not a.has_key( 'href' ):
            continue
        url = a['href']
        url = urlparse.urljoin( "http://www.newsoftheworld.co.uk", url )


        skip = False
        for b in blacklist:
            if b in url:
                skip = True
        if skip:
            continue


        srcid = CalcSrcID( url )
        if srcid is not None:
            context = {
                'srcurl': url,
                'srcid': srcid,
                'permalink': url,
                'lastseen': datetime.now(),
                'srcorgname' : u'notw',
                }
            found.append( context )
    return found

def ScrubFunc( context, entry ):
    """mungefunc for ScraperUtils.FindArticlesFromRSS()"""

    url = context[ 'srcurl' ]
#    if url.find('feedburner') != -1:
#        url = entry.feedburner_origlink
#    context['srcurl'] = url
#    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    return context



def FindArticles():
    """Gather articles to scrape from the notw website."""

#    feeds =  [ ('NOTW Politics', 'http://blogs.notw.co.uk/politics/atom.xml') ]

#    found = ScraperUtils.FindArticlesFromRSS( feeds, u'notw', ScrubFunc )

#    return found

    found = []
    html = ukmedia.FetchURL( 'http://www.newsoftheworld.co.uk/' )
    soup = BeautifulSoup( html )

    nav_primary = soup.find('div',{'class':'nav-primary'})
    # for each primary section...
    for li in nav_primary.findAll('li'):
        a = li.a
        url = a['href']
        url = urlparse.urljoin( "http://www.newsoftheworld.co.uk", url )
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        ukmedia.DBUG2( "scan %s [%s]\n" % (name,url) )

        # don't bother fetching home page again - we've already got it
        if url == 'http://www.newsoftheworld.co.uk/':
            prim_soup = soup
        else:
            try:
                html2 = ukmedia.FetchURL( url )
            except urllib2.HTTPError, e:
                # continue even if we get http errors (bound to be a borked
                # link or two)
                ukmedia.DBUG( "SKIP '%s' (%d error)\n" %(url, e.code) )
                continue

            prim_soup = BeautifulSoup( html2 )

        found = found + ScanPrimary( prim_soup )

    return found


def ScanPrimary( soup ):
    found = ArticlesFromSoup( soup )
    ukmedia.DBUG2("  %d articles\n" % ( len(found) ) )
 
    # find links to all the secondary sections
    nav_sec = soup.find('div',{'class':'nav-secondary'})
    nav_links = []
    if nav_sec is not None:
        for li in nav_sec.findAll('li'):
            nav_links.append( li.a )
    else:
        # "fabulous" mag section has it's own layout.
        # main nav menu on left column is done via javascript (why?)
        # but there are links at the bottom we can use.
        nav_sec = soup.find('div',{'id':'fabfooter-links-container'} )
        if nav_sec is not None:
            for li in nav_sec.findAll('li'):
                nav_links.append( li.a )


    for a in nav_links:
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        url = a['href']
        if url == 'INVALID_ARTICLE_ID':
            ukmedia.DBUG2( "  SKIP %s [%s]\n" % (name,url) )
            continue
        url = urlparse.urljoin( "http://www.newsoftheworld.co.uk", url )

        ukmedia.DBUG2( "  scan %s [%s]..." % (name,url) )

        try:
            html = ukmedia.FetchURL( url )
            # continue even if we get urllib2 errors (bound to be a borked
            # link or two)
        except urllib2.HTTPError, e:
            ukmedia.DBUG( "ERROR fetching '%s' (code %d)\n" %(url, e.code) )
            continue
        except urllib2.URLError, e:
            ukmedia.DBUG( "ERROR connecting to '%s' (reason: %s)\n" %(url, e.reason) )
            continue

        soup_sec = BeautifulSoup( html )
        found_sec = ArticlesFromSoup( soup_sec )
        ukmedia.DBUG2(" %d articles\n" % ( len(found_sec) ) )
        found = found + found_sec
    return found




def Extract( html, context ):
    o = urlparse.urlparse( context['srcurl'] )
#    if o[1] == 'notw.typepad.com':
#        return Extract_typepad( html, context )

    if o[1] == 'blogs.notw.co.uk':
        return Extract_blog( html, context )
    else:
        return Extract_notw( html, context )



def Extract_blog( html, context ):
    """extractor for blog articles articles (think they are hosted on typepad)"""

    art = context
    art['srcorgname'] = u'notw'

    ukmedia.DBUG2( "SKIP BLOG: %s" %(art['permalink'],) )
    return None


    soup = BeautifulSoup( html )

    # find headline
    datespan = soup.find('span',{'class':'entry-date-bar'})
    b = datespan.findPrevious('b')
    headline_txt = ukmedia.FromHTMLOneLine( b.renderContents(None) )
    art['title'] = headline_txt

    # pubdate
    # if we found this article via an rss/atom feed we'll already
    # have a good date and time. If not, we'll have to use the shitty
    # date-only text on the page.
    if not 'pubdate' in art:
        datep = soup.find('p',{'class':"entry-footer-info"})
        foo = ukmedia.FromHTMLOneLine( datep.renderContents(None) )
        m = re.compile('Posted on\s+(.*?)\s*[|]').search( foo )
        art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

    # byline
    # there is no byline, but there is a thumbnail image of the blogger
    # image src url contains bloggers first name.
    # ugh.
    art['byline'] = u''
    thumb_img = soup.find('img',{'src':re.compile('thumb[.]jpg$')})
    thumb_url = thumb_img['src'].lower()
    for b in notw_bloggers:
        firstname = b.split()[0].lower()
        if firstname in thumb_url:
            art['byline'] = b
            break

    # content
    # html is so borked that we're going to use regex to grab the text,
    # then use beautifulsoup to reformat it.
    content_pat = re.compile( r'(<span\s+class="entry-body"\s*>.*?)\s*<!-- forward and back buttons -->', re.DOTALL )
    m = content_pat.search( html )
    content_soup = BeautifulSoup(unicode( m.group(1), soup.originalEncoding ) )

    for cruft in content_soup.findAll('script' ):
        cruft.extract()

    content_txt = ukmedia.SanitiseHTML( content_txt )
    art['content'] = content_txt
    art['description'] = ukmedia.FirstPara( content_txt )
    return art






def Extract_notw( html, context ):
    """extractor for newsoftheworld.co.uk articles"""
    art = context

    art['srcorgname'] = u'notw'
#   if re.search( 'Sorry,\\s+the\\s+story\\s+you\\s+are\\s+looking\\s+for\\s+has\\s+been\\s+removed.', html ):
#       ukmedia.DBUG2( "IGNORE missing article (%s)\n" % ( art['srcurl']) )
#       return None

    soup = BeautifulSoup( html )

    col2 = soup.find('div',{'id':'column2'})

    # headline
    h1 = col2.find('h1')
    headline_txt = u''
    if h1 is not None:
        headline_txt = h1.renderContents( None )
        headline_txt = ukmedia.FromHTMLOneLine( headline_txt )
    else:
        # gah. stupid notw articles with image-based headlines.
        header_div = col2.find('div',{'class':'image-holder'} )
        if header_div is not None:
            header_img = header_div.img
#            if '_header_' in header_img['src']:
            headline_txt = header_img['alt']
            headline_txt = ukmedia.FromHTMLOneLine( headline_txt )
            if headline_txt != u'':
                # don't want to pick this one up as an image
                header_div.extract()

    if headline_txt == u'':
        # last ditch try:
        m = re.search( r'var jsTitle = "(.*?)";', html )
        headline_txt = m.group(1).decode( soup.originalEncoding )
        headline_txt = ukmedia.DescapeHTML( headline_txt )

    art['title'] = headline_txt

    # byline and pubdate
    bylinep = col2.find( 'p',{'class':'byline'} )
    # kill the twitter link, if any
    for a in bylinep.findAll( 'a', {'href':re.compile('http://twitter[.]com')} ):
        a.extract()

    byline_txt = bylinep.renderContents( None )
    byline_txt = ukmedia.FromHTMLOneLine( byline_txt )

    m = re.match( r'(.*?)\s*,?\s*(\d{2}/\d{2}/\d{4})', byline_txt )
    if m:
        byline_txt = m.group(1)
        date_txt = m.group(2)
        art['byline'] = byline_txt
        art['pubdate'] = ukmedia.ParseDateTime( date_txt )
    else:
        # sigh.
        art['byline'] = byline_txt
        art['pubdate'] = datetime.now() # fudge

    # images
    art['images'] = []
    # inline images
    for d in col2.findAll( 'div', {'class':re.compile(r"inline-image-") } ):
        img = d.img
        img_url = img['src']
        img_caption = u''
        capdiv = d.find( 'div', {'class':'caption'} )
        if capdiv:
            img_caption = ukmedia.FromHTMLOneLine( capdiv.renderContents(None) )
        art['images'].append( {'url': img_url, 'caption':img_caption, 'credit':u''} )
    # non-inline images
    for img_holder in col2.findAll( 'div', {'class':'image-holder'} ):
        img = img_holder.img
        if img is None:
            continue
        img_url = img['src']
        img_caption = u''
        if '/multimedia/archive/' not in img_url:
            continue
        d = img_holder.nextSibling
        while not isinstance( d, Tag ):
            d = d.nextSibling

        if d.name == 'div' and d.has_key('class' ) and d['class'] =='caption-container':
            capdiv = d.find( 'div', {'class':'caption'} )
            if capdiv:
                img_caption = ukmedia.FromHTMLOneLine( capdiv.renderContents(None) )
        art['images'].append( {'url': img_url, 'caption':img_caption, 'credit':u''} )

    # count the comments
    comment_a = soup.find('a',{'name':'comments'})
    if comment_a is not None:
        art['commentlinks'] = []
        o = urlparse.urlparse( art['srcurl'] )
        comment_url = urlparse.urlunsplit( (o[0],o[1],o[2],o[3],comment_a['name']) )
        num_comments=0
        for n in soup.findAll('div',{'class':'individual-comment'}):
            num_comments=num_comments+1
        art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )


    # main text starts after the byline, and goes on until the crap at the bottom.

    cruft = bylinep
    # kill byline and everything before it
    while cruft:
        prev = cruft.previousSibling
        cruft.extract()
        cruft = prev

    # kill off assorted non-content crap
    contentdiv = col2.find( 'div', {'id':'column2-inner-article'} )

    #strip out cruft from text
    for cruft in contentdiv.findAll(['script','div','link'] ):
        cruft.extract()
    for cruft in contentdiv.findAll('a', href="javascript:;" ):
        cruft.extract()
    for cruft in soup.findAll(text=lambda text:isinstance(text, Comment)):
        cruft.extract()


    content_txt = contentdiv.renderContents( None )
    content_txt = ukmedia.SanitiseHTML( content_txt )
    art['content'] = content_txt
    art['description'] = ukmedia.FirstPara( content_txt )

    return art


def CalcSrcID( url ):
    o = urlparse.urlparse( url )
    if o[1] == 'blogs.notw.co.uk':
        # eg: "http://blogs.notw.co.uk/politics/2008/11/jingle-hells.html"
        if o[2].endswith('.html'):
            return 'notw_blogs_' + o[2]

    if o[1] in ('newsoftheworld.co.uk','www.newsoftheworld.co.uk'):
        # eg http://www.newsoftheworld.co.uk/news/83126/TV-chef-Gordon-Ramsay-cheats-with-Jeffrey-Archers-ex-Sarah-Symonds-behind-wife-Tanas-back.html
        notw_idpat = re.compile( "/([0-9]+)/[^/]+[.]html$" )
        m = notw_idpat.search( o[2] )
        if m:
            return 'notw_' + m.group(1)
    return None

def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'notw'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, maxerrors=50 )

