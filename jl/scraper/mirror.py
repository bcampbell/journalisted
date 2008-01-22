#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for Mirror and Sunday Mirror
#
#

import re
from datetime import datetime
import time
import string
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia, ScraperUtils

# Full list of mirror rss feeds is at http://www.mirror.co.uk/more/
mirror_rssfeeds = {
	# NEWS feed looks like it's supposed to be an aggregate of all the other
	# feeds, but I'm not sure this is actually the case. So I'll include the
	# lot... Articles appearing in more than one feed will still only be
	# scraped once.
	'NEWS': 'http://www.mirror.co.uk/news/rss.xml',
	# individual news feeds:
	'Top Stories': 'http://www.mirror.co.uk/news/topstories/rss.xml',
	'latest': 'http://www.mirror.co.uk/news/latest/rss.xml',
	'voice of the mirror': 'http://www.mirror.co.uk/news/voiceofthemirror/rss.xml',
	'money news': 'http://www.mirror.co.uk/news/money/rss.xml',
	'motoring news': 'http://www.mirror.co.uk/news/motoring/rss.xml',
	'technology': 'http://www.mirror.co.uk/news/technology/rss.xml',
	'investigates': 'http://www.mirror.co.uk/news/investigates/rss.xml',
	'jobs news': 'http://www.mirror.co.uk/news/columnists/rss.xml',
	'columnists': 'http://www.mirror.co.uk/news/travel/rss.xml',
	'hope not hate': 'http://www.mirror.co.uk/news/hopenothate/rss.xml',
	# leaving out sport, showbiz and blogs for now
	}


# Full list of feeds at:
# http://www.sundaymirror.co.uk/more/
sundaymirror_rssfeeds = {
	# overall news feed
	'news': 'http://www.sundaymirror.co.uk/news/rss.xml',
	# But we'll also include all the individual ones (just in case)
	'sunday': 'http://www.sundaymirror.co.uk/news/sunday/rss.xml',
	'columnists': 'http://www.sundaymirror.co.uk/news/columnists/rss.xml',
	'your money': 'http://www.sundaymirror.co.uk/news/yourmoney/rss.xml',
	'motoring': 'http://www.sundaymirror.co.uk/news/motoring/rss.xml',
	#'homes and holidays': 'http://www.sundaymirror.co.uk/news/homesandholidays/rss.xml',
	'weather': 'http://www.sundaymirror.co.uk/news/weather/rss.xml',
	# ignoring sport and showbiz feeds for now
	}



# mirror bylines have date in
bylinetidypat = re.compile( """\s*(.*?)\s*(\d{2}/\d{2}/\d{4})\s*""", re.UNICODE )



def Extract( html, context ):
	"""extract article from a mirror.co.uk page"""

	art = context
	soup = BeautifulSoup( html )

	maindiv = soup.find( 'div', { 'class': 'art-body' } )

	headlinediv = maindiv.find( 'h1', { 'class': 'art-headline' } )
	art['title'] = headlinediv.renderContents(None)
	art['title'] = ukmedia.DescapeHTML( art['title'] )
	art['title'] = ukmedia.UncapsTitle( art['title'] )		# don't like ALL CAPS HEADLINES!  

	bylinediv = maindiv.find( 'h2', { 'class': 'art-byline' } )
	rawbyline = bylinediv.renderContents(None)
	m = bylinetidypat.match( rawbyline )
	art['byline'] = m.group(1)
	art['pubdate' ] = ukmedia.ParseDateTime( m.group(2) )

	# use first paragraph as description
	firstpara = maindiv.find( 'p', {'class': 'art-p'} );
	art['description'] = firstpara.renderContents(None)
	art['description'] = ukmedia.FromHTML( art['description'] )

	art['content'] = unicode();
	for para in maindiv.findAll( 'p', {'class': 'art-p'} ):
		art['content'] = art['content'] + u'<p>' + para.renderContents(None) + '</p>\n';
	art['content'] = ukmedia.SanitiseHTML( art['content'] )

	return art



# to get unique id out of url
srcid_patterns = [
	re.compile( "-89520-([0-9]+)/$""" ),	# mirror
	re.compile( "-98487-([0-9]+)/$""" ),	# sundaymirror
	re.compile( "%26objectid=([0-9]+)%26""" )	# old url style
	]

def CalcSrcID( url ):
	for pat in srcid_patterns:
		m = pat.search( url )
		if m:
			break
	if not m:
		raise Exception, "Couldn't extract srcid from url ('%s')" % (url)
	return m.group(1)


def ScrubFunc( context, entry ):
	title = context['title']
	title = ukmedia.DescapeHTML( title )
	title = ukmedia.UncapsTitle( title )	# all mirror headlines are caps. sigh.
	context['title'] = title

	# mirror feeds go through mediafed.com. sigh.
	# Luckily the guid has proper link (marked as non-permalink)
	url = entry.guid

	# just in case they decide to change it...
	if url.find( 'mirror.co.uk' ) == -1:
		raise Exception, "URL not from mirror.co.uk or sundaymirror.co.uk ('%s')" % (url)


	context[ 'srcid' ] = CalcSrcID( url )
	context[ 'srcurl' ] = url
	context[ 'permalink'] = url

	return context




def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context[ 'srcid' ] = CalcSrcID( url )
	if url.find( 'sundaymirror.co.uk' ) == -1:
		context['srcorgname'] = u'mirror'
	else:
		context['srcorgname'] = u'sundaymirror'
	context['lastseen'] = datetime.now()
	return context



def FindArticles():
	found = ukmedia.FindArticlesFromRSS( mirror_rssfeeds, u'mirror', ScrubFunc )
	found = found + ukmedia.FindArticlesFromRSS( sundaymirror_rssfeeds, u'sundaymirror', ScrubFunc )
	return found



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )


