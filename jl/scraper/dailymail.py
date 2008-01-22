#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - columnists require separate scrape path (no rss feeds!)?
#

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ScraperUtils


rssfeeds = {
	'Homepage': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1766',
	'Mail on Sunday': 'http://feeds.feedburner.com/dailymail/MailonSundayHomepage',
	'News': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1770',
	'News headlines': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1770&in_chn_id=1674&in_headlines=Y',
	'World news': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1811',
	# Comment has editorials, but no columnists...
	'Comment': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1787',
	'Sport': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1771',
	'Football': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1779',
	'Rugby union': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1780',
	'Cricket': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1849',
	'Other sport': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1781',
	'TV & showbiz': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1773',
	'Health': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1774',
	'Diet & fitness': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1798',
	'Women & family': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1799',
	'Femail': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1879',
	'In the stands': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1867',
	# promos have missing dates... (maybe other stuff missing too)...
#	'Promotions': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1777',
	'Live Night & Day': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1889',
	'Health notes': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1909',
	'Baz Bamigboye': 'http://feeds.feedburner.com/dailymail/BazBamigboye',
	'Jaci Stephen': 'http://feeds.feedburner.com/dailymail/JaciStephen',
	'Reviews': 'http://feeds.feedburner.com/dailymail/TVShowbizReviews',
	'Rugby league': 'http://feeds.feedburner.com/dailymail/MailOnlineRugbyLeague',
	'Motorsport': 'http://feeds.feedburner.com/dailymail/Motorsport',
	'Golf': 'http://feeds.feedburner.com/dailymail/MailOnlineGolf',
	'American sports': 'http://feeds.feedburner.com/dailymail/AmericanSports',
	'Schools football': 'http://feeds.feedburner.com/dailymail/SchoolsFootball',
	'Science and technology': 'http://feeds.feedburner.com/dailymail/ScienceandTech',
	'Horse racing': 'http://feeds.feedburner.com/dailymail/HorseRacing',
	'Tennis': 'http://feeds.feedburner.com/dailymail/tennis',
	'Big Brother': 'http://feeds.feedburner.com/dailymail/BigBrother',
	'Cricket World Cup': 'http://feeds.feedburner.com/TheMailOnline/Cricketworldcup',
	'Arsenal': 'http://feeds.feedburner.com/dailymail/arsenal',
	'Aston Villa': 'http://feeds.feedburner.com/dailymail/astonvilla',
	'Birmingham': 'http://feeds.feedburner.com/dailymail/birmingham',
	'Blackburn': 'http://feeds.feedburner.com/dailymail/blackburn',
	'Bolton': 'http://feeds.feedburner.com/dailymail/bolton',
	'Chelsea': 'http://feeds.feedburner.com/dailymail/chelsea',
	'Derby': 'http://feeds.feedburner.com/dailymail/derby',
	'Everton': 'http://feeds.feedburner.com/dailymail/everton',
	'Fulham': 'http://feeds.feedburner.com/dailymail/fulham',
	'Liverpool': 'http://feeds.feedburner.com/dailymail/liverpool',
	'Manchester City': 'http://feeds.feedburner.com/dailymail/mancity',
	'Manchester United': 'http://feeds.feedburner.com/dailymail/manutd',
	'Middlesbrough': 'http://feeds.feedburner.com/dailymail/middlesbrough',
	'Newcastle': 'http://feeds.feedburner.com/dailymail/newcastle',
	'Portsmouth': 'http://feeds.feedburner.com/dailymail/portsmouth',
	'Reading': 'http://feeds.feedburner.com/dailymail/reading',
	'Sunderland': 'http://feeds.feedburner.com/dailymail/sunderland',
	'Tottenham': 'http://feeds.feedburner.com/dailymail/tottenham',
	'West Ham': 'http://feeds.feedburner.com/dailymail/westham',
	'Wigan': 'http://feeds.feedburner.com/dailymail/wigan',
	'Rangers': 'http://feeds.feedburner.com/dailymail/rangers',
	'Celtic': 'http://feeds.feedburner.com/dailymail/celtic',
}

# page which lists columnists and their latest rants
columnistmainpage = 'http://www.dailymail.co.uk/pages/live/columnists/dailymail.html'


def FindColumnistArticles():
	"""Dailymail doesn't seem to have an RSS feed for it's columnists,
	so we'll just grep for links on the columnist page.
	TODO: could follow archive links for more articles..."""

	ukmedia.DBUG2("Searching Columnist page for articles\n")
	foundarticles = []
	html = ukmedia.FetchURL( columnistmainpage )
	soup = BeautifulSoup( html )

	srcorgname = u'dailymail'
	lastseen = datetime.now()

	for h in soup.findAll( 'h3' ):
		url = TidyURL( 'http://www.dailymail.co.uk' + h.a['href'] )

		context = {
			'srcid': url,
			'srcurl': url,
			'permalink': url,
			'srcorgname' : srcorgname,
			'lastseen': lastseen,
			}
		foundarticles.append( context )

	ukmedia.DBUG2("found %d columnist articles\n" % (len(foundarticles)) )
	return foundarticles

# get datetime from dailymail <span class='artDate'> format:
# "Last updated at 13:23pm on 29th August 2006"
def CrackDate( d ):
	m = re.match( 'Last updated at ([0-9]+):([0-9]+)([ap]m) on ([0-9]+)\w\w (\w+) ([0-9]+)', d )
	hours = int( m.group(1) )	# already 24hr time
	minutes = int( m.group(2) )
	ampm = m.group(3)
	day = int( m.group(4) )
	month = ukmedia.MonthNumber( m.group(5) )
	year = int( m.group(6) )

	d = datetime( year, month, day, hours, minutes )
	return d


def KillCruft( soup, name, attrs ):
	for cruft in soup.findAll( name, attrs ):
		cruft.extract()


# extract a single article from a page
def Extract( html, context ):

	# check for dailymail error message
	if re.search( u"Article:\\d+ Not Found", html ):
		ukmedia.DBUG2( "IGNORE Missing article (%s)\n" % (context['srcurl']) );
		return None


	art = context

	# do a pre-emptive strike and zap _everything_ after the main
	# article text

	cruftkillpat1 = re.compile( "\\s*<div id=\"social_links_sub\">.*", re.DOTALL )
	cruftkillpat2 = re.compile( "\\s*<a name=\"StartComments\" id=\"StartComments\" ></a>.*", re.DOTALL )

	html = cruftkillpat1.sub( '', html )
	html = cruftkillpat2.sub( '', html )

	# dailymail _claims_ to use "iso-8859-1" encoding, but really it
	# uses "windows-1252". Sigh.
	soup = BeautifulSoup( html, fromEncoding='windows-1252' )

	# get Description
	foo = soup.find( 'meta', {'name':'description'} )
	art['description'] = foo[ 'content' ]
	art['description'] = ukmedia.FromHTML( art['description'] )

	articlediv = soup.find( 'div', id='ArtContent' )

	# get headline
	headline = articlediv.find( 'h1' )
	art[ 'title' ] = headline.renderContents( None )
	art[ 'title' ] = ukmedia.DescapeHTML( art['title'] )

	# get date posted
	# two formats used:
	# ""Last updated at 13:23pm on 29th August 2006" (main paper?)
	# "20:12pm 23rd November 2007" (columnists?)
	datespan = articlediv.find( 'span', {'class':'artDate' } )

	if datespan:
		datestr = datespan.string
		if datestr.find("Last updated") != -1:
			art['pubdate'] = CrackDate( datestr )	# old format
		else:
			art['pubdate'] = ukmedia.ParseDateTime( datestr )	# new format
	else:
		# Soap watch column has no date
		if art['title'] == u'SOAP WATCH':
			art['pubdate'] = datetime.now()
		else:
			raise Exception, ("Missing date")


	# is there a byline?
	bylinespan = articlediv.find( 'span', {'class':'artByline' } )
	if bylinespan:
		byline = bylinespan.renderContents( None )
		byline = ukmedia.DescapeHTML( byline )
		byline = re.sub( u"\s*-\s*<a.*?>.*?</a>\s*", u'', byline )
	else:
		# Columnist pages have columnist name in colT div
		colt = articlediv.find( 'div', {'class':'colT'} )
		if colt:
			colt.span.extract()	# cruft
			byline = colt.renderContents( None )
			colt.extract()
		else:
			byline = u''

	art['byline'] = byline

	# Text extraction time...

	# remove title, date, byline
	headline.extract()
	if datespan:
		datespan.extract()
	if  bylinespan:
		bylinespan.extract()

	# remove cruft

	KillCruft( articlediv, 'a', {'id': 'endAds'} )
	KillCruft( articlediv, 'a', {'id': 'statecontent'} )

	# comments link
	KillCruft( articlediv, 'a', {'href': re.compile(".*StartComments$") } )

	# zap blocks (top stories, email newsletter etc...)
	KillCruft( articlediv, 'div', { 'class':'right', 'id':'LookHere' } )

	# zap extra links embedded in the article
	for cruft in articlediv.findAll( 'span', { 'class':'ereaderFilter' } ):
		cruft.extract()

	# image blocks...
	for cruft in articlediv.findAll( 'strong', ):
		if unicode( cruft ).find( u"Scroll down for more" ) != -1:
			cruft.extract()
	KillCruft( articlediv, 'div', { 'id': re.compile('.*ArtContentImgBody.*' ) } )

	# "Read More..." blocks...
	KillCruft( articlediv, 'div', { 'class': re.compile( '.*ArtInlineReadLinks.*' ) } )

	# whatever is left is our text!
	content = articlediv.renderContents( None )
	art['content'] = ukmedia.SanitiseHTML( content )
	return art




urltrimpat=re.compile( "(.*?[?]in_article_id=[0-9]+).*$" )

def TidyURL( url ):
	# trim off cruft
	return urltrimpat.sub( "\\1", url )

	
def ScrubFunc( context, entry ):
	"""mungefunc for ukmedia.FindArticlesFromRSS()"""

	# most dailymail RSS feeds go through feedburner, but luckily the original url is still there...
	url = context[ 'srcurl' ]
	if url.find('feedburner') != -1:
		url = entry.feedburner_origlink

	url = TidyURL( url )

	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = url
	return context


def ContextFromURL( url ):
	"""Set up for scraping a single article from a bare url"""
	url = TidyURL( url )
	context = {
		'srcurl': url,
		'permalink': url,
		'srcid': url,
		'srcorgname': u'dailymail',
		'lastseen': datetime.now(),
	}
	return context


def FindArticles():
	"""Look for recent articles"""
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'dailymail', ScrubFunc )
	# extra articles not from RSS feeds...
	found = found + FindColumnistArticles()
	return found


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

