#!/usr/bin/env python2.4
#
# get a list of RSS feeds from The Scotsman
#

import re
import sys
import urllib2
import urlparse

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia


masterpage = "http://thescotsman.scotsman.com/webfeeds.aspx"
feedpat = re.compile( "getFeed[.]aspx[?]Format=rss&sectionid=[0-9]+" )


f = urllib2.urlopen( masterpage )
html = f.read()
f.close()

soup = BeautifulSoup( html )

print "scotsman_rssfeeds = {"
for a in soup.findAll('a', href=feedpat):
	name = ukmedia.FromHTML(a.string)
	url = urlparse.urljoin( masterpage, a['href'] )
	print "\t\"%s\": \"%s\"," %( name,url )
print "}"


