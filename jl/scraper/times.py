#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - extract better srcids, instead of using whole url
#

import re
from datetime import datetime
import sys
import os
import urlparse

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils

# NOTES:
#
# The Times website seems a little crap and stalls regularly, so
# we timeout. Should handle it a bit more gracefully...
#
# The Times RSS feeds used to be a bit rubbish,  but have improved.
#
# Also scrape links from the html pages. The Times has a page for
# each days edition which contains links to all the headlines for that day.
# That's what we want. But it doesn't cover online-only articles


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


def FetchRSSFeeds():
    """Scrape a list of all the timesonline rss feeds, returns a list of (name,url) tuples"""
    html = ukmedia.FetchURL( 'http://www.timesonline.co.uk/tol/tools_and_services/rss/' )
    soup = BeautifulSoup.BeautifulSoup(html)

    feeds = []

    foo = soup.find( 'div', {'id':'region-column1-layout2'} )
    for a in foo.findAll( 'a' ):
        name = ukmedia.FromHTMLOneLine( a.renderContents( None ) )
        url = a['href'].encode('ascii').strip()
        o = urlparse.urlparse( url )

        ok = False
        if 'timesonline.co.uk' in o[1] or 'typepad.com' in o[1]:
            ok = True
        if 'podcast.timesonline.co.uk' in o[1]:
            ok = False
        if ok:
            feeds.append( (name,url) )

    return feeds


def FindBlogFeeds():
    """Scrape a list of all the timesonline blogs, returns a list of (name,url) tuples"""

    mainblogpage = 'http://www.timesonline.co.uk/tol/comment/blogs/'

    html = ukmedia.FetchURL( mainblogpage )
    soup = BeautifulSoup.BeautifulSoup( html )
    feed_dict = {}
    for a in soup.findAll( 'a', {'class':'link-06c', 'href':re.compile(r'\btypepad[.]com\b(?!.*[.]html$)') } ):
        url = a['href'].strip()
        if not url.endswith('/'):
            url += '/'
#        url += 'atom.xml'   # atom
#        url += 'index.rdf'  # rss1.0
        url += 'rss.xml'    # rss2.0

        url = url.encode('ascii').strip()
        name = a.renderContents(None).strip()

        feed_dict[url] = name



    feeds = []
    for url,name in feed_dict.iteritems():
        feeds.append( (name,url) )

    return feeds


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


def ScrubFunc( context, entry ):
    """mungefunc for ScraperUtils.FindArticlesFromRSS()"""

    url = context[ 'srcurl' ]
    o = urlparse.urlparse( url )
    if o[1] == 'feeds.timesonline.co.uk':
        # sigh... obsfucated url (presumably for tracking)
        # Luckily the guid has proper link, marked as non-permalink
        url = entry.guid

    context['srcid'] = CalcSrcID( url )
    return context


def FindArticles():
    """ use various sources to make sure we get both print and online-only articles"""
    foundarticles = []

    # part 1: get RSS feeds
    ukmedia.DBUG2( "*** times ***: scraping lists of feeds...\n" )
    mainfeeds = FetchRSSFeeds()
    blogfeeds = FindBlogFeeds()     # their main rss list isn't 100% complete. sigh.
    feeds = MergeFeedLists( (blogfeeds,mainfeeds) )

    foundarticles = ScraperUtils.FindArticlesFromRSS( feeds, u'times', ScrubFunc )

    # part 2: get main paper articles from scraping pages (_should_ mirror what's in the print editions)
    ukmedia.DBUG2( "*** times ***: scraping newspaper article list...\n" )

    # hit the page which shows the covers of the papers for the week
    # and extract a link to each day
    ukmedia.DBUG2( "fetching /tol/newspapers/the_times...\n" )
    html = ukmedia.FetchURL( siteroot + '/tol/newspapers/the_times' )
#   ukmedia.DBUG2( "  got it.\n" )
    soup = BeautifulSoup.BeautifulSoup(html)

    # (one day of the week will always be missing, as it'll have
    # been renamed 'Today')
    days = ( 'Today', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'The Sunday Times' )
    daypat = re.compile( "/tol/newspapers/(.*?)/[?]days=(.*?)" )

    daypages = {}
    for link in soup.findAll( 'a', {'class':"link-06c"} ):

        url = link['href']
        if daypat.match( url ):
            day = link.renderContents(None).strip()
            if day in days:
                daypages[day] = siteroot + url

    # go through each days page and extract links to articles
    for day, url in daypages.iteritems():

        ukmedia.DBUG2( "fetching " + day + "\n" )
        html = ukmedia.FetchURL( url )
