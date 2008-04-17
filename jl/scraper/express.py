#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# use bylineomatic on health, food others?

import sys
import re
from datetime import datetime
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ScraperUtils


expressroot = u'http://www.express.co.uk'


rssfeeds = {
	'News / Showbiz': 'http://www.express.co.uk/rss/news.xml',
	'Sport': 'http://www.express.co.uk/rss/sport.xml',
	'Features (All Areas)': 'http://www.express.co.uk/rss/features.xml',
	'Day & Night': 'http://www.express.co.uk/rss/dayandnight.xml',
	'Express Yourself': 'http://www.express.co.uk/rss/expressyourself.xml',
	'Health': 'http://www.express.co.uk/rss/health.xml',
	'Fashion & Beauty': 'http://www.express.co.uk/rss/fashionandbeauty.xml',
	'Gardening': 'http://www.express.co.uk/rss/gardening.xml',
	'Food & Recipes': 'http://www.express.co.uk/rss/food.xml',
	'Have Your Say': 'http://www.express.co.uk/rss/haveyoursay.xml',
	'Express Comment': 'http://www.express.co.uk/rss/expresscomment.xml',
	'Entertainment(All Areas)': 'http://www.express.co.uk/rss/entertainment.xml',
	'Music Reviews': 'http://www.express.co.uk/rss/music.xml',
	'DVD Reviews': 'http://www.express.co.uk/rss/dvd.xml',
	'Film Reviews': 'http://www.express.co.uk/rss/films.xml',
	'Theatre Reviews': 'http://www.express.co.uk/rss/theatre.xml',
	'Book Reviews': 'http://www.express.co.uk/rss/books.xml',
#	'TV Guide': 'http://www.express.co.uk/rss/tv.xml',
	'The Crusader': 'http://www.express.co.uk/rss/crusader.xml',
	'Money (All Areas)': 'http://www.express.co.uk/rss/money.xml',
	'City & Business': 'http://www.express.co.uk/rss/city.xml',
	'Your Money': 'http://www.express.co.uk/rss/yourmoney.xml',
	'Columnists (All)': 'http://www.express.co.uk/rss/columnists.xml',
	'Motoring': 'http://www.express.co.uk/rss/motoring.xml',
	'Travel': 'http://www.express.co.uk/rss/travel.xml',
#	'Competitions': 'http://www.express.co.uk/rss/competitions.xml',
	# blogs handled separately
#	'Express BLOGS': 'http://www.express.co.uk/rss/blogs.xml',

	# extra feeds not on the rss page (found by guessing urls, using the express
	# sitemap page as a guide):
	'Retirement': 'http://www.express.co.uk/rss/retirement.xml',
	'Careers': 'http://www.express.co.uk/rss/careers.xml',
	'Diana inquest': 'http://www.express.co.uk/rss/dianainquest.xml',
	'Football': 'http://www.express.co.uk/rss/football.xml',
	'Cricket': 'http://www.express.co.uk/rss/cricket.xml',
	'Rugby Union': 'http://www.express.co.uk/rss/rugbyunion.xml',
	'Rugby League': 'http://www.express.co.uk/rss/rugbyleague.xml',
	'Golf': 'http://www.express.co.uk/rss/golf.xml',
	'Tennis': 'http://www.express.co.uk/rss/tennis.xml',
	'Motorsport': 'http://express.co.uk/rss/motorsport.xml',
	'Racing': 'http://express.co.uk/rss/racing.xml',
	'Netball': 'http://express.co.uk/rss/netball.xml',
	'Our Comment': 'http://express.co.uk/rss/ourcomment.xml',
	'Games & Gadgets': 'http://www.express.co.uk/rss/games.xml',
	'Credit Advice': 'http://express.co.uk/rss/creditadvice.xml',
	'Property': 'http://express.co.uk/rss/property.xml'
}

# url formats:
# http://www.dailyexpress.co.uk/posts/view/13737
# http://www.express.co.uk/posts/view/25358/HISTORY-The-Queen-60-Years-Of-Marriage-8-30pm-ITV1
srcidpat = re.compile( "/posts/view/(\d+)(/.*)?$" )

