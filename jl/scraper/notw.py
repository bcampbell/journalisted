#!/usr/bin/env python2.4
#
# Scraper for NewsOfTheWorld
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# NOTW have their content split into two: their own website, and on
# notw.typepad.com. Perhaps the former is for stuff that appears in
# print, and the typepad stuff is additional online-only content?
#
# notw rss feeds look pretty useless, so we do a shallow crawl for links.
# the typepad rss feeds would probably be OK...
#
# TODO:
# Can we split the crawling out (similar code in sun.py)?

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





def FindArticles():
	"""Gather articles to scrape from the notw website.

	Returns a list of scrape contexts, one for each article.
	"""
	ukmedia.DBUG2( "*** notw ***: looking for articles...\n" )

	urls = Crawl( 'http://www.newsoftheworld.co.uk' )

	found = []
	for url in urls:
		print url
		found.append( ContextFromURL( url ) )

	return found



# keep track of pages visited by Crawl(), so we don't process them
# multiple times
crawled = set()

# 
notw_artpat = re.compile( "newsoftheworld[.]co[.]uk[/][0-9]+.*[.]shtml(#.*?)?([?].*)?" )
typepad_artpat = re.compile( "notw.typepad.com/.*/\\d{4}/\\d{2}/.*[.]html" )

def Crawl( url, depth=0 ):
	"""Recursively crawl the sun website looking for article links.

	Returns a set containing article urls.
	"""

	global crawled
	maxdepth = 2	# Very shallow. We only go 2 levels in.

	if depth==0:	# Starting a new crawl?
		crawled = set()

	articlelinks = set()
	indexlinks = set()

	if url in crawled:
		ukmedia.DBUG2( "(already visited '%s')\n" % (url) )
		return articlelinks

	if '/mobile/' in url:
		ukmedia.DBUG2( "SKIP mobile page (%s)\n" % (url) )
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
		href = href.replace( '../', '' )	# cheesiness


		href = urlparse.urljoin( url, href )

		o = urlparse.urlparse( href)
		if o[0] != 'http':
			continue

		# discard external sites
		if o[1] not in ( 'www.newsoftheworld.co.uk', 'notw.typepad.com' ):
			continue

		# trim off fragments (eg '#comments')
		href = urlparse.urlunparse( (o[0], o[1], o[2], o[3], o[4],'') )

		if notw_artpat.search( href ) or typepad_artpat.search(href):
			# trim off parameters
			href = urlparse.urlunparse( (o[0], o[1], o[2], '', '', '') )
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
	o = urlparse.urlparse( context['srcurl'] )
	if o[1] == 'notw.typepad.com':
		return Extract_typepad( html, context )		
	else:
		return Extract_notw( html, context )		


def Extract_typepad( html, context ):
	"""extractor for notw.typepad.com articles"""

	art = context
	soup = BeautifulSoup( html )

	ediv = soup.find( 'div', {'class':'entry'} )
	h3 = ediv.find( 'h3', {'class':'entry-header'} )
	#contentdiv = ediv.find( 'div', {'class':'entry-content'} )
	bodydiv = ediv.find( 'div', {'class':'entry-body'} )
	morediv = ediv.find( 'div', {'class':'entry-more'} )
	footerspan = soup.find( 'span', {'class':'post-footers'} )

	headline = h3.renderContents(None)
	headline = ukmedia.FromHTML( headline )
	art['title'] = headline



	byline = u''
	footertxt = footerspan.renderContents(None)
	footercracker = re.compile( "Posted by\\s+(.*?)\\s+on\\s+(.*?\\s+at\\s+.*?)\\s*", re.UNICODE )
	m = footercracker.search(footertxt)
	if m:
		byline = m.group(1)
		datetxt = m.group(2)
	else:
		# "Posted at 12:01 AM"
		d = soup.find( 'h2', {'class':'date-header'} ).renderContents(None)
		m = re.search( "Posted at\\s+(.*)", footertxt )
		datetxt = d + u' ' + m.group(1)

	if byline == u'Online Team':
		byline = u''

	# often, the first non-empty para is byline
	if byline == u'':
		for p in bodydiv.findAll('p'):
			txt = ukmedia.FromHTML( p.renderContents( None ) )
			if not txt:
				continue
			m = re.match( "By\\s+((\\b\\w+(\\s+|\\b)){2,3})\\s*$" , txt, re.UNICODE|re.IGNORECASE )
			if m:
				byline = m.group(1)
				p.extract()
			break


	# can sometimes get proper author from blog title...
	if byline == u'':
		t = soup.find('title')
		tpat = re.compile( "\\s*((\\b\\w+\\b){2,3}):", re.UNICODE )
		m = tpat.search( t.renderContents(None) )
		if m:
			byline = m.group(1)


	art['byline'] = byline
	art['pubdate'] = ukmedia.ParseDateTime( datetxt )


	content = bodydiv.renderContents(None)
	if morediv:
		content = content + morediv.renderContents(None)
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	art['description'] = ukmedia.FromHTML( ukmedia.FirstPara( content ) )


	return art