#       ukmedia.DBUG( " got " + day + "\n" )
        fetchtime = datetime.now()
        soup = BeautifulSoup.BeautifulSoup(html)


        # Which newspaper?
        if re.search( "/?days=Sunday$", url ):
            srcorgname = "sundaytimes"
        else:
            srcorgname = "times"
        ukmedia.DBUG2( "** PAPER: " + srcorgname + "\n" )

        # go through by section
        for heading in soup.findAll( 'h3', {'class': 'section-heading' } ):
            sectionname = heading.find( text = sectionnames )
            if not sectionname:
#               print "Ignoring section ",heading
                continue

            ukmedia.DBUG2( "  " + sectionname + "\n" )

            ul = heading.findNextSibling( 'ul' )
            for a in ul.findAll( 'a' ):
                title = ukmedia.FromHTMLOneLine( a.renderContents(None) )
                url = siteroot + a['href']

                # don't do puzzle solutions
                skip = False
                if "games_and_puzzles" in url:
                    for pat in puzzle_blacklist:
                        if pat.match( title ):
#                            ukmedia.DBUG2( "SKIP puzzle solution: '%s' (%s)\n" % (title, url) )
                            skip=True

#                    if not skip:
#                        print "PUZ: '%s': '%s'" %(title,url)

                if not skip:
                    context = {
                        'title': title,
                        'srcurl': url,
                        'srcid': CalcSrcID(url),
                        'permalink': url,
                        'lastseen': fetchtime,
                        'srcorgname' : srcorgname,
                        }

                    foundarticles.append( context )

    ukmedia.DBUG2( "Found %d articles\n" % ( len(foundarticles) ) )
    return foundarticles


# http://www.timesonline.co.uk/tol/news/politics/article3471714.ece
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

    if domain in blogdomains:
        if o[2].endswith('.html'):
            return 'times_' + o[2]
        else:
            return None

    # main paper?
    if not o[1].endswith( 'timesonline.co.uk' ):
        return None

    m = srcidpat_ece.search( o[2] )
    if m:
        return 'times_' + m.group(1)

    return None


def Extract( html, context ):
    o = urlparse.urlparse( context['srcurl'] )
    if o[1].endswith( 'typepad.com' ):
        return Extract_typepad( html, context )
    else:
        return Extract_escenic( html,context )



def NextTag( el ):
    while el:
        el = el.nextSibling
        if isinstance( el, BeautifulSoup.Tag ):
            break
    return el


def Extract_escenic( html, context ):
    """Extract fn for main newspaper (Escenic CMS)"""
    art = context
    html = CleanHtml(html)
    soup = BeautifulSoup.BeautifulSoup( html )

    h1 = soup.find( 'h1', {'class':'heading'} )
    art['title'] = h1.renderContents(None).strip()
    art['title'] = ukmedia.DescapeHTML( ukmedia.StripHTML( art['title'] ) )

    byline = u''
    # times stuffs up bylines for obituaries (used for date span instead)
    if art['srcurl'].find( '/obituaries/' ) == -1:
        authdiv = soup.find( 'div', {'class':'article-author'} )
        if authdiv:
            bylinespan = authdiv.find( 'span', { 'class': 'byline' } )
            if bylinespan:
                byline = bylinespan.renderContents( None )
                byline = ukmedia.StripHTML( byline )
                byline = ukmedia.DescapeHTML( byline )
                byline = u' '.join( byline.split() )
    art['byline'] = byline


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
    comment_form_div = soup.find( 'div', {'id':"comments-form"} )
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
    postfooter = soup.find( 'span', {'class':'post-footers'} )
    if not postfooter:
        postfooter = soup.find( 'p', {'class':'entry-footer'} )

    footertxt = postfooter.renderContents( None )

    # "Posted by Alice Fishburn on October  9, 2008 in ..."
    m = re.compile( r"Posted by\s+(.*?)\s+on (\w+\s+\d+,\s+\d{4})" ).search( footertxt )
    if m:
        byline = m.group(1)
        datetxt = m.group(2)
        art['pubdate'] = ukmedia.ParseDateTime(datetxt)

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


    return art



def MergeFeedLists( feedlists ):
    """merges lists of (name,url) tuples, keyed by url"""
    u = {}
    for l in feedlists:
        for f in l:
            u[f[1]] = f[0]
    return [(v,k) for k,v in u.iteritems()]



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'times'    # TODO: or sundaytimes!
    context['lastseen'] = datetime.now()
    return context



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

