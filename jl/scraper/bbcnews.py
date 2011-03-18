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
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils







# example bbc news url:
# "http://news.bbc.co.uk/1/hi/world/africa/7268903.stm"
old_news_srcid_pat = re.compile( '/(\d+)\.stm$' )
news_srcid_pat = re.compile( '(\d{4,})/?$' )

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

    m = old_news_srcid_pat.search( url )
    if m:
        return 'bbcnews_' + m.group(1)


    if '/iplayer/' in url:
        return None

    m = news_srcid_pat.search( url )
    if m:
        return url

    return None


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
            # new, eg http://www.bbc.co.uk/news/world-latin-america-12629647
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






def Extract_blog( html, context ):
    """Parse the html of a bbc blog post page"""

    art = context
    soup = BeautifulSoup( html )
    post_div = soup.find( 'div', {'class':'post'} )
    headline_hx = post_div.find( re.compile(r'h\d') )
    art['title'] = ukmedia.FromHTMLOneLine( headline_hx.renderContents(None) )

    meta_div = post_div.find('div', {'class':'meta'} )

    author = meta_div.find('span', {'class':'vcard author'} )
    if author is not None:
        art['byline'] = ukmedia.FromHTMLOneLine( author.renderContents(None) )
    else:
        art['byline'] = u''

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

    # or "<!-- START CPS_SITE CLASS: story -->" for story
    # or could use class of "#main-content" div to determine

    bod = soup.find('div',{'class':'story-body'})
    if bod is None:
        bod = soup.find('div',{'id':'story-body'})

    if bod is None:
        ukmedia.DBUG2( "IGNORE non-story page ( %s )\n" %( context['srcurl'] ) )
        return None

    if "<!-- START CPS_SITE CLASS: media_asset -->" in html:
        ukmedia.DBUG2( "IGNORE video page ( %s )\n" %( context['srcurl'] ) )
        return None

    # strip out html comments
    comments = bod.findAll(text=lambda text:isinstance(text, Comment))
    [comment.extract() for comment in comments]


    # get pubdate from meta tag
    meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
    if meta:
        art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

    # headline
    meta = soup.find( 'meta', { 'name': 'Headline' } )
    art['title'] = ukmedia.FromHTMLOneLine( meta['content'] )


    authors = []

    for byline in bod.findAll('span',{'class':re.compile(r'\bbyline\b')}):
        for author in byline.findAll('span',{'class':re.compile(r'\b((byline-name)|(author-name))\b')}):
            authors.append( ukmedia.FromHTMLOneLine( author.renderContents(None) ) )
            #TODO: could also use "byline-title"/"author-position" span to get jobtitle
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

    comments_div = bod.find('div',{'class':re.compile(r'\bdna-comments_module\b')})
    if comments_div:
        # TODO: could get comment count
        comments_div.extract()

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
    for cruft in bod.findAll( 'div', {'class':'embedded-hyper' } ):
        cruft.extract()


    art['content'] = ukmedia.SanitiseHTML( bod.renderContents(None) )
    art['description'] = ukmedia.FirstPara( art['content'] )

    return art




def FindArticles():
    """ crawl the bbc news site for articles """

    root_url = "http://www.bbc.co.uk/news/"

    err_cnt = 0
    max_errs = 10

    article_urls = set()
    visited = set()
    queued = set()
    queued.add( root_url )

    while queued:
        page_url = queued.pop()
        visited.add(page_url)
        try:
            html = ukmedia.FetchURL(page_url)
        except urllib2.HTTPError, e:
            err_cnt += 1
            if err_cnt >= max_errs:
                ukmedia.DBUG('error count exceeded - BAILING\n')
                raise
            ukmedia.DBUG('%s: %s\n' % (page_url,str(e)))
            continue
        doc = lxml.html.fromstring(html)
        doc.make_links_absolute(page_url)

        art_cnt = 0
        nav_cnt = 0
        for a in doc.cssselect('a'):
            url = a.get('href')
            if url is None:
                continue


            # kill query and fragment parts
            # TODO: should save originals as alternate urls (maybe with query, but no fragment)
            o = urlparse.urlparse(url)
            url = urlparse.urlunparse((o[0], o[1], o[2], o[3], '', ''))
            # skip the mobile version of articles
            # TODO: should collect mobile urls and map them to the non-mobile versions
            if '/mobile/' in url:
                continue
            if '/weather/forecast/' in url:
                continue
            #if '/in_pictures/' in url:
            #    continue
            if CalcSrcID(url):
                # it looks like an article url...
                if url not in article_urls:
                    art_cnt += 1
                    article_urls.add(url)
            elif url not in visited and url.startswith(page_url):
                if not url.endswith('.stm'):
                    # treat it as a navigation page
                    nav_cnt += 1
                    queued.add(url)

        ukmedia.DBUG2("scan %s (%d articles)\n" % (page_url,art_cnt))
    ukmedia.DBUG2("scanned %d pages, found %d articles" %(len(visited),len(article_urls)))
    return [ContextFromURL(url) for url in article_urls]




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
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract )

