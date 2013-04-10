#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#

import re
from datetime import datetime,timedelta
import sys
import os
import urlparse
import urllib   # for urlencode
import urllib2
import ConfigParser

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils

TIMESPLUS_CONFIG_FILE = '../conf/timesplus.ini'


# NOTES:
# 
# scraper for the paywall-enabled times and sundaytimes
#
# The domains involved are:
#
# thetimes.co.uk
#  - The Times
# sundaytimes.co.uk
#  - The Sunday Times
# timesplus.co.uk
#  - site used for account management
#  - the login system is here
# timesonline.co.uk
#  - the old Times/Sundaytimes site. Now redirects to thetimes.co.uk
#
# There are also mobile versions of everything.
#
# The login system is really convoluted (7 HTTP requests!)




def Prep():
    """ perform a login """

    return

    config = ConfigParser.ConfigParser()
    config.read( TIMESPLUS_CONFIG_FILE )
    timesplus_username = config.defaults()[ 'username' ]
    timesplus_password = config.defaults()[ 'password' ]

    # the times paywall login is really convoluted...
    # starting with an empty cookiejar, it takes three POSTs to the login URL
    # to gather up all the cookies needed. And each one does some 302 redirects,
    # so you end up doing about 7 HTTP requests in all before you can start
    # fetching stories. Ugh.
    ukmedia.DBUG2( "Logging in as %s\n" % (timesplus_username) )
    postdata = urllib.urlencode( {'userName':timesplus_username,'password':timesplus_password, 'keepMeLoggedIn':'false' } )

    req = urllib2.Request( "https://www.timesplus.co.uk/iam/app/barrier?execution=e2s1&_eventId=loginEvent", postdata );
    handle = urllib2.urlopen( req )
#    html = handle.read()

    #dump_cookies()

    req = urllib2.Request( "https://www.timesplus.co.uk/iam/app/barrier?execution=e2s1&_eventId=loginEvent", postdata );
    handle = urllib2.urlopen( req )
#    html = handle.read()

    #dump_cookies()
    #print "Three"

    req = urllib2.Request( "https://www.timesplus.co.uk/iam/app/barrier?execution=e2s1&_eventId=loginEvent", postdata );
    handle = urllib2.urlopen( req )
#    html = handle.read()


    # OK... should now be logged in
#    dump_cookies()


# lots of links on the page which we don't want, so we'll
# look for sections with links we _do_ want...
sectionnames = ('News',
        'incomingFeeds',
        'Comment',
        'Business',
        'Sport',
        'Life & Style',
        'Arts & Entertainment',
        )

siteroot = "http://timesonline.co.uk"


def CleanHtml(html):
    '''
    Replaces incorrect Windows-1252 entities with the correct HTML entity.
    They were probably inserted by Microsoft Word.
    '''
    # e.g. http://www.timesonline.co.uk/tol/comment/columnists/david_aaronovitch/article587744.ece
    #
    # Referring to <http://en.wikipedia.org/wiki/Windows-1252>.
    #
    def repl(m):
        s = m.group(1) or m.group(2)
        if s.startswith('x'): n = int(s[1:], 16)
        else: n = int(s)
        if 128 <= n <= 159:  # windows-1252 chars not found in iso 8859-1
            return '&#%d;' % ord(chr(n).decode('windows-1252'))
        return m.group()
    return re.sub(r'(?i)&#(1\d\d);|&#(x0*[0-9a-f]{2});', repl, html)




puzzle_blacklist = (
    re.compile( r'^Chess:' ),
    re.compile( r'^Bridge:' ),
    re.compile( r'^Codeword solution:'),
    re.compile( r'^Codeword:'),
    re.compile( r'^Killer Sudoku'),
    re.compile( r'^Sudoku \d+'),
    re.compile( r'^Sudoku solutions'),
    re.compile( r'^The Workout'),
    re.compile( r'^Word watching:'),
    re.compile( r'^Word watching answers:', re.IGNORECASE),
    re.compile( r'^KenKen'),
    re.compile( r'^Polygon:'),
)




def FindArticles():

    arts = []
    arts = arts + FindTimesArticles()
    arts = arts + FindSundayTimesArticles()
    return arts



