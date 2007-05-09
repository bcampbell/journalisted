#!/usr/bin/env python

import sys
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulStoneSoup
from JL import ukmedia, ArticleDB


expressroot = u'http://www.express.co.uk'

frontpages = {
	'Breaking News':'http://www.express.co.uk/news_breaking.html',
	'News':'http://www.express.co.uk/news.html'
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

	articletd = soup.find( 'td', { 'class':'article' } )

	headline = articletd.find( 'h1' )
	art['title'] = headline.renderContents( None ).strip()
	art['title'] = ukmedia.DescapeHTML( art['title' ] )

	pubdate = articletd.find( 'h4' )
	art['pubdate'] = CrackDate( pubdate.renderContents() )

	content = articletd.find( 'h2' )


	if articletd.find( 'span', {'class':'pa'} ):
		art['byline'] = u'pa'	# Press Association
	else:
		art['byline'] = u''

	art['content'] = content.renderContents( None )

	# first child should be navigable string...
	art['description'] = unicode(content.contents[0])

	return art


def FindArticles():
	""" Find articles by just grepping the front pages for links """
	foundarticles = []
	
	articlepat = re.compile( u'href="(\\w*?\\.html\\?sku=[^"]*)"><b>(Read )?More..</b></a></td>', re.UNICODE )

	for pagename, pageurl in frontpages.iteritems():
		ukmedia.DBUG( '---Express: scraping %s page\n' % (pagename) )
		pagehtml = ukmedia.FetchURL( pageurl ).decode( "iso-8859-1" )
		fetchtime = datetime.now()

		for m in articlepat.finditer( pagehtml ):
			context = {}
			context['srcid'] = m.group(1)
			context['srcurl'] = expressroot + u'/' + m.group(1)
			context['permalink'] = context['srcurl']
			context['srcorgname'] = u'express'
			context['lastseen'] = fetchtime

			foundarticles.append( context )

	return foundarticles



def main():
	found = FindArticles()

	store = ArticleDB.DummyArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

