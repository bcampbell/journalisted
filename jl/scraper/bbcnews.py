#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News
#

import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils




# bbc blog feedlist automatically scraped by ./bbcblogs-scrape-rsslist.py
# (run 2009-02-16 12:03:24)
# got 95 feeds
# THEN HAND EDITED!
blog_feeds = [
#    ("BBC Internet blog", "http://www.bbc.co.uk/blogs/bbcinternet/rss.xml"),
#    ("BBCi Labs", "http://www.bbc.co.uk/blogs/bbcilabs/rss.xml"),
    ("The Editors", "http://www.bbc.co.uk/blogs/theeditors/rss.xml"),
    ("JZ's diary (Radio Scotland)", "http://www.bbc.co.uk/blogs/jeffzycinski/index.xml"),
#    ("Radio Labs", "http://www.bbc.co.uk/blogs/radiolabs/index.xml"),
    ("Sport Editors' blog", "http://www.bbc.co.uk/blogs/sporteditors/index.xml"),
#    ("Ouch", "http://www.bbc.co.uk/blogs/ouch/rss.xml"),
    ("Peston's Picks", "http://www.bbc.co.uk/blogs/thereporters/robertpeston/rss.xml"),
    ("The Devenport Diaries", "http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml"),
#    ("Stuart Bailie", "http://www.bbc.co.uk/blogs/stuartbailie/rss.xml"),
    ("Will & Testament", "http://www.bbc.co.uk/blogs/ni/index.xml"),
    ("Blether with Brian", "http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml"),
#    ("Bryan Burnett", "http://www.bbc.co.uk/blogs/bryanburnett//index.xml"),
#    ("JZ's diary", "http://www.bbc.co.uk/blogs/jeffzycinski/index.xml"),
    ("Pauline McLean", "http://www.bbc.co.uk/blogs/thereporters/paulinemclean/rss.xml"),
#    ("Scottish Symphony Orchestra", "http://www.bbc.co.uk/blogs/bbcsso//index.xml"),
    ("Betsan's blog", "http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml"),
#    ("Blog C2", "http://www.bbc.co.uk/blogs/c2/rss.xml"),
#    ("North-east Wales weblog", "http://blogs.bbc.co.uk/walesnortheast/index.xml"),

# WELSH
###     ("Vaughan Roderick", "http://www.bbc.co.uk/blogs/thereporters/vaughanroderick/rss.xml"),

#    ("Wales Nature", "http://www.bbc.co.uk/blogs/gardenersworld/alysfowlerrss.xml"),
#    ("Bobby Friction", "http://www.bbc.co.uk/blogs/friction//index.xml"),
#    ("Bollywood blog", "http://www.bbc.co.uk/blogs/bollywood//index.xml"),
#    ("Chart blog", "http://www.bbc.co.uk/blogs/chartblog//index.xml"),
#    ("The Culture Show", "http://www.bbc.co.uk/blogs/thecultureshow/rss.xml"),
#    ("Introducing", "http://www.bbc.co.uk/blogs/introducing//index.xml"),
#    ("Kermode Uncut", "http://www.bbc.co.uk/blogs/markkermode/rss.xml"),
#    ("Mike Harding's Folk blog", "http://www.bbc.co.uk/blogs/folk//index.xml"),
#    ("Pauline McLean", "http://www.bbc.co.uk/blogs/thereporters/paulinemclean/rss.xml"),
#    ("Stuart Bailie", "http://www.bbc.co.uk/blogs/stuartbailie/rss.xml"),
#    ("Writers' Room", "http://www.bbc.co.uk/blogs/writersroom/index.xml"),
#    ("Scottish Symphony Orchestra", "http://www.bbc.co.uk/blogs/bbcsso//index.xml"),
#    ("Betsan's blog", "http://www.bbc.co.uk/blogs/thereporters/betsanpowys/rss.xml"),
#    ("Blether with Brian", "http://www.bbc.co.uk/blogs/thereporters/briantaylor/rss.xml"),
#    ("The Devenport Diaries", "http://www.bbc.co.uk/blogs/thereporters/markdevenport/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/10_things/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/caption_comp/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/crunch_creep/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/daily_miniquiz/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/housekeeping/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/how_to_say/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/paper_monitor/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/quote_of_the_day/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/random_stat/rss.xml"),
#    ("Magazine Monitor", "http://www.bbc.co.uk/blogs/magazinemonitor/your_letters/rss.xml"),
    ("Mark Easton's UK", "http://www.bbc.co.uk/blogs/thereporters/markeaston/rss.xml"),
    ("Mark Urban", "http://www.bbc.co.uk/blogs/newsnight/markurban/index.xml"),
    ("Michael Crick", "http://www.bbc.co.uk/blogs/newsnight/michaelcrick/index.xml"),
    ("Nick Robinson's Newslog", "http://blogs.bbc.co.uk/nickrobinson/rss.xml"),
    ("Open Secrets", "http://www.bbc.co.uk/blogs/opensecrets/rss.xml"),
    ("James Reynolds' China", "http://www.bbc.co.uk/blogs/thereporters/jamesreynolds/rss.xml"),
    ("Justin Webb's America", "http://www.bbc.co.uk/blogs/thereporters/justinwebb/rss.xml"),
    ("Mark Mardell's Europe", "http://www.bbc.co.uk/blogs/thereporters/markmardell/rss.xml"),
    ("Nick Bryant's Australia", "http://www.bbc.co.uk/blogs/thereporters/nickbryant/rss.xml"),
    ("5 Live Breakfast", "http://www.bbc.co.uk/blogs/fivelivebreakfast/index.xml"),
#    ("Ace & Vis (1Xtra)", "http://www.bbc.co.uk/blogs/aceandvis/index.xml"),
#    ("Bryan Burnett (Radio Scotland)", "http://www.bbc.co.uk/blogs/bryanburnett//index.xml"),
#    ("Chris Evans (Radio 2)", "http://www.bbc.co.uk/blogs/chrisevans//index.xml"),
#    ("Chris Moyles (Radio 1)", "http://www.bbc.co.uk/blogs/chrismoyles/index.xml"),
#    ("Greg James (Radio 1)", "http://www.bbc.co.uk/blogs/gregjames//index.xml"),

# UNSURE if we should do iPM blog
###    ("iPM (Radio 4)", "http://www.bbc.co.uk/blogs/ipm//index.xml"),
#    ("Jo Whiley (Radio 1)", "http://www.bbc.co.uk/blogs/jowhiley/index.xml"),
#    ("Mistajam (1Xtra)", "http://www.bbc.co.uk/blogs/mistajam/rss.xml"),
    ("PM (Radio 4)", "http://www.bbc.co.uk/blogs/pm/index.xml"),
#    ("Pods and Blogs (Radio 5 Live)", "http://www.bbc.co.uk/blogs/podsandblogs/index.xml"),
#    ("Steve Lamacq (6 Music)", "http://www.bbc.co.uk/blogs/stevelamacq/index.xml"),
    ("Today - Evan Davis (Radio 4)", "http://www.bbc.co.uk/blogs/today/evandavis/index.xml"),
    ("Today - Tom Feilden (Radio 4)", "http://www.bbc.co.uk/blogs/today/tomfeilden/index.xml"),
    ("Today - Jim Naughtie (Radio 4)", "http://www.bbc.co.uk/blogs/today/jimnaughtie/index.xml"),
#    ("Victoria Derbyshire (Radio 5 Live)", "http://www.bbc.co.uk/blogs/victoriaderbyshire/index.xml"),
    ("World Tonight (Radio 4)", "http://www.bbc.co.uk/blogs/worldtonight//index.xml"),
#    ("World Update (World Service)", "http://www.bbc.co.uk/blogs/worldupdate/index.xml"),
#    ("Toby Buckland (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/tobybuckland/rss.xml"),
#    ("Alys Fowler (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/alysfowler/rss.xml"),
#    ("Joe Swift (gardening)", "http://www.bbc.co.uk/blogs/gardenersworld/joeswift/rss.xml"),
#    ("Euro 2008", "http://www.bbc.co.uk/blogs/football/index.xml"),

    # MIHIR BOSE link is wrong!
#    ("Mihir Bose", "http://www.bbc.co.uk/blogs/mihirbose/rss.xml"),
    # corrected:
    ("Mihir Bose", "http://www.bbc.co.uk/blogs/thereporters/mihirbose/rss.xml"),

#    ("Olympics", "http://www.bbc.co.uk/blogs/olympics/rss.xml"),
#    ("Test Match Special", "http://www.bbc.co.uk/blogs/tms/index.xml"),
    ("Dot.life", "http://www.bbc.co.uk/blogs/technology/rss.xml"),
#    ("Amazon", "http://www.bbc.co.uk/blogs/amazon/rss.xml"),
#    ("Autumnwatch", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("The Culture Show", "http://www.bbc.co.uk/blogs/thecultureshow/rss.xml"),
#    ("Gardeners' World", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("Last Chance to See", "http://www.bbc.co.uk/blogs/lastchancetosee/rss.xml"),
    ("Newsnight", "http://www.bbc.co.uk/blogs/newsnight/index.xml"),
#    ("The One Show - Backstage", "http://www.bbc.co.uk/blogs/theoneshow/backstage/rss.xml"),
#    ("The One Show - Consumer", "http://www.bbc.co.uk/blogs/theoneshow/consumer/rss.xml"),
#    ("The One Show - One Passions", "http://www.bbc.co.uk/blogs/theoneshow/onepassions/rss.xml"),
#    ("Springwatch", "http://www.bbc.co.uk/blogs/gardenersworld/rss.xml"),
#    ("Watchdog", "http://www.bbc.co.uk/blogs/watchdog/styles.css"),
#    ("BBC Brazil", "http://www.bbc.co.uk/blogs/portuguese/index.xml"),
#    ("BBC Mundo", "http://www.bbc.co.uk/blogs/spanish/index.xml"),
#    ("BBC Urdu", "http://www.bbc.co.uk/blogs/urdu/index.xml"),

    # HMMM.. she isn't listed on bbc.co.uk/blogs...
    ("Razia Iqbal", "http://www.bbc.co.uk/blogs/thereporters/raziaiqbal/rss.xml" ),
]






# example bbc news url:
# "http://news.bbc.co.uk/1/hi/world/africa/7268903.stm"
news_srcid_pat = re.compile( '/(\d+)\.stm$' )


# some blog url patterns:
# http://www.bbc.co.uk/blogs/thereporters/robertpeston/2009/02/chelsea_reduces_dependence_on.html
# http://www.bbc.co.uk/blogs/pm/2009/02/pmtv.shtml
blog_srcid_pat = re.compile( 'http://(?:www[.])?bbc[.]co[.]uk/blogs(/.*[.]s?html)$' )


def CalcSrcID( url ):
    """ Extract unique srcid from url. Returns None if this scraper doesn't handle it."""

    m = blog_srcid_pat.match(url)
    if m:
        return "bbcblogs_" + m.group(1)

    o = urlparse.urlparse(url)
    if o[1] not in ('news.bbc.co.uk','bbc.co.uk', 'www.bbc.co.uk' ):
        return None

    m = news_srcid_pat.search( url )
    if m:
        return 'bbcnews_' + m.group(1)
    return url


def Extract( html, context ):
    if '/low/' in context['srcurl']:
        return Extract_low( html, context )
    if 'bbc.co.uk/blogs' in context['srcurl']:
        return Extract_blog( html, context )
    else:
        if '<div id="mediaAsset"' in html:
            ukmedia.DBUG2( "IGNORE media-only page ( %s )\n" %( context['srcurl'] ) )
            return None


        if 'table class="storycontent"' in html:
            # old style
            return Extract_hi( html, context )
        else:
            return Extract_tableless( html, context )