def FindTimesArticles():
    """ fetch list of articles by crawling for links using the sitemap"""

    # use full a-z sitemap, as otherwise lots of stuff missing
    sitemap_url = "http://www.thetimes.co.uk/tto/public/?view=sitemap&layout=atoz"


    html = ukmedia.FetchURL( sitemap_url )
    soup = BeautifulSoup.BeautifulSoup( html )

    pages = []

    sitemap = soup.find( 'div', {'id':'sitemap-body'} )
    for a in sitemap.findAll( 'a' ):
        name = a.string
        url = urlparse.urljoin( sitemap_url, a.get('href') )

        if url is not None:
            o = urlparse.urlparse( url )
            if o[1] in ('www.thetimes.co.uk', 'thetimes.co.uk', 'www.timesonline.co.uk', 'timesonline.co.uk' ):
                pages.append( url )
            elif o[1].endswith( '.typepad.com' ):
                pages.append( url )


    article_urls = set()
    for page_url in pages:
        article_urls.update( ReapArticles( page_url ) )

    foundarticles =[]
    for url in article_urls:
        context = ContextFromURL( url )
        if context is not None:
            foundarticles.append( context )

    ukmedia.DBUG2( "Found %d times articles\n" % ( len(foundarticles) ) )
    return foundarticles




def FindSundayTimesArticles():
    """ fetch list of articles by crawling for links using the sitemap"""

    sitemap_url = "http://www.thesundaytimes.co.uk/sto/public/Sitemap/?view=sitemap"
    html = ukmedia.FetchURL( sitemap_url )
    soup = BeautifulSoup.BeautifulSoup( html )

    pages = []

    sitemap = soup.find('div',{'id':'sitemap'})
    for a in sitemap.findAll('a'):
        name = a.string
        url = a.get('href')
        if a:
            url = urlparse.urljoin( sitemap_url, url )
            o = urlparse.urlparse( url )
            if o[1] in ('www.thesundaytimes.co.uk', 'thesundaytimes.co.uk', 'www.timesonline.co.uk', 'timesonline.co.uk' ):
                pages.append( url )


    article_urls = set()
    for page_url in pages:
        article_urls.update( ReapArticles( page_url ) )

    foundarticles =[]
    for url in article_urls:
        context = ContextFromURL( url )
        if context is not None:
            foundarticles.append( context )

    ukmedia.DBUG2( "Found %d sundaytimes articles\n" % ( len(foundarticles) ) )
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

    soup = BeautifulSoup.BeautifulSoup( html )


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



# http://www.timesonline.co.uk/tol/news/politics/article3471714.ece
#http://www.thesundaytimes.co.uk/sto/travel/Destinations/article328119.ece
srcidpat_ece = re.compile( '/(article[0-9]+\.ece)$' )

def CalcSrcID( url ):
    """ work out a unique srcid for this url """

    o = urlparse.urlparse( url )

    # sigh... blogs scattered over a bunch of typepad.com domains.
    domain = re.sub( '^www.', '', o[1] )
    blogdomains = (
        'timesbusiness.typepad.com',
        'timescolumns.typepad.com',
        'timescorrespondents.typepad.com',
        'timesnews.typepad.com',
        'timesonline.typepad.com' )

    if o[2].endswith( '/archives.html' ):
        return None

    if domain in blogdomains:
        if o[2].endswith( '/index.html' ):
            return None
        if o[2].endswith('.html'):
            return 'times_' + o[2]
        return None

    # main paper?

    if '/News_multimedia/' in url:
        return None # skip videos

    if o[1].startswith('feeds.' ):
        return None  # got wrong url from rss feed!

    m = srcidpat_ece.search( o[2] )
    if m is None:
        return None

    paper_domains = ( 'thetimes.co.uk','www.thetimes.co.uk',
        'sundaytimes.co.uk', 'www.sundaytimes.co.uk',
        'timesonline.co.uk', 'www.timesonline.co.uk' )
    if o[1] in ( 'thetimes.co.uk','www.thetimes.co.uk' ):
        return 'thetimes.co.uk_' + m.group(1)

    if o[1] in ( 'thesundaytimes.co.uk', 'www.thesundaytimes.co.uk' ):
        return 'thesundaytimes.co.uk_' + m.group(1)

    if o[1] in ('timesonline.co.uk', 'www.timesonline.co.uk' ):
        if 'timesonline.co.uk/tol/feeds/rss/' in url:
            return None
        return 'times_' + m.group(1)

    return None


