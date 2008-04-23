import site; site.addsitedir('../pylib')

import sys
import re
import feedparser
from htmlentitydefs import entitydefs
from BeautifulSoup import BeautifulSoup as Soup, BeautifulStoneSoup as XmlSoup
from JL import ukmedia
from telegraph_journo import absurl

def get_feeds():
    '''
    Returns a list of (url, title) pairs of Telegraph blogs.
    '''
    soup = Soup(ukmedia.FetchURL('http://blogs.telegraph.co.uk/'))
    bloglist = soup.find('div', id='blogList')
    links = []
    for li in bloglist.ul('li', {'class': 'ico iMore'}):
        url = li.a['href']
        url = absurl(url, 'http://blogs.telegraph.co.uk/')
        links.append((url, li.a.renderContents(None)))
    return links

feedurls = get_feeds()
for url, title in feedurls:
    ukmedia.DBUG2(u'%s %r\n' % (url, title))
    soup = Soup(ukmedia.FetchURL(url))
    rss = None
    # The RSS feed contains the author, the Atom feed doesn't!
    for type in 'application/rss+xml', 'application/atom+xml':
        links = soup.head('link', rel='alternate', type=type)
        if links:
            rss = ukmedia.FetchURL(links[0]['href'])
            break
    if not rss:
        continue
    feed = feedparser.parse(rss, agent='JournalistedBot')
    if feed.bozo:
        ukmedia.DBUG2(u'BOZO! %r %s\n' % ((feed.bozo_exception,)*2))
    elif feed.entries:
        # TODO: store the email address and blog link for each author
        ukmedia.DBUG2(u'%d entries, e.g.: %r %s\n' %
            (len(feed.entries), feed.entries[0].title, feed.entries[0].author))
    else:
        ukmedia.DBUG2('No entries.\n')
