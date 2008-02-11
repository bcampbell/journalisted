#!/usr/bin/env python2.4
#
# Scraper for NewsOfTheWorld
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# TODO:
# pubdate
#
#

import sys
import re
from datetime import datetime
import sys
import urllib2
import urlparse
import traceback

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils



rssfeeds = {
#	'News / Showbiz': 'http://www.express.co.uk/rss/news.xml',
}


def FindArticles():
	"""Gather articles to scrape from the notw website.

	Returns a list of scrape contexts, one for each article.
	"""
	ukmedia.DBUG2( "*** notw ***: looking for articles...\n" )

	urls = Crawl( 'http://www.newsoftheworld.co.uk' )

	found = []
	for url in urls:
		found.append( ContextFromURL( url ) )

	return found



# keep track of pages visited by Crawl(), so we don't process them
# multiple times
crawled = set()

# 
articleurlpat = re.compile( "http:[/][/]www[.]newsoftheworld[.]co[.]uk[/][0-9]+.*[.]shtml(#.*?)?([?].*)?" )

def Crawl( url, depth=0 ):
	"""Recursively crawl the sun website looking for article links.

	Returns a set containing article urls.
	"""

	global crawled
	maxdepth = 1	# Very shallow. We only go 1 level down.

	if depth==0:	# Starting a new crawl?
		crawled = set()

	articlelinks = set()
	indexlinks = set()

	if url in crawled:
		ukmedia.DBUG2( "(already visited '%s')\n" % (url) )
		return articlelinks

	try:
		html = ukmedia.FetchURL( url )
	except urllib2.HTTPError, e:
		# continue even if we get http errors (bound to be a borked
		# link or two)
		traceback.print_exc()
		print >>sys.stderr, "SKIP '%s' (%d error)\n" %(url, e.code)
		return articlelinks

	soup = BeautifulSoup( html )
	for a in soup.findAll( 'a' ):
		if not a.has_key( 'href' ):
			continue
		href = a['href'].strip()

		if href.startswith( 'javascript:' ):
			continue
		if href.startswith( 'mailto:' ):
			continue


		# handle relative links
		href = urlparse.urljoin( url, href )

		# discard external sites, discussion pages, login pages etc...
		if not href.startswith( 'http://www.newsoftheworld.co.uk' ):
			continue

		if articleurlpat.match( href ):
			articlelinks.add( href )
		else:
			indexlinks.add( href )

	crawled.add( url )
	ukmedia.DBUG2( "Crawled '%s' (depth=%d), found %d articles\n" % ( url, depth, len( articlelinks ) ) )

	if depth < maxdepth:
		for l in indexlinks:
			if not (l in crawled):
				articlelinks = articlelinks | Crawl( l, depth+1 )
			else:
				ukmedia.DBUG2( "  [already visited '%s']\n" % (l) )

	return articlelinks



def Extract( html, context ):
	art = context

	# notw claims to be iso-8859-1, but it seems to be windows-1252 really
	soup = BeautifulSoup( html, fromEncoding = 'windows-1252' )


	# sometimes have a graphical headline and a text one...
	headline = u''
	for h1 in soup.findAll( 'h1' ):
		headline = h1.renderContents( None )
		headline = ukmedia.FromHTML( headline )
		headline = u' '.join( headline.split() )
		if headline != u'':
			break

	art['title'] = headline

	td = h1.parent
	h1.extract()

	byline = u''
	bylinep = td.find( 'p', {'class':'byline'} )
	if bylinep:
		byline = ukmedia.FromHTML( bylinep.renderContents(None) )
		byline = u' '.join( byline.split() )
		bylinep.extract()

	art['byline'] = byline


	#
	for cruft in td.findAll( 'a', href=re.compile( 'notw.typepad.com/thebigone/' ) ):
		cruft.extract()

	for cruft in td.findAll( 'a' ):
		if cruft.find( text=re.compile( '^\\s*READ:' ) ):
			cruft.extract()


#	art['pubdate'] = pubdate
	content = td.renderContents( None )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	art['description'] = ukmedia.FirstPara( content )


	return art


def ScrubFunc( context, entry ):
	return context



# pattern to extract unique id from urls
# eg:
# "http://www.newsoftheworld.co.uk/1002_scroungers.shtml"
idpat = re.compile( "/([0-9]+[^/]*[.]shtml)$" )


def CalcSrcID( url ):
	m = idpat.search( url )
	return m.group(1)


def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'notw'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
#    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

	lst = FindArticles()
	for l in lst:
		print l['srcurl']

