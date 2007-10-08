#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

import sys
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup,BeautifulStoneSoup
from JL import ukmedia, ArticleDB


expressroot = u'http://www.express.co.uk'


rssfeeds = {
	'News / Showbiz': 'http://www.express.co.uk/rss/news.xml',
#	'Sport': 'http://www.express.co.uk/rss/sport.xml',
#	'Features (All Areas)': 'http://www.express.co.uk/rss/features.xml',
#	'Day & Night': 'http://www.express.co.uk/rss/dayandnight.xml',
#	'Express Yourself': 'http://www.express.co.uk/rss/expressyourself.xml',
#	'Health': 'http://www.express.co.uk/rss/health.xml',
#	'Fashion & Beauty': 'http://www.express.co.uk/rss/fashionandbeauty.xml',
#	'Gardening': 'http://www.express.co.uk/rss/gardening.xml',
#	'Food & Recipes': 'http://www.express.co.uk/rss/food.xml',
#	'Have Your Say': 'http://www.express.co.uk/rss/haveyoursay.xml',
#	'Express Comment': 'http://www.express.co.uk/rss/expresscomment.xml',
#	'Entertainment(All Areas)': 'http://www.express.co.uk/rss/entertainment.xml',
#	'Music Reviews': 'http://www.express.co.uk/rss/music.xml',
#	'DVD Reviews': 'http://www.express.co.uk/rss/dvd.xml',
#	'Film Reviews': 'http://www.express.co.uk/rss/films.xml',
#	'Theatre Reviews': 'http://www.express.co.uk/rss/theatre.xml',
#	'Book Reviews': 'http://www.express.co.uk/rss/books.xml',
#	'TV Guide': 'http://www.express.co.uk/rss/tv.xml',
#	'The Crusader': 'http://www.express.co.uk/rss/crusader.xml',
#	'Money (All Areas)': 'http://www.express.co.uk/rss/money.xml',
#	'City & Business': 'http://www.express.co.uk/rss/city.xml',
#	'Your Money': 'http://www.express.co.uk/rss/yourmoney.xml',
#	'Columnists (All)': 'http://www.express.co.uk/rss/columnists.xml',
#	'Motoring': 'http://www.express.co.uk/rss/motoring.xml',
#	'Travel': 'http://www.express.co.uk/rss/travel.xml',
#	'Competitions': 'http://www.express.co.uk/rss/competitions.xml',
#	'Express BLOGS': 'http://www.express.co.uk/rss/blogs.xml',
}



# eg '02/09/06'
def CrackDate( raw ):
	dpat = re.compile( '([0-9]{2})/([0-9]{2})/([0-9]{2})' )

	m=dpat.search(raw)
	day = int( m.group(1) )
	month = int( m.group(2) )
	year = int( '20' + m.group(3) )

	return datetime( year, month, day )


def Extract( html, context ):
	art = context

	soup = BeautifulStoneSoup( html )



	headline = soup.find( 'h1', { 'class':'articleHeading' } )
	art['title'] = headline.renderContents( None )
	art['title'] = ukmedia.FromHTML( art['title' ] )
	art['title'] = ukmedia.UncapsTitle( art['title'] )		# don't like ALL CAPS HEADLINES!  

	datepara = soup.find( 'p', {'class':'date'} )
	art['pubdate'] = ukmedia.ParseDateTime( datepara.renderContents(None).strip() )

	bylineh4 = soup.find( 'h4' )
	if bylineh4:
		art['byline'] = ukmedia.FromHTML(bylineh4.renderContents(None))
	else:
		art['byline'] = u''

	introcopypara = soup.find( 'p', {'class': 'introcopy' } )
	art['description'] = ukmedia.FromHTML( introcopypara.renderContents(None) )

	textpart = BeautifulSoup()
	textpart.insert( len(textpart.contents), introcopypara )

	for para in soup.findAll( 'p', {'class':'storycopy'} ):
		# kill off some stuff we might accidentally pick up as text
		# (search form embedded in article)
		for cruft in para.findAll( 'form', {'action':'/search/'} ):
			cruft.extract()
		textpart.append( para )

	content = textpart.prettify( None )
	content = ukmedia.DescapeHTML( content )
	# stonesoup matches <br> tags - replace them with single <br> instead
	content = re.sub( u'<br>\s*', u'', content )
	content = content.replace( u'</br>', u'<br>' )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	return art




def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'express' )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

