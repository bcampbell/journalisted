#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# NOTES:
# express has rss feeds, but they seem a little a) chaotic and b) rubbish
#

# TODO:
# use bylineomatic on health, food others?

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils




# url formats:
#   http://www.dailyexpress.co.uk/posts/view/13737
#   http://www.express.co.uk/posts/view/25358/HISTORY-The-Queen-60-Years-Of-Marriage-8-30pm-ITV1
#
# blogs are a bit annoying - blog summary pages _look_ like normal pages:
#   http://www.express.co.uk/blogs/post/36635/blog
# but we should ignore these.
#
# the real blog urls are like this:
#   http://www.dailyexpress.co.uk/blogs/post/36635/blog/2009/08/20/121709/America-may-have-a-black-president-but-racism-is-still-deeply-rooted
#   http://www.dailyexpress.co.uk/blogs/post/267/blog/2009/08/18/121246/Prince-Charles-should-stop-bullying-opponents

main_srcidpat = re.compile( "/view/(\d+)(/.*)?$" )
blog_srcidpat = re.compile( "/blogs/.*/(\d+)(?:/[^/]+/?)?$" )


def CalcSrcID( url ):
    """ Work out a unique srcid from an express url """
    o = urlparse.urlparse( url )
    expressdomains = ( 'dailyexpress.co.uk', 'express.co.uk', 'sundayexpress.co.uk', 'sundayexpress.co.uk' )
    
    d = re.sub( '^www[.]', '', o[1] )
    if d not in expressdomains:
        return None

    # _don't_ want to accidentally scrape blog summary pages
    if o[2].endswith( '/blog' ):
        return None

    m = main_srcidpat.search( url )
    if m is not None:
        return 'express_' + m.group(1)

    m = blog_srcidpat.search( url )
    if m is not None:
        return 'express_blog_' + m.group(1)

    return None



def Extract(html, context, **kw):
    """ handle express articles, including blogs """
    art = context

    # cheesiness - kill everything from comments onward..
    cullpats = [
        re.compile( "<a name=\"comments\">.*", re.DOTALL ),
        # when comments disabled, it just shows a message
        re.compile( r"""<img src="http://images[.]\w+[.]co[.]uk/img/comments/nocomments[.](gif|png)".*""", re.DOTALL )
    ]
    for cullpat in cullpats:
        html = cullpat.sub( "", html )

    # express claims to be iso-8859-1, but it seems to be windows-1252 really
    soup = BeautifulSoup( html, fromEncoding = 'windows-1252' )

    wrapdiv = soup.find( 'div', {'class':'articleWrapper'} )
    if wrapdiv is None:
        # for blogs(?)
        wrapdiv = soup.find( 'td', {'class':'contentcontainer'} )

    missing = soup.find( 'p', text=u"The article you are looking for does not exist.  It may have been deleted." )
    if missing:
        if 'title' in art:
            ukmedia.DBUG2( "IGNORE missing article '%s' (%s)\n" % (art['title'],art['srcurl'] ) )
        else:
            ukmedia.DBUG2( "IGNORE missing article (%s)\n" % ( art['srcurl'] ) )
        return None

    headline = wrapdiv.find( 'h1', { 'class':'articleHeading' } )
    art['title'] = headline.renderContents( None )
    art['title'] = ukmedia.FromHTML( art['title' ] )
    if art['title'].upper() == art['title']:
        art['title'] = ukmedia.UncapsTitle( art['title'] )      # don't like ALL CAPS HEADLINES!  

    introcopypara = wrapdiv.find( 'p', {'class': re.compile(r'\bintrocopy\b') } )
    art['description'] = ukmedia.FromHTMLOneLine( introcopypara.renderContents(None) )

    datepara = wrapdiv.find( 'p', {'class':'date'} )
    if datepara is None:
        #"<span class="date">Monday October 27 2008 <b> byEmily Garnham for express.co.uk</b>"
        datespan = wrapdiv.find( 'span', {'class':'date'} )
        bylineb = datespan.find( 'b' )
        if bylineb is not None:
            art['byline'] = ukmedia.FromHTMLOneLine( bylineb.renderContents(None).strip() )
            art['byline'] = re.sub( '([bB]y)([A-Z])', r'\1 \2', art['byline'] )
            bylineb.extract()
        else:
            if 'blog' in art['srcurl']:
                # blogs(?) have slightly different date/byline layout
                bylineb = wrapdiv.b
                art['byline'] = ukmedia.FromHTMLOneLine( bylineb.renderContents(None).strip() )
            else:
                art['byline'] = u''


        art['pubdate'] = ukmedia.ParseDateTime( datespan.renderContents(None).strip() )
        datespan.extract()        
    else:
        art['pubdate'] = ukmedia.ParseDateTime( datepara.renderContents(None).strip() )
        bylineh4 = wrapdiv.find( 'h4' )
        if bylineh4:
            art['byline'] = ukmedia.FromHTML(bylineh4.renderContents(None))
        else:
            # for some sections, try extracting a journo from the description...
            # (Express usually has names IN ALL CAPS, which the byline-o-matic
            # misses, so we'll turn anything likely-looking into titlecase
            # first).
            art['byline'] = u''
            if art['srcurl'].find('/travel/') != -1 or art['srcurl'].find('/motoring/') != -1:
                desc = ukmedia.DecapNames( art['description'] )
                art['byline'] = ukmedia.ExtractAuthorFromParagraph( desc )

    #comments
    art['commentlinks'] = []
    comment_cnt_pat = re.compile( "Have your say\s*[(](\d+)[)]" )
    num_comments = None
    comment_url = None
    for marker in soup.findAll( text=comment_cnt_pat ):
        if marker.parent.name != 'a':
            continue
        m = comment_cnt_pat.search( marker )
        if m:
            num_comments = int( m.group(1) )
            comment_url = urlparse.urljoin( art['srcurl'], '#comments' )
            art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )
        break   # just the one.


    #images
    art['images'] = []
    for imgdiv in soup.findAll( 'div', {'class':'articleFirstImage'} ):
        img = imgdiv.find('img')
        im = { 'url': img['src'].strip(), 'caption':u'', 'credit': u'' }
        if im['url'].endswith( "/missingimage.gif" ):
            continue
        # find caption para
        # eg class="articleFirstImageCaption"
        capp = imgdiv.find('p',{'class':re.compile('caption$',re.IGNORECASE) } )
        if capp:
            im['caption'] = ukmedia.FromHTMLOneLine( capp.renderContents(None) ).strip()
        art['images'].append(im)

    # cruft removal - mismatched tags means that cruft can get drawn into
    # story paragraphs... sigh...