def Extract(html, context, **kw):
    o = urlparse.urlparse( context['srcurl'] )
    if o[1].endswith( 'typepad.com' ):
        return Extract_typepad( html, context )
    if o[1] in ('www.timesonline.co.uk', 'timesonline.co.uk'):
        return Extract_ece_timesonline( html,context )
    if o[1] in ('www.thetimes.co.uk', 'thetimes.co.uk'):
        return Extract_ece_thetimes( html,context )
    if o[1] in ('www.thesundaytimes.co.uk', 'thesundaytimes.co.uk'):
        return Extract_ece_thesundaytimes( html,context )





def Extract_ece_thetimes( html, context ):
    """ article extractor for thetimes.co.uk """
    art = context
    art['srcorgname'] = u'times';

    soup = BeautifulSoup.BeautifulSoup( html )
    if soup.find( 'div', {'id':'login-popup'} ):
        raise Exception, "Not logged in"

#    open( "/tmp/wibble.html",'w' ).write(html)
#    sys.exit(0)

    # get headline and pubdate from meta tags
    dashboard_header = soup.head.find( 'meta', {'name':'dashboard_header'} )
    art['title'] = ukmedia.FromHTMLOneLine( dashboard_header[ 'content' ] )

    dashboard_updated_date = soup.head.find( 'meta', {'name':'dashboard_updated_date'} )
    dashboard_published_date = soup.head.find( 'meta', {'name':'dashboard_published_date'} )
    art['pubdate'] = ukmedia.ParseDateTime( dashboard_published_date['content'] )

    tab1_div = soup.find( 'div', {'id':'tab-1'} )


    #byline_timestamp_div = tab1_div.find( 'div', {'class':'byline-timestamp' } )
    authors = []
    for strong in soup.findAll( 'strong', {'class':'f-author' } ):
        authors.append( ukmedia.FromHTMLOneLine(strong.renderContents(None)) )
 
    art['byline'] = u', '.join( authors )
    # TODO - should also grab title and location

    bodycopy = tab1_div.find('div',{'id':'bodycopy'} )
    art['content'] = ukmedia.SanitiseHTML( bodycopy.renderContents( None ) )
    art['description'] = ukmedia.FirstPara( art['content'] )

    if art['content'].strip() == u'':
        ukmedia.DBUG2( "SKIP contentless article '%s' (%s)\n" % (art['title'],art['srcurl']) )
        return None

    # images
    art['images'] = []
    slideshow_div = tab1_div.find( 'div',{'class':re.compile(r'\btto-slideshow\b')} )
    if slideshow_div:
        for slide in slideshow_div.findAll( 'li', {'class':re.compile(r'\btto-slide\b')} ):
            img = slide.img
            caption = slide.find( 'span', {'class':'f-caption'} )
            credit = slide.find( 'span', {'class':'f-credit'} )

            image = {}
            image[ 'url' ] = urlparse.urljoin( art['srcurl'], img.get('src') )
            if caption:
                image['caption'] = ukmedia.FromHTMLOneLine( caption.renderContents(None) )
            else:
                image['caption'] = u''
            if credit:
                image['credit'] = ukmedia.FromHTMLOneLine( credit.renderContents(None) )
            else:
                image['credit'] = u''
            art['images'].append( image )

    # check for single-image articles
    single_media = tab1_div.find( 'div', {'class': 'media single-media'} )
    if single_media:
        img = single_media.img

        # caption/credit is elsewhere on page...
        utilities_head_div = tab1_div.find( 'div', {'class':'utilities-head'} )
        caption = utilities_head_div.find( 'div', {'class':'f-caption'} )
        credit = utilities_head_div.find( 'div', {'class':'f-credit'} )
        
        image = {}

        image['url'] = urlparse.urljoin( art['srcurl'], img.get('src') )
        if caption:
            image['caption'] = ukmedia.FromHTMLOneLine( caption.renderContents(None) )
        else:
            image['caption'] = u''
        if credit:
            image['credit'] = ukmedia.FromHTMLOneLine( credit.renderContents(None) )
        else:
            image['credit'] = u''
        art['images'].append( image )

    return art



def Extract_ece_thesundaytimes( html, context ):
    """ article extractor for thesundaytimes.co.uk """
    art = context
    art['srcorgname'] = u'sundaytimes';

    soup = BeautifulSoup.BeautifulSoup( html )
    if soup.find( 'div', {'id':'login-popup'} ):
        raise Exception, "Not logged in"


    # get headline and pubdate from meta tags
    dashboard_header = soup.head.find( 'meta', {'name':'dashboard_header'} )
    art['title'] = ukmedia.FromHTMLOneLine( dashboard_header[ 'content' ] )

    dashboard_updated_date = soup.head.find( 'meta', {'name':'dashboard_updated_date'} )
    dashboard_published_date = soup.head.find( 'meta', {'name':'dashboard_published_date'} )
    art['pubdate'] = ukmedia.ParseDateTime( dashboard_published_date['content'] )

    interactive_article_div = soup.find( 'div', {'id':'interactive-article'} )
    if interactive_article_div is not None:
        ukmedia.DBUG2( "SKIP interactive-article (%s)\n" % (art['srcurl']) )
        return None

    content_div = soup.find('div',{'class':'standard-content'} )
    # get byline
    authors = []

    author_comments_div = content_div.find( 'div',{'class':['author-comments', 'standard-summary', 'standard-summary-full-width']} )
    if author_comments_div:
        for span in author_comments_div.findAll( 'span',{'class':'author-name'} ):
            authors.append( ukmedia.FromHTMLOneLine( span.renderContents(0) ) )
        author_comments_div.extract()

    art['byline'] = u', '.join( authors )

    # special case to handle non-bylined columnists
    if art['byline'].lower() == u'the sunday times' and '/columns/' in art['srcurl']:
        m = re.compile(r'^\s*(\w+\s+\w+)\s*:\s*(.*)').match(art['title'])
        if m:
            columnist = m.group(1)
            slugpart = '/columns/'+''.join(columnist.split()).lower()
            if slugpart in art['srcurl']:
                art['byline'] = columnist
                art['title'] = m.group(2)




    # process and remove photos from content
    art['images'] = []
    for image_span in content_div.findAll('span',{'class':re.compile( r'\bmulti-position-img-left\b' )}):
        img = {}
        img['url'] = urlparse.urljoin( art['srcurl'],image_span.img['src'] )
        t = image_span.find('span',{'class':re.compile(r'\bmulti-position-photo-text\b')}).renderContents(None)
        t=t.strip()
        m = re.compile(r'(.*)\s*([(](.*?)[)])$').search(t)
        if m:
            img['caption'] = m.group(1)
            img['credit'] = m.group(3)
        else:
            img['caption'] = t
            img['credit']= u''
        art['images'].append(img)
        image_span.extract() 

    # trim out non-content bits
    content_div.h2.extract()
    for cruft in content_div.findAll( 'p',{'class':re.compile(r'hideinprint')}):
        cruft.extract()
    for cruft in content_div.findAll( 'div',{'class':re.compile(r'\btools_border\b')}):
        cruft.extract()
    for cruft in content_div.findAll( 'div',{'class':re.compile(r'\btools\b')}):
        cruft.extract()
    art['content'] = ukmedia.SanitiseHTML( content_div.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] )
    return art



