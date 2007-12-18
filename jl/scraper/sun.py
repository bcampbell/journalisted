#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# NOTES:
#
# Sun RSS feeds are rubbish, and the section pages look like they
# could change often (according to whatever campaigns the sun is
# banging on about at any one time)... so to get articles to scrape
# we do a shallow crawl the site looking for likely links...
#
# For some bizzare reason, the sun occasionally gives us the date
# at the top of the page in Finnish, or maybe other languages. Seems
# random... maybe some bizzare anti-scraping thing?
#
# KNOWN ISSUES:
#
# - the sun sometimes embed multiple stories on the same webpage,
#   for now we just process only the "main" story and discard the substory.
# - we miss subheadings for the occasional article
#   (they sometimes skip the "article" class we look for...)
# - pages with flash video leave some cruft in the content text
#    ("You need Flash Player 8 or higher..." etc)
#

import re
import urllib2
import sys
import traceback
from datetime import date,datetime
from optparse import OptionParser

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia



calcsrcid_pat = re.compile( '.*[/]article(\\d+)[.]ece' )

def CalcSrcID( url ):
	"""Extract a Sun-specific ID from a URL."""
	m = calcsrcid_pat.match( url )
	if m:
		return m.group(1)
	return None




def FindArticles():
	"""Gather articles to scrape from the sun website.

	Returns a list of scrape contexts, one for each article.
	"""
	ukmedia.DBUG2( "*** sun ***: looking for articles...\n" )

	urls = Crawl( 'http://www.thesun.co.uk/sol/homepage/' )

	found = []
	for url in urls:
		found.append( ContextFromURL( url ) )

	return found



# keep track of pages visited by Crawl(), so we don't process them
# multiple times
crawled = set()

# 
articleurlpat = re.compile( "http:[/][/]www[.]thesun[.]co[.]uk[/]sol[/].*[/]article\\d+[.]ece([?].*)?" )

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
		if href.startswith('/'):
			# handle relative links
			href = 'http://www.thesun.co.uk' + href

		# discard external sites, discussion pages, login pages etc...
		if not href.startswith( 'http://www.thesun.co.uk/sol/' ):
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
	soup = BeautifulSoup( html )

	# main column is column2 div - we can exclude a lot of nav cruft by starting here.
	col2 = soup.find( 'div', {'id':"column2"} )

	# sigh.... the sun sometimes embed multiple stories on the same page...
	# For now we'll just discard the sub-story. Unhappy about this, but
	# it just makes things too complicated.
	# TODO: something better.
	col3 = col2.find('div', { 'id':re.compile("\\bcolumn3\\b") } )
	if col3:
		col3.extract()

	# get headline
	h1 = col2.h1
	if not h1:
		# their html is so messed up that sometimes BeautifulSoup mistakenly
		# closes the column2 div before the main article. If that is the
		# case, just use the whole soup instead...
		col2 = soup
		# need to skip the h1 banner at top of page
		artmodule = soup.find( text=re.compile(".*BEGIN: Module - Main Article.*"))
		h1 = artmodule.findNext('h1')

	titletxt = h1.renderContents(None).strip()
	titletxt = ukmedia.FromHTML( titletxt )
	art['title'] = titletxt


	if html.find( "<!-- BEGIN: Module - Main Article -->" ) == -1:
		ukmedia.DBUG2( "IGNORE non-story '%s' (%s)\n" % (art['title'], art['srcurl']) );
		return None


	# ignore some known pages
	ignore_titles = [ "Contact us", "HAVE YOUR SAY" ]
		#	"Your stars for the month ahead"?
	if art['title'] in ignore_titles:
		ukmedia.DBUG2( "IGNORE '%s' (%s)\n" % (art['title'], art['srcurl']) );
		return None


	if html.find("BEGIN ROO vxFlashPlayer embed") != -1:
		ukmedia.DBUG2( "IGNORE video page '%s' (%s)\n" % (art['title'], art['srcurl']) );
		return None

	# 'author' class paras for author, email link and date...
	bylinetxt = u''
	datetxt = u''
	# get page date (it's in format "Friday, December 14, 2007")
	#pagedatetxt = soup.find( 'p', {'id':"masthead-date"}).string.strip()

	for author in col2.findAll( 'p', { 'class': re.compile( '\\bauthor\\b' ) } ):
		txt = author.renderContents( None ).strip()
		if txt == '':
			continue
		if txt.find( 'Email the author' ) != -1:
			continue		# ignore email links

#		print "-----"
#		print txt
#		print "-----"


		m = re.match( u'Published:\s+(.*)', txt )
		if m:
			# it's a date (eg '11 Dec 2007' or 'Today')
			datetxt = m.group(1)
		else:
			# assume it's the byline
			if bylinetxt != u'':
				raise Exception, "Uhoh - multiple bylines..."
			bylinetxt = txt
			# replace "<br />" with ", " and zap any other html
			bylinetxt = re.sub( u'\s*<br\s*\/>\s*', u', ', bylinetxt )
			bylinetxt = ukmedia.FromHTML( bylinetxt )

	if datetxt == u'' or datetxt == u'Today':
		d = date.today()
		art['pubdate'] = datetime( d.year, d.month, d.day )
	else:
		art['pubdate' ] = ukmedia.ParseDateTime( datetxt )

	art['byline'] = bylinetxt


	# first para has 'first-para' class
	# (sometimes have multiple first-paras, so use first non-empty one)
	for p in col2.findAll('p', { 'class': re.compile( '\\bfirst-para\\b' ) } ):
		contenttxt = p.prettify( None )
		desctxt = ukmedia.FromHTML( contenttxt )
		if desctxt != u'':
			break

	# other paras have 'article' class
	# KNOWN issue - some subheadings are done with non-article class paras...
	for para in col2.findAll( 'p', { 'class': re.compile( '\\barticle\\b' ) } ):
		for cruft in para.findAll( 'div' ):
			cruft.extract()

		contenttxt += para.prettify(None)

	contenttxt = ukmedia.SanitiseHTML( contenttxt );

	art['content'] = contenttxt
	art['description'] = desctxt

	return art






tidyurl_pat = re.compile( "(http:[/][/].*[/]article\\d+[.]ece)([?].*)?" )
def TidyURL( url ):
	"""Sun urls can have params (eg to say they came from an rss feed)."""
	url = tidyurl_pat.sub( "\\1", url )
	return url



def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	url = TidyURL(url)
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'sun'
	context['lastseen'] = datetime.now()
	return context




def main():
	parser = OptionParser()
	parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
	parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")

	(options, args) = parser.parse_args()

	found = []
	if options.url:
		context = ContextFromURL( options.url )
		found.append( context )
	else:
		found = found + FindArticles()

	if options.dryrun:
		store = ArticleDB.DummyArticleDB()	# testing
	else:
		store = ArticleDB.ArticleDB()

	# use a huge maxerrors because of the sheer volume of articles we
	# pick up in the crawl
	ukmedia.ProcessArticles( found, store, Extract, maxerrors=150 )

	return 0

if __name__ == "__main__":
    sys.exit(main())

