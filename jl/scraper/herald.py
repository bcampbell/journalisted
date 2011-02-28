#!/usr/bin/env python
#
# Scraper for The Herald (http://www.theherald.co.uk)
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# TODO:
# - could get journo email addresses from bylines

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils
#from SpiderPig import SpiderPig



def old_Extract( html, context ):
    url = context['srcurl']

    if re.search( 'Copyright Press Association Ltd \\d{4}, All Rights Reserved', html ):
        ukmedia.DBUG2( "IGNORE Press Association item (%s)\n" % (url) )
        return None


    # TODO: skip NEWS COMPILER pages instead?
    badtitles = ( "The Herald : Features: LETTERS",
        "Poetry Blog (from The Herald )",
        "Arts Blog (from The Herald )",
        "The Herald : Business: MAIN BUSINESS",
        "The Herald : Motors videos",
        "The Herald - Scotland's Leading Quality Daily Newspaper",
        )

    m = re.search( '<title>(.*?)</title>', html )
    pagetitle = m.group(1)
    if pagetitle in badtitles:
        ukmedia.DBUG2( "IGNORE page '%s' (%s)\n" % ( pagetitle, url) )
        return None

    # blog or article?
    if html.find( "<div class=\"entry2\">" ) != -1:
        return old_blog_Extract( html,context )
    else:
        return old_news_Extract( html,context )
    raise Exception, "can't determine type (news or blog) of article (%s)" % (url)


def old_news_Extract( html, context ):
    """extract function for handling main news site articles, OLD cms"""
    art = context
    soup = BeautifulSoup.BeautifulSoup( html )

    # TODO: skip NEWS COMPILER pages?

    headlinediv = soup.find( 'div', {'class':'artHeadline'} )
    bylinediv = soup.find( 'td', {'class':'artByline'} )
    datediv = soup.find( 'td', {'class':'artDate'} )
    # PA items seem to use a different format... sigh...
    itdatespan = soup.find( 'span', {'class':'itdate'} )
    contentdiv = soup.find( 'div', {'class':'articleText'} )


    # images
    art['images'] = []
    for img in soup.findAll( src=re.compile( r"http://images[.]newsquest[.]co[.]uk/.*" ) ):
        im = { 'url': img['src'], 'caption': img['alt'], 'credit': u'' }
        art['images'].append( im )

    # comments
    art['commentlinks'] = []
    comment_pat = re.compile( r"Read Comments\s+[(]\s*(\d+)\s*[)]" )
    for marker in soup.findAll( text=comment_pat ):
        a = marker.parent
        if a.name != 'a':
            continue
        comment_url = urlparse.urljoin( art['srcurl'], a['href'] )
        num_comments = None
        m = comment_pat.search( marker )
        if m:
            num_comments = int( m.group(1) )
        art['commentlinks'].append( {'num_comments':num_comments, 'comment_url':comment_url} )
        break   # just the one.

    # byline
    byline = u''
    if bylinediv:
        byline = bylinediv.renderContents( None )
        byline = ukmedia.FromHTML( byline )

    # look for press association notice
    # <div class="paNews articleText">
    if byline == u'' and soup.find( 'div', {'class': re.compile('paNews') } ):
        # it's from the Press Association
        byline = u'PA'

    # sometimes byline is first line of article text, in bold...
    if byline == u'':
        # but not obituaries (they always have a bit of bold at the top)...
        if not 'obituaries' in art['srcurl']:
            n=None
            if len(contentdiv.p.contents) > 0:
                n = contentdiv.p.contents[0]
            if isinstance( n, BeautifulSoup.Tag ):
                # Want bold elements, with no <br>s inside them, but followed directly by a <br>...
                if n.name == 'b' and not n.find( "br" ):
                    if isinstance( n.nextSibling, BeautifulSoup.Tag ) and n.nextSibling.name == 'br':
                        byline = n.renderContents(None)
                        byline = ukmedia.FromHTML( byline )
                        byline = u' '.join( byline.split() )
                        n.extract()
                        # TODO: sometimes followed by place... (eg "in Paris<br />")

    headline = headlinediv.renderContents( None )
    headline = ukmedia.FromHTML( headline )

    for cruft in contentdiv.findAll( 'div', {'id':'midpagempu'} ):
        cruft.extract()
    content = contentdiv.renderContents(None)
    desc = ukmedia.FirstPara( content )
    desc = ukmedia.FromHTML( desc )

    pubdatetxt = u''
    if datediv:
        pubdatetxt = datediv.renderContents(None).strip()
    elif itdatespan:
        pubdatetxt = itdatespan.renderContents(None).strip()
        # replace 'today' with current date
        today = datetime.now().strftime( '%a %d %b %Y' )
        pubdatetxt = pubdatetxt.replace( 'today', today )

    if pubdatetxt == u'':
        # if still no date, try the web issue date at top of page...
        # (which will be todays date, rather than real date... but best we can do)
        issuedate = soup.find( 'td', {'align':'right', 'class':'issueDate'} )
        if issuedate:
            pubdatetxt = issuedate.renderContents(None)

    pubdatetxt = ukmedia.FromHTML( pubdatetxt )

    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )
    art['byline'] = byline
    art['title'] = headline
    art['content'] = content
    art['description'] = desc

    return art


def old_blog_Extract( html, context ):
    """extract function for handling blog entries, OLD cms"""

    if html.find( "No blog entries found." ) != -1: 
        ukmedia.DBUG2( "IGNORE missing blog entry (%s)\n" % (context[srcurl]) )
        return None

    art = context
    soup = BeautifulSoup.BeautifulSoup( html )

    entdiv = soup.find( 'div', {'class':'entry2'} )
    headbox = entdiv.findPreviousSibling( 'div', {'class':'b_box'} )

    headline = headbox.a.renderContents(None).strip()
    headline = ukmedia.FromHTML( headline )
    art['title'] = headline

    byline = u''
    postedby = headbox.find( text=re.compile('Posted by') )
    if postedby:
        byline = postedby.nextSibling.renderContents(None).strip()
    art['byline'] = byline

    datespan = headbox.find( 'span', {'class':'itdate'} )
    # replace 'today' with current date
    today = datetime.now().strftime( '%a %d %b %Y' )
    datetxt = ukmedia.FromHTML( datespan.renderContents(None) )
    datetxt = datetxt.replace( 'today', today )
    art['pubdate'] = ukmedia.ParseDateTime( datetxt )

    content = entdiv.renderContents(None)
    art['content'] = content

    desc = ukmedia.FirstPara( content )
    desc = ukmedia.FromHTML( desc )
    art['description'] = desc

    return art


def Extract( html, context ):
    art = context
    soup = BeautifulSoup.BeautifulSoup( html )

    article_div = soup.find('div',{'class':re.compile(r'\barticle\b')})
    artbody_div = article_div.find( 'div',{'class':re.compile( r"\barticle-body\b")} )

    bylinetxt = u''
    pubdatetxt = u''
    byline_p = article_div.find( 'p', {'class':'byline'} )
    if byline_p is not None:
        bylinetxt = ukmedia.FromHTMLOneLine( byline_p.renderContents( None ) )
        # sometimes date is in byline (for blogs, I think)
        # "Michael Settle, 9 Sep 2009 12.33"
        m = re.compile( '\s*(.*)\s*,\s*(\d+\s+\w+\s+\d{4}\s+\d\d[.]\d\d)' ).match( bylinetxt )
        if m:
            # it's a combined byline/date
            bylinetxt = m.group(1)
            pubdatetxt = m.group(2)
    else:
        # no byline. might be a columnist - try the url for names
        m = re.compile( r'/comment/([-\w]+?)(?:s-diary)?/' ).search( art['srcurl'] )
        if m:
            bylinetxt = unicode( m.group(1).replace( '-', ' ' ) )
    art['byline'] = bylinetxt

    # if no pubdate in byline, look for a proper pubdate para
    if pubdatetxt == u'':
        pubdate_p = article_div.find( 'p', {'class':'pubdate'} )
        pubdatetxt = ukmedia.FromHTMLOneLine( pubdate_p.renderContents( None ) )

    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )

    h1 = article_div.h1
    art['title'] = ukmedia.FromHTMLOneLine( h1.renderContents( None ) )
    art['content'] = artbody_div.renderContents( None )
    art['description'] = ukmedia.FirstPara( art['content'] )

    # TODO: comments (not working on herald site at time of writing)

    #images
    art['images'] = []
    for pic_div in article_div.findAll( 'div', {'class':'pic-onecol'} ):
        img = pic_div.img
        im_url = urlparse.urljoin( art['srcurl'], img['src'] )
        im_credit = img['title']
        cap = pic_div.find('div',{'class':"pic-caption"} )
        im_caption = ukmedia.FromHTMLOneLine( cap.li.renderContents(None) )
        art['images'].append( { 'url': im_url, 'caption': im_caption, 'credit': im_credit } )


    return art


