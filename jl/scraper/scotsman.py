#!/usr/bin/env python2.4
#
# Scraper for the Scotsman (and Scotland on Sunday and Edinburgh News)
#

import re
from datetime import date,datetime
import time
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


rssfeeds = {
	'Scotsman.com News': 'http://news.scotsman.com/index.cfm?format=rss',
	# I _think_ "latest news" is all from reuters...
	'Scotsman.com News - Latest News': 'http://news.scotsman.com/latest.cfm?format=rss',
	'Scotsman.com News - Latest News - UK': 'http://news.scotsman.com/latest_uk.cfm?format=rss',
	'Scotsman.com News - Latest News - International': 'http://news.scotsman.com/latest_international.cfm?format=rss',
	'Scotsman.com News - Latest News - Entertainment': 'http://news.scotsman.com/latest_entertainment.cfm?format=rss',
	'Scotsman.com News - Latest News - Odd': 'http://news.scotsman.com/latest_odd.cfm?format=rss',
	'Scotsman.com News - Latest News - Technology': 'http://news.scotsman.com/latest_technology.cfm?format=rss',
	'Scotsman.com News - Scotland': 'http://news.scotsman.com/scotland.cfm?format=rss',
	'Scotsman.com News - Scotland - Aberdeen': 'http://news.scotsman.com/aberdeen.cfm?format=rss',
	'Scotsman.com News - Scotland - Dundee': 'http://news.scotsman.com/dundee.cfm?format=rss',
	'Scotsman.com News - Scotland - Edinburgh': 'http://news.scotsman.com/edinburgh.cfm?format=rss',
	'Scotsman.com News - Scotland - Glasgow': 'http://news.scotsman.com/glasgow.cfm?format=rss',
	'Scotsman.com News - Scotland - Inverness': 'http://news.scotsman.com/inverness.cfm?format=rss',
	'Scotsman.com News - UK': 'http://news.scotsman.com/uk.cfm?format=rss',
	'Scotsman.com News - International': 'http://news.scotsman.com/international.cfm?format=rss',
	'Scotsman.com News - Politics': 'http://news.scotsman.com/politics.cfm?format=rss',
	'Scotsman.com News - Sci-Tech': 'http://news.scotsman.com/scitech.cfm?format=rss',
	'Scotsman.com News - Health': 'http://news.scotsman.com/health.cfm?format=rss',
	'Scotsman.com News - Education': 'http://news.scotsman.com/education.cfm?format=rss',
	'Scotsman.com News - Entertainment': 'http://news.scotsman.com/entertainment.cfm?format=rss',
	'Scotsman.com News - Entertainment - Movies': 'http://news.scotsman.com/movies.cfm?format=rss',
	'Scotsman.com News - Entertainment - Music': 'http://news.scotsman.com/music.cfm?format=rss',
	'Scotsman.com News - Entertainment - Arts': 'http://news.scotsman.com/arts.cfm?format=rss',
	'Scotsman.com News - Entertainment - Celebrities': 'http://news.scotsman.com/celebrities.cfm?format=rss',
	# TODO: add language markers to articles!
#	'Scotsman.com News - Gaelic': 'http://news.scotsman.com/gaelic.cfm?format=rss',
	'Scotsman.com News - Opinion': 'http://news.scotsman.com/opinion.cfm?format=rss',
	'Scotsman.com News - Opinion - Leaders': 'http://news.scotsman.com/leaders.cfm?format=rss',
	'Scotsman.com News - Opinion - Columnists': 'http://news.scotsman.com/columnists.cfm?format=rss',
	'Scotsman.com News - Obituaries': 'http://news.scotsman.com/obituaries.cfm?format=rss',
}





def ScrubFunc( context, entry ):
	# pull id out of the url
	url = context['srcurl']
	idpat = re.compile( 'id=(\\d+)\\s*$' )
	m = idpat.search( url )
	context['srcid'] = m.group(1)
	return context



def Extract( html, context ):

	if "<p class=\"footerResource\">To read the full article now" in html:
		raise ukmedia.NonFatal, "subscription-only article"

	art = context
	
	soup = BeautifulSoup( html )

	# figure out which publication it's from
	pubhome = soup.find( 'div', {'id':'publication'} ).a['href']
	if pubhome == "http://edinburghnews.scotsman.com/":
		srcorgname = u'edinburghnews'
	elif pubhome == "http://thescotsman.scotsman.com/":
		srcorgname = u'scotsman'
	elif pubhome == "http://scotlandonsunday.scotsman.com/":
		srcorgname = u'scotlandonsunday'
	elif pubhome ==	'http://partners.scotsman.com/?redirect=http%3A%2F%2Fwww%2Ereuters%2Eco%2Euk%2F':
		# put reuters articles under 'scotsman' for now...
		srcorgname = u'reutersscotsman'
	elif pubhome == "http://www.scotsman.com/":
		# scotsman.com - I guess this is online-only article...
		srcorgname = u'scotsman'
	else:
		raise Exception, "Can't determine publication! (url: %s)" % (pubhome)
	art['srcorgname'] = srcorgname


	titleh2 = soup.find( 'h2' )
	title = ukmedia.FromHTML( titleh2.renderContents( None ) )

	byline = None
	bylinediv = soup.find( 'div', {'id':'byline'} )
	if bylinediv:
		namespan = bylinediv.find( 'span', {'class':'name'} )
		if namespan:
			byline = namespan.renderContents( None ).strip()

	# use last updated as pubdate
	lastupdatedp = soup.find( 'p', {'id':'updated'} )
	foo = lastupdatedp.find( text=re.compile( '\\d+-\\w+-\\d\\d \\d\\d:\\d\\d' ) )
	pubdate = ukmedia.ParseDateTime( unicode(foo).strip() )

	if bylinediv:
		startmark = bylinediv
	else:
		startmark = titleh2

	textpart = BeautifulSoup()
	for tag in startmark.findNextSiblings():

		# reuters articles don't have bylinediv, instead it is first para
		if byline == None:
			if tag.name == 'p' and tag.find( text=re.compile( '^\\s*By .*' ) ):
				byline = tag.renderContents(None).strip()
			else:
				byline = u''

		# check for various end-of-article-text markers
		strong = tag.find( 'strong' )
		if strong and strong.find( text=re.compile( 'Related topics?' ) ):
			break
		if tag.name == 'p' and tag.get( 'class' ) == 'print':
			break
		if tag.name == 'p' and tag.get( 'id' ) == 'updated':
			break
		if tag.name == 'div' and tag.get( 'id' ) == 'comments':
			break

		textpart.append( tag )

	content = textpart.prettify(None)

	art['title'] = title
	art['content'] = content
	art['byline'] = ukmedia.FromHTML( byline )
	art['pubdate'] = pubdate

	return art


def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'scotsman', ScrubFunc )

	store = ArticleDB.DummyArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0


if __name__ == "__main__":
    sys.exit(main())

