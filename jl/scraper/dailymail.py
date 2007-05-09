#!/usr/bin/env python

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


rssfeeds = {
	'News': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1770',
	'World News': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1770',


#	'Homepage':'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1766',
#	'Comment': 'http://www.dailymail.co.uk/pages/xml/index.html?in_page_id=1787' 
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
	art['description'] = ukmedia.DescapeHTML( art['description'] )

	articlediv = soup.find( 'div', id='ArtContent' )

	# get headline
	headline = articlediv.find( 'h1' )
	art[ 'title' ] = headline.renderContents( None )
	art[ 'title' ] = ukmedia.DescapeHTML( art['title'] )


	# get date posted
	datespan = articlediv.find( 'span', {'class':'artDate' } )
	art['pubdate'] = CrackDate( datespan.string )

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

	# dailymail RSS feeds go through feedburner, but luckily the original url is still there...
	url = entry.feedburner_origlink
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

