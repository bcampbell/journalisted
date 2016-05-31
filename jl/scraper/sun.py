#!/usr/bin/env python
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# NOTE: The sun isn't a real web site - it's a (more-or-less)
# single page app which uses javascript to fetch and display
# articles.

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

try:
    import json
except ImportError:
    import simplejson as json


base_url = 'http://www.thesun.co.uk'


def jsonify_url(url):
    """ convert a sun html url to it's webapp equivalent

    The sun isn't a real website - all the pages are just
    stubs which fetch json and display it via javascript.
    All article and section URLs have equivalent json URLs.
    """

    return url.replace("thesun.co.uk/sol/", "thesun.co.uk/web/thesun/sol/")

def ContextFromURL( url ):
    """get a context ready for scraping a single url"""
    context = {}
    context['permalink'] = url
    context['srcorgname'] = u'sun'
    # Fudge the srcurl to grab the json data
    context['srcurl'] = jsonify_url(url)
    context['lastseen'] = datetime.now()
    return context


def Extract( html, context, **kw ):
    art = context
    # not really html - it's json
    dat = json.loads(html)

    art['title'] = ukmedia.FromHTMLOneLine(dat['headline'])
    art['content'] = dat['articleBody']
    unix_time = dat['articlePublishedTimestamp']/1000
    art['pubdate'] = datetime.fromtimestamp(unix_time)
    if 'authorByline' in dat:
        art['byline'] = ukmedia.FromHTMLOneLine(dat['authorByline']['byline'])
    else:
        art['byline'] = u''

    return art

def FindArticles(sesh):

    arts = set()
    section_urls = find_sections(sesh) 
    for sect_url in section_urls:
        raw = ukmedia.FetchURL(jsonify_url(sect_url), sesh=sesh)
        dat = json.loads(raw)

        for teaser in dat['articleTeasers']:

            url = teaser['articleUrl']
            if teaser['articleType'] != u'article':
#                ukmedia.DBUG("WARN NONARTICLE: %s\n" %(url,))
                continue

            if not url.startswith('/'):
#                ukmedia.DBUG("WARN FOOK1: %s\n" %(url,))
                continue
            # articleType 'article'
            url = base_url + url
            arts.add(url)

    return [ ContextFromURL(u) for u in arts ]



def find_sections(sesh):

    menu_url = base_url + '/web/thesun/sol/resources/menu/all/471/'
    raw = ukmedia.FetchURL(menu_url, sesh=sesh)
    sects = json.loads(raw)
    #pprint( sects)

    urls = set()
    for foo in sects:
        for item in foo['items']:
            if 'url' not in item:
                continue
            url = item['url']
            if not url.startswith('/'):
                continue
            if u'.html' in url:
                continue
            if u'.ece' in url:
                continue
            if u'/page3/' in url:
                continue
            if u'/video/' in url:
                continue

            url = base_url + url
            urls.add(url)

    return urls




    raw = ukmedia.FetchURL(url, sesh=sesh)
    raw = ukmedia.FetchURL(url, sesh=sesh)
    return json.loads(raw)



if __name__ == "__main__":
    ScraperUtils.scraper_main( FindArticles, ContextFromURL, Extract, max_errors=50 )