def Extract_low( html, context ):
    """parse html of a low-graphics page"""

    art = context
    page_enc = 'ISO-8859-1'

    # pubdate
    pubdate_pat = re.compile( r'<meta\s+name="OriginalPublicationDate"\s+content="(.*?)"\s*/?>' )
    m = pubdate_pat.search( html )
    art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

    # title
    headline_pat = re.compile( r'<a name="startcontent"></a>\s*<h\d>(.*?)</h\d>', re.DOTALL )
    m = headline_pat.search(html)
    art['title'] = m.group(1).strip().decode( page_enc )

    # byline
    byline = u''
    byline_pat = re.compile( r'<!-- S IBYL -->(.*?)<!-- E IBYL -->', re.DOTALL )
    m = byline_pat.search( html )
    if m:
        byline = m.group(1).decode( page_enc )

        # trim off possible leading all-caps cruft (eg "<b>WHO, WHAT, WHY?</b><br />")
        byline = re.sub( r'<b>[^a-z]+</b>\s*<br\s*/>', '', byline )
        # replace <br /> with a comma to retain a little more context when we strip html tags
        byline = re.sub( ur'<br\s*/>', u',', byline )
        byline = ukmedia.FromHTMLOneLine(byline)
        byline = re.sub( u'\s+,', u',', byline )
        byline = re.sub( u',$', u'', byline )
        byline = byline.strip()
        html = byline_pat.sub( '', html )
    art['byline'] = byline

    # images
    # NOTE: low-graphics version of page has no caption, but alt attr is OKish.
    art['images'] = []
    image_pat = re.compile( r'<!-- S IIMA -->(.*?)<!-- E IIMA -->', re.DOTALL )
    for im in image_pat.finditer( html ):
        imtxt = im.group(1)
        m = re.search( r'src="(.*?)"', imtxt )
        img_url = m.group(1)
        m = re.search( r'alt="(.*?)"', imtxt )
        img_caption = unicode( m.group(1), page_enc )
        art['images'].append( { 'url': img_url, 'caption': img_caption, 'credit': u'' } )
    html = image_pat.sub( '', html )

    # main text
    main_pat = re.compile( r'(?:<!-- S BO -->)+(.*?)<!-- E BO -->', re.DOTALL )
    m = main_pat.search(html)
    art['content'] = m.group(1).decode( page_enc )

    art['description'] = ukmedia.FirstPara( art['content'] )

    # if description came up blank, maybe it's because it was a gallery page
    if art['description'] == u'':
        picpage = False
        for foo in ( r'\bpictures\b',r'\bphotos\b', r'\bgallery\b' ):
            pat = re.compile( foo, re.IGNORECASE )
            if pat.search( art['title'] ):
                picpage = True
                break
        if picpage:
            ukmedia.DBUG2( "IGNORE pictures/photos page ( %s )\n" %( art['srcurl'] ) )
            return None

    return art