def CalcSrcID( url ):
	""" Work out a unique srcid from an express url """
	o = urlparse.urlparse( url )
 	expressdomains = ( 'dailyexpress.co.uk', 'express.co.uk', 'sundayexpress.co.uk', 'sundayexpress.co.uk' )
	
	d = re.sub( '^www[.]', '', o[1] )
	if d not in expressdomains:
		return None

	m = srcidpat.search( url )
	if not m:
		return None

	return 'express_' + m.group(1)



def Extract( html, context ):
	art = context

	# cheesiness - kill everything from comments onward..
	cullpat = re.compile( "<a name=\"comments\">.*", re.DOTALL )
	html = cullpat.sub( "", html )

	# express claims to be iso-8859-1, but it seems to be windows-1252 really
	soup = BeautifulSoup( html, fromEncoding = 'windows-1252' )

	wrapdiv = soup.find( 'div', {'class':'articleWrapper'} )

	missing = soup.find( 'p', text=u"The article you are looking for does not exist.  It may have been deleted." )
	if missing:
		ukmedia.DBUG2( "IGNORE missing article '%s' (%s)\n" % (art['title'],art['srcurl'] ) )
		return None

	headline = wrapdiv.find( 'h1', { 'class':'articleHeading' } )
	art['title'] = headline.renderContents( None )
	art['title'] = ukmedia.FromHTML( art['title' ] )
	art['title'] = ukmedia.UncapsTitle( art['title'] )		# don't like ALL CAPS HEADLINES!  

	datepara = wrapdiv.find( 'p', {'class':'date'} )
	art['pubdate'] = ukmedia.ParseDateTime( datepara.renderContents(None).strip() )

	introcopypara = wrapdiv.find( 'p', {'class': 'introcopy' } )
	art['description'] = ukmedia.FromHTML( introcopypara.renderContents(None) )

	bylineh4 = wrapdiv.find( 'h4' )
	if bylineh4:
		art['byline'] = ukmedia.FromHTML(bylineh4.renderContents(None))
	else:
		# for some sections, try extracting a journo from the description...
		# (Express usually has names IN ALL CAPS, which the byline-o-matic
		# misses, so we'll turn anything likely-looking into titlecase
		# first).
		art['byline'] = u''
		if art['srcurl'].find('/travel/') != -1 or art['srcurl'].find('/motoring/') != -1:
			desc = ukmedia.DecapNames( art['description'] )
			art['byline'] = ukmedia.ExtractAuthorFromParagraph( desc )


	# cruft removal - mismatched tags means that cruft can get drawn into
	# story paragraphs... sigh...

#	cruft = wrapdiv.find('a', {'name':'comments'} )
#	if cruft:
#		# delete _everything_ from the comments onward
#		n = cruft.next
#		cruft.extract()
#		cruft = n

	for cruft in wrapdiv.findAll('form' ):		# (search form etc )
		cruft.extract()

	# OK to build up text body now!
	textpart = BeautifulSoup()
	textpart.insert( len(textpart.contents), introcopypara )

	#for para in wrapdiv.findAll( 'p', ):	#{'class':'storycopy'} ):

	# sigh... sometimes express articles have nested paras, without the
	# "storycopy" class. probably due to cutting and pasting from another
	# source...
	p = wrapdiv.find( 'p', {'class':'storycopy'} )
	while p:
		n = p.findNext('p')
		# because of the tag mismatching, we sometimes
		# get nested paras. extract()ing paras as we go should flatten
		# things out...
		p.extract()
		textpart.append( p )
		p = n

	content = textpart.prettify( None )
	content = ukmedia.DescapeHTML( content )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	return art


def ScrubFunc( context, entry ):
	# there are some test articles lurking in the rss feeds - skip 'em!
	if context['srcurl'].startswith( "http://venus.netro42.com" ):
		return None

	context['srcid'] = CalcSrcID( context['srcurl'] )

	return context


def FindArticles():
	""" get a set of articles to scrape from the express rss feeds """
	return ukmedia.FindArticlesFromRSS( rssfeeds, u'express', ScrubFunc )



def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'express'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

