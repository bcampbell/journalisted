#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# Telegraph seems to have three different formats:
# .xml, .html and blogs (.htm).
# The blog ones are done by blogs.py
#
# TODO:
#
# - see if the telegraph update their master rss feed page. Currently the
#   list of feeds is a manually-cobbled-together mix of old and new feeds.
#   At time of writing (2008-07-23) telegraph rss feeds page only lists
#   old-style feeds.
#
# - better sundaytelegraph handling
#
# - tidy URLs ( strip jsessionid etc)
#     http://www.telegraph.co.uk/earth/main.jhtml?view=DETAILS&grid=&xml=/earth/2007/07/19/easeabird119.xml
#     (strip view param)
#
# - handle multi-page articles (currently only pick up first page) (is this a problem with new website format too?)
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


# these were obtained by manually going through each section and noting down the feeds.
new_rssfeeds = {

    "Home feed": "http://www.telegraph.co.uk/rss",

    "News feed": "http://www.telegraph.co.uk/news/rss",
    "  UK News feed": "http://www.telegraph.co.uk/news/uknews/rss",
    "  World News feed": "http://www.telegraph.co.uk/news/worldnews/rss",
    "  Politics feed": "http://www.telegraph.co.uk/news/newstopics/politics/rss",
    "    Conservative feed": "http://www.telegraph.co.uk/news/newstopics/politics/conservative/rss",
    "    Labour feed": "http://www.telegraph.co.uk/news/newstopics/politics/labour/rss",
    "    Liberal Democrats feed": "http://www.telegraph.co.uk/news/newstopics/politics/liberaldemocrats/rss",
    "  Celebrity News feed": "http://www.telegraph.co.uk/news/newstopics/celebritynews/rss",
    "  Obituaries feed": "http://www.telegraph.co.uk/news/obituaries/rss",
    "  How About That? feed": "http://www.telegraph.co.uk/news/newstopics/howaboutthat/rss",
    # science section is still old style, and mixed in with earth section
    "  News Topics feed": "http://www.telegraph.co.uk/news/newstopics/rss",

    "Sport feed": "http://www.telegraph.co.uk/sport/rss",
    "  Football feed": "http://www.telegraph.co.uk/sport/football/rss",
    "    Leagues feed": "http://www.telegraph.co.uk/sport/football/leagues/rss",
    # There are actually feeds for every team... but I'm not entering them all by hand :-)
    # And the leagues feed should cover them all anyway.
    "    European feed": "http://www.telegraph.co.uk/sport/football/european/rss",
    "    International feed": "http://www.telegraph.co.uk/sport/football/international/rss",
    "  Cricket feed": "http://www.telegraph.co.uk/sport/cricket/rss",
    "    International feed": "http://www.telegraph.co.uk/sport/cricket/international/rss",
    "    Counties feed": "http://www.telegraph.co.uk/sport/cricket/counties/rss",
    "  Olympics feed": "http://www.telegraph.co.uk/sport/othersports/olympics/rss",
    "  Rubgy Union feed": "http://www.telegraph.co.uk/sport/rugbyunion/rss",
    "    International feed": "http://www.telegraph.co.uk/sport/rugbyunion/international/rss",
    "    Club feed": "http://www.telegraph.co.uk/sport/rugbyunion/club/rss",
    "  Formula One feed": "http://www.telegraph.co.uk/sport/motorsport/formulaone/rss",
    "  Golf feed": "http://www.telegraph.co.uk/sport/golf/rss",
    "  Tennis feed": "http://www.telegraph.co.uk/sport/tennis/rss",
    "  Horse Racing feed": "http://www.telegraph.co.uk/sport/horseracing/rss",
    "  Other Sports feed": "http://www.telegraph.co.uk/sport/othersports/rss",
    "  sports columnists": "http://www.telegraph.co.uk/sport/columnists/rss",

    # Business section is still old style

    # Comment section is still old style

    "Travel feed": "http://www.telegraph.co.uk/travel/rss",
    # I think "Types of Trips" and "Destinations" feeds might always be empty
    "  Types of Trips feed": "http://www.telegraph.co.uk/travel/typesoftrips/rss",
    "  Destinations feed": "http://www.telegraph.co.uk/travel/destinations/rss",
    "  Hotels feed": "http://www.telegraph.co.uk/travel/hotels/rss",
    "    UK Hotel reviews feed": "http://www.telegraph.co.uk/travel/hubs/ukhotelreviews/rss",
    "    Europe Hotel reviews Feed": "http://www.telegraph.co.uk/travel/hubs/europehotelreviews/rss",
    "  Travel News feed": "http://www.telegraph.co.uk/travel/travelnews/rss",
    "  Columnists feed": "http://www.telegraph.co.uk/travel/columnists/rss",

    # Lifestyle section is still old style
    # Culture section is still old style

}