def Extract_hi( html, context ):

    art = context
    soup = BeautifulSoup( html )

    story_table = soup.find( 'table', {'class':'storycontent' } )
    h1 = story_table.find('h1')
    if h1 is None:
        # sigh... special case for "earth news" section (and others?)
        h1 = story_table.find('div', {'class':'sh'} )
        if h1 is None:  # special special case... bloody hell.
            h1 = soup.find('div', {'class':'sh'} )

    art['title'] = ukmedia.FromHTMLOneLine( h1.renderContents(None) )

    # get pubdate from meta tag
    date_meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
    if date_meta:
        art['pubdate'] = ukmedia.ParseDateTime( date_meta['content'] )

    bod = story_table.find( 'td', {'class':'storybody'} )

    byline_parts = []
    # TODO: could also use byline description in "span .byd"
    for byl in bod.findAll( 'span', {'class':'byl'} ):
        byline_parts.append( ukmedia.FromHTMLOneLine( byl.renderContents( None ) ) );
    art['byline'] = u' and '.join( byline_parts )

    # images
    art['images'] = []
    for img in bod.findAll( 'img' ):
        d = img.findNextSiblings( limit=1 )
        if d:
            if d[0].name=='div' and d[0]['class']=='cap':
                caption_div = d[0]
                img_caption = ukmedia.FromHTMLOneLine( caption_div.renderContents(None) )
                img_credit = u''
                img_url = img['src']
                art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )

                caption_div.extract()
        img.extract()

    for cruft in bod.findAll('div', {'id':'socialBookMarks'} ):
        cruft.extract()
    for cruft in bod.findAll('script' ):
        cruft.extract()
    for cruft in bod.findAll('table' ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':('mvtb','mvb')} ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':re.compile('^video') } ):
        cruft.extract()

    art['content'] = bod.renderContents(None)
    art['description'] = ukmedia.FirstPara( art['content'] );

#    print art['content'].encode( 'utf-8' )

#    print 80*'-'
#    for f in ('title','byline','pubdate' ):
#        print art[f]

    return art



