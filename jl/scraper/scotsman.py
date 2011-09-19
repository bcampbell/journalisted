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

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup,Comment
from JL import ukmedia, ScraperUtils



def Extract(html, context, **kw):
    art = context
    # for some reason BeautifulSoup gets the encoding as ISO-8859-1...
    # but we know it's utf-8
    soup = BeautifulSoup( html, fromEncoding='utf-8' )

    if soup.find( 'div', {'id':'divSubscribe','class':'premiumon'} ):
        ukmedia.DBUG2( "IGNORE: subscription-only article (%s)\n" % ( art['srcurl']) );
        return None

    # check for and ignore broken pages
    artdiv = soup.find( 'div', {'id':'viewarticle'} )
    if not artdiv and html.find( "The article has been unable to display.") != -1:
        ukmedia.DBUG2( "IGNORE article ('unable to display') (%s)\n" % ( art['srcurl']) );
        return None

    # pull out source publication
    # eg"<li> <span id="spanPub"> <strong> Source: </strong> The Scotsman </span> </li>"
    # "Press Association"
    # "The Scotsman"
    # "Scotland On Sunday"
    # "Edinburgh Evening News"
    source = u''
    pubspan = soup.find( 'span', {'id':'spanPub'} )
    if not pubspan:
        ukmedia.DBUG2( "IGNORE article (borked?) (%s)\n" % ( art['srcurl']) );
        return None

    txt = ukmedia.FromHTML( pubspan.renderContents(None) )
    m = re.search( u'Source:\\s+(.*)\\s*$', txt )
    if m:
        source = m.group(1)


    if source in ( u'Edinburgh Evening News', u'NHS Choices' ):
        ukmedia.DBUG2( "IGNORE: %s item (%s)\n" % (source, context['srcurl']) )
        return None

    if source == u'Press Association':
        ukmedia.DBUG2( "IGNORE: PA item (%s)\n" % (context['srcurl']) )
        return None

    if source in ( u'The Scotsman', u'scotsman.com' ):
        art['srcorgname'] = u'scotsman'
    elif source == u'Scotland On Sunday':
        art['srcorgname'] = u'scotlandonsunday'
    else:
        ukmedia.DBUG2( "SKIPPING - unknown source org '%s' (%s)\n" % (source, context['srcurl']) )
        return None
 
    # else throw exception?

    headline_div = soup.find('div', {'class':'headline'} )
    h1 = headline_div.h1
    headline = h1.renderContents( None )
    headline = ukmedia.FromHTMLOneLine( headline )
    art['title'] = headline


    # pull out date
    # there is a Published Date, without timestamp:
    # eg "<div> <strong> Published Date: </strong> 12 February 2008 </div>"
    # If it's not there (eg for NIB articles?) we'll use the Last Updated instead:
    datemarker = soup.find( text=re.compile('Published Date:') )
    if datemarker:
        datediv = datemarker.findParent('div')
        datediv.strong.extract()
        datetxt = datediv.renderContents( None ).strip()
    else:
        datemarker = soup.find( 'ul', {'class': 'viewarticle_info'} ).find( text=re.compile('Last Updated:') )
        datediv = datemarker.findParent('li')
        datediv.strong.extract()
        datetxt = datediv.renderContents( None ).strip()

    art['pubdate'] = ukmedia.ParseDateTime( datetxt )


    if headline in ( u'Cryptic crossword', u'Compact crossword' ):
        ukmedia.DBUG2( "IGNORE '%s' (%s)\n" % ( headline, art['srcurl']) );
        return None

    firstparadiv = artdiv.find( 'div', {'id':'ds-firstpara'} )
    desc = firstparadiv.renderContents( None )
    desc = ukmedia.FromHTMLOneLine( desc )
    art['description'] = desc

    byline = u''
    bylinediv = artdiv.find( 'div', {'id':'ds-byline'} )
    bylinetextdiv = artdiv.find( 'div', {'id':'ds-bylinetext'} )
    if bylinediv:
        byline = bylinediv.renderContents( None ).strip()
        if bylinetextdiv:
            extra = bylinetextdiv.renderContents(None).strip()
            if extra:
                byline = byline + u', ' + extra
        byline = ukmedia.FromHTML( byline )

    # for some sections, try and extract journo from first para