old_rssfeeds = {
    "Telegraph | Arts": "http://www.telegraph.co.uk/newsfeed/rss/arts.xml",
    "Telegraph | Books": "http://www.telegraph.co.uk/newsfeed/rss/arts-books.xml",
    "Telegraph | Digital Life": "http://www.telegraph.co.uk/newsfeed/rss/connected.xml",

    "Telegraph | Earth": "http://www.telegraph.co.uk/newsfeed/rss/earth.xml",
    "Telegraph | Science news": "http://www.telegraph.co.uk/newsfeed/rss/earth-science.xml",
    "Telegraph | Education": "http://www.telegraph.co.uk/newsfeed/rss/education.xml",
    "Telegraph | Expat": "http://www.telegraph.co.uk/newsfeed/rss/global.xml",
    "Telegraph | Fashion": "http://www.telegraph.co.uk/newsfeed/rss/fashion.xml",
    "Telegraph | Gardening": "http://www.telegraph.co.uk/newsfeed/rss/gardening.xml",
    "Telegraph | Health": "http://www.telegraph.co.uk/newsfeed/rss/health.xml",
    "Telegraph | Motoring": "http://www.telegraph.co.uk/newsfeed/rss/motoring.xml",
    "Telegraph | News | All": "http://www.telegraph.co.uk/newsfeed/rss/news.xml",
    "Telegraph | News | Major": "http://www.telegraph.co.uk/newsfeed/rss/news-major.xml",
    "Telegraph | News | UK": "http://www.telegraph.co.uk/newsfeed/rss/news-uk_news.xml",
    "Telegraph | News | International": "http://www.telegraph.co.uk/newsfeed/rss/news-international_news.xml",
      
      # BLOGS:
#    "Telegraph | News | Blog Yourview": "http://www.telegraph.co.uk/newsfeed/rss/news-blog-yourview.xml",

    "Telegraph Business RSS": "http://www.telegraph.co.uk/newsfeed/rss/money_city.xml",
#    "Telegraph Business | Markets RSS": "http://www.telegraph.co.uk/newsfeed/rss/money_markets.xml",
    "Telegraph Money | Personal Finance RSS": "http://www.telegraph.co.uk/newsfeed/rss/money_pf.xml",

  
    "Telegraph | News | Business": "http://www.telegraph.co.uk/newsfeed/rss/money-city_news.xml",
    "Telegraph | Your Money": "http://www.telegraph.co.uk/newsfeed/rss/money-personal_finance.xml",
      
      # blogs?
#    "Telegraph | Opinion": "http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_opinion.xml",

    "Telegraph Opinion RSS": "http://www.telegraph.co.uk/newsfeed/rss/opinion.xml",
    "Telegraph Opinion | Leaders RSS": "http://www.telegraph.co.uk/newsfeed/rss/leaders.xml",

    "Telegraph | Leaders": "http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_leaders.xml",
    "Telegraph | Property": "http://www.telegraph.co.uk/newsfeed/rss/property.xml",
    "Telegraph | Sport": "http://www.telegraph.co.uk/newsfeed/rss/sport.xml",
    "Telegraph | Sport | Football": "http://www.telegraph.co.uk/newsfeed/rss/sport-football.xml",
    "Telegraph | Sport | Premiership Football": "http://www.telegraph.co.uk/newsfeed/rss/sport-football-premiership.xml",
    "Telegraph | Sport | Cricket": "http://www.telegraph.co.uk/newsfeed/rss/sport-cricket.xml",

# doesn't work?
#    "Telegraph | Sport | International Cricket": "http://www.telegraph.co.uk/newsfeed/rss/sport-international_cricket.xml",
    "Telegraph | Sport | Rugby Union": "http://www.telegraph.co.uk/newsfeed/rss/sport-rugby_union.xml",
    "Telegraph | Sport | Golf": "http://www.telegraph.co.uk/newsfeed/rss/sport-golf.xml",
    "Telegraph | Sport | Tennis": "http://www.telegraph.co.uk/newsfeed/rss/sport-tennis.xml",
    "Telegraph | Sport | Motor Sport": "http://www.telegraph.co.uk/newsfeed/rss/sport-motor_sport.xml",
    "Telegraph | Travel": "http://www.telegraph.co.uk/newsfeed/rss/travel.xml",
    "Telegraph | Wine": "http://www.telegraph.co.uk/newsfeed/rss/wine.xml",
  #  "Telegraph | Podcast": "http://www.telegraph.co.uk/newsfeed/rss/podcast.xml",
  #  "Telegraph | Podcast | mp3": "http://www.telegraph.co.uk/newsfeed/rss/podcastmp3.xml",
  
  # seems to cause an error:
#    "Telegraph | Top Ten Stories":
 #   "http://stats.telegraph.co.uk/rss/topten.xml",
      # type="rss" language="en-gb" /> 

    # blogs style?
#    "Telegraph | My Telegraph":
#    "http://my.telegraph.co.uk/feed.rss"
      # type="rss" language="en-gb" />   

 #   "Telegraph | Blogs | All Posts":
 #   "http://blogs.telegraph.co.uk/Feed.rss"

}