def Extract_OLDtableless( html, context ):
    # new(?) format, tableless, some linked data appearing?...
    # eg /1/hi/technology/10102126.stm
    #

    art = context
    soup = BeautifulSoup( html )

    # get pubdate from meta tag
    date_meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
    if date_meta:
        art['pubdate'] = ukmedia.ParseDateTime( date_meta['content'] )

    # headline
    meta_div = soup.find('div',{'id':'meta-information'})
    art['title'] = ukmedia.FromHTMLOneLine( meta_div.h1.renderContents(None) )


    bod = soup.find('div',{'id':'story-body'})

    authors = []
    byline = bod.find('span',{'class':'byline'})
    if byline:
        for author in byline.findAll('span',{'class':'author-name'}):
            authors.append( ukmedia.FromHTMLOneLine( author.renderContents(None) ) )
        #TODO: could also use "span.author-position" info
        byline.extract()
    art['byline'] = u' and '.join(authors)


    # images
    art['images'] = []
    for cap in bod.findAll('span',{'class':'caption'}):
        if cap.img:
            img_url = cap.img['src']
            cap.img.extract()
            img_caption = ukmedia.FromHTMLOneLine( cap.renderContents(None) )
            img_credit = u''
            art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )
        cap.extract()

    for cruft in bod.findAll( 'div', {'class':re.compile('^video') } ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':'story-feature' } ):
        cruft.extract()

    art['content'] = bod.renderContents(None)
    art['description'] = ukmedia.FirstPara( art['content'] )

    return art




def Extract_blog( html, context ):
    """Parse the html of a bbc blog post page"""

    art = context
    soup = BeautifulSoup( html )
    post_div = soup.find( 'div', {'class':'post'} )
    headline_hx = post_div.find( re.compile(r'h\d') )
    art['title'] = ukmedia.FromHTMLOneLine( headline_hx.renderContents(None) )

    meta_div = post_div.find('div', {'class':'meta'} )

    author = meta_div.find('span', {'class':'vcard author'} )
    art['byline'] = ukmedia.FromHTMLOneLine( author.renderContents(None) )

    # <abbr class="published" title="2010-04-02T12:35:44+00:00">12:35 UK time, Friday,  2 April 2010</abbr>
    pub = meta_div.find('abbr', {'class':'published'} )
    art['pubdate'] = ukmedia.ParseDateTime( pub['title'] )

    #if art['byline'] == u'Nick' and '/nickrobinson/' in context['srcurl']:
    #    art['byline'] = u'Nick Robinson';

    content_div = post_div.find( 'div', {'class':"post_content"} )



    # images
    art['images'] = []
    for mt in content_div.findAll( 'div',{'class':re.compile('mt-image-enclosure' )} ):
        img = mt.img
        img_caption = u'' #ukmedia.FromHTMLOneLine( img.get( 'alt',u'' ) )
        # occasional image is just broken (usually because of a bad alt, eg alt="that isn"t cricket")
        #if not 'src' in img:
        #    continue
        img_url = img['src']
        img_credit = u''
        art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )


    # comments
    comment_div = soup.find( 'div', {'id':'comments'} )
    if comment_div is not None:
        num_comments = 0
        # no easy total on page, so look for highest numbered comment
        comment_num_pat = re.compile( r'\s*(\d+)\s*[.]\s*');
        for c in comment_div.findAll( 'span', {'class':'comment-number'} ):
            m = comment_num_pat.match( ukmedia.FromHTMLOneLine( c.renderContents(None) ) )
            num = int( m.group(1) )
            if num > num_comments:
                num_comments = num

        comment_url = art['srcurl'] + "#comments"
        art['commentlinks'] = [ {'num_comments':num_comments, 'comment_url':comment_url} ]


    # get the text, minus assorted cruft
    for cruft in content_div.findAll( 'ul', {'class':'ami_social_bookmarks'} ):
        cruft.extract()
    for cruft in content_div.findAll( 'span', {'class':re.compile( 'mt-enclosure')} ):
        cruft.extract()
    for cruft in content_div.findAll( 'object' ):
        cruft.extract()

    # embedded bbc players are a div placeholder, followed by script
    for cruft in content_div.findAll( 'div', {'class':'player'} ):
        cruft.extract()
    for cruft in content_div.findAll( 'script' ):
        cruft.extract()

    art['content'] = content_div.renderContents(None)
    art['description'] = ukmedia.FirstPara( art['content'] )

    return art