# OLD-style URLS:
#  main news site:
#   "http://www.theherald.co.uk/news/news/display.var.2036423.0.Minister_dismisses_more_tax_power_for_Holyrood.php"
#  blogs:
#   "http://www.theherald.co.uk/features/bookblog/index.var.9706.0.at_home_in_a_story.php"
old_idpat = re.compile( "/((display|index)[.]var[.].*[.]php)" )

# NEW URLS (as of sept 2009):
#  http://www.heraldscotland.com/news/politics/macaskill-denies-brother-s-role-in-libya-oil-interests-1.918391
#  http://www.heraldscotland.com/comment/iain-macwhirter/how-do-we-prevent-google-from-turning-into-hal-1.918020
#  http://www.heraldscotland.com/sport/more-scottish-football/fletcher-desperate-to-make-world-cup-dream-come-true-1.918563?localLinksEnabled=false


def CalcSrcID( url ):
    """ extract unique srcid from url """
    url = TidyURL( url.lower() )
    o = urlparse.urlparse( url )
    if o[1].endswith( 'theherald.co.uk' ):
        # OLD url
        m = old_idpat.search( o[2] )
        if m:
            return 'herald_' + m.group(1)
    elif o[1].endswith( 'heraldscotland.com' ):
        # NEW url
        new_idpat = re.compile( r"(?:.*?)(\d+)$" )
        m = new_idpat.match( o[2] )
        if m:
            return 'heraldscotland_' + m.group(1)
    return None


def TidyURL( url ):
    """ Tidy up URL - trim off params, query, fragment... """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    url = TidyURL( url )
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    if context['srcid'] == None:
        return None
    context['srcorgname'] = u'herald'
    context['lastseen'] = datetime.now()
    return context


def ScrubFunc( context, entry ):
    url = TidyURL( context['srcurl'] )
    context['permalink'] = url
    context['srcurl'] = url
    context['srcid'] = CalcSrcID( url )
    return context

def FindArticles():
    """Gather articles to scrape from the herald website. """
    feeds = FindRSSFeeds()
    found = ScraperUtils.FindArticlesFromRSS( feeds, u'herald', ScrubFunc, maxerrors=10 )
    return found



def FindRSSFeeds():
    rss_page = 'http://www.heraldscotland.com/services/rss' 
    html = ukmedia.FetchURL( rss_page )
    soup = BeautifulSoup.BeautifulSoup( html )
    feeds = []
    for a in soup.findAll( 'a', {'class':'rss-link'} ):
        a.img.extract() # kill the rss icon
        feed_name = a.renderContents( None )
        feed_url = urlparse.urljoin( rss_page, a['href'] )
        feeds.append( (feed_name,feed_url) )

    ukmedia.DBUG2( "Scanned '%s', found %d RSS feeds\n" %( rss_page, len(feeds) ) )
    return feeds

if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract )

