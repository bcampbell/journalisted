#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the independent
#
# NOTES:
#
# Indy runs eScenic CMS and has blogs at typepad.com
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

# ===========================================================================
# NOTE: sgmllib in python2.5 is buggy, and causes at least this scraper to
# bork.
# http://bugs.python.org/issue1651995
#
# Quick fix: patch /usr/lib/python2.5/sgmllib.py
# in  convert_charref(self, name), change:
#        if not 0 <= n <= 255:
# to:
#        if not 0 <= n <= 127:
#

import getopt
import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
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

    # ".body" div is better container, but beautiful soup doesn't seem to parse it as expected
#    bodydiv = soup.find( 'div', {'class':'body'} )
    foo = soup.find('div', {'id':'content'} )

    # two kinds of link "/rss" for main paper, ".xml" for blogs
    for a in foo.findAll( 'a', {'href':re.compile( '(/rss$)|([.]xml$)' ) } ):
        url = a['href']
        # the page has some borked urls...
        url = url.replace( "http://http://", "http://" )

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
        return 'independent_' + o[2]

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

    url = context['srcurl']


    o = urlparse.urlparse( url )
    # we don't handle blogs here (see blogs.py instead).
    if o[1] in ( 'indyblogs.typepad.com', 'blogs.independent.co.uk' ):
        return Extract_typepad( html, context )
    else:
        return Extract_eScenic( html, context )


def Extract_eScenic( html, context ):
    """Extract fn for main paper (eScenic CMS)"""

    art = context
    soup = BeautifulSoup( html )

    articlediv = soup.find( 'div', { 'id':'article' } )

    # the headline
    headline = articlediv.find( 'h1' )
    art['title'] = ukmedia.FromHTMLOneLine( headline.renderContents(None) )

    # some articles have taglines
    taglinepara = articlediv.find('p',{'class':'tagline'})

    # "info" para contains byline, date
    infopara = articlediv.find( 'p', {'class':'info'} )

    # date fmt: "Thursday, 24 January 2008"
    pubdatetext = infopara.em.renderContents(None)
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetext )

    # a couple of ways to get byline...
    byline = u''
    authorelement = articlediv.find('p',{'class':'author'})
    if authorelement:
        # it's got a _proper_ byline!
        byline = ukmedia.FromHTMLOneLine( authorelement.renderContents(None) )

    # Big names have their own sections which makes bylining them easy
    if not byline:
        try:
            foos = soup.find('div', id='breadcrumbs').findAll('a')
            if foos[-2].string in ('Commentators', 'Columnists'):
                byline = foos[-1].string
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
        img_caption = ukmedia.FromHTMLOneLine( img_caption )
        img_credit = ukmedia.FromHTMLOneLine( img_credit )
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )

    # article text is in "body" div

    # which, of course, doesn't parse properly...
#    bodydiv = articlediv.find( 'div',{'class':'body'} )
    m = re.compile( r'<div class="body">.*<!-- end body -->', re.DOTALL ).search( html )


    bodydiv = BeautifulSoup( m.group(0) ).find('div',{'class':'body'} )



    # Kill cruft:
    for cruft in bodydiv.findAll( 'script' ):
        cruft.extract()
    for cruft in bodydiv.findAll( 'div' ):
        cruft.extract()

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


def Extract_typepad( html, context ):
    """Extract fn for indy blogs (on typepad.com)"""
    art = context
    soup = BeautifulSoup( html )

    # the headline
    headlinediv = soup.find( 'h3', {'class':'entry-header'} )
    art['title'] = ukmedia.FromHTMLOneLine( headlinediv.renderContents(None) )

    # timestamp
    # some blogs have a little rdf block with iso timestamp, but don't some don't
#    m = re.compile( r'dc:date="(.*?)"' ).search( html )
#    d = m.group(1)
#    art['pubdate'] = dateutil.parser.parse( m.group(1) )

    # date and time in separate places. sigh.
    dateheader = soup.find( 'h2', {'class':'date-header'} )
    d = dateheader.renderContents(None)     # "Thursday, 05 June 2008"

    postfooter = soup.find( 'span', {'class':'post-footers'} )
    t = postfooter.renderContents( None )   # "Posted at 02:22 PM in "...
    m = re.compile( r"Posted at\s+(\d+:\d+\s+\w\w)\s+" ).search(t)
    d = d + u' ' + m.group(1)

    art['pubdate'] = ukmedia.ParseDateTime( d )

    # description, byline, content
    # byline (if present) is in first para
    byline = u''
    bodydiv = soup.find( 'div', {'class':'entry-body'} )
    bylinep = bodydiv.p
    if bylinep:
        firstpara = ukmedia.FromHTMLOneLine( bylinep.renderContents(None) )
        if firstpara.startswith( u"By") and len(firstpara.split()) <= 4:
            byline = firstpara
            bylinep.extract()
    
    if not byline:
        # try the RDF block (raw regex search in the html)
        creatorpat = re.compile( r'dc:creator="(.*?)"' )
        m = creatorpat.search( html )
        if m:
            byline = unicode( m.group(1) )

    content = bodydiv.renderContents(None)
    morediv = soup.find( 'div', {'class':'entry-more'} )
    if morediv:
        if art['title'].startswith( u'Cyberclinic:' ):
            cruft = morediv.find( 'span', {'style':'color: #cccc00;'})
            if cruft:
                if u'CONFUSED ABOUT TECHNOLOGY?' in cruft.renderContents(None):
                    cruft.extract()

        content = content + morediv.renderContents( None )
    content = ukmedia.SanitiseHTML( content )

    desc = ukmedia.FirstPara( content )

    art['byline'] = byline
    art['description'] = desc
    art['content'] = content

    return art




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