def Extract_tableless(html,context):
    art = context
    soup = BeautifulSoup( html )

    if "<!-- START CPS_SITE CLASS: media_asset -->" in html:
        ukmedia.DBUG2( "IGNORE video page ( %s )\n" %( context['srcurl'] ) )
        return None
    # or "<!-- START CPS_SITE CLASS: story -->" for story
    # or could use class of "#main-content" div to determine


    # get pubdate from meta tag
    meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
    if meta:
        art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

    # headline
    meta = soup.find( 'meta', { 'name': 'Headline' } )
    art['title'] = ukmedia.FromHTMLOneLine( meta['content'] )

    bod = soup.find('div',{'class':'story-body'})

    authors = []

    for byline in bod.findAll('span',{'class':'byline'}):
        for author in byline.findAll('span',{'class':'byline-name'}):
            authors.append( ukmedia.FromHTMLOneLine( author.renderContents(None) ) )
            #TODO: could also use "byline-title" span
        byline.extract()
    art['byline'] = u' and '.join(authors)

    # images
    art['images'] = []
    for cap in bod.findAll('div',{'class':re.compile(r'\bcaption\b')}):
        if cap.img:
            img_url = cap.img['src']
            cap.img.extract()
            img_caption = ukmedia.FromHTMLOneLine( cap.renderContents(None) )
            img_credit = u''
            art['images'].append( {'url': img_url, 'caption': img_caption, 'credit': img_credit } )
        cap.extract()

    for cruft in bod.findAll( 'div', {'class':re.compile('^video') } ):
        cruft.extract()
    for cruft in bod.findAll( 'span', {'class':'story-date'} ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':re.compile(r'\bstory-feature\b') } ):
        cruft.extract()
    for cruft in bod.findAll( 'h1', {'class':'story-header' } ):
        cruft.extract()
    for cruft in bod.findAll( 'div', {'class':'share-help' } ):
        cruft.extract()
    for cruft in bod.findAll( 'script' ):
        cruft.extract()


    art['content'] = ukmedia.SanitiseHTML( bod.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] )

    return art




def FindFeeds():
    """ scrape the BBC news site to find all the RSS feeds """

    visited = set()
    queued = set()
    queued.add( "http://www.bbc.co.uk/news/" )                  # main starting point
    queued.add( "http://news.bbc.co.uk/local/hi/default.stm" )  # for local new list

    feeds = []

    while queued:
        page_url = queued.pop()
        visited.add( page_url )
        ukmedia.DBUG2( "fetching %s\n" % (page_url) )
        html = ukmedia.FetchURL( page_url )
        soup = BeautifulSoup( html )
        # first, look for any other pages we might want to scrape

        found = []
        # use the nav bars....
        for nav in soup.findAll( 'ul', {'id':['nav','sub-nav']}):
            for a in nav.findAll('a'):
                section_url = urlparse.urljoin( page_url, a['href'] )
                found.append( section_url )
        # ...and also look for local news sections
        local_list = soup.find( 'div', {'class':'local_site_list' } )
        if local_list:
            for a in local_list.findAll( 'a'):
                url = urlparse.urljoin( page_url, a['href'] )
                found.append( url )

        # queue up any additional pages we found
        for url in found:
            if url not in visited and url not in queued:
                queued.add( url )

        # now check this page for stories (or rss feeds...)
        for foo in soup.head.findAll('link', type='application/rss+xml' ):
            feeds.append( (foo['title'], foo['href']) )

    return feeds






def ScrubFunc( context, entry ):
    """ per-article callback for processing RSS feeds """

    url = context['srcurl']
    # BBC has special rss versions which redirect to real article...
    if '/rss/' in url:
        # ...luckily the guid has proper link (marked as non-permalink)
        url = entry.guid

    # a story can have multiple paths (eg uk vs international version)
    srcid = CalcSrcID( url )
    if not srcid:
        return None # suppress it

    if '/in_pictures/' in url:
        return None

    context['srcid'] = srcid
    context['srcurl'] = url

    return context


def FindArticles():
    """ get a set of articles to scrape from the bbc rss feeds """
    news_feeds = FindFeeds()
    articles = ScraperUtils.FindArticlesFromRSS( blog_feeds, u'bbcnews', ScrubFunc )
    articles = articles + ScraperUtils.FindArticlesFromRSS( news_feeds, u'bbcnews', ScrubFunc )
    return articles


def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    # NOTE: urls from the rss feed have a couple of extra components which
    # we _could_ strip out here...
    context = {}
    context['permalink'] = url
    context['srcurl'] = url
    # scrape the low-graphics version of the page
    # NOTE: a few pages give 404 errors for their low-graphics counterpart...
    # I _think_ these are video pages (only text is a small caption)
#    context['srcurl'] = re.sub( '/hi/', '/low/', context['srcurl'] )
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'bbcnews'
    context['lastseen'] = datetime.now()
    return context


if __name__ == "__main__":
    # high maxerrors to cope with photo gallerys, things-to-do pages etc... better culling needed :-)
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=100 )

