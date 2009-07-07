#!/usr/bin/env python2.4

import sys
import urlparse
import urllib2
import re
from time import strftime

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia





pagequeue =  set()
seen = set()
feeds = []



def ShouldFollowURL( url ):
    o = urlparse.urlparse( url )

    accepted_domains = (
        re.compile( 'blogs[.]telegraph[.]co[.]uk' ),
        re.compile( '(www[.])?telegraph[.]co[.]uk' )
    )

    ok=0
    for pat in accepted_domains:
        if pat.match( o[1] ) is not None:
            ok=1
    if not ok:
#        print " skip page [%s]" % ( url )
        return False

    return True


def DoPage( page_url ):
    if page_url in seen:
#        print "seen: ", url
        return

    print "processing: ", page_url

    try:
        html = ukmedia.FetchURL( page_url )
    except urllib2.HTTPError, e:
        if e.code == 404:
            print >>sys.stderr, "404 Not found - skipping [%s]" % (url)
            return
        raise


    soup = BeautifulSoup( html )
    seen.add( page_url )

    # guess a name for the feed
    rss_name = soup.find('title').renderContents(None)
    h1 = soup.find('h1')
    if h1:
        rss_name = h1.renderContents(None)


    # find feed(s) for this page
    found = 0
    # look for <a class="rss"> links first:
    for a in soup.findAll( 'a', {'class':'rss'} ):
        if a['href'] == '/feeds':
            continue
        rss_url = a['href']
        rss_name = a.renderContents(None)
        feeds.append( (rss_name,rss_url) )
        found = found + 1

    # none found? then look in <head> links
    if found == 0:
        feed = soup.head.find( 'link', {'type':"application/rss+xml"} )
        if feed:
            rss_url = feed[ 'href' ]
            feeds.append( (rss_name,rss_url) )
#            print " %s: %s" % (thispage, rss_url )



    # find other pages to check
    if 'blogs.telegraph.co.uk' in page_url:
        # for blog pages... (list of blogs on right hand side)
        for li in soup.findAll( 'li', {'class':re.compile("iMore")} ):
                a = li.a
                if a is None:
                    continue
                a_url = urlparse.urljoin(page_url,a['href'])
                if ShouldFollowURL( a_url ):
                    pagequeue.add( a_url )
    else:
        # for main site...
        for nav_id in ('tmglPrimaryNav', 'tmglSecondNav', 'tmglThirdNav' ):
            foo = soup.find( 'div', {'id':nav_id} )
            if foo is None:
                continue
            ul = foo.find( 'ul', {'class':'mainNav'} )
            if ul is None:
                continue
            for li in ul.findAll( 'li' ):
                a = li.a
                if a is None:
                    continue
                a_url = urlparse.urljoin(page_url,a['href'])
                if ShouldFollowURL( a_url ):
                    pagequeue.add( a_url )

    # special case to scan columnists page
    if page_url == 'http://www.telegraph.co.uk/comment/columnists/':
        # looking for the sidebar which lists columnists
        for div in soup.findAll('div', {'class':'summary'}):
            if div.find(text='Columnists'):
                for a in div.findAll('a'):
                    a_url = urlparse.urljoin(page_url,a['href'])
                    if ShouldFollowURL( a_url ):
                        pagequeue.add( a_url )


def main():
    pagequeue.add( 'http://www.telegraph.co.uk/' )
    pagequeue.add( 'http://blogs.telegraph.co.uk/' )

    while len( pagequeue ) > 0:
        url = pagequeue.pop()
        DoPage( url )

    OUTFILENAME = "telegraph_rss.out"

    outfile = open( OUTFILENAME, 'w' )
    outfile.write( "# telegraph non-blog feedlist automatically scraped by %s\n" %( sys.argv[0] ) )
    outfile.write( "# (run %s)\n" % ( strftime("%Y-%m-%d %H:%M:%S") ) )
    outfile.write( "# got %d feeds\n" %( len( feeds ) ) )
    outfile.write( "rssfeeds = [\n" )
    for f in feeds:
        name = f[0].encode('ascii','replace')
        url = f[1].encode('ascii','replace')
        outfile.write( "    (\"%s\", \"%s\"),\n" % (name,url) )
    outfile.write( "]\n" )

    print( "Wrote output to %s" %(OUTFILENAME) )

if __name__ == "__main__":
	main()

