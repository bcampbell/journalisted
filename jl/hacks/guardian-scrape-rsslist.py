#!/usr/bin/env python2.4

import sys
import urlparse
import urllib2
from time import strftime

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia

rooturl = 'http://www.guardian.co.uk'



blacklist = [
    # these two are missing
#    '/commentisfree/series/radiocif',
#    '/arts/studentpoliticseducation',
    ]

pagequeue =  set()
seen = set()
feeds = []


def ShouldFollowURL( url ):
    o = urlparse.urlparse( url )
    if o[1] == 'blogs.guardian.co.uk':
#        print " skip offsite page [%s]" % ( url )
        return False

    if not o[1].endswith( 'guardian.co.uk' ):
#        print " skip offsite page [%s]" % ( url )
        return False

    for b in blacklist:
        if b in url:
#            print " skip blacklisted page [%s]" % ( url )
            return False

    return True


def DoPage( url ):
    if url in seen:
#        print "seen: ", url
        return

#    print "processing: ", url

    try:
        html = ukmedia.FetchURL( url )
    except urllib2.HTTPError, e:
        if e.code == 404:
            print >>sys.stderr, "404 Not found - skipping [%s]" % (url)
            return
        raise


    soup = BeautifulSoup( html )
    seen.add( url )

    # construct a name for this page using crumbtrail
    thispage = 'UNKNOWN'
    crumbs = soup.find( 'div', {'id':'crumb-nav'} )
    if crumbs:
        parts = []
        for a in crumbs.findAll( 'a' ):
            parts.append( a.string )
        thispage = ' / '.join( parts )

    # find the feed for this page
    feed = soup.head.find( 'link', {'type':"application/rss+xml"} )
    if feed:
        rss_url = feed[ 'href' ]

        feed_url_ok=1
        rss_url_blacklist = ('/audio/','/video/','/gallery')
        for b in rss_url_blacklist:
            if b in rss_url:
                feed_url_ok = 0
                break

        if feed_url_ok:
            feeds.append( (thispage,rss_url) )
#        print " %s: %s" % (thispage, rss_url )


    # find other pages to check
    globnav = soup.find( 'div', {'id':'global-nav'} )
    if globnav:
        for a in globnav.findAll( 'a' ):
            url = a['href']
            if ShouldFollowURL( url ):
                pagequeue.add(url)

    localnav = soup.find( 'div', {'id':'local-nav'} )
    if localnav:
        for a in localnav.findAll( 'a' ):
            url = a['href']
            if ShouldFollowURL( url ):
                pagequeue.add(url)


def main():
    pagequeue.add( rooturl )

    while len( pagequeue ) > 0:
        url = pagequeue.pop()
        DoPage( url )

    print "# guardian non-blog feedlist automatically scraped by " + sys.argv[0]
    print "# (run %s)" % ( strftime("%Y-%m-%d %H:%M:%S") )
    print "# got", len( feeds ), "feeds"
    print "rssfeeds = ["
    for f in feeds:
        name = f[0].encode('ascii','replace')
        url = f[1].encode('ascii','replace')
        print "    (\"%s\", \"%s\")," % (name,url)
    print "]"

if __name__ == "__main__":
	main()

