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

import sys
import re
from datetime import datetime
import sys
import urlparse
import urllib2

import site
site.addsitedir("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils
from SpiderPig import SpiderPig



# pattern to extract unique id from urls
# main news site urls:
# "http://www.theherald.co.uk/news/news/display.var.2036423.0.Minister_dismisses_more_tax_power_for_Holyrood.php"
# blog urls:
# "http://www.theherald.co.uk/features/bookblog/index.var.9706.0.at_home_in_a_story.php"
idpat = re.compile( "/((display|index)[.]var[.].*[.]php)" )

def CalcSrcID( url ):
	""" extract unique srcid from url """
	url = url.lower()
	o = urlparse.urlparse( url )
	if not o[1].endswith( 'theherald.co.uk' ):
		return None

	m = idpat.search( o[2] )
	if m:
		return 'herald_' + m.group(1)
	else:
		return None

# pattern to find blog rss feeds on the blog index pages
blogrsspat = re.compile( "http://www.theherald.co.uk/(.*)/rss.xml" )



def FindArticles():
	"""Gather articles to scrape from the herald website.

	Returns a list of scrape contexts, one for each article.
	"""
	ukmedia.DBUG2( "*** herald ***: spidering for blog rss feeds...\n" )
	found = FindBlogEntries()
	ukmedia.DBUG2( "*** herald ***: spidering for article links...\n" )
	found = found + FindArticlesBySpidering()

	ukmedia.DBUG2( "found %d articles in total\n" % (len(found)) )
	return found


def blog_url_handler( feedlist, url, depth, a ):
	""" SpiderPig callback for searching out links of blog rss feeds """
	if depth>2:
		return None

	if a.find( text=re.compile( 'LINK' ) ):
		#print "%d BLOGINDEX: %s" %(depth, url)
		return url

	m = blogrsspat.search( url )
	if m:
		blogname = m.group(1)
		if not blogname in feedlist:
		#	print "%d RSS '%s': %s" %(depth,blogname,url)
			feedlist[blogname] = url

def FindBlogEntries():
	"""spider to find blog RSS feeds, then use the articles from the feeds"""
	feeds = {}
	pig = SpiderPig( blog_url_handler, userdata=feeds, logfunc=ukmedia.DBUG2 )
	pig.AddSeed( 'http://www.theherald.co.uk/heraldblogs' )
	pig.Go()

	found = ukmedia.FindArticlesFromRSS( feeds, u'herald', ScrubFunc );

	return found


def art_url_handler( arturls, url, depth, a ):
	""" SpiderPig callback for searching out article links """

	# follow up to three links in
	if depth > 3:
		return None

	# we use the class attribute to decide what sort of link it is
	if not a.has_key('class'):
		return None

	classes = a['class'].split()

	# links to articles...
	# We record them but don't follow them.
	if ('headlineLink' in classes) or ('sectTopHeadline' in classes):
		if idpat.search( url ):
			# Might actually be a link to a page listing more articles...
			if re.match( u'\\s*More\\s*...\\s*', a.renderContents(None) ):
				return url
			# OK we think it's an article!
			arturls.add(url)
		return None

	# links to other lists of articles
	if ('channelLink' in classes) or ('navLink' in classes):
		return url

	return None


def FindArticlesBySpidering():
	""" spider through the site looking for articles """
	urls = set()
	pig = SpiderPig( art_url_handler, userdata=urls, logfunc=ukmedia.DBUG2 )
	pig.AddSeed( 'http://www.theherald.co.uk' )
	pig.Go()

	found = []
	for url in urls:
		found.append( ContextFromURL( url ) )

	ukmedia.DBUG( "spidering found %d articles\n" %(len(found)) )
	return found





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
	soup = BeautifulSoup.BeautifulSoup( html )

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

	# sometimes byline is first line of article text, in bold...
	if byline == u'':
		# but not obituaries (they always have a bit of bold at the top)...
		if not 'obituaries' in art['srcurl']:
			n=None
			if len(contentdiv.p.contents) > 0:
				n = contentdiv.p.contents[0]
			if isinstance( n, BeautifulSoup.Tag ):
				# Want bold elements, with no <br>s inside them, but followed directly by a <br>...
				if n.name == 'b' and not n.find( "br" ):
					if isinstance( n.nextSibling, BeautifulSoup.Tag ) and n.nextSibling.name == 'br':
						byline = n.renderContents(None)
						byline = ukmedia.FromHTML( byline )
						byline = u' '.join( byline.split() )
						n.extract()
						# TODO: sometimes followed by place... (eg "in Paris<br />")

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

	if pubdatetxt == u'':
		# if still no date, try the web issue date at top of page...
		# (which will be todays date, rather than real date... but best we can do)
		issuedate = soup.find( 'td', {'align':'right', 'class':'issueDate'} )
		if issuedate:
			pubdatetxt = issuedate.renderContents(None)

	pubdatetxt = ukmedia.FromHTML( pubdatetxt )

	art['pubdate'] = ukmedia.ParseDateTime( pubdatetxt )
	art['byline'] = byline
	art['title'] = headline
	art['content'] = content
	art['description'] = desc

	return art


def blog_Extract( html, context ):
	"""extract function for handling blog entries"""

	if html.find( "No blog entries found." ) != -1:	
		ukmedia.DBUG2( "IGNORE missing blog entry (%s)\n" % (context[srcurl]) )
		return None

	art = context
	soup = BeautifulSoup.BeautifulSoup( html )

	entdiv = soup.find( 'div', {'class':'entry2'} )
	headbox = entdiv.findPreviousSibling( 'div', {'class':'b_box'} )

	headline = headbox.a.renderContents(None).strip()
	headline = ukmedia.FromHTML( headline )
	art['title'] = headline

	byline = u''
	postedby = headbox.find( text=re.compile('Posted by') )
	if postedby:
		byline = postedby.nextSibling.renderContents(None).strip()
	art['byline'] = byline

	datespan = headbox.find( 'span', {'class':'itdate'} )
	# replace 'today' with current date
	today = datetime.now().strftime( '%a %d %b %Y' )
	datetxt = ukmedia.FromHTML( datespan.renderContents(None) )
	datetxt = datetxt.replace( 'today', today )
	art['pubdate'] = ukmedia.ParseDateTime( datetxt )

	content = entdiv.renderContents(None)
	art['content'] = content

 	desc = ukmedia.FirstPara( content )
	desc = ukmedia.FromHTML( desc )
	art['description'] = desc

	return art


def ScrubFunc( context, entry ):
	context['srcid'] = CalcSrcID( context['srcurl'] )
	return context







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

