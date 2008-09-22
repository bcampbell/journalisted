#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the independent
#
# NOTES:
#
# They changed over to a new system around the end of 2007/beginning of
# 2008.
# Old format urls look like:
#   http://news.independent.co.uk/world/middle_east/article2790961.ece
# New ones look like:
#   http://www.independent.co.uk/news/uk/home-news/harry-set-to-be-pulled-out-of-afghanistan-789513.html
#
# Unfortunately it doesn't look like they redirect the old format ones to
# the new format, so they've broken a lot of our permalinks :-(
#
# For blogs, hostnames indyblogs.typepad.com and blogs.independent.co.uk
# are interchangable.
#
# TODO:
#  - migrate to new RSS feeds (at last check their rss index still had
#    the old ones only (which still work fine)
#


import getopt
import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia, ScraperUtils



# things that this scraper might mistakenly use as a byline:
dudbylines = [ u'leading article', u'leadinga article', u'the third leader' ]




def FindRSSFeeds():
    """ fetch a list of RSS feeds for the indy.

    returns a list of (name, url) tuples, one for each feed
    """

    rsspage = "http://www.independent.co.uk/service/list-of-rss-feeds-775086.html"

    ukmedia.DBUG2( "Fetching list of rss feeds\n" );

    html = ukmedia.FetchURL( rsspage )
    soup = BeautifulSoup(html)

    feeds = []
    bodydiv = soup.find( 'div', {'class':'body'} )
    for a in bodydiv.findAll( 'a', {'href':re.compile( '/rss$' ) } ):
        url = a['href']
        title = ukmedia.FromHTMLOneLine( a.renderContents( None ) )

        skip = False
#        for banned in url_blacklist:
#            if banned in url:
#                ukmedia.DBUG2( " ignore feed '%s' [%s]\n" % (title,url) )
#                skip = True

#        print "%s: %s" %(title,url)
        if not skip:
            feeds.append( (title,url) )

    ukmedia.DBUG2( "found %d rss feeds to fetch\n" % ( len(feeds) ) );
    return feeds



# new-format url (the number is the important bit - the text you
# can fiddle with and still get the same article :-)
# http://www.independent.co.uk/news/uk/home-news/harry-set-to-be-pulled-out-of-afghanistan-789513.html
srcidpat_newformat = re.compile( '/[^/]+-(\d+)[.]html$' )

# old-format url
# http://news.independent.co.uk/world/middle_east/article2790961.ece
srcidpat_oldformat = re.compile( '/(article\d+[.]ece)$' )

# http://indyblogs.typepad.com/independent/2007/11/terrorism-whos-.html
# http://blogs.independent.co.uk/independent/2007/12/the-fife-diet.html

def CalcSrcID( url ):
    """ Calculate a unique srcid from a url """


    o = urlparse.urlparse( url )
    # we don't handle blogs here (see blogs.py instead).
    if o[1] in ( 'indyblogs.typepad.com', 'blogs.independent.co.uk' ):
        return None

    if not o[1].endswith( ".independent.co.uk" ):
        return None

    m = srcidpat_newformat.search( o[2] )
    if m:
        return 'independent_' + m.group(1)

    # probably never encounter the old format urls (they seem to
    # have been turned off now)... but just in case:
    m = srcidpat_oldformat.search( o[2] )
    if m:
        return 'independent_' + m.group(1)

    return None


def Extract( html, context ):
    """Extract article from html"""

    art = context
    soup = BeautifulSoup( html )

    articlediv = soup.find( 'div', { 'id':'article' } )

    # the headline
    headline = articlediv.find( 'h1' )
    art['title'] = ukmedia.FromHTML( headline.renderContents(None) )

    # some articles have taglines
    taglinepara = articlediv.find('p',{'class':'tagline'})

    # "info" para contains byline, date
    infopara = articlediv.find( 'p', {'class':'info'} )

    # date fmt: "Thursday, 24 January 2008"
    pubdatetext = infopara.em.renderContents(None)
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetext )

    # a couple of ways to get byline...
    byline = u''
    authorelement = infopara.find('author')
    if authorelement:
        # it's got a _proper_ byline!
        byline = authorelement.renderContents(None)

    # Big names have their own sections which makes bylining them easy
    if not byline:
        try:
            as = soup.find('div', id='breadcrumbs').findAll('a')
            if as[-2].string in ('Commentators', 'Columnists'):
                byline = as[-1].string
        except (IndexError, AttributeError):
            pass
    
    if byline == u'' and taglinepara:
        # if there's a tagline, try the byline-o-matic on it:
        byline = ukmedia.ExtractAuthorFromParagraph( taglinepara.renderContents(None) )

    if byline == u'':
        # a lot of stories (particularly comment pieces) have
        # name in title...
        # eg "Janet Street-Porter: Our politicians know nothing of real life"
        m = re.match( "([\\w\\-']+\\s+[\\w\\-']+(\\s+[\\w\\-']+)?\\s*):", art['title'], re.UNICODE )
        if m:
            byline = m.group(1)
            # cull out duds
            if byline.lower() in dudbylines:
                byline = u''


    art['byline'] = ukmedia.FromHTML( byline )

    # look for images
    art['images'] = []
    for imgdiv in articlediv.findAll( 'div', {'class': 'photoCaption'} ):
        img = imgdiv.img
        img_url = img['src']
        img_caption = img['alt']
        img_credit = u''
        p = imgdiv.find( 'p', {'class': 'caption'} )
        if p:
            img_caption = p.renderContents(None)
        p = imgdiv.find( 'p', {'class': 'credits'} )
        if p:
            img_credit = p.renderContents(None)
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )

    # article text is in "body" div
    bodydiv = articlediv.find( 'div',{'class':'body'} )

    # Kill cruft:

    #"<a href="http://indyblogs.typepad.com/openhouse/have_your_say/index.html" target="new"> Click here to have your say</a>"
    for cruft in bodydiv.findAll( 'a', {'href':'http://indyblogs.typepad.com/openhouse/have_your_say/index.html'} ):
        cruft.extract()

    #"<a id="proximic_proxit:aid=inm&query_url=http://www.independent.co.uk/news/world/middle-east/freedom-for-gaza-but-for-one-day-only-773189.html" title="Click here to explore further" onclick="return false;">Interesting? Click here to explore further</a>"
    for cruft in bodydiv.findAll( 'a', {'title':'Click here to explore further'} ):
        cruft.extract()


    contenttext = bodydiv.renderContents(None)
    contenttext = ukmedia.SanitiseHTML( contenttext )
    contenttext = contenttext.strip()

    art['content'] = contenttext

    # description from tagline
    if taglinepara:
        art['description'] = ukmedia.FromHTML( taglinepara.renderContents(None) )
    else:
        # use first para of main text
        art['description'] = ukmedia.FromHTML( ukmedia.FirstPara( contenttext ) )

    return art