def Extract_notw( html, context ):
	"""extractor for newsoftheworld.co.uk articles"""
	art = context


	if re.search( 'Sorry,\\s+the\\s+story\\s+you\\s+are\\s+looking\\s+for\\s+has\\s+been\\s+removed.', html ):
		ukmedia.DBUG2( "IGNORE missing article (%s)\n" % ( art['srcurl']) );
		return None

	# notw claims to be iso-8859-1, but it seems to be windows-1252 really
	soup = BeautifulSoup( html, fromEncoding = 'windows-1252' )

	# look for the table cell which contains the article
	h = soup.find( 'h1' )
	if not h:
		h = soup.find( 'h2' )
	td = h.parent

	# headlines are a total mess. Sometimes only have a graphic headline.
	# in prefered order:
	# first non-blank <h1>
	# The beginning of the page title
	# first non-blank <h2>

	headline = u''
	for h1 in soup.findAll( 'h1' ):
		headline = h1.renderContents( None )
		headline = ukmedia.FromHTML( headline )
		headline = u' '.join( headline.split() )
		h1.extract()
		if headline != u'':
			break

	if headline == u'':
		m = re.match( '(.*?)\\s*[|]', soup.title.renderContents(None) )
		if m:
			headline = m.group(1)
			headline = ukmedia.DescapeHTML( headline )
			headline = u' '.join( headline.split() )

	if headline == u'':
		for h2 in soup.findAll( 'h2' ):
			headline = h2.renderContents( None )
			headline = ukmedia.FromHTML( headline )
			headline = u' '.join( headline.split() )
			if headline != u'':
				break

	art['title'] = headline


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


	content = td.renderContents( None )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	art['description'] = ukmedia.FirstPara( content )



	# get year from meta tags, eg:
	#<meta name="date" content="10 February 2008" />
	meta = soup.find( 'meta', {'name':'date'} )
	m = re.search( '\\b(\\d{4})\\b', meta['content'] )
	year = m.group(1)
	# but meta has unreliable format, so get day and month from url...
	m = re.search( '/(\\d{2})_?(\\d{2})[^/]+[.]shtml', art['srcurl'] )
	day = m.group(1)
	month = m.group(2)
	
	art['pubdate'] = datetime( int(year),int(month),int(day) )

	return art




def CalcSrcID( url ):
	o = urlparse.urlparse( url )
	if o[1] == 'notw.typepad.com':
		# "http://notw.typepad.com/hyland/2008/01/no-sniping-ross.html"
		return o[2]
	else:
		# "http://www.newsoftheworld.co.uk/1002_scroungers.shtml"
		notw_idpat = re.compile( "/([0-9]+[^/]*[.]shtml)$" )
		m = notw_idpat.search( url )
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
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, maxerrors=50 )

