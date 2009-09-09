#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for Mirror and Sunday Mirror
#
# TODO: scrape list of blog rss feeds instead of using hardcoded table

import re
from datetime import datetime
import time
import string
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia, ScraperUtils



def FindRSSFeeds():
    """ fetch a list of RSS feeds for the mirror.

    returns a list of (name, url) tuples, one for each feed
    """

    # miriam blacklisted for now, as her column is redirected to blogs (and picked up by our blog rss list)
    url_blacklist = ( '/fun-games/', '/pictures/', '/video/', '/miriam/', '/celebs/topics/',
        '/news/topics/', '/tv-entertainment/topics/' )

    ukmedia.DBUG2( "Fetching list of rss feeds\n" );

    sitemap_url = 'http://www.mirror.co.uk/sitemap/'
    html = ukmedia.FetchURL( sitemap_url )
    soup = BeautifulSoup(html)

    feeds = []
    for a in soup.findAll( 'a', {'class':'sitemap-rss' } ):
        url = a['href']
#        a2 = a.findNextSibling( 'a' )
#        if a2:
#            title = a2.renderContents( None )
#        else:
        m = re.search( r'mirror.co.uk/(.*)/rss[.]xml', url )
        title = m.group(1)

        skip = False
        for banned in url_blacklist:
            if banned in url:
#                ukmedia.DBUG2( " ignore feed '%s' [%s]\n" % (title,url) )
                skip = True

        if not skip:
            feeds.append( (title,url) )

    ukmedia.DBUG2( "found %d feeds\n" % ( len(feeds) ) );
    return feeds


# feedburner blogs, see "http://www.mirror.co.uk/opinion/blogs/"
blog_rssfeeds = [
    ("blog: 3pm", "http://feeds.feedburner.com/mirror-3pm"),
    ("blog: Amber & friends", "http://feeds.feedburner.com/mirrorfashion"),
    ("blog: Big Brother'", "http://feeds.feedburner.com/big-brother/" ),
    ("blog: Christopher Hitchens", 'http://feeds2.feedburner.com/christopher-hitchens-blog'),
    ("blog: Cricket", "http://feeds.feedburner.com/mirror/cricket"),
    ("blog: Dear Miriam", "http://feeds.feedburner.com/dear-miriam"),
    ("blog: Football Spy", "http://feeds.feedburner.com/FootballSpy" ),
    ("blog: Kevin Maguire & Friends","http://feeds.feedburner.com/KevinMaguire" ),
    # tv blog handled by main site
#    ("blog: Kevin O'Sullivan", '' ),
    ("Mirror Investigates","http://feeds.feedburner.com/mirror/investigations" ),
    # science one is also borked...
#    ("Science, Health and the Environment", "http://feeds.feedburner.com/investigations" ),
    # jim shelly handled by main site
    #("Shelleyvision", "" ),
    ("Showbiz with Zoe", "http://feeds.feedburner.com/showbiz-with-zoe" ),
    # Sue Carroll handled by main site
#    ("Sue Carroll", "" ),
    ("The Sex Doctor", "http://feeds.feedburner.com/sex-doctor/" ),
]












def Extract( html, context ):
    url = context['srcurl']
    
    if re.search( r'/(blogs|fashion)[.]mirror[.]co[.]uk/', url ):
        return Extract_Blog( html, context )
    else:
        return Extract_MainSite( html, context )


