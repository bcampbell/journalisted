#!/usr/bin/env python2.4
#
# Scraper for NewsOfTheWorld
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# NOTW have their content split into two: their own website, and on
# notw.typepad.com. Perhaps the former is for stuff that appears in
# print, and the typepad stuff is additional online-only content?
#
# notw rss feeds look pretty useless, so we do a shallow crawl for links.
# the typepad rss feeds would probably be OK...
#
# TODO:
# Can we split the crawling out (similar code in sun.py)?


import sys
import re
from datetime import datetime
import sys
import urllib2
import urlparse
import traceback

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,Tag
from JL import ukmedia, ScraperUtils

def ArticlesFromSoup( soup ):
    found = []
    for a in soup.findAll('a'):
        if not a.has_key( 'href' ):
            continue
        url = a['href']
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


def FindArticles():
    """Gather articles to scrape from the notw website."""

    found = []
    html = ukmedia.FetchURL( 'http://www.newsoftheworld.co.uk/' )
    soup = BeautifulSoup( html )

    nav_primary = soup.find('div',{'class':'nav-primary'})
    # for each primary section...
    for li in nav_primary.findAll('li'):
        a = li.a
        url = a['href']
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        ukmedia.DBUG2( "scan %s [%s]\n" % (name,url) )

        # don't bother fetching home page again - we've already got it
        if url == 'http://www.newsoftheworld.co.uk/':
            prim_soup = soup
        else:
            html2 = ukmedia.FetchURL( url )
            prim_soup = BeautifulSoup( html2 )

        found = found + ScanPrimary( prim_soup )

    return found


def ScanPrimary( soup ):
    found = ArticlesFromSoup( soup )
    ukmedia.DBUG2("  %d articles\n" % ( len(found) ) )
 
    # find links to all the secondary sections
    nav_sec = soup.find('div',{'class':'nav-secondary'})
    if nav_sec is None:
        return found

    for li in nav_sec.findAll('li'):
        a = li.a
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        url = a['href']
        if url == 'INVALID_ARTICLE_ID':
            ukmedia.DBUG2( "  SKIP %s [%s]\n" % (name,url) )
            continue

        ukmedia.DBUG2( "  scan %s [%s]..." % (name,url) )

        html = ukmedia.FetchURL( url )
        soup_sec = BeautifulSoup( html )
        found_sec = ArticlesFromSoup( soup_sec )
        ukmedia.DBUG2(" %d articles\n" % ( len(found_sec) ) )
        found = found + found_sec
    return found




def Extract( html, context ):
    o = urlparse.urlparse( context['srcurl'] )
    if o[1] == 'notw.typepad.com':
        return Extract_typepad( html, context )
    else:
        return Extract_notw( html, context )


def Extract_typepad( html, context ):
    """extractor for notw.typepad.com articles"""

    art = context
    art['srcorgname'] = u'notw'

    soup = BeautifulSoup( html )

    ediv = soup.find( 'div', {'class':'entry'} )
    h3 = ediv.find( 'h3', {'class':'entry-header'} )
    #contentdiv = ediv.find( 'div', {'class':'entry-content'} )
    bodydiv = ediv.find( 'div', {'class':'entry-body'} )
    morediv = ediv.find( 'div', {'class':'entry-more'} )
    footerspan = soup.find( 'span', {'class':'post-footers'} )

    headline = h3.renderContents(None)
    headline = ukmedia.FromHTML( headline )
    art['title'] = headline



    byline = u''
    footertxt = footerspan.renderContents(None)
    footercracker = re.compile( "Posted by\\s+(.*?)\\s+on\\s+(.*?\\s+at\\s+.*?)\\s*", re.UNICODE )
    m = footercracker.search(footertxt)
    if m:
        byline = m.group(1)
        datetxt = m.group(2)
    else:
        # "Posted at 12:01 AM"
        d = soup.find( 'h2', {'class':'date-header'} ).renderContents(None)
        m = re.search( "Posted at\\s+(.*)", footertxt )
        datetxt = d + u' ' + m.group(1)

    if byline == u'Online Team':
        byline = u''

    # often, the first non-empty para is byline
    if byline == u'':
        for p in bodydiv.findAll('p'):
            txt = ukmedia.FromHTML( p.renderContents( None ) )
            if not txt:
                continue
            m = re.match( "By\\s+((\\b\\w+(\\s+|\\b)){2,3})\\s*$" , txt, re.UNICODE|re.IGNORECASE )
            if m:
                byline = m.group(1)
                p.extract()
            break


    # can sometimes get proper author from blog title...
    if byline == u'':
        t = soup.find('title')
        tpat = re.compile( "\\s*((\\b\\w+\\b){2,3}):", re.UNICODE )
        m = tpat.search( t.renderContents(None) )
        if m:
            byline = m.group(1)


    art['byline'] = byline
    art['pubdate'] = ukmedia.ParseDateTime( datetxt )


    content = bodydiv.renderContents(None)
    if morediv:
        content = content + morediv.renderContents(None)
    content = ukmedia.SanitiseHTML( content )
    art['content'] = content

    art['description'] = ukmedia.FromHTML( ukmedia.FirstPara( content ) )


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

    art['title'] = headline_txt

    # byline and pubdate
    bylinep = col2.find( 'p',{'class':'byline'} )
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


    # main text is inside a bare <div>, near the byline para
    contentdiv = bylinep.findNextSibling('div', {'class':'clear-left'}).div

    #strip out cruft from text
    for cruft in contentdiv.findAll('div'):
        cruft.extract()
    for cruft in contentdiv.findAll('a', href="javascript:;" ):
        cruft.extract()
    content_txt = contentdiv.renderContents( None )
    content_txt = ukmedia.DescapeHTML(content_txt)
    art['content'] = content_txt
    art['description'] = ukmedia.FirstPara( content_txt )

    return art


def CalcSrcID( url ):
    o = urlparse.urlparse( url )
    if o[1] == 'notw.typepad.com':
        # eg "http://notw.typepad.com/hyland/2008/01/no-sniping-ross.html"
#        return o[2]
        # SCRAPER NEEDS WORK!
        return None
    if o[1] in ('newsoftheworld.co.uk','www.newsoftheworld.co.uk'):
        # eg http://www.newsoftheworld.co.uk/news/83126/TV-chef-Gordon-Ramsay-cheats-with-Jeffrey-Archers-ex-Sarah-Symonds-behind-wife-Tanas-back.html
        notw_idpat = re.compile( "/([0-9]+)/[^/]+[.]html$" )
        m = notw_idpat.search( url )
        if m:
            return m.group(1)
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
