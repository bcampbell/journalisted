#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

'''
Scrapes wikipedia articles (headings and paragraphs).
'''

# Doubtless I'll find that there's a pre-XML-ified version of wikipedia
# somewhere, but until then...

import sys
import re
import urllib2

sys.path.append('../pylib')
from BeautifulSoup import BeautifulSoup
from JL import DB, ukmedia, ScraperUtils
from datetime import datetime


if 0: # not needed, provided by ScraperUtils with --url
    def ScrapeArticle(url):
        '''Wrapper for testing on a single URL.'''
        context = ContextFromURL(url)
        req = urllib2.Request(url, headers={'User-Agent': 'JournalistedBot'})
        html = urllib2.urlopen(req).read()
        return Extract(html, context)


def Extract(html, context):
    ukmedia.DBUG2( "*** wikipedia ***: scraping %s\n" % context['srcurl'])

    if 'Wikipedia does not have an article with this exact name' in html:
        raise Exception("No article with this exact name.")

    if 'href="/wiki/Journalist"' not in html:
        raise Exception("No link to 'Journalist' page found, may be wrong person.")

    m = re.search(r'<li id="lastmod"> This page was last modified on (.*?, at \d\d:\d\d).</li>', html)
    if m is None:
        raise Exception("No modification date.")  # probably no article
    context['pubdate'] = ukmedia.ParseDateTime(m.group(1))
    
    soup = BeautifulSoup(html)
    
    keywords = []
    for meta in soup.findAll('meta'):
        attrs = dict(meta.attrs)
        if attrs.get('name')=='keywords' and attrs.get('content'):
            keywords.append(attrs.get('content'))
    context['keywords'] = ','.join(keywords)
    
    # Remove relative links and [<number>] links.
    for a in soup.findAll('a'):
        if a.string and re.match(r'\[\d+\]', a.string):
            a.extract()
        elif not a.get('href', '').startswith('http://'):
            a.replaceWith(a.string or '')

    # Remove [edit] spans in headings
    for span in soup.findAll('span', {'class': ['editsection', 'mw-headline']}):
        if span['class'] == 'editsection':
            span.extract()
        else:
            span.replaceWith(span.string or '')
    
    # Remove references
    for sup in soup.findAll('sup', attrs={'class': 'reference'}):
        sup.extract()
    
    tags = soup.body.findAll(['h2', 'p'])

    def strip(s):
        '''Strip outer whitespace from s, including <br /> tags.'''
        s = re.search(r'(?s)\s*(?:<br\s*/>\s*)*(.*)', s).group(1)
        return re.search(r'(?s)(.*?)\s*(?:<br\s*/>\s*)*\Z', s).group(1)

    # Remove paragraphs with no strings and headings with no paragraphs.
    i = 1
    while i < len(tags):
        if tags[i].name=='h2' and tags[i-1].name=='h2':
            tags.pop(i-1)
        elif tags[i].name=='p' and not strip(tags[i].renderContents(None)):
            tags.pop(i)
        else:
            i += 1
    
    while tags and tags[-1].name=='h2':
        tags.pop()
    
    # Replace contents heading with an empty paragraph tag.
    # Since it's now the only one, we can use it to detect the
    # end of the introductory paragraphs.
    #
    # Also strip tag contents and stringify.
    
    for i, tag in enumerate(tags):
        if tag.name=='h2' and tag.string=='Contents':
            tags[i] = u'<p></p>'
        else:
            tagname = unicode(tag.name)
            tags[i] = u'<%s>%s</%s>' % \
                (tagname, strip(tag.renderContents(None)), tagname)
    
    text = '\n'.join(tags)
    
    # Replace ", ." with ".". Arises from our removing cross-references.
    text = re.sub(r',\s*\.', '.', text)
    
    context['content'] = text
    return context


def FindArticles():
    ukmedia.DBUG2( "*** wikipedia ***: generating URLs for journalists...\n" )
    conn = DB.Connect()
    c = conn.cursor()
    c.execute("SELECT prettyname FROM journo")
    found = []
    for row in c.fetchall():
        ctx = ContextFromURL('http://en.wikipedia.org/wiki/%s'
                             % row[0].replace(' ', '_'))
        found.append(ctx)
    return found


def ContextFromURL(url):
    """Build up an article scrape context from a bare url."""
    context = {}
    prettyname = url.split('/')[-1].replace('_', ' ')
    context['title'] = u'wikipedia: ' + prettyname
    context['description'] = u'Wikipedia article on ' + prettyname
    context['srcurl'] = url
    context['permalink'] = url
    context['srcid'] = url
    context['srcorgname'] = u'wikipedia:journo'
    context['lastseen'] = datetime.now()
    context['byline'] = u''
    return context


if __name__=='__main__':
    ScraperUtils.RunMain(FindArticles, ContextFromURL, Extract)
    if 0:
        import pprint
        args = sys.argv[1:]
        if '--help' in args or '-h' in args:
            sys.exit('usage: wikipedia.py [URL]\n'
                     '"file:" URLs are supported for local files. '
                     'With no URL, runs scraper framework.')
        if len(args) == 1:
            url = sys.argv[1]
            if re.match('[a-zA-Z_\ ]+$', url):
                url = 'http://en.wikipedia.org/wiki/' + url.replace(' ', '_')
            pprint.pprint(ScrapeArticle(url))
        elif len(args)==0:
            ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )
        else:
            sys.exit('usage: wikipedia.py URL  (may be "file:..." for local files)')