def OLDExtract( html, context ):
    """parser for old (pre 24jan2008) format articles, just in case we need it..."""

    art = context

    soup = BeautifulSoup( html )

    # if we don't have a description, try the deckheader meta tag...
    # <meta name="icx_deckheader" content="Stars of reality TV &ndash; and now the culprits blamed for spreading disease" />
    if not 'description' in art:
        art['description'] = u''
    if not art['description'].strip():
        deckheader = soup.find( 'meta', {'name':'icx_deckheader'} )
        if deckheader:
            art['description'] = ukmedia.FromHTML( deckheader['content'] )
            #print "DECKHEADER: '%s'" % (art['description'])

    articlediv = soup.find( 'div', { 'class':'article' } )

    headline = articlediv.find( 'h1' )
    for cruft in headline.findAll( 'span' ):
        cruft.extract()
    art[ 'title' ] = headline.renderContents(None).strip()
    art[ 'title' ] = ukmedia.FromHTML( art['title'] )

    bylinepart = articlediv.find( 'h3' )
    if bylinepart:
        byline = bylinepart.renderContents(None).strip()
    else:
        byline = u''

    # for comment pages - if byline is empty, try and get it from title
    author_maybe_in_headline = 0
    comment_section_prefixes = [
        'http://comment.independent.co.uk', 
        'http://news.independent.co.uk/fisk',   # special case for Robert Fisk
        'http://sport.independent.co.uk/football/comment',
        'http://www.independent.co.uk/living/motoring/comment',
        ]

    for prefix in comment_section_prefixes:
        if art['srcurl'].startswith( prefix ):
            author_maybe_in_headline = 1

    if byline == u'' and author_maybe_in_headline:
        # eg "Janet Street-Porter: Our politicians know nothing of real life"
        m = re.match( "([\\w\\-']+\\s+[\\w\\-']+(\\s+[\\w\\-']+)?\\s*):", art['title'], re.UNICODE )
        if m:
            byline = m.group(1)
            # cull out duds
            if byline.lower() in dudbylines:
                byline = u''


    art[ 'byline' ] = ukmedia.FromHTML( byline )

    pubdate = articlediv.find( 'h4' )
    art[ 'pubdate' ] = CrackDate( pubdate.renderContents(None) )

    body = articlediv.find( 'div', id='bodyCopyContent' )

    # remove the "Interesting? Click here to explore further" link
    cruft = body.find( 'a', id=re.compile("^proximic_proxit") )
    if cruft:
        cruft.extract()

    art['content'] = body.renderContents( None )
    art['content'] = ukmedia.SanitiseHTML( art['content'] )

    # if we still don't have any description, use first para
    if art['description'] == u'':
        art['description'] = ukmedia.FromHTML( body.p.renderContents(None) )
        #print "FIRSTPARA: '%s'" %(art['description'])

    return art



# TODO: replace with ukmedia generic dateparser
def CrackDate( raw ):
    """ return datetime, or None if matching fails
    
    example date string: 'Published:&nbsp;01 September 2006'
    """

    datepat = re.compile( '([0-9]{2})\s+(\w+)\s+([0-9]{4})' )
    m = datepat.search( raw )
    if not m:
        return None
    day = int( m.group(1) )
    month = ukmedia.MonthNumber( m.group(2) )
    year = int( m.group(3) )

    return datetime( year,month,day )


#def TidyURL( url ):
#    """ strip off cruft from URLs """
#    url = re.sub( "[?]r=RSS", "", url )
#    return url


def ScrubFunc( context, entry ):
    """ description contains html entities and tags...  scrub it! """
    context[ 'description' ] = ukmedia.FromHTML( context['description'] )

    url = context['srcurl']
#    url = TidyURL( context['srcurl'] )
    if 'rss.feedsportal.com' in url:
        # luckily, the guid (marked as non-permalink) has the real url
        url = entry.guid

    context['srcid'] = CalcSrcID( url )
    context['srcurl'] = url
    context['permalink'] = url
    return context


def FindArticles():
    """get a set of articles to scrape from the rss feeds """

    rssfeeds = FindRSSFeeds()

    return ScraperUtils.FindArticlesFromRSS( rssfeeds, u'independent', ScrubFunc )


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
#    url = TidyURL( url )
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'independent'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, maxerrors=50 )

