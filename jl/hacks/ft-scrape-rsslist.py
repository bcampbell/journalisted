#!/usr/bin/env python2.4
#
# get a list of RSS feeds from The FT
#
# TODO:
# Filter out unicode chars from name (there are a couple of 0xe2 apostrophes which
# cause warnings when used in python sourcecode)

import re
import sys
import urllib2
import urlparse

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia




def DoIt( masterpage ):
	feedpat = re.compile( "http://.*" )
	f = urllib2.urlopen( masterpage )
	html = f.read()
	f.close()

	soup = BeautifulSoup( html )


	#<div class="splitcolcontainer" id="rssnews">
	c = soup.find( 'div', id='rssnews' )
	for a in c.findAll('a', href=feedpat):
		a.img.extract()
		name = ukmedia.FromHTML(a.renderContents(None))
		url = a['href']	#urlparse.urljoin( masterpage, a['href'] )
		o = urlparse.urlparse(url)
		if o[1] not in ('www.ft.com','blogs.ft.com' ):
			continue

		# their blog RSS links are wrong
		if o[1] == 'blogs.ft.com':
			url = url.replace( '/rss.xml', '/feed/' )

		print "\t\"%s\": \"%s\"," %( name.encode('utf-8'),url.encode('utf-8') )


print "ft_rssfeeds = {"
DoIt( 'http://www.ft.com/servicestools/newstracking/rss' )
print "}"


