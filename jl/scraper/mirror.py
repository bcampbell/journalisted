#!/usr/bin/env python2.4
#
# Scraper for Mirror and Sunday Mirror
#
# TODO:
# - improve headline case prettifing (all mirror headlines are IN CAPS)
#
#

import re
from datetime import datetime
import time
import string
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia

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



# mirror bylines have date in them which we don't need
bylinetidypat = re.compile( """\s*(.*?)\s*\d{2}/\d{2}/\d{4}\s*""", re.UNICODE )



def PrettifyTitle( title ):
	"""Try and produce a prettier version of AN ALL CAPS TITLE"""
	title = title.title()
	# "Title'S Apostrophe Badness" => "Title's Apostrophe Badness"
	pat = re.compile( "(\w)('S\\b)", re.UNICODE );
	title = pat.sub( "\\1's", title );
	return title.strip()




def Extract( html, context ):
	"""extract article from a mirror.co.uk page"""

	art = context
	soup = BeautifulSoup( html )

	maindiv = soup.find( 'div', { 'class': 'art-body' } )

	headlinediv = maindiv.find( 'h1', { 'class': 'art-headline' } )
	art['title'] = headlinediv.renderContents(None)
	art['title'] = ukmedia.DescapeHTML( art['title'] )
	art['title'] = PrettifyTitle( art['title'] )		# don't like ALL CAPS HEADLINES!  

	bylinediv = maindiv.find( 'h2', { 'class': 'art-byline' } )
	rawbyline = bylinediv.renderContents(None)
	m = bylinetidypat.match( rawbyline )
	art['byline'] = m.group(1)

	# use first paragraph as description
	firstpara = maindiv.find( 'p', {'class': 'art-p'} );
	art['description'] = firstpara.renderContents(None)
	art['description'] = ukmedia.FromHTML( art['description'] )

	art['content'] = unicode();
	for para in maindiv.findAll( 'p', {'class': 'art-p'} ):
		art['content'] = art['content'] + u'<p>' + para.renderContents(None) + '</p>\n';
	art['content'] = ukmedia.SanitiseHTML( art['content'] )

	return art



def ScrubFunc( context, entry ):
	title = context['title']
	title = string.capwords(title)	# all mirror headlines are caps. sigh.
	title = ukmedia.DescapeHTML( title )
	context['title'] = title
	return context





def ScrapeMirror():
	found = ukmedia.FindArticlesFromRSS( mirror_rssfeeds, u'mirror', ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )


def ScrapeSundayMirror():
	found = ukmedia.FindArticlesFromRSS( sundaymirror_rssfeeds, u'sundaymirror', ScrubFunc )
	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )



def main():
	ScrapeMirror()
	ScrapeSundayMirror()
	return 0

if __name__ == "__main__":
    sys.exit(main())