def Extract_MainSite( html, context ):
    art = context
    soup = BeautifulSoup( html )


    if '/sunday-mirror/' in art['srcurl']:
        art['srcorgname'] = u'sundaymirror'
    else:
        art['srcorgname'] = u'mirror'

    maindiv = soup.find( 'div', { 'id': 'three-col' } )
    if not maindiv:
        if "<p>You are viewing:</p>" in html:
            ukmedia.DBUG2("IGNORE gallery page '%s' [%s]\n" % (art['title'],art['srcurl']) )
            return None
        



    h1 = maindiv.h1

    title = h1.renderContents(None)
    title = ukmedia.FromHTMLOneLine( title )
    art['title'] = title

    # eg "By Jeremy Armstrong 24/07/2008"
    bylinepara = maindiv.find( 'p', {'class': 'article-date' } )
    bylinetxt = bylinepara.renderContents( None )
    bylinetxt = ukmedia.FromHTMLOneLine( bylinetxt )
    bylinepat = re.compile( r'\s*(.*?)\s*(\d{1,2}/\d{1,2}/\d{4})\s*' )
    m = bylinepat.match( bylinetxt )
    art['byline'] = m.group(1)
    art['pubdate'] = ukmedia.ParseDateTime( m.group(2) )

    # sometimes, only sundaymirror.co.uk in byline is only indicator
    if u'sundaymirror' in art['byline'].lower():
        art['srcorgname'] = u'sundaymirror'

    # look for images
    art['images'] = []
    caption_pat = re.compile( ur"\s*(.*?)\s*[(]\s*(?:pic\s*:|pics\s*:)?\s*(.*)[)]\s*$", re.UNICODE|re.IGNORECASE )

    # pick out gallery images first
    galimages = []
    for galdiv in maindiv.findAll( 'div', {'class': 'galleryembed' } ):
        for picdiv in galdiv.findAll( 'div', {'id': re.compile(r'gallery_\d+_pic_\d+') } ):
            img = picdiv.img
            img_url = img['src']
            caption = img['alt']
            credit = u''
            m = caption_pat.match(caption)
            if m:
                caption = m.group(1)
                credit = m.group(2)
            # use a proper caption if there is one
            p = picdiv.find('p', {'class':'gallery-caption'})
            if p:
                caption = ukmedia.FromHTMLOneLine( p.renderContents(None) )

            galimages.append( {'url':img_url, 'caption':caption, 'credit':credit } )
        galdiv.extract()

    # now get any non-gallery images
    for imgdiv in maindiv.findAll( 'div', {'class': re.compile('article-image|art-o')} ):
        img = imgdiv.img
        if not img:
            continue
        # special exception to avoid star rating on review pages :-)
        if img['height'] == "15":
            continue
        img_url = img['src']
        p = imgdiv.find( 'p', {'class': 'article-date'} )
        t = img['alt']
        if p:
            t = p.renderContents(None)
            t = ukmedia.FromHTMLOneLine(t)
        m = caption_pat.match(t)
        cap = t
        cred = u''
        if m:
            cap = m.group(1)
            cred = m.group(2)

        art['images'].append( {'url':img_url, 'caption':cap, 'credit':cred } )

    # add the gallery images last (ordering probably will get lost at some point, but hey)
    art['images'].extend( galimages )



    # get the main content.
    # sometimes there is an <div id="article-body">, but not always

    contentdiv = maindiv.find( 'div', {'id':'article-body'} )
    if contentdiv:
        pass
    else:
        # use main div as the content...
        contentdiv = maindiv
        # ...trying to remove everything except for article text
        h1.extract()
        bylinepara.extract()

    # kill adverts, photos etc...
    for cruft in contentdiv.findAll( 'div' ):
        cruft.extract()
    # sometimes a misplaced "link" element!
    for cruft in contentdiv.findAll( 'link' ):
        cruft.extract()

    content = contentdiv.renderContents(None)

    art['content'] = content
    art['description'] = ukmedia.FirstPara( content )

    if art['description'].strip() == u'':
        # check for obvious reasons we might get empty content
        t = art['title'].lower()
#        if re.search( r'\bpix\b', t ):
#            ukmedia.DBUG2("IGNORE pix page '%s' [%s]\n" % (art['title'],art['srcurl']) )
#            return None
        if re.search( r'^video:', t ):
            ukmedia.DBUG2("IGNORE video page '%s' [%s]\n" % (art['title'],art['srcurl']) )
            return None
        if re.search( r'\bdummy story\b', t ) or re.search( r'\bholding story\b', t ):
            ukmedia.DBUG2("IGNORE dummy story '%s' [%s]\n" % (art['title'],art['srcurl']) )
            return None

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

def CalcSrcID( url ):
    """ Calculate a unique srcid from a url """
    o = urlparse.urlparse( url )

    # only want pages from mirror.co.uk or sundaymirror.co.uk
    # domains (includes blogs.mirror.co.uk)
    if not o[1].endswith( 'mirror.co.uk' ) and not o[1].endswith('sundaymirror.co.uk'):
        return None

    for pat in srcid_patterns:
        m = pat.search( url )
        if m:
            break
    if not m:
        return None

    return 'mirror_' + m.group(1)


def ScrubFunc( context, entry ):
    title = context['title']
    title = ukmedia.DescapeHTML( title )
    title = ukmedia.UncapsTitle( title )    # all mirror headlines are caps. sigh.
    context['title'] = title

    url = context['srcurl']
    o = urlparse.urlparse( url )

    if o[1] in ( 'feeds.feedburner.com', 'feedproxy.google.com' ):
        # Luckily, feedburner feeds have a special entry
        # which contains the original link
        url = entry.feedburner_origlink
#        o = urlparse.urlparse( url )

    if o[1] == 'rss.feedsportal.com':
        # Luckily the guid has proper link (marked as non-permalink)
        url = entry.guid

    # sanity check - make sure we've got a direct link
    if url.find( 'mirror.co.uk' ) == -1:
        raise Exception, "URL not from mirror.co.uk or sundaymirror.co.uk ('%s')" % (url)

    if '/video/' in url:
        ukmedia.DBUG2( "ignore video '%s' [%s]\n" % (title,url) )


    context[ 'srcid' ] = CalcSrcID( url )
    context[ 'srcurl' ] = url
    context[ 'permalink'] = url

    return context




def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context[ 'srcid' ] = CalcSrcID( url )
    # looks like sundaymirror.co.uk domainname has been deprecated
    if 'sundaymirror.co.uk' in url or '/sunday-mirror/' in url:
        context['srcorgname'] = u'sundaymirror'
    else:
        context['srcorgname'] = u'mirror'

    context['lastseen'] = datetime.now()
    return context



def FindArticles():
    feeds = FindRSSFeeds()          # scrape the list of feeds for the main site
    feeds = feeds + blog_rssfeeds   # add the blog feeds
    # feedsportal.com has _lots_ of HTTP Error 503:
    # "Feed is currently being prepared; try again real soon"
    # The muppets.
    # hence the large maxerrors
    found = ScraperUtils.FindArticlesFromRSS( feeds, u'mirror', ScrubFunc, maxerrors=100 )
    return found



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, maxerrors=50 )


