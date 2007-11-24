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
from JL import ArticleDB,ukmedia


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



# extract a single article from a page
def Extract( html, context ):
	art = context

	soup = BeautifulSoup( html )

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
	# ""Last updated at 13:23pm on 29th August 2006" (old)
	# "20:12pm 23rd November 2007" (new)
	datespan = articlediv.find( 'span', {'class':'artDate' } )

	datestr = datespan.string
	if datestr.find("Last updated") != -1:
		art['pubdate'] = CrackDate( datestr )	# old format
	else:
		art['pubdate'] = ukmedia.ParseDateTime( datestr )	# new format

	# is there a byline?
	bylinespan = articlediv.find( 'span', {'class':'artByline' } )
	if bylinespan:
		byline = bylinespan.renderContents( None )
		byline = ukmedia.DescapeHTML( byline )
		byline = re.sub( u"\s*-\s*<a.*?>.*?</a>\s*", u'', byline )
		art['byline'] = byline
	else:
		art['byline'] = u''



	# find the comment link at the top, and delete it and everything above it
	cruft = articlediv.find( 'a', {'class':'t11'} )
	while cruft:
		prev = cruft.previous
		cruft.extract()
		cruft = prev;


	# zap blocks (top stories, email newsletter etc...)
	cruft = articlediv.find( 'div', { 'class':'right', 'id':'LookHere' } )
	if cruft:
		cruft.extract()

	# zap extra links embedded in the article
	for cruft in articlediv.findAll( 'span', { 'class':'ereaderFilter' } ):
		cruft.extract()

	# After the text there could be a whole heap of cruft (comments etc)
	# which we want to zap.

	# Look for the comments section, which follows the text.
	cruft = articlediv.find( 'a', { 'name': 'StartComments', 'id': 'StartComments' } )

	# delete comments and anything else following
	while cruft:
		n = cruft.nextSibling
		cruft.extract()
		cruft = n

	# just about there - just got to cull out some leftover cruft...

	# little empty divs
	for cruft in articlediv.findAll( 'div' ):
		cruft.extract()

	# "Scroll down" messages
	for cruft in articlediv.findAll( 'strong', text="Scroll down for more" ):
		cruft.parent.extract()


	# <p class="sm">Have your Daily Mail and Mail on Sunday delivered to your door...
	# delete it. and everything following it.
	cruft = articlediv.find( 'p', {'class':'sm'} )
	while cruft:
		n = cruft.nextSibling
		cruft.extract()
		cruft = n

	# cull any "see also..." paragraphs embedded in the text
#	for cruft in articlediv.findAll( 'strong' ):
#		if cruft.find( text='See also...' ):
#			cruft.parent.extract()


	# whatever is left is our text!
	art['content'] = articlediv.renderContents( None )
	return art




urltrimpat=re.compile( "(.*?[?]in_article_id=[0-9]+).*$" )

def ScrubFunc( context, entry ):
	"""mungefunc for ukmedia.FindArticlesFromRSS()"""

	# most dailymail RSS feeds go through feedburner, but luckily the original url is still there...
	url = context[ 'srcurl' ]
	if url.find('feedburner') != -1:
		url = entry.feedburner_origlink

	# trim off cruft
	url = urltrimpat.sub( "\\1", url )

	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = url
	return context




def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'dailymail', ScrubFunc )
	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

