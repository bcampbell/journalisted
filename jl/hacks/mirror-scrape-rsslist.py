#!/usr/bin/env python2.4
#
# get a list of RSS feeds from The Mirror and Sunday Mirror
#

import re
import sys
import urllib2

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia


# mirror

f = urllib2.urlopen( "http://www.mirror.co.uk/more/" )
html = f.read()
f.close()

soup = BeautifulSoup( html )

bnav = soup.find( 'div', {'class':'bnav'} )

print "mirror_rssfeeds = {"
for a in bnav.findAll('a'):
	name = ukmedia.FromHTML(a.string)
	url = a['href'] + "rss.xml"
	print "\t\"%s\": \"%s\"," %( name,url )
print "}"


# sunday mirror
f = urllib2.urlopen( "http://www.sundaymirror.co.uk/more/" )
html = f.read()
f.close()

soup = BeautifulSoup( html )


print "sundaymirror_rssfeeds = {"
for a in soup.findAll('a',{'class':'nav-rss-lnk'} ):
	name = ukmedia.FromHTML(a.img['alt'])
	name = re.sub( "\s*\[RSS\]\s*", "", name )
	url = a['href']
	print "\t\"%s\": \"%s\"," %( name,url )
print "}"




