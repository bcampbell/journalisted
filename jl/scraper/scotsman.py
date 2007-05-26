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
	'Headlines': 'http://news.scotsman.com/index.cfm?format=rss',
	'Latest News': 'http://news.scotsman.com/latest.cfm?format=rss'
	# TODO: More! Lots more...
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
		raise Exception, "subscription-only article"

	art = context
	
	soup = BeautifulSoup( html )

	print art['srcurl']
	# figure out which publication it's from
	pubhome = soup.find( 'div', {'id':'publication'} ).a['href']
	if pubhome == "http://edinburghnews.scotsman.com/":
		srcorgname = u'edinburghnews'
	elif pubhome == "http://thescotsman.scotsman.com/":
		srcorgname = u'scotsman'
	elif pubhome == "http://scotlandonsunday.scotsman.com/":
		srcorgname = u'scotlandonsunday'
	else:
		# TODO: what to do about reuters articles?
		raise Exception, "Can't determine publication!"
	art['srcorgname'] = srcorgname


	titleh2 = soup.find( 'h2' )
	title = titleh2.renderContents( None ).strip()

	byline = u''
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
	art['byline'] = byline
	art['pubdate'] = pubdate

	return art


def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'scotsman', ScrubFunc )

	store = ArticleDB.DummyArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0


if __name__ == "__main__":
    sys.exit(main())

