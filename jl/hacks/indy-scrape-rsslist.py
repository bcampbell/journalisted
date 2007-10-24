#!/usr/bin/env python2.4
#
# get a list of RSS feeds from The Independent website
# (they have a master page which lists 'em all)
#

#import re
#from datetime import date,datetime,timedelta
#import time
import sys
import urllib2

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia

url = "http://news.independent.co.uk/article293771.ece"

f = urllib2.urlopen(url)
html = f.read()
f.close()

soup = BeautifulSoup( html )

menudiv = soup.find( 'div', id='rssmenu' )

for a in menudiv.findAll( 'a' ):

	indent = 0
	foo =a
	while foo.parent != menudiv:
		foo = foo.parent
		indent = indent+1

	name = a.string
	name = name.replace( '&nbsp;', ' ')
	name = ukmedia.DescapeHTML( name )
	name = name.encode( 'ASCII', 'replace' )

	url = a['href']
	print "\t'%s%s': '%s'," % (' '*indent,name,url)


