#!/usr/bin/env python

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


rssfeeds = {
	'Breaking News': 'http://www.telegraph.co.uk/newsfeed/rss/news-breaking_news.xml',
	'International News': 'http://www.telegraph.co.uk/newsfeed/rss/news-international_news.xml',
	'UK News': 'http://www.telegraph.co.uk/newsfeed/rss/news-uk_news.xml',
	'Business': 'http://www.telegraph.co.uk/newsfeed/rss/money-city_news.xml',
	'Personal finance': 'http://www.telegraph.co.uk/newsfeed/rss/money-personal_finance.xml',
	'Opinion': 'http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_opinion.xml',
	'Leaders': 'http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_leaders.xml',
#	'Sport': 'http://www.telegraph.co.uk/newsfeed/rss/sport.xml',
#	'Football': 'http://www.telegraph.co.uk/newsfeed/rss/sport-football.xml',
#	'Cricket': 'http://www.telegraph.co.uk/newsfeed/rss/sport-cricket.xml',
#	'Rugby Union': 'http://www.telegraph.co.uk/newsfeed/rss/sport-rugby_union.xml',
#	'Golf': 'http://www.telegraph.co.uk/newsfeed/rss/sport-golf.xml',

	'Arts': 'http://www.telegraph.co.uk/newsfeed/rss/arts.xml',
	'Books': 'http://www.telegraph.co.uk/newsfeed/rss/arts-books.xml',
	'Connected': 'http://www.telegraph.co.uk/newsfeed/rss/connected.xml',
	'Education': 'http://www.telegraph.co.uk/newsfeed/rss/education.xml',
	'Expat': 'http://www.telegraph.co.uk/newsfeed/rss/global.xml',
	'Fashion': 'http://www.telegraph.co.uk/newsfeed/rss/fashion.xml',
	'Gardening': 'http://www.telegraph.co.uk/newsfeed/rss/gardening.xml',
	'Health': 'http://www.telegraph.co.uk/newsfeed/rss/health.xml',
	'Motoring': 'http://www.telegraph.co.uk/newsfeed/rss/motoring.xml',
#	'Property': 'http://www.telegraph.co.uk/newsfeed/rss/property.xml',
	'Travel': 'http://www.telegraph.co.uk/newsfeed/rss/travel.xml',
	'Wine': 'http://www.telegraph.co.uk/newsfeed/rss/wine.xml',
}


srcidpat = re.compile( """main\.jhtml\?xml=(.*?)$""" )

#def CalcPrintURL( fullurl ):
#	m = srcidpat.search( fullurl )
#	srcid = m.group(1)
#	print srcid
#	printerurl = "http://www.telegraph.co.uk/core/Content/displayPrintable.jhtml?xml=" + srcid
#	print printerurl
#	return printerurl


# return datetime, or None if matching fails


def Extract( html, context ):

	# Sometimes the telegraph has missing articles.
	# But the website doesn't return proper 404 (page not found) errors.
	# Instead, it redirects to an error page which has a 200 (OK) code.
	# Sigh.
	if re.search( """<title>.*404 Error: file not found</title>""", html ):
		raise Exception, ("missing article (telegraph doesn't return proper 404s)")

	art = context



	soup = BeautifulSoup( html )

	headline = soup.find( 'h1' )
	art['title'] = ukmedia.DescapeHTML( headline.renderContents(None) ).strip()
	# we just use pubdate passed in from RSS, but might be better getting
	# it from the page (it has a 'last updated' item)
	# filedspan = soup.find( 'span', { 'class': 'filed' } )
	#    Last Updated: <span style="color:#000">2:43pm BST</span>&nbsp;16/04/2007

	bylinespan = soup.find( 'span', { 'class': 'storyby' } )
	byline = u''
	if bylinespan:
		byline = bylinespan.renderContents( None )

		#if re.search( u',\\s+Sunday\\s+Telegraph\\s*$', byline ):
			# byline says it's the sunday telegraph
		#	if art['srcorgname'] != 'sundaytelegraph':
		#		raise Exception, ( "Byline says Sunday Telegraph!" )
		#else:
		#	if art['srcorgname'] != 'telegraph':
		#		raise Exception, ( "Byline says Telegraph!" )

		# don't need ", Sunday Telegraph" on end of byline
		byline = re.sub( u',\\s+Sunday\\s+Telegraph\\s*$', u'', byline )

	art['byline'] = byline


	# text (all paras use 'story' or 'story2' class, so just discard everything else!)
	# build up a new soup with only the story text in it
	textpart = BeautifulSoup()

	ExtractParas( soup, textpart )
	# TODO: support multi-page articles
	# check for and grab other pages here!!!
	# (note: printable version no good - only displays 1st page)


	if textpart.find('p') == None:
		# no text!
		if html.find( """<script src="/portal/featurefocus/RandomSlideShow.js">""" ) != -1 or art['title'] == 'Slideshowxl':
			# it's a slideshow, we'll quietly ignore it
			return None
		else:
			raise Exception, 'No text found'

	content = textpart.prettify(None)
	content = ukmedia.DescapeHTML( content )
	content = ukmedia.SanitiseHTML( content )
	art['content'] = content

	# we'll use first paragraph as description
	# NOTE: in a lot of arts, motoring etc... we could get writer from
	# the first paragraph ("... Fred Smith reports",
	# "... talks to Fred Smith" etc)
	desc = textpart.p.renderContents( None )
	desc = ukmedia.DescapeHTML( desc )
	desc = ukmedia.StripHTML( desc )
	art['description'] = desc


	return art


# pull out the article body paragraphs in soup and append to textpart
def ExtractParas( soup, textpart ):
	for para in soup.findAll( 'p', { 'class': re.compile( 'story2?' ) } ):

		# skip title/byline
		if para.find( 'h1' ):
			continue

		# quit if we hit one with the "post this story" links in it
		if para.find( 'div', { 'class': 'post' } ):
			break

		textpart.insert( len(textpart.contents), para )




def ScrubFunc( context, entry ):
	# suppress cruft pages
#	if context['title'] == 'Slideshowxl':
#		return None
	if context['title'] == 'Horoscopes':
		return None

	# we'll assume that all articles published on a Sunday are from
	# the sunday telegraph...
	if context['pubdate'].strftime( '%a' ).lower() == 'sun':
		context['srcorgname'] = u'sundaytelegraph'
	else:
		context['srcorgname'] = u'telegraph'
	return context


def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'telegraph', ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0



def OLDmain():
	url = sys.argv[1]

	#f= open(url,"rt")
	#html= f.read()
	#f.close()
	html = ukmedia.FetchURL( url )

	context = {
	}
	art = Extract( html, context )

	print art['content'].encode( 'utf-8' )
	print "---"
	print art['description'].encode( 'utf-8' )
	print "---"
	print art['byline'].encode( 'utf-8' )


if __name__ == "__main__":
    sys.exit(main())

