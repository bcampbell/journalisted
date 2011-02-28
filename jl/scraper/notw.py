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
import cookielib
import urllib   # for urlencode
import urllib2
import urlparse
import traceback
import ConfigParser
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup,Tag,Comment
from JL import ukmedia, ScraperUtils

NOTW_CONFIG_FILE = '../conf/notw.ini'

# Storage for cookies we receive in this session
cookiejar = cookielib.LWPCookieJar()

def dump_cookies():
    print "----------------------------------------"
    print 'These are the cookies we have received so far :'
    for index, cookie in enumerate(cookiejar):
        print index, '  :  ', cookie
    print "----------------------------------------"

def Prep():
    """ perform a login """
    global cookiejar

    config = ConfigParser.ConfigParser()
    config.read( NOTW_CONFIG_FILE )
    username = config.defaults()[ 'username' ]
    password = config.defaults()[ 'password' ]

    ukmedia.DBUG2( "Logging in as %s\n" % (username,) )
    opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookiejar))
    urllib2.install_opener(opener)

    postdata = urllib.urlencode( {'loginEmail':username,'loginPassword':password } )

    req = urllib2.Request( "https://www.newsoftheworld.co.uk/iamreg/login.do", postdata );
    handle = urllib2.urlopen( req )
#    html = handle.read()

    # OK... should now be logged in
#    dump_cookies()



notw_bloggers = (
    u'Ian Kirby',
    u'Jamie Lyons',
    u'Fraser Nelson',
    u'Sophy Ridge' )

def ArticlesFromSoup( soup ):

    blacklist = ( '/celebgallery/', '/yourscore/', '/_video/' )

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

    all_articles = []
    html = ukmedia.FetchURL( 'http://www.newsoftheworld.co.uk/' )
    soup = BeautifulSoup( html )

    nav_main = soup.find('ul',{'id':'nav-main'} )

    for a in nav_main.findAll('a'):
        url = a['href']
        url = urlparse.urljoin( "http://www.newsoftheworld.co.uk", url )
        name = ukmedia.FromHTMLOneLine( a.renderContents(None) )

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

        found = ArticlesFromSoup( prim_soup )
        ukmedia.DBUG2( "scanned %s [%s], got %d articles\n" % (name,url,len(found)) )
        all_articles = all_articles + found

    # note: there'll be _loads_ of duplicate articles!
    # but ScrapeUtils can handle that.
    return all_articles






def Extract( html, context ):
    o = urlparse.urlparse( context['srcurl'] )
#    if o[1] == 'notw.typepad.com':
#        return Extract_typepad( html, context )
    if '<title>News Of The World - Page not found</title>' in html:
        raise Exception( "Should-be-a-404-error" )

    if o[1] == 'blogs.notw.co.uk':
        return Extract_blog( html, context )
    else:
        if 'id="container1"' in html:
            return Extract_notw_prepaywall( html, context )
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
    """extractor for post-paywall newsoftheworld.co.uk articles"""
    art = context

    art['srcorgname'] = u'notw'

    soup = BeautifulSoup( html )

    maindiv = soup.find( 'div', {'id': 'main'})

    h1 = maindiv.h1
    if h1 is None:
        h1 = maindiv.h2 # fabulous magazine only?
    art['title'] = ukmedia.FromHTMLOneLine( h1.renderContents(None) )

    byline_span = maindiv.find('span', {'class':'byline'} )
    if byline_span:
        art['byline'] = ukmedia.FromHTMLOneLine( byline_span.renderContents(None) )
    else:
        art['byline'] = u''

    date_div = maindiv.find('div', {'class':('byline-date','byline add-rule')} )
    if date_div is not None:
        art['pubdate'] = ukmedia.ParseDateTime( date_div.renderContents(None) )
    else:
        # no date... sigh...
        # there is some likely-looking javascript:
        pat = re.compile( r's[.]prop18="(\d{2})(\d{2})(\d{4})";' )
        m = pat.search( html )
        if m:
            art['pubdate'] = datetime( int(m.group(3)), int(m.group(2)), int(m.group(1)) ) 


    bod = maindiv.find( 'div', {'class':re.compile(r'\barticle-body\b' ) } )

    # remove cruft
    for cruft in bod.findAll( 'div', {'class':re.compile(r'\bpullquote-') } ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':'article-right-column'} ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'id':'rating'} ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class': re.compile( r'\b(vid-canvas)|(vid-section)\b' ) } ):
        cruft.extract()
    for cruft in bod.findAll( 'object' ):
        cruft.extract()

    # TODO: should extract image links
    for cruft in bod.findAll( 'span', {'class':re.compile(r'\binline-image')} ):
        cruft.extract()
    for cruft in bod.findAll( 'span', {'class': 'image-holder'} ):
        cruft.extract()

    art['content'] = ukmedia.SanitiseHTML( bod.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] )
    return art





def Extract_notw_prepaywall( html, context ):
    """extractor for pre-paywall newsoftheworld.co.uk articles"""
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
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=100, prep=Prep )

