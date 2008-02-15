#!/usr/bin/env python2.4
#
# Scraper for The Herald (http://www.theherald.co.uk)
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
#
# TODO:
# - could get journo email addresses from bylines
# - factor out and rewrite crawler (Should use an active edge list)

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils


# herald rss feeds not very complete, so just crawl the site for links instead!
UNUSED_rssfeeds = {
	'Sport': 'http://www.theherald.co.uk/sport/headlines/rss.xml',
	'News': 'http://www.theherald.co.uk/news/news/rss.xml',
	'Features': 'http://www.theherald.co.uk/features/features/rss.xml',
	'Business': 'http://www.theherald.co.uk/business/news/rss.xml',
	'Politics': 'http://www.theherald.co.uk/politics/news/rss.xml',
	'Going Out': 'http://www.theherald.co.uk/goingout/top/rss.xml',
}




def FindArticles():
	"""Gather articles to scrape from the herald website.

	Returns a list of scrape contexts, one for each article.
	"""
	ukmedia.DBUG2( "*** herald ***: looking for articles...\n" )

	# a bit messy - but do blogs first by themselves (as we only go in one level)
	urls = Crawl( 'http://www.theherald.co.uk/heraldblogs' )
	urls = urls | Crawl( 'http://www.theherald.co.uk' )
	found = []
	for url in urls:
		found.append( ContextFromURL( url ) )

	return found



# keep track of pages visited by Crawl(), so we don't process them
# multiple times
crawled = set()

#
acceptedhosts = [ 'www.theherald.co.uk' ]
artpats = [ re.compile( "/((display)|(index)[.]var[.].*[.]php)" ) ]


def Crawl( url, depth=0 ):
	"""Recursively crawl website looking for article links.

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
		href = href.replace( '../', '' )	# cheesiness

		href = urlparse.urljoin( url, href )

		o = urlparse.urlparse( href)
		if o[0] != 'http':
			continue

		# discard external sites
		if o[1] not in acceptedhosts:
			continue

		# trim off fragments (eg '#comments')
		href = urlparse.urlunparse( (o[0], o[1], o[2], o[3], o[4],'') )

		is_article = 0
		for pat in artpats:
			if pat.search( href ):
				is_article = 1
				break

		if is_article:
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
	url = context['srcurl']

	if re.search( 'Copyright Press Association Ltd \\d{4}, All Rights Reserved', html ):
		ukmedia.DBUG2( "IGNORE Press Association item (%s)\n" % (url) )
		return None


	# TODO: skip NEWS COMPILER pages instead?
	badtitles = ( "The Herald : Features: LETTERS",
		"Poetry Blog (from The Herald )",
		"Arts Blog (from The Herald )",
		"The Herald : Business: MAIN BUSINESS",
		"The Herald : Motors videos",
		"The Herald - Scotland's Leading Quality Daily Newspaper",
		)

	m = re.search( '<title>(.*?)</title>', html )
	pagetitle = m.group(1)
	if pagetitle in badtitles:
		ukmedia.DBUG2( "IGNORE page '%s' (%s)\n" % ( pagetitle, url) )
		return None

	# blog or article?
	if html.find( "<div class=\"entry2\">" ) != -1:
		return blog_Extract( html,context )
	else:
		return news_Extract( html,context )

#	if re.search( '/display[.]var[.]\\d+', url ):
#		return news_Extract( html,context )

#	if re.search( '/index[.]var[.]\\d+', url ):
#		return blog_Extract( html,context )

	raise Exception, "can't determine type (news or blog) of article (%s)" % (url)


def news_Extract( html, context ):
	"""extract function for handling main news site articles"""
	art = context
	soup = BeautifulSoup( html )

	# TODO: skip NEWS COMPILER pages?

	headlinediv = soup.find( 'div', {'class':'artHeadline'} )
	bylinediv = soup.find( 'td', {'class':'artByline'} )
	datediv = soup.find( 'td', {'class':'artDate'} )
	# PA items seem to use a different format... sigh...
	itdatespan = soup.find( 'span', {'class':'itdate'} )
	contentdiv = soup.find( 'div', {'class':'articleText'} )


	byline = u''
	if bylinediv:
		byline = bylinediv.renderContents( None )
		byline = ukmedia.FromHTML( byline )

	# look for press association notice
	# <div class="paNews articleText">
	if byline == u'' and soup.find( 'div', {'class': re.compile('paNews') } ):
		# it's from the Press Association
		byline = u'PA'

	headline = headlinediv.renderContents( None )
	headline = ukmedia.FromHTML( headline )

	for cruft in contentdiv.findAll( 'div', {'id':'midpagempu'} ):
		cruft.extract()
	content = contentdiv.renderContents(None)
	desc = ukmedia.FirstPara( content )
	desc = ukmedia.FromHTML( desc )

	pubdatetxt = u''
	if datediv:
		pubdatetxt = datediv.renderContents(None).strip()
	elif itdatespan:
		pubdatetxt = itdatespan.renderContents(None).strip()
		# replace 'today' with current date
		today = datetime.now().strftime( '%a %d %b %Y' )
		pubdatetxt = pubdatetxt.replace( 'today', today )

	art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )
	art['byline'] = byline
	art['title'] = headline
	art['content'] = content
	art['description'] = desc

	return art


def blog_Extract( html, context ):
	"""extract function for handling blog entries"""


	art = context
	soup = BeautifulSoup( html )

	entdiv = soup.find( 'div', {'class':'entry2'} )
	headbox = entdiv.findPreviousSibling( 'div', {'class':'b_box'} )

	headline = headbox.a.renderContents(None).strip()
	art['title'] = headline

	byline = u''
	postedby = headbox.find( text=re.compile('Posted by') )
	if postedby:
		byline = postedby.nextSibling.renderContents(None).strip()
	art['byline'] = byline

	datespan = headbox.find( 'span', {'class':'itdate'} )
	datetxt = ukmedia.FromHTML( datespan.renderContents(None) )
	art['pubdate'] = ukmedia.ParseDateTime( datetxt )

	content = entdiv.renderContents(None)
	art['content'] = content

 	desc = ukmedia.FirstPara( content )
	desc = ukmedia.FromHTML( desc )
	art['description'] = desc

	return art


def ScrubFunc( context, entry ):
	return context



# pattern to extract unique id from urls
# main news site urls:
# "http://www.theherald.co.uk/news/news/display.var.2036423.0.Minister_dismisses_more_tax_power_for_Holyrood.php"
# blog urls:
# "http://www.theherald.co.uk/features/bookblog/index.var.9706.0.at_home_in_a_story.php"
idpat = re.compile( "/((display)|(index)[.]var[.].*[.]php)" )




def CalcSrcID( url ):
	m = idpat.search( url )
	return m.group(1)


def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'herald'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

