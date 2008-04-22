#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

'''
Reads BBC blog feeds.
'''

import sys
import os
import re
import site; site.addsitedir('../pylib')
import feedparser
from BeautifulSoup import BeautifulSoup as Soup
from JL import ukmedia
from urllib2 import HTTPError, HTTPErrorProcessor
from urlparse import urlsplit, urlunsplit
from StringIO import StringIO
from telegraph_journo import absurl

def cleanurl(url):
    prefix, host, path, query, fragment = urlsplit(url)
    path = re.sub('/+', '/', path)
    return urlunsplit((prefix, host, path, query, fragment))

if 0:
 # This avoids a security hole in feedparser but provokes a bug in urllib2.
 # Sigh.
 class UrlHandler(feedparser._FeedURLHandler):
    def http_response(self, req, response):
        return response
        def clean(data):
            data = data.replace('&nbsp;', ' ')
            data = re.sub(r'<\?xml-stylesheet.*?\?>', '', data)
            soup = Soup(data)
            return unicode(soup).encode('utf-8')
        all = response.read()
        r = StringIO(clean(all))
        response.read = lambda *args: r.read(self, *args)
        response.readline = lambda *args: r.readline(self, *args)
        response.readlines = lambda *args: r.readlines(self, *args)
        return response

FEEDS = {}

def download(url):
    ukmedia.DBUG2('*<-- %s\n' % url)
    return ukmedia.FetchURL(url)

if 0:
 #FEEDS = {'debug': 'http://www.bbc.co.uk/blogs/chrismoyles/index.xml'}
 #FEEDS = {'debug': 'http://www.bbc.co.uk/comedy/blog/index.rdf'}
 FEEDS = {'debug': 'http://www.bbc.co.uk/blogs/arabic/atom.xml'}
else:
 soup = Soup(download('http://www.bbc.co.uk/blogs/'))
 blogdivL = soup.find('div', id='allblogsleft')
 blogdivR = soup.find('div', id='allblogsright')
 for a in blogdivL('a') + blogdivR('a'):
    url = a['href']
    soup2 = Soup(download(url))
    got = ''
    for type in 'application/rss+xml', 'application/atom+xml':
        try:
            got = soup2.head('link', rel='alternate', type=type)[0]['href']
            got = absurl(got, url)
        except IndexError:
            pass
    if not got:
        basename, filename = url.rsplit('/', 1)
        if filename:
            if '.' in filename:
                url = basename + '/'
            else:
                url += '/'
        got = url + 'rss.xml'
    got = cleanurl(got)
    FEEDS['bbc: ' + a.string.strip()] = got
    ukmedia.DBUG2('bbc: %s - %s\n' % (a.string.strip(), got))

#from pprint import pprint; pprint(FEEDS)

for feedname, url in FEEDS.items():
    ukmedia.DBUG2(url+'\n')
    # Alas, using our own URL handler (to clean up the response)
    # provokes a bug in Python's urllib2.
    try:
        html = ukmedia.FetchURL(url)
    except HTTPError, e:
        if e.code==404:
            ukmedia.DBUG2(u'HTTP 404: %s\n' % url)
            continue
        raise
    try:
        open(html)
        assert False, "would provoke feedparser security bug"
    except IOError:
        pass
    feed = feedparser.parse(html)
#    feed = feedparser.parse(url, agent='JournalistedBot', handlers=[UrlHandler()])
#    feed = feedparser.parse(url, agent='JournalistedBot')
    if feed.bozo: ukmedia.DBUG2('BOZO! %r %s\n' % ((feed.bozo_exception,)*2))
    for entry in feed.entries:
        author = entry.get('author', 'NO AUTHOR')
        ukmedia.DBUG2(u'%s %r %s\n' %
                      (entry.get('updated', 'NO DATE'), 
                       entry.get('title', 'NO TITLE'),
                       author))
    ukmedia.DBUG2('\n')

