#!/usr/bin/env python2.4
#
# get a list of RSS feeds from the Telegraph website
# (they have a master page which lists 'em all)
#

import re
import sys
import urllib2

sys.path.append("../pylib")
from JL import ukmedia

url = "http://www.telegraph.co.uk/feeds.opml"

f = urllib2.urlopen(url)
xml = f.read()
f.close()

# eg:
#<outline text="Telegraph | Arts"				title="Telegraph | Arts"				xmlUrl="http://www.telegraph.co.uk/newsfeed/rss/arts.xml" type="rss" language="en-gb" />
outline_pat = re.compile( '\\s*<outline.*title="(.*?)".*xmlUrl="(.*?)"' )

for m in outline_pat.finditer( xml ):
	title = m.group(1)
	feedurl = m.group(2)
	print "\t'%s': '%s'," % (title,feedurl)



