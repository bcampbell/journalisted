#!/usr/bin/env python2.4

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


rssfeeds = { 'News': 'http://www.thesun.co.uk/rssFeed/rssIndexDisplay/0,,2,00.xml' }


# format: 'September 06, 2006'
# return None if no match
def CrackDate( raw ):
	datepat = re.compile( '(\w+) ([0-9]{2}), ([0-9]{4})' )
	m = datepat.search( raw )
	if not m:
		return None
	month = ukmedia.MonthNumber( m.group(1) )
	day = int( m.group(2) )
	year = int( m.group(3) )

	return datetime( year,month,day )




def Extract( html, context ):
	art = context

	soup = BeautifulSoup( html )

	# might have date then byline, or just date
	pubdate = soup.find( 'span', { 'class': 'black12' } )

	# find the containing <td> element for later
	td = pubdate.parent

	# is it a date?
	d = CrackDate( pubdate.renderContents( None ) )
	art['byline'] = u''
	if not d:
		# not a date - assume it's the byline
		byline = pubdate
		b = byline.renderContents( None )
		b = ukmedia.StripHTML( b )
		b = ukmedia.DescapeHTML( b )
		art['byline'] = b.strip()

		# date follows
		pubdate = byline.findNextSibling( 'span', { 'class': 'black12' } )
		d = CrackDate( pubdate.renderContents( None ) )

	art['pubdate'] = d



	# just use a raw regex on the html to get the article body
	# try these patterns in order of preference:
	contentpats = [
		re.compile( u"<span\\s+class=\"norm12\">(.*?)<P\\s+align=right>", re.UNICODE|re.DOTALL ),
		re.compile( u"<span\\s+class=\"?norm12\"?>(.*?)<CENTER>", re.UNICODE|re.DOTALL ),
		re.compile( u"<span\\s+class=\"norm12\">(.*?)<a href=\"javascript: var emailWin=window.open", re.UNICODE|re.DOTALL ),
		]

	m = None
	for p in contentpats:
		m = p.search( html )
		if m:
			break	

	# try and sanitise the content html
	contentsoup = BeautifulSoup( m.group(1) )
	# kill off photos
	for cruft in contentsoup.findAll( 'table' ):
		cruft.extract()
	# some of the contentpats can leave some leftover table cruft...
	for cruft in contentsoup.findAll( 'tr' ):
		cruft.extract()

	art['content'] = contentsoup.prettify( None )
	art['content'] = ukmedia.DescapeHTML( art['content'] )

	# first child should be navigable string...
	art['description'] = unicode(contentsoup.contents[0])
	art['description'] = ukmedia.StripHTML( art['description'] )
	art['description'] = ukmedia.DescapeHTML( art['description'] )

	return art







trimpat = re.compile( u'#cid=.*?$', re.UNICODE )

def ScrubFunc( context, entry ):
	"""mungefunc for ukmedia.FindArticlesFromRSS()"""

	#TODO: use guid instead of link!
	# the suns rss goes through mediafeed.com, but the guid still
	# contains the real link.
	url = entry.guid

	url = trimpat.sub( '', url )

	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = url

	return context



def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'sun', ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

