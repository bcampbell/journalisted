#!/usr/bin/env python2.4
#
# get a list of RSS feeds from the DailyMail website
# (they have a master page which lists 'em all)
#

import re
import sys
import urllib2

sys.path.append("../pylib")
from JL import ukmedia

url = "http://www.dailymail.co.uk/pages/dmstandard/article.html?in_article_id=334032"

f = urllib2.urlopen(url)
html = f.read()
f.close()

# eg:
# <a href="/pages/xml/index.html?in_page_id=1766"><img src="http://img.dailymail.co.uk/i/std/rssIcon.gif" width="27" height="15" alt="Homepage RSS feed" hspace="3" border="0">Homepage</a>

outline_pat = re.compile( '\\s*<a href="(.*?)".*<img src=".*/rssIcon.gif".*>(.*?)</a>' )

for m in outline_pat.finditer( html ):
	feedurl = m.group(1)
	# some are relative, some are full urls (link to feedburner)
	if feedurl.startswith( '/' ):
		feedurl = "http://www.dailymail.co.uk" + feedurl

	title = m.group(2)
	print "\t'%s': '%s'," % (title,feedurl)