#   if not byline and '/opinion/' in art['srcurl']:
#       byline = ukmedia.ExtractAuthorFromParagraph( ukmedia.DecapNames(desc) )

    # Should we even store press association articles?
    if byline == u'' and source.lower() == 'press association':
        art['byline'] = u'PA'

    art['byline'] = byline


    #comments
    art['commentlinks'] = []
    commentli = soup.find('li', {'class':'commentlink'} )
    if commentli:
        a = commentli.a
        comment_url = urlparse.urljoin( art['srcurl'], a['href'] )
        txt = a.renderContents(None)
        num_comments = None
        if u'Be the first to comment on this article' in txt:
            num_comments = 0;
        else:
            cnt_pat = re.compile( r"(\d+) comments on this article" )
            m = cnt_pat.search(txt)
            num_comments = int( m.group(1) )

        art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )

    bodydiv = artdiv.find( 'div', {'id':'va-bodytext'} )

    for cruft in bodydiv.findAll( 'div', {'class':'MPUTitleWrapperClass'}):
        # kill adverts
        cruft.extract()
    for cruft in bodydiv.findAll( 'div', {'id':'ds-mpu'}):
        cruft.extract()
    for cruft in bodydiv.findAll( 'div', {'id':'va-inlinerightwrap'}):
        cruft.extract()
    for cruft in bodydiv.findAll( 'div', {'id':'ds-mpu'}):
        cruft.extract()
    for cruft in bodydiv.findAll(text=lambda text:isinstance(text, Comment)):
        cruft.extract()



    content = firstparadiv.renderContents(None)
    content = content + bodydiv.renderContents(None)
    content = content.replace( "<br />", "<br />\n" )
    art['content'] = content



    # images (handled with javascript. why oh why do they all use javascript for images?)
    art['images'] = []
    gallery_pat = re.compile( r"var\s+strData\s+=\s+\"(.*?)\"\s*;" )
    img_pat = re.compile( r"\d+[|](http://.*?)[|](.*?[.]jpg)[|](.*)" )
    for galmatch in gallery_pat.finditer( html ):
        bits = galmatch.group(1).split( '[|]' )
        for b in bits:
            if b.strip() == '':
                continue
            m = img_pat.match( b )
            url = m.group(1) + m.group(2)

            caption = unicode( m.group(3).decode('string_escape'), soup.originalEncoding )
            credit = u''
            # a couple of patterns to split out image copyright
            credit_pats = (
                re.compile( r'(.*?)\s*(?:picture|photo|photograph)s?:\s+(.*)\s*$',re.IGNORECASE ),
                re.compile( r'(.*?)\s*(?:[pP]icture|[pP]hoto|[pP]hotograph)s?\s+([A-Z]+)\s*$' )
                )
            for p in credit_pats:
                m = p.search( caption )
                if m:
                    caption = m.group(1)
                    credit = m.group(2)
                    break
            art['images'].append( {'url':url, 'caption':caption, 'credit':credit } )

    return art


# pattern to extract unique id from urls
# eg "http://thescotsman.scotsman.com/latestnews/SNP-threatens-to-tax-supermarkets.3766548.jp"
idpat = re.compile( "/([^/]+[.][0-9]+[.]jp)" )

def CalcSrcID( url ):
    """ extract unique id from url """

    o = urlparse.urlparse( url )
    # thescotsman.scotsman.com, scotlandonsunday.scotsman.com
    if not o[1].endswith( 'scotsman.com' ):
        return None

    m = idpat.search( o[2] )
    if m:
        return 'scotsman_' + m.group(1)

    return None


def ScrubFunc( context, entry ):
    context['srcid'] = CalcSrcID( context['srcurl'] )

    # ignore pa items
    if '/pa-entertainment-news/' in context['srcurl']:
        return None
    return context




def FindRSSFeeds( feed_page ):
    """ scrape a list of RSS feeds """

    feeds = []
    html = ukmedia.FetchURL( feed_page )
    soup = BeautifulSoup( html )

    blacklist = ( re.compile( 'Latest.*- National' ),  # PA articles
        re.compile( "Reader Offers - National" ),
        re.compile( "Letters - Scotland" ),
        re.compile( "Reader Offers - National" ),
        re.compile( "Gaelic - Scotland" ),
        re.compile( "Cartoon - Scotland" ) )

    for a in soup.findAll( 'a', {'href':re.compile('getFeed[.]aspx[?]Format=rss')} ):
        feed_url = urlparse.urljoin( feed_page, a['href'] )
        feed_name = a.renderContents( None )
        accept = 1
        for b in blacklist:
            if b.match( feed_name ):
                accept = 0
                continue
        if accept:
            feeds.append( (feed_name,feed_url) )

    ukmedia.DBUG2( "scanned '%s', found %d feeds\n" %(feed_page,len(feeds)) )
    return feeds



def FindArticles():
    """ get a set of articles to scrape from the rss feeds """

    feeds = FindRSSFeeds( "http://www.scotsman.com/webfeeds.aspx?format=rss" )
    feeds = feeds + FindRSSFeeds( "http://scotlandonsunday.scotsman.com/webfeeds.aspx?format=rss" )

    found = ScraperUtils.FindArticlesFromRSS( feeds, u'scotsman', ScrubFunc )
    return found


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    o = urlparse.urlparse( url )
    if o[1] == 'scotlandonsunday.scotsman.com':
        context['srcorgname'] = u'scotlandonsunday'
    else:
        context['srcorgname'] = u'scotsman'

    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract )

