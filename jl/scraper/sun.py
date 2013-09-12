#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# KNOWN ISSUES:
#
# - the sun sometimes embed multiple stories on the same webpage,
#   for now we just process only the "main" story and discard the substory.
# - we miss subheadings for the occasional article
#   (they sometimes skip the "article" class we look for...)
# - pages with flash video leave some cruft in the content text
#    ("You need Flash Player 8 or higher..." etc)
#

import re
import urllib   # for urlencode
import urllib2
import ConfigParser
import sys
import traceback
from datetime import date,datetime
import urlparse
import lxml.html

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ScraperUtils

from pprint import pprint

# current url format:
# http://www.thesun.co.uk/sol/homepage/news/2471744/Browns-Nailed-Plotters.html
srcidpat_slugstyle = re.compile( '/(\\d+)/[^/]+.html$' );

# prev url format:
# http://www.thesun.co.uk/sol/homepage/news/royals/article862982.ece
# http://www.thescottishsun.co.uk/scotsol/homepage/news/article2438517.ece
srcidpat_ecestyle = re.compile( '/(article\\d+[.]ece)$' )

# Old url format, no longer used (vignette storyserver cms, I think)
# http://www.thesun.co.uk/article/0,,2007400986,00.html
srcidpat_oldstyle = re.compile( '/(article/[^/]+[.]html)$' )


# names of columnists and indicators in urls, for last resort bylines
columnist_lookups = [
    {'url': '/columnists/fergus_shanahan/', 'name':u'Fergus Shanahan' },
    {'url': '/columnists/ally_ross/', 'name': u'Ally Ross' },
    {'url': '/columnists/jane_moore/', 'name': u'Jane Moore' },
    {'url': '/columnists/blunkett/', 'name': u'David Blunkett' },
    {'url': '/columnists/kelvin_mackenzie/', 'name': u'Kelvin MacKenzie' },
    {'url': '/columnists/john_gaunt/', 'name': u'John Gaunt' },
    {'url': '/columnists/lorraine_kelly/', 'name': u'Lorraine Kelly' },
    {'url': '/columnists/clarkson/', 'name': u'Jeremy Clarkson' },
    {'url': '/columnists/kavanagh/', 'name': u'Trevor Kavanagh' },
]


def CalcSrcID( url ):
    """Extract a unique srcid from url"""

    o = urlparse.urlparse( url )
    if not (o[1].endswith('thesun.co.uk') or o[1].endswith('thescottishsun.co.uk') ):
        return None

    for blacklisted in ( '/mystic_meg/', '/virals/', '/video/' ):
        if blacklisted in o[2]:
            return None

    m = srcidpat_slugstyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    m = srcidpat_ecestyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    m = srcidpat_oldstyle.search( o[2] )
    if m:
        return 'sun_' + m.group(1)

    return None




def FindArticles():
    start_page = "http://www.thesun.co.uk"
    art_url_pat = re.compile('.*/([a-z0-9_]+-){1,}([a-z0-9_]+).html$', re.I)
    navsel = "#mainNav a"
    domain_whitelist = ('www.thesun.co.uk',)

    # NOTE: this requires being logged in
    # non-logged-in users see a different homepage

    urls = GenericFindArtLinks(start_page,domain_whitelist,navsel,art_url_pat)
    arts = []
    for url in urls:
        good = True
        for blacklisted in ( '/mystic_meg/', '/virals/', '/video/' ):
            if blacklisted in url:
                good = False
        if good:
            arts.append(ContextFromURL(url))

    return arts


def GenericFindArtLinks(start_page, domain_whitelist, navsel, art_url_pat):
    sections = set( (start_page,))
    sections_seen = set(sections)
    err_404_cnt = 0

    arts = set()

    while len(sections)>0:
        section_url = sections.pop()
        try:
            html = ukmedia.FetchURL(section_url)
        except urllib2.HTTPError as e:
            # allow a few 404s
            if e.code == 404:
                ukmedia.DBUG("ERR fetching %s (404)\n" %(section_url,))
                err_404_cnt += 1
                if err_404_cnt < 5:
                    continue
            raise

        try:
            doc = lxml.html.fromstring(html)
            doc.make_links_absolute(section_url)
        except lxml.etree.XMLSyntaxError as e:
            ukmedia.DBUG("ERROR parsing %s: %s\n" %(section_url, e))
            continue


        # check nav bars for sections to scan
        for navlink in doc.cssselect(navsel):
            url = navlink.get('href')
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue
            # strip fragment
            url = urlparse.urlunparse((o[0],o[1],o[2],o[3],o[4],''))

            if url in sections_seen:
                continue

            blacklisted_sections = ()
            if [foo for foo in blacklisted_sections if foo in url]:
                continue

            # section is new and looks ok - queue for scanning
            #ukmedia.DBUG( "Queue section %s\n" % (url,))
            sections.add(url)
            sections_seen.add(url)

        # now scan this section page for article links
        section_arts = set()
        for a in doc.cssselect('body a'):
            url = a.get('href',None)
            if url is None:
                continue
            if art_url_pat.search(url) is None:
                continue
            o = urlparse.urlparse( url )
            if o.hostname not in domain_whitelist:
                continue

            section_arts.add(url)

        ukmedia.DBUG("%s: found %d articles\n" % (section_url,len(section_arts)) )
        arts.update(section_arts)

    return list(arts)










def Extract(html, context, **kw):
    art = context

    # we know it's utf-8 (lxml.html seems to get it wrong sometimes)
    parser = lxml.html.HTMLParser(encoding='utf-8')
    doc = lxml.html.document_fromstring(html, parser, base_url=art['srcurl'])
    doc.make_links_absolute(art['srcurl'])

    main_div = doc.cssselect('#articlebody')[0]

    h1 = main_div.cssselect('h1')[0]
    art['title'] = u' '.join(unicode(h1.text_content()).split())

    bylines = main_div.cssselect('.display-byline')
    if bylines:
        art['byline'] = u' '.join(unicode(bylines[0].text_content()).split())
    else:
        art['byline'] = u''

    pubdate_txt = doc.cssselect('meta[property="article:published_time"]')[0].get('content')
    art['pubdate'] = ukmedia.ParseDateTime(pubdate_txt)

    content_div = main_div.cssselect('#bodyText')[0]
    art['content'] = ukmedia.SanitiseHTML(unicode(lxml.html.tostring(content_div)))
    art['description'] = ukmedia.FirstPara( art['content'] )


    return art




tidyurl_pat = re.compile( "(http:[/][/].*[/]article\\d+[.]ece)([?].*)?" )
def TidyURL( url ):
    """Sun urls can have params (eg to say they came from an rss feed)."""
    url = tidyurl_pat.sub( "\\1", url )
    return url



def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    url = TidyURL(url)
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = CalcSrcID( url )
    context['srcorgname'] = u'sun'
    context['lastseen'] = datetime.now()
    return context


def Prep():
    """ Perform a login """

    # credentials in config file
    THESUN_CONFIG_FILE = '../conf/thesun.ini'
    config = ConfigParser.ConfigParser()
    config.read(THESUN_CONFIG_FILE)
    username = config.defaults()[ 'username' ]
    password = config.defaults()[ 'password' ]


    ukmedia.DBUG2( "Logging in as %s\n" % (username,) )
    postdata = urllib.urlencode({
        'username':username,
        'password':password,
    #   'keepMeLoggedIn':'false'
        })
    req = urllib2.Request( "https://login.thesun.co.uk/", postdata );
    resp = urllib2.urlopen( req )
    ukmedia.DBUG2( "Login returned %s %s\n" % (resp.getcode(),resp.geturl()) )

    # the login request always returns 200. We know it works if it redirects
    # us to the sun front page
    if 'login.thesun.co.uk' in resp.geturl():
       raise Exception("Login failed.") 


if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=150, prep=Prep )

