#!/usr/bin/env python

import sys
import re
from datetime import datetime
import sys

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulStoneSoup
from JL import ukmedia, ArticleDB


siteroot = u'http://www.dailystar.co.uk'

frontpages = {
	'Front Page': siteroot + '/index.html',
	'Top stories': siteroot + '/news.html',
	'Breaking News': siteroot + '/news_breaking.html',
	'Celeb News': siteroot + '/celeb.html',
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
	art['pubdate'] = CrackDate( pubdate.renderContents(None) )

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
	

	articlepat = re.compile( u'<a href="(news_detail\\.html\\?sku=[0-9]+)">((?!<img).*?)</a>', re.UNICODE )

	for pagename, pageurl in frontpages.iteritems():
		ukmedia.DBUG( '--- DailyStar: scraping %s\n' % (pageurl) )
		pagehtml = ukmedia.FetchURL( pageurl ).decode( "iso-8859-1" )
		fetchtime = datetime.now()

		for m in articlepat.finditer( pagehtml ):
			context = {}
			context['srcid'] = m.group(1)
			context['srcurl'] = siteroot + u'/' + m.group(1)
			context['permalink'] = context['srcurl']
			context['srcorgname'] = u'dailystar'
			context['lastseen'] = fetchtime
			context['title'] = m.group(2)

			foundarticles.append( context )

	return foundarticles



def main():
	found = FindArticles()

	for f in found:
		print f['title']

#	store = ArticleDB.ArticleDB()
#	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

