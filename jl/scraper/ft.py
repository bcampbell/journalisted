#!/usr/bin/env python2.4
#
# Scraper for Financial Times
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - how to handle NY times articles on FT site?
#

import sys
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils



rssfeeds = {
#	'News / Showbiz': 'http://www.express.co.uk/rss/news.xml',
}




def Extract( html, context ):
	art = context
	soup = BeautifulSoup( html )

	headerdiv = soup.find( 'div', {'class':'ft-story-header'} )
	
	h2 = headerdiv.h2
	headline = h2.renderContents( None )
	headline = ukmedia.FromHTML( headline )

	print headline
	art['title'] = headline

    # "Published: February 10 2008 22:05 | Last updated: February 10 2008 22:05"
	datepat = re.compile( u"Published:\\s+(.*?)\\s*[|]", re.UNICODE )

	byline = u''
	pubdate = None
	for p in headerdiv.findAll( 'p' ):
		txt = p.renderContents( None )	
		m = datepat.match( txt )
		if m:
			# it's the datestamp
			pubdate = ukmedia.ParseDateTime( m.group(1) ) 
		else:
			if byline != u'':
				raise Exception, "uh-oh..."
			byline = ukmedia.FromHTML( txt )

	art['byline'] = byline
	art['pubdate'] = pubdate



	bodydiv = soup.find( 'div', {'class':'ft-story-body'} )

	for cruft in bodydiv.findAll( 'script' ):
		cruft.extract()

	content = bodydiv.renderContents( None )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	art['description'] = ukmedia.FirstPara( content )



	if soup.find( 'div', {'id':'DRMUpsell'} ):
		raise Exception, "Uh-oh... we're being shortchanged..."

	return art


def ScrubFunc( context, entry ):
	return context


def FindArticles():
	""" get a set of articles to scrape from the express rss feeds """
	return ukmedia.FindArticlesFromRSS( rssfeeds, u'ft', ScrubFunc )

# pattern to extract unique id from FT urls
# eg "http://www.ft.com/cms/s/8ca13fba-d80d-11dc-98f7-0000779fd2ac.html"
idpat = re.compile( "/([0-9a-fA-F\\-]+)[.]html" )

def CalcSrcID( url ):
	m = idpat.search( url )
	return m.group(1)


def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'ft'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