#   cruft = wrapdiv.find('a', {'name':'comments'} )
#   if cruft:
#       # delete _everything_ from the comments onward
#       n = cruft.next
#       cruft.extract()
#       cruft = n

    for cruft in wrapdiv.findAll('object'):
        cruft.extract()
    for cruft in wrapdiv.findAll('div',{'class':'right'}):
        cruft.extract()

    for cruft in wrapdiv.findAll('form' ):      # (search form etc )
        cruft.extract()

    for cruft_url_pat in ( re.compile("/creditadvice$"),re.compile("/money$") ):
        for cruft in wrapdiv.findAll( 'a', href=cruft_url_pat ):
            cruft.extract()

    # OK to build up text body now!
    textpart = BeautifulSoup()
    textpart.insert( len(textpart.contents), introcopypara )

    #for para in wrapdiv.findAll( 'p', ):   #{'class':'storycopy'} ):

    # sigh... sometimes express articles have nested paras, without the
    # "storycopy" class. probably due to cutting and pasting from another
    # source...
    for p in wrapdiv.findAll( 'p', {'class':'storycopy'} ):
        p.extract()
        textpart.append( p )

    content = textpart.prettify( None )
    content = ukmedia.DescapeHTML( content )
    content = ukmedia.SanitiseHTML( content )
    art['content'] = content

    if art['description'] == u'':
        art['description'] = ukmedia.FirstPara( content )

    return art


def ScrubFunc( context, entry ):
    # there are some test articles lurking in the rss feeds - skip 'em!
    if context['srcurl'].startswith( "http://venus.netro42.com" ):
        return None

    context['srcid'] = CalcSrcID( context['srcurl'] )

    return context


def FindArticles():
    """ collect article list for the express"""
    ukmedia.DBUG2( "express - finding articles\n" )
    articles = FindArticlesFromNavPages()

    # special case for blogs - use the rss feed
    feeds = [ ("blogs","http://www.express.co.uk/posts/rss/27/blogs") ]   
    articles = articles + ScraperUtils.FindArticlesFromRSS( feeds, u'express', ScrubFunc, maxerrors=10 )

    return articles


def FindArticlesFromNavPages():
    """ get a set of articles to scrape by scraping pages in the navigation menu """

    # (the home page is in the nav menu under "/home", which means we'll be
    # fetching it one extra redundant time, but hey)
    start_url = 'http://www.express.co.uk'

    visited = set()
    queued = set()
    queued.add(start_url)

    article_urls = {}

    err_404_cnt = 0
    while queued:
        page_url = queued.pop()
        visited.add( page_url )
        try:
            #print "FETCH '%s'" %(page_url,)
            html = ukmedia.FetchURL( page_url )
        except urllib2.HTTPError, e:
            # allow a few 404s
            if e.code == 404:
                ukmedia.DBUG2("ERR fetching %s (404)\n" %(page_url,))
                err_404_cnt += 1
                if err_404_cnt < 5:
                    continue
            raise
        soup = BeautifulSoup( html )
        # first, look for any sections (or subsections) we might want to scrape
        nav = soup.find('div',{'id':'nav'} )
        if nav is None:
            continue
        for a in nav.findAll( 'a', {'class':re.compile( r"(\bnav\b)|(\bnavon\b)|(\bsubnav\b)|(\bsubnavactive\b)" ) } ):
            name = ukmedia.FromHTMLOneLine( a.renderContents(None) )
            section_url = urlparse.urljoin( page_url, a['href'] )
            if section_url not in visited and section_url not in queued:
                o = urlparse.urlparse( section_url )
                if o[2] not in [ '/myexpress', '/cartoon', '/horoscopes', '/fun', '/video',' /galleries','/readeroffers' ]:
                    queued.add( section_url )

        # now look for articles
        artcnt=0
        for a in soup.findAll( 'a' ):
            if not a.has_key( 'href' ):
                continue
            art_url = TidyURL( urlparse.urljoin( page_url, a['href'] ) )
            srcid = CalcSrcID( art_url )
            if srcid is not None:
                artcnt += 1
                article_urls[srcid] = art_url
        ukmedia.DBUG2("scanning %s: %d articles\n" % (page_url,artcnt))

    articles = []
    for art_url in article_urls.itervalues():
        context = ContextFromURL( art_url )
        if context is not None:
            articles.append( context );

    return articles


def TidyURL( url ):
    """ Tidy up URL - trim off params, query, fragment... """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url
    



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}

    url = TidyURL( url )

    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    if context['srcid'] is None:
        return None
    context['srcorgname'] = u'express'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=100 )


