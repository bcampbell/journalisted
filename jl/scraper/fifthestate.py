#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

'''
Scrapes the bios of authors at 5thEstate.co.uk.
'''

import sys
import urllib2

sys.path.append('../pylib')
from JL import DB, ukmedia, ScraperUtils
from BeautifulSoup import BeautifulSoup
from datetime import datetime
import time
import re


AUTHORS = {}  # URL -> author, populated by FindAuthors

def Extract(html, context):
    '''Scrapes a particular article.'''
    soup = BeautifulSoup(html)
    text = soup.find('div', {'class': 'content'}).renderContents(None)
    context['content'] = ukmedia.DescapeHTML(ukmedia.SanitiseHTML(text))
    context['title'] = ukmedia.DescapeHTML(soup.h1.string)
    context['description'] = context['title']  # FIXME: should allow empty, surely?
    meta = soup.find('div', {'class':'meta'})
    if meta is None:
        byline = re.split(r'\s*&(?:#183|middot);\s*', soup.head.title.string)[1]
        pubdate = soup.find('div', id='entries') \
                      .find('div', {'class': 'recent'}) \
                      .h2.findNext('p', {'class': 'metadata'}).string
    else:
        byline = meta.h3.a.string
        pubdate = meta.p.string
    context['byline'] = byline
    context['pubdate'] = ukmedia.ParseDateTime(pubdate)
    license = re.search(u'<a rel="license" href="(.*?)">', html, re.UNICODE)
    context['license'] = license and license.group(1) or None
    return context

def Download(url):
    time.sleep(1)  # don't get banned
    return urllib2.urlopen(url).read()

def FindArticles():
    '''
    Returns a list of URLs of author pages.
    '''
    global AUTHORS
    html = Download('http://fifthestate.co.uk/')
    soup = BeautifulSoup(html)
    authors_ul = soup.find('ul', {'class': 'navlist', 'id':'authors-list'})
    links = authors_ul.findAll('a')
    AUTHORS = {}
    for a in links:
        author = a.string
        author_url = a['href']
        AUTHORS[author_url] = author

    # Scrape individual author pages
    all_articles = []
    for i, (author_url, author) in enumerate(AUTHORS.items()):
        ukmedia.DBUG2('*** fifthestate: getting article list: %s\n' % author_url)
        all_articles += ScrapeAuthorPage(Download(author_url))
        if MAX_ARTICLES is not None and i==MAX_ARTICLES-1:
            ukmedia.DBUG2('*** fifthestate: --max=%d: stopping after %d articles\n'
                          % (MAX_ARTICLES, MAX_ARTICLES))
            break
    return all_articles

def ScrapeAuthorPage(html):
    '''
    Scrapes a FifthEstate page listing articles by a particular author.
    Called internally by FindArticles.
    Returns a list of article contexts.
    '''
    soup = BeautifulSoup(html)
    profile = soup.find('div', id='profile') \
                  .find('div', {'class': 'content'})
    bio = ukmedia.DescapeHTML(ukmedia.SanitiseHTML(profile.renderContents(None).strip()))
    articles_div = soup.find('div', id='entries') \
                       .find('div', {'class': 'recent'})
    articles = []
    for a in articles_div.findAll('a'):
        context = ContextFromURL(a['href'])
        context['bio'] = bio  # FIXME: replicated
        articles.append(context)
    return articles

def ContextFromURL(url):
	context = {}
	context['permalink'] = url
	context['srcid'] = url
	context['srcorgname'] = u'fifthestate'
	context['srcurl'] = url
	context['lastseen'] = datetime.now()
	context['author'] = AUTHORS.get(url, None)
	context['pubdate'] = context['lastseen'] = datetime.now()
	return context

if __name__=='__main__':
    import sys
    MAX_ARTICLES = None
    for arg in sys.argv:
        if arg.startswith('--max='):
            MAX_ARTICLES = int(arg[len('--max='):])
            sys.argv.remove(arg)
    ScraperUtils.RunMain(FindArticles, ContextFromURL, Extract)
