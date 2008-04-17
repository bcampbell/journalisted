#!/usr/bin/env python2.4
#
# Copyright (c) 2008 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

'''
Scrapes articles from The Telegraph per-journo pages linked from COLUMNISTS_URL.
'''

import sys
import re
import urllib
import urlparse

from telegraph import ScraperUtils, ContextFromURL, Extract, ukmedia
from telegraph import BeautifulSoup


COLUMNISTS_URL = ('http://www.telegraph.co.uk/opinion/main.jhtml?'
    'menuId=6795&menuItemId=-1&view=DISPLAYCONTENT&grid=A1&targetRule=0')


def absurl(url, base_url):
    '''Makes url be absolute, assuming it was a link on a page at base_url.'''
    http, computer = urlparse.urlparse(base_url)[:2]
    base_url = '%s://%s/' % (http, computer)
    return urllib.basejoin(base_url, url)

def FindArticles():
    ukmedia.DBUG2("*** telegraph_journo ***: looking for articles...\n")
    foundarticles = []
    for url in FindColumnistURLs():
        # Read multiple pages of results building up article_urls.
        html = ukmedia.FetchURL(url)
        soup = BeautifulSoup.BeautifulSoup(html)
        bio = soup.find('div', {'class':'summarytrue'}).renderContents(None)
        bio = ukmedia.SanitiseHTML(bio).replace('<br><br>', '')
        journo_upper = soup.find('div', {'class':'boxhdnolink'})
        if journo_upper:
            journo_upper = journo_upper.renderContents(None).encode('utf-8')
            ukmedia.DBUG2(journo_upper + ':')
        ukmedia.DBUG2(bio.encode('utf-8'))
        SaveJournoBio(bio)
        article_links = soup('a', {'class': 'main'})
        articles = [absurl(a['href'], url) for a in article_links]
        ukmedia.DBUG2('(%d articles) ' % len(articles))
        foundarticles += articles
        ukmedia.DBUG2('\n')
    ukmedia.DBUG2( "Found %d articles\n" % len(foundarticles) )
    return [ContextFromURL(url) for url in foundarticles]

def FindColumnistURLs():
    '''
    Searches the page at COLUMNISTS_URL for the list of links to
    pages about each columnist, returns the URLs.
    '''
    html = ukmedia.FetchURL(COLUMNISTS_URL)
    soup = BeautifulSoup.BeautifulSoup(html)
    urls = []
    for div in soup('div', {'class': 'menu2'}):
        url = div.a['href']
        url = re.sub(r'targetRule=\d+', 'targetRule=9999', url)  # no pagination
        url = absurl(url, COLUMNISTS_URL)
        urls.append(url)
    return urls

def SaveJournoBio(bio):
    # TODO: Implement SaveJournoBio.
    # journo_bio table needs columns for context['srcorgname'] ("srctype"?)
    # and context['srcurl'].
    pass

if __name__=='__main__':
    ScraperUtils.RunMain(FindArticles, ContextFromURL, Extract)
