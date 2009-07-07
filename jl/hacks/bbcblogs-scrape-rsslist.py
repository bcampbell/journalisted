#!/usr/bin/env python2.4

import sys
import urlparse
import urllib2
import re
from time import strftime

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia


def RSSLinksFromPage( page_url ):
    urls = []
    html = ukmedia.FetchURL( page_url )
    soup = BeautifulSoup( html )
    for link in soup.head.findAll( 'link', {'type': ('application/rss+xml') } ):
        # a lot of blogs also have atom: 'application/atom+xml', but we'll just look for rss
        urls.append( urlparse.urljoin( page_url, link['href'] ) )
    return urls
     


def FindBlogFeeds():
    feeds = []
    page_url = "http://www.bbc.co.uk/blogs"
    html = ukmedia.FetchURL( page_url )
    soup = BeautifulSoup( html )

    blog_div = soup.find( 'div', {'id':'moreblogs'} )
    for a in blog_div.findAll( 'a' ):
        if not a['href'].startswith('/blogs/'):
            print >>sys.stderr, "WARNING - unexpected link: '%s'" % (a['href'])
            continue

        blog_url = urlparse.urljoin( page_url, a['href'] )
        rss_urls = RSSLinksFromPage( blog_url )
        txt = ukmedia.FromHTMLOneLine( a.renderContents(None) )
        for rss_url in rss_urls:
            feeds.append( (txt,rss_url) )
    return feeds


def main():
    feeds = FindBlogFeeds()

    OUTFILENAME = "bbcblogs_rss.out"

    outfile = open( OUTFILENAME, 'w' )
    outfile.write( "# bbc blog feedlist automatically scraped by %s\n" %( sys.argv[0] ) )
    outfile.write( "# (run %s)\n" % ( strftime("%Y-%m-%d %H:%M:%S") ) )
    outfile.write( "# got %d feeds\n" %( len( feeds ) ) )
    outfile.write( "blog_feeds = [\n" )
    for f in feeds:
        name = f[0].encode('ascii','replace')
        url = f[1].encode('ascii','replace')
        outfile.write( "    (\"%s\", \"%s\"),\n" % (name,url) )
    outfile.write( "]\n" )

    print( "Wrote output to %s" %(OUTFILENAME) )

if __name__ == "__main__":
	main()

