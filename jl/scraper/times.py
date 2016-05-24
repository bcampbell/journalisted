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
import cookielib
import ConfigParser
import contextlib
import lxml.html

import site
site.addsitedir("../pylib")
from JL import ukmedia, ScraperUtils

TIMESPLUS_CONFIG_FILE = '../conf/timesplus.ini'


def Prep(sesh):
    """ perform a login """

    assert sesh is not None

    config = ConfigParser.ConfigParser()
    config.read( TIMESPLUS_CONFIG_FILE )
    timesplus_username = config.defaults()[ 'username' ]
    timesplus_password = config.defaults()[ 'password' ]


    # the login page bounces us through 5 or 6 different domains to pick up cookies
    ukmedia.DBUG2( "Logging in as %s\n" % (timesplus_username) )
    postdata = urllib.urlencode( {'username':timesplus_username,'password':timesplus_password, 'rememberMe': 'on' } )

    with contextlib.closing(sesh.open("https://login.thetimes.co.uk",postdata)) as resp:
        for code,url in resp.redirects:
            ukmedia.DBUG2( " -> %s %s\n" % (code,url))

    # OK... should now be logged in



art_url_pat = re.compile(r"^.*/[^/]+-[^/]+$", re.I)


def FindArticles(sesh):

    base_url = "http://www.thetimes.co.uk"
    past6days = base_url + "/past-six-days"


    parser = lxml.html.HTMLParser(encoding='utf-8')

    section_pages = []
    with contextlib.closing(sesh.open(past6days)) as resp:
        html = resp.read()
        doc = lxml.html.document_fromstring(html, parser, base_url=base_url)
        doc.make_links_absolute(base_url)
        for a in doc.cssselect("ul.EditionList a"):
            u = a.get('href')
            u = TidyURL(u)
            if '/puzzles' in u:
                ukmedia.DBUG2("skip section %s\n" % (u,))
                continue
            section_pages.append(u)

    link_threshold = len("http://www.thetimes.co.uk/past-six-daysPADPAD")


    found = set()
    for sect_url in section_pages:
        links = []
        with contextlib.closing(sesh.open(sect_url)) as resp:
            html = resp.read()
            doc = lxml.html.fromstring(html)
            doc.make_links_absolute(sect_url)
            for a in doc.cssselect("a"):
                u = a.get('href')
                o = urlparse.urlparse( u )
                if o.hostname != 'www.thetimes.co.uk':
                    continue
                # strip query, fragment
                u = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
                u = u.strip()
                if len(u) < link_threshold:     # probably a section link
                    continue
                if art_url_pat.search(u):
                    print("ACCEPT %s\n" %(u,))
                    links.append(u)

        ukmedia.DBUG2("%s (%d article links)\n" % (sect_url, len(links)))
        for l in links:
            found.add(l)

    return [ContextFromURL(url) for url in found]







def Extract(html, context, **kw):
    art = context
    art['srcorgname'] = u'times';
    return None


def TidyURL( url ):
    """ Tidy up URL - trim off params, query, fragment... """
    o = urlparse.urlparse( url )
    url = urlparse.urlunparse( (o[0],o[1],o[2],'','','') );
    return url

def ContextFromURL( url ):
    """Build up an article scrape context from a bare url."""
    url = TidyURL(url)
    context = {}
    context['srcurl'] = url
    context['permalink'] = url
    context['srcorgname'] = u'times'
    context['lastseen'] = datetime.now()
    return context



if __name__ == "__main__":
    # create a url opener which remembers cookies (as well as throttling and all the other uber-opener stuff)
    cj = cookielib.LWPCookieJar()
    opener = ScraperUtils.build_uber_opener(cookiejar=cj)

    # large maxerrors to handle video-only pages
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=200, prep=Prep, sesh=opener )