def Extract_ece_timesonline( html, context ):
    """ for the pre-paywall timesonline.co.uk (Escenic CMS)"""
    art = context
    html = CleanHtml(html)
    soup = BeautifulSoup.BeautifulSoup( html )

    bodydiv = soup.find( 'div', {'id':'region-body'} )

    # assume times by default
    if 'srcorgname' not in art:
        art['srcorgname'] = u'times';

    h1 = bodydiv.find( 'h1', {'class':'heading'} )
    art['title'] = h1.renderContents(None).strip()
    art['title'] = ukmedia.DescapeHTML( ukmedia.StripHTML( art['title'] ) )

    authors = []
    # times stuffs up bylines for obituaries (used for date span instead)
    if art['srcurl'].find( '/obituaries/' ) == -1:
        # TODO: "byline" class is used by both the publication and the actual byline.
        # should use position on page to determine, rather than just text matching
        # before headline => publication, after headline => byline
        for bylinespan in bodydiv.findAll( 'span', { 'class': 'byline' } ):
            byline = ukmedia.FromHTMLOneLine( bylinespan.renderContents( None ) )
            if byline in( u'The Sunday Times' ):
                art['srcorgname'] = 'sundaytimes';
            elif byline in ( u'Times Online', u'The Times', u'The Times Literary Supplement' ):
                art['srcorgname'] = 'times';
            else:   # it's a real byline!
                authors.append( byline )

    art['byline'] = u' and '.join( authors )


    # extract images
    # they seem to use javascript to handle all their images. sigh...
    art['images'] = []
    # use regex to pull out block with just one image at a time...
    img_pat = re.compile( r"aArticleImages\[i\]\s*=\s*'(.*?)'\s*;(.*?)i=i[+]1;", re.DOTALL )
    # ...then regexes to pull out the parts we're interested in
    url_pat = re.compile( r"aArticleImages\[i\]\s*=\s*'(.*?)'\s*;" )
    enlargelink_url_pat = re.compile( r"aImageEnlargeLink\[i\]\s*=\s*'(.*?)'\s*;" )
    desc_pat = re.compile( r"aImageDescriptions\[i\]\s*=\s*\"(.*?)\"\s*;" )
    alttext_pat = re.compile( r"aImageAltText\[i\]\s*=\s*\"(.*?)\"\s*;" )
    credit_pat = re.compile( r"aImagePhotographer\[i\]\s*=\s*\"(.*?)\"\s*;" )
    for img_m in img_pat.finditer( html ):
        im = { 'url':None, 'caption':u'', 'credit':u'' }
        # url (use enlargeimage if present)
        txt = img_m.group(0).decode( soup.originalEncoding )
        img_url = url_pat.search( txt ).group(1)
        m = enlargelink_url_pat.search( txt )
        if m:
            img_url = m.group(1)
        im[ 'url' ] = urlparse.urljoin( art['srcurl'], img_url )

        # description (use alt text if we have to)
        m = alttext_pat.search( txt )
        if m:
            im['caption'] = m.group(1)
        m = desc_pat.search( txt )
        if m:
            im['caption'] = m.group(1)

        # photographer
        m = credit_pat.search( txt )
        if m:
            im['credit'] = m.group(1)

        art['images'].append(im)


    # comments
    # TODO: this doesn't count small numbers of comments (it only looks for the "read all N comments" link
    art['commentlinks'] = []

    num_comments = None
    comment_form_div = bodydiv.find( 'div', {'id':"comments-form"} )
    if comment_form_div is not None:
        # this page supports comments, but none have yet been posted
        num_comments = 0

    numcomment_pat = re.compile( r"sReadAllComments\s*=\s*'Read all\s+(\d+)\s+comments'\s*;" )
    m = numcomment_pat.search(html)
    comment_url = urlparse.urljoin( art['permalink'], '#comments-form' )
    if m:
        num_comments = int( m.group(1) )

    if num_comments is not None:
        art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )


    # Extract the article text and build it into it's own soup
    rawcontent_pat = re.compile( u"<!-- Pagination -->(.*)<!-- End of pagination -->", re.UNICODE|re.DOTALL )

    m = rawcontent_pat.search( html )
    contentsoup = BeautifulSoup.BeautifulSoup( m.group(1), fromEncoding = soup.originalEncoding )


#   paginationstart = soup.find( text=re.compile('^\s*Pagination\s*$') )
#   paginationend = soup.find( text=re.compile('^\s*End of pagination\s*$') )

#   if not paginationstart:
#       raise Exception, "couldn't find start of main text!"
#   if not paginationend:
#       raise Exception, "couldn't find end of main text!"



#   contentsoup = BeautifulSoup.BeautifulSoup()
#   p = paginationstart.nextSibling
#   while p != paginationend:
#       print p.name
#       next = p.nextSibling
#       if not isinstance( p, BeautifulSoup.Comment ):
#           contentsoup.insert( len(contentsoup.contents), p )
#       p = next


    for cruft in contentsoup.findAll( 'div', {'class':'float-left related-attachements-container' } ):
        cruft.extract()
    for cruft in contentsoup.findAll( 'script' ):
        cruft.extract()
    #...more?

    art['content'] = ukmedia.SanitiseHTML( contentsoup.prettify(None) )

    # skip crossword solutions etc...
    if art['content'].strip() == u'' and art['srcurl'].find( "games_and_puzzles" ) != -1:
        ukmedia.DBUG2( "IGNORE puzzle solution: '%s' (%s)\n" % (art['title'], art['srcurl']) );
        return None

    # description is in a meta tag
    descmeta = soup.find('meta', {'name':'Description'} )
    desc = descmeta['content']
    desc = ukmedia.DescapeHTML( desc )
    desc = ukmedia.RemoveTags( desc )
    art['description' ] = desc


#NEW VERSION
    # the pubdate is in a comment, eg:
    # <!-- Article Published Date : Apr 23, 2008 12:00 AM -->
    pubdate_pat = re.compile( "<!-- Article Published Date\\s*:\\s*(.*)\\s*-->" )
    m = pubdate_pat.search( html )
    art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

#OLD VERSION
    # There is some javascript with a likely-looking pubdate:
    # var tempDate="02-Jan-2006 00:00";
#   datepat = re.compile( u"\s*var tempDate=\"(.*?)\";", re.UNICODE )
#   m = datepat.search(html)
#   art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

    return art



def Extract_typepad( html, context ):
    """Extract fn for times blogs (hosted on typepad.com)"""

    art = context

    # BeautifulSoup seems to go haywire on some pages with embedded video clips
    # eg http://timesonline.typepad.com/environment/2008/09/clutching-the-m.html
    # this should work around it
    html_kludgepat = re.compile( "<object.*?>.*?</object>", re.DOTALL )
    html = html_kludgepat.sub( '', html )

    soup = BeautifulSoup.BeautifulSoup( html )
#    print soup.renderContents('utf-8')

    # the headline
    headlinediv = soup.find( 'h3', {'class':'entry-header'} )
    art['title'] = ukmedia.FromHTMLOneLine( headlinediv.renderContents(None) )


    # TODO: use rdf timestamp if it's there - otherwise we'll have date but no time
    # some blogs have a little rdf block with iso timestamp (do all of them?)
#    m = re.compile( r'dc:date="(.*?)"' ).search( html )
#    pubdate = dateutil.parser.parse( m.group(1) )

    # footer format varies by blog... some have byline, some have time
    byline = u''
    pubdate = None


    postfooter = soup.find( 'span', {'class':'post-footers'} )
    if not postfooter:
        postfooter = soup.find( 'p', {'class':'entry-footer'} )

    footertxt = postfooter.renderContents( None )

    
    # stupid tech blog uses usa dates. gah.
    if 'timesonline.typepad.com/technology/' in art['srcurl']:
        usa_date = True
    else:
        usa_date = False

    # "Posted by Alice Fishburn on October  9, 2008 in ..."
    m = re.compile( r"Posted by\s+(.*?)\s+on\s+(.*)" ).search( footertxt )
    if m:
        byline = m.group(1)
        datetxt = m.group(2)
        art['pubdate'] = ukmedia.ParseDateTime( datetxt, usa_format=usa_date )
    else:
        m = re.compile( r"Posted by\s+(.*)", re.IGNORECASE ).search( footertxt )
        if m:
            byline = m.group(1)
        date_header = soup.find('h2',{'class':'date-header'})
        if date_header:
            art['pubdate'] = ukmedia.ParseDateTime( date_header.renderContents(None), usa_format=usa_date )
            



    if 'pubdate' not in art:
        # date-header has date but no time
        # sometimes american-style - eg "07/17/2009". ugh.
        dateheader = soup.find( 'h2', {'class':'date-header'} )
        datetxt = dateheader.renderContents(None)     # "Thursday, 05 June 2008"
        art['pubdate'] = ukmedia.ParseDateTime(datetxt)

    # "Posted at 02:22 PM in "...
    #    m = re.compile( r"Posted at\s+(\d+:\d+\s+\w{2})\s+" ).search( footertxt )

    # description, byline, content
    # byline (if present) is in first para
    bodydiv = soup.find( 'div', {'class':'entry-body'} )
 
    if not byline:
        # try the RDF block (raw regex search in the html)
        creatorpat = re.compile( r'dc:creator="(.*?)"' )
        m = creatorpat.search( html )
        if m:
            byline = unicode( m.group(1).strip() )


    content = bodydiv.renderContents(None)
    morediv = soup.find( 'div', {'class':'entry-more'} )
    if morediv:
        content = content + morediv.renderContents( None )

    # TODO: scan content for images and comments 
    content = ukmedia.SanitiseHTML( content )

    desc = ukmedia.FirstPara( content )

    art['byline'] = byline
    art['description'] = desc
    art['content'] = content

    art['srcorgname'] = u'times'

    return art



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    url = re.sub( '#cid=OTC-RSS&attr=[0-9]+', '', url )
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
#    context['srcorgname'] = u'times'    # TODO: or sundaytimes!
    context['lastseen'] = datetime.now()
    return context



if __name__ == "__main__":

    # large maxerrors to handle video-only pages
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200, prep=Prep )