rssfeeds = new_rssfeeds
rssfeeds.update( old_rssfeeds )




def Extract( html, context ):
    # blog url format: (handled by blogs.py)
    # http://blogs.telegraph.co.uk/politics/threelinewhip/feb/speakerfurorenotclasswarfare.htm

    o = urlparse.urlparse( context['srcurl'] )

    if o[2].endswith( ".html" ):
        # HTML article url format:
        #   http://www.telegraph.co.uk/travel/africaandindianocean/maldives/759764/Maldives-family-holiday-Game-Boys-v-snorkels.html
        return Extract_HTML_Article( html, context )

    if o[2].endswith( ".jhtml" ):
        # XML article url format:
        #   http://www.telegraph.co.uk/news/main.jhtml?xml=/news/2008/02/25/ncameron125.xml
        return Extract_XML_Article( html, context )

#   if o[1] == "blogs.telegraph.co.uk":
#       ukmedia.DBUG2( "IGNORE: blog ('%s')\n" % ( context['srcurl']) )
#       return None

    raise Exception, "Uh-oh... don't know how to handle url '%s'" % (context['srcurl'])


def Extract_HTML_Article( html, context ):
    art = context


    # cull out video section before we do anything
    vidpat = re.compile( r"<!-- Start of Brightcove Player -->.*?<!-- End of Brightcove Player -->", re.DOTALL )
    html = vidpat.sub( '', html )

    soup = BeautifulSoup.BeautifulSoup( html )

    # 'storyHead' div contains headline and description
    storyheaddiv = soup.find( 'div', {'class': 'storyHead' } )

    title = storyheaddiv.h1.renderContents( None )
    title = ukmedia.FromHTML( title )
    title = u' '.join( title.split() )
    art['title'] = title

    desctxt = u''
    h2 = storyheaddiv.find('h2')
    if h2:
        desctxt = h2.renderContents(None)
        desctxt = ukmedia.FromHTML( desctxt )
        desctxt = u' '.join( desctxt.split() )

    # 'story' div contains byline and main article text
    storydiv = soup.find( 'div', {'class': 'story' } )
    bylinediv = storydiv.find( 'div', {'class':'byline'} )
    # byline div contains both byline and pubdate
    txt = bylinediv.renderContents(None)
    txt = ukmedia.FromHTML( txt )
    txt = u' '.join( txt.split() )
    m = re.match( r"\s*(.*?)\s*Last Updated:\s+(.*)", txt )
    art['byline'] = m.group(1)
    pubdatetxt = m.group(2) # eg "11:52PM BST 22 Jul 2008"
    art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )

    # cull out cruft from the story div:
    bylinediv.extract()
    for cruft in storydiv.findAll( 'div', {'class': re.compile(r'\bslideshow\b') } ):
        cruft.extract()
    for cruft in storydiv.findAll( 'ul', {'class': 'storylist'} ):
        cruft.extract()

    contenttxt = storydiv.renderContents(None)
    contenttxt = ukmedia.SanitiseHTML( contenttxt )
    art['content'] = contenttxt

    if desctxt == u'':
        desctxt = ukmedia.FirstPara( art['content'] )
    art['description'] = desctxt

    return art




def Extract_XML_Article( html, context ):
    # Sometimes the telegraph has missing articles.
    # But the website doesn't return proper 404 (page not found) errors.
    # Instead, it redirects to an error page which has a 200 (OK) code.
    # Sigh.
    # there do seem to be a few borked pages on the site, so we'll treat it
    # as non-fatal (so it won't contribute toward the error count/abort)
    if re.search( """<title>.*404 Error: file not found</title>""", html ):
        raise ukmedia.NonFatal, ("missing article (telegraph doesn't return proper 404s)")

    art = context



    soup = BeautifulSoup.BeautifulSoup( html )

    headline = soup.find( 'h1' )
    if not headline:
        # is it a blog? if so, skip it for now (no byline, so less important to us)
        # TODO: update scraper to handle blog page format
        hd = soup.find( 'div', {'class': 'bloghd'} )
        if hd:
            raise ukmedia.NonFatal, ("scraper doesn't yet handle blog pages (%s) on feed %s" % (context['srcurl'],context['feedname']) );
        # gtb:
        raise ukmedia.NonFatal, ("couldn't find headline to scrape (%s) on feed %s" % (context['srcurl'],context['feedname']) );

    title = ukmedia.DescapeHTML( headline.renderContents(None) )
    # strip out excess whitespace (and compress to one line)
    title = u' '.join( title.split() )
    art['title'] = title

    # try to get pubdate from the page:
    #    Last Updated: <span style="color:#000">2:43pm BST</span>&nbsp;16/04/2007
    filedspan = soup.find( 'span', { 'class': 'filed' } )
    if filedspan:
        # clean it up before passing to ParseDateTime...
        datetext = filedspan.renderContents(None)
        datetext = datetext.replace( "&nsbp;", " " )
        datetext = ukmedia.FromHTML( datetext )
        datetext = re.sub( "Last Updated:\s+", "", datetext )
        pubdate = ukmedia.ParseDateTime( datetext )
        art['pubdate'] = pubdate
    # else just use one from context, if any... (eg from rss feed)


    # NOTE: in a lot of arts, motoring etc... we could get writer from
    # the first paragraph ("... Fred Smith reports",
    # "... talks to Fred Smith" etc)

    bylinespan = soup.find( 'span', { 'class': 'storyby' } )
    byline = u''
    if bylinespan:
        byline = bylinespan.renderContents( None )

        #if re.search( u',\\s+Sunday\\s+Telegraph\\s*$', byline ):
            # byline says it's the sunday telegraph
        #   if art['srcorgname'] != 'sundaytelegraph':
        #       raise Exception, ( "Byline says Sunday Telegraph!" )
        #else:
        #   if art['srcorgname'] != 'telegraph':
        #       raise Exception, ( "Byline says Telegraph!" )

        # don't need ", Sunday Telegraph" on end of byline
        byline = re.sub( u',\\s+Sunday\\s+Telegraph\\s*$', u'', byline )
        byline = ukmedia.FromHTML(byline)
        # single line, compress whitespace, strip leading/trailing space
        byline = u' '.join( byline.split() )

    art['byline'] = byline


    # Some articles have a hidden bit where the author name is stored:  
    # fill in author name:
    if not byline:
        # cv.c6="/property/features/article/2007/10/25/lpsemi125.xml|Max+Davidson";
        authorMatch = re.search(u'cv.c6=".*?\|(.*?)";', html)
        if authorMatch:
            author = authorMatch.group(1)
            author = re.sub(u'\+',' ',author)                                       # convert + signs to spaces
            author = re.sub(u'\\b([A-Z][a-z]{3,})([A-Z][a-z]+)\\b', '\\1-\\2', author)  # convert SparckJones to Sparck-Jones (that's how they encode it)
            # n.b. {3,} makes McTaggart not go to Mc-Taggart... bit hacky

            # discard "healthtelegraph", "fashiontelegraph" etc...
            if author.lower().find( 'telegraph' ) == -1:
                art['byline'] = unicode( author )

    # text (all paras use 'story' or 'story2' class, so just discard everything else!)
    # build up a new soup with only the story text in it
    textpart = BeautifulSoup.BeautifulSoup()

    art['description'] = ExtractParas( soup, textpart )


    if (not ('byline' in art)) or art['byline']==u'':
        author = ukmedia.ExtractAuthorFromParagraph(art['description'])
        if author!=u'':
            art['byline'] = author

    
# DEBUG:
#   if ('byline2' in art) and ('byline' in art) and art['byline2']!=art['byline']:
#       print "byline2: "+art['byline2']+" ("+art['byline']
#   elif ('byline2' in art):
#       print "byline2: "+art['byline2']

    # Deal with Multiple authors:
    # e.g."Borrowing money is becoming ever more difficult, say Harry Wallop and Faith Archer"

    # Deal with ones with no verb clue but there's only one name:
    #     "Many readers complain that the financial
    #         institutions that are keen to take their money are less willing to
    #         answer legitimate questions. Sometimes the power of the press, in
    #         the shape of Jessica Gorst-Williams, can help"


#################

    # TODO: support multi-page articles
    # check for and grab other pages here!!!
    # (note: printable version no good - only displays 1st page)

    if textpart.find('p') == None:
        # no text!
        if html.find( """<script src="/portal/featurefocus/RandomSlideShow.js">""" ) != -1 or art['title'] == 'Slideshowxl':
            # it's a slideshow, we'll quietly ignore it
            return None
        else:
            raise Exception, 'No text found'


    content = textpart.prettify(None)
    content = ukmedia.DescapeHTML( content )
    content = ukmedia.SanitiseHTML( content )
    art['content'] = content

    return art


# pull out the article body paragraphs in soup and append to textpart
# returns description (taken from first nonblank paragraph)
def ExtractParas( soup, textpart ):
    desc = u''
    for para in soup.findAll( 'p', { 'class': re.compile( 'story2?' ) } ):

        # skip title/byline
        if para.find( 'h1' ):
            continue

        # quit if we hit one with the "post this story" links in it
        if para.find( 'div', { 'class': 'post' } ):
            break

        textpart.insert( len(textpart.contents), para )

        # we'll use first nonblank paragraph as description
        if desc == u'':
            desc = ukmedia.FromHTML( para.renderContents(None) )
            
    # gtb: replace all whitespace (including newlines) by one space... 
    # (needed for author extraction from description)
    desc = re.sub(u'\s+',u' ', desc)
    return desc


# eg http://www.telegraph.co.uk/travel/759562/Is-cabin-air-making-us-sick.html
srcidpat_html = re.compile( "/(\d+)/[^/]+[.]html$" )

# http://www.telegraph.co.uk/earth/main.jhtml?xml=/earth/2008/03/02/earecycling102.xml
# pick out the xml=part
srcidpat_xml = re.compile( "(xml=.*[.]xml)" )

def CalcSrcID( url ):
    """ extract unique id from url """

    url = url.lower()

    o = urlparse.urlparse( url )

    if not o[1].endswith( 'telegraph.co.uk' ):
        return None
    if o[1].startswith( "blogs." ):
        return None     # blogs handled in blogs.py

    m = srcidpat_html.search( o[2] )
    if m:
        return 'telegraph_' + m.group(1)

    # pick out from the the "xml=" param
    m = srcidpat_xml.search( o[4] )
    if m:
        return 'telegraph_' + m.group(1)

    return None


def ScrubFunc( context, entry ):
    """ tidy up context, work out srcid etc... entry param not used """

    # we'll assume that all articles published on a Sunday are from
    # the sunday telegraph...
    # TODO: telegraph and sunday telegraph should share srcid space...
    if ('pubdate' in context) and (context['pubdate'].strftime( '%a' ).lower() == 'sun'):
        context['srcorgname'] = u'sundaytelegraph'
    else:
        context['srcorgname'] = u'telegraph'

    url = context['srcurl']
    o = urlparse.urlparse( url )
    if o[2].lower().endswith( ".html" ):
        # it's an html article...
        # eg "http://www.telegraph.co.uk/travel/759562/Is-cabin-air-making-us-sick.html"
        # trim off all params, fragments...
        url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
        context['srcurl'] = url
        context['permalink'] = url
        # use printer version for scraping
#        context['srcurl'] = urlparse.urlunparse( (o[0],o[1],o[2],'','service=print','') );

        context['srcid'] = CalcSrcID( url )

    elif o[2].lower().endswith( ".jhtml" ):
        # it's an xml-based article
        # eg "http://www.telegraph.co.uk/money/main.jhtml?xml=/money/2008/02/26/bcnpersim126.xml"

        context['srcid'] = CalcSrcID( url )

        # suppress cruft pages
        if ('title' in context) and (context['title'] == 'Horoscopes'):
            return None

        # skip slideshow pages, eg
        # "http://www.telegraph.co.uk/health/main.jhtml?xml=/health/2007/07/10/pixbeauty110.xml",
        slideshow_pattern = pat=re.compile( '/pix\\w+[.]xml$' )
        if slideshow_pattern.search( context['srcurl'] ):
            return None

    else:
        # blog? some other unsupported page...
        return None


    return context



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = url
    context['lastseen'] = datetime.now()

    # apply the various url-munging rules :-)
    context = ScrubFunc( context, None )

    return context


def FindArticles():
    return ScraperUtils.FindArticlesFromRSS( rssfeeds, u'telegraph', ScrubFunc )


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

