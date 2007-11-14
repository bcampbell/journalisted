#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)

import re
from datetime import datetime
import sys
import os

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


rssfeeds = {
#	'Breaking News': 'http://www.telegraph.co.uk/newsfeed/rss/news-breaking_news.xml',
#	'International News': 'http://www.telegraph.co.uk/newsfeed/rss/news-international_news.xml',
#	'UK News': 'http://www.telegraph.co.uk/newsfeed/rss/news-uk_news.xml',
#	'Business': 'http://www.telegraph.co.uk/newsfeed/rss/money-city_news.xml',
#	'Personal finance': 'http://www.telegraph.co.uk/newsfeed/rss/money-personal_finance.xml',
#	'Opinion': 'http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_opinion.xml',
#	'Leaders': 'http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_leaders.xml',
#ben	'Sport': 'http://www.telegraph.co.uk/newsfeed/rss/sport.xml',
#ben	'Football': 'http://www.telegraph.co.uk/newsfeed/rss/sport-football.xml',
#ben	'Cricket': 'http://www.telegraph.co.uk/newsfeed/rss/sport-cricket.xml',
#ben	'Rugby Union': 'http://www.telegraph.co.uk/newsfeed/rss/sport-rugby_union.xml',
#ben	'Golf': 'http://www.telegraph.co.uk/newsfeed/rss/sport-golf.xml',

#	'Arts': 'http://www.telegraph.co.uk/newsfeed/rss/arts.xml',
#	'Books': 'http://www.telegraph.co.uk/newsfeed/rss/arts-books.xml',
#	'Connected': 'http://www.telegraph.co.uk/newsfeed/rss/connected.xml',
#	'Education': 'http://www.telegraph.co.uk/newsfeed/rss/education.xml',
#	'Expat': 'http://www.telegraph.co.uk/newsfeed/rss/global.xml',
#	'Fashion': 'http://www.telegraph.co.uk/newsfeed/rss/fashion.xml',
#	'Gardening': 'http://www.telegraph.co.uk/newsfeed/rss/gardening.xml',
#	'Health': 'http://www.telegraph.co.uk/newsfeed/rss/health.xml',
#	'Motoring': 'http://www.telegraph.co.uk/newsfeed/rss/motoring.xml',
#ben	'Property': 'http://www.telegraph.co.uk/newsfeed/rss/property.xml',

	# scraper handles travel articles OK, but they often have huge bylines with descriptions in them... so leave em out for now...
	# eg "The scooter holiday is an exhilarating way to catch the sights, sounds and smells of Italy, as Gregory Peck demonstrated 50 years ago. In Chianti, Charles Starmer-Smith follows his lead."
	# sigh.

#ben	'Travel': 'http://www.telegraph.co.uk/newsfeed/rss/travel.xml',
#	'Wine': 'http://www.telegraph.co.uk/newsfeed/rss/wine.xml',
	
	
    "Telegraph | Arts": 
# title="Telegraph | Arts"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/arts.xml",
    "Telegraph | Books": 
# title="Telegraph | Books"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/arts-books.xml",
    "Telegraph | Digital Life":
# title="Telegraph | Digital Life"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/connected.xml",

    "Telegraph | Earth": 
# title="Telegraph | Earth"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/earth.xml",
    "Telegraph | Science news":
# title="Telegraph | Science news"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/earth-science.xml",
    "Telegraph | Education":
# title="Telegraph | Education"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/education.xml",
    "Telegraph | Expat": 
# title="Telegraph | Expat"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/global.xml",
    "Telegraph | Fashion":
# title="Telegraph | Fashion"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/fashion.xml",
    "Telegraph | Gardening":
# title="Telegraph | Gardening"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/gardening.xml",
    "Telegraph | Health":
# title="Telegraph | Health"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/health.xml",
    "Telegraph | Motoring":
# title="Telegraph | Motoring"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/motoring.xml",
    "Telegraph | News | All":
# title="Telegraph | News | All"	 			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/news.xml",
    "Telegraph | News | Major":
# title="Telegraph | News | Major" 			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/news-major.xml",
    "Telegraph | News | UK":
# title="Telegraph | News | UK" 				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/news-uk_news.xml",
    "Telegraph | News | International":
# title="Telegraph | News | International"		xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/news-international_news.xml",
      
      # BLOGS:
#    "Telegraph | News | Blog Yourview":
# title="Telegraph | News | Blog Yourview"		xmlUrl=
 #   "http://www.telegraph.co.uk/newsfeed/rss/news-blog-yourview.xml",
  
  
    "Telegraph | News | Business":
# title="Telegraph | News | Business"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/money-city_news.xml",
    "Telegraph | Your Money":
# title="Telegraph | Your Money"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/money-personal_finance.xml",
      
      # blogs?
#    "Telegraph | Opinion":
# title="Telegraph | Opinion"			 	xmlUrl=
#    "http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_opinion.xml",


    "Telegraph | Leaders":
# title="Telegraph | Leaders"			 	xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/opinion-dt_leaders.xml",
    "Telegraph | Property":
# title="Telegraph | Property"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/property.xml",
    "Telegraph | Sport": 
# title="Telegraph | Sport"			 	xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport.xml",
    "Telegraph | Sport | Football":
# title="Telegraph | Sport | Football"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-football.xml",
    "Telegraph | Sport | Premiership Football":
# title="Telegraph | Sport | Premiership Football"        xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-football-premiership.xml",
    "Telegraph | Sport | Cricket":
# title="Telegraph | Sport | Cricket"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-cricket.xml",

# doesn't work?
#    "Telegraph | Sport | International Cricket":
# title="Telegraph | Sport | International Cricket"	xmlUrl=
#    "http://www.telegraph.co.uk/newsfeed/rss/sport-international_cricket.xml",
    "Telegraph | Sport | Rugby Union":
# title="Telegraph | Sport | Rugby Union"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-rugby_union.xml",
    "Telegraph | Sport | Golf":
# title="Telegraph | Sport | Golf"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-golf.xml",
    "Telegraph | Sport | Tennis":
# title="Telegraph | Sport | Tennis"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-tennis.xml",
    "Telegraph | Sport | Motor Sport":
# title="Telegraph | Sport | Motor Sport"			xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/sport-motor_sport.xml",
    "Telegraph | Travel":
# title="Telegraph | Travel"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/travel.xml",
    "Telegraph | Wine": 
# title="Telegraph | Wine"				xmlUrl=
    "http://www.telegraph.co.uk/newsfeed/rss/wine.xml",
  #  "Telegraph | Podcast":
# title="Telegraph | Podcast"				xmlUrl=
  #  "http://www.telegraph.co.uk/newsfeed/rss/podcast.xml",
  #  "Telegraph | Podcast | mp3":
# title="Telegraph | Podcast | mp3"			xmlUrl=
  #  "http://www.telegraph.co.uk/newsfeed/rss/podcastmp3.xml",
  
  # seems to cause an error:
#    "Telegraph | Top Ten Stories":
# title="Telegraph | Top Ten Stories"                     xmlUrl=
 #   "http://stats.telegraph.co.uk/rss/topten.xml",
      # type="rss" language="en-gb" /> 

	# blogs style?
#    "Telegraph | My Telegraph":
# title="Telegraph | My Telegraph"                        xmlUrl=
#    "http://my.telegraph.co.uk/feed.rss"
      # type="rss" language="en-gb" />   

 #   "Telegraph | Blogs | All Posts":
# title="Telegraph | Blogs | All Posts"                   xmlUrl=
 #   "http://blogs.telegraph.co.uk/Feed.rss"

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
	# there do seem to be a few borked pages on the site, so we'll treat it
	# as non-fatal (so it won't contribute toward the error count/abort)
	if re.search( """<title>.*404 Error: file not found</title>""", html ):
		raise ukmedia.NonFatal, ("missing article (telegraph doesn't return proper 404s)")

	art = context



	soup = BeautifulSoup( html )

	headline = soup.find( 'h1' )
	if not headline:
		# is it a blog? if so, skip it for now (no byline, so less important to us)
		# TODO: update scraper to handle blog page format
		hd = soup.find( 'div', {'class': 'bloghd'} )
		if hd:
			raise ukmedia.NonFatal, ("scraper doesn't yet handle blog pages (%s) on feed %s" % (context['srcurl'],context['feedname']) );
		# gtb:
		raise ukmedia.NonFatal, ("couldn't find headline to scrape (%s) on feed %s" % (context['srcurl'],context['feedname']) );

	title = ukmedia.DescapeHTML( headline.renderContents(None) )
	# strip out excess whitespace (and compress to one line)
	title = u' '.join( title.split() )
	art['title'] = title

	# we just use pubdate passed in from RSS, but might be better getting
	# it from the page (it has a 'last updated' item)
	# filedspan = soup.find( 'span', { 'class': 'filed' } )
	#    Last Updated: <span style="color:#000">2:43pm BST</span>&nbsp;16/04/2007

	# NOTE: in a lot of arts, motoring etc... we could get writer from
	# the first paragraph ("... Fred Smith reports",
	# "... talks to Fred Smith" etc)

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
		byline = ukmedia.FromHTML(byline)
		# single line, compress whitespace, strip leading/trailing space
		byline = u' '.join( byline.split() )

	art['byline'] = byline


	# Some articles have a hidden bit where the author name is stored:	
	# fill in author name:
	if True: #not byline:
		# cv.c6="/property/features/article/2007/10/25/lpsemi125.xml|Max+Davidson";
		authorMatch = re.search(u'cv.c6=".*?\|(.*?)";', html)
		if authorMatch:
			author = authorMatch.group(1)
			author = re.sub(u'\+',' ',author) 										# convert + signs to spaces
			author = re.sub(u'\\b([A-Z][a-z]{3,})([A-Z][a-z]+)\\b', '\\1-\\2', author)	# convert SparckJones to Sparck-Jones (that's how they encode it)
			# n.b. {3,} makes McTaggart not go to Mc-Taggart... bit hacky
			art['byline'] = author
	

	# text (all paras use 'story' or 'story2' class, so just discard everything else!)
	# build up a new soup with only the story text in it
	textpart = BeautifulSoup()

	art['description'] = ExtractParas( soup, textpart )


	if (not ('byline' in art)) or art['byline']==u'':
		author = ukmedia.ExtractAuthorFromParagraph(art['description'])
		if author!=u'':
			art['byline'] = author
		
# DEBUG:
#	if ('byline2' in art) and ('byline' in art) and art['byline2']!=art['byline']:
#		print "byline2: "+art['byline2']+" ("+art['byline']
#	elif ('byline2' in art):
#		print "byline2: "+art['byline2']

	# Deal with Multiple authors:
	# e.g."Borrowing money is becoming ever more difficult, say Harry Wallop and Faith Archer"

	# Deal with ones with no verb clue but there's only one name:
	#     "Many readers complain that the financial
    #         institutions that are keen to take their money are less willing to
    #         answer legitimate questions. Sometimes the power of the press, in
    #         the shape of Jessica Gorst-Williams, can help"


#################

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

	if False:		# debug
		print "\n\nARTICLE (+RSS CONTEXT) FIELDS:"
		for a in art.keys():
			# hack:
			print "\n",a,": ",
			if type(art[a])==type(u""):
				print art[a].encode('latin-1','replace')
			else:
				print str(art[a])


	return art


# pull out the article body paragraphs in soup and append to textpart
# returns description (taken from first nonblank paragraph)
def ExtractParas( soup, textpart ):
	desc = u''
	for para in soup.findAll( 'p', { 'class': re.compile( 'story2?' ) } ):

		# skip title/byline
		if para.find( 'h1' ):
			continue

		# quit if we hit one with the "post this story" links in it
		if para.find( 'div', { 'class': 'post' } ):
			break

		textpart.insert( len(textpart.contents), para )

		# we'll use first nonblank paragraph as description
		if desc == u'':
			desc = ukmedia.FromHTML( para.renderContents(None) )
			
	# gtb: replace all whitespace (including newlines) by one space... 
	# (needed for author extraction from description)
	desc = re.sub(u'\s+',' ', desc)
	return desc


def ScrubFunc( context, entry ):
	# suppress cruft pages
#	if context['title'] == 'Slideshowxl':
#		return None
	if context['title'] == 'Horoscopes':
		return None

	# skip slideshow pages, eg
	# "http://www.telegraph.co.uk/health/main.jhtml?xml=/health/2007/07/10/pixbeauty110.xml",
	slideshow_pattern = pat=re.compile( '/pix\\w+[.]xml$' )
	if slideshow_pattern.search( context['srcurl'] ):
		return None

	# we'll assume that all articles published on a Sunday are from
	# the sunday telegraph...
	if context['pubdate'].strftime( '%a' ).lower() == 'sun':
		context['srcorgname'] = u'sundaytelegraph'
	else:
		context['srcorgname'] = u'telegraph'
	return context


def main():

	# test	
#	author = "Karen SparckJones"
#	author = re.sub(u'\\b([A-Z][a-z]+)([A-Z][a-z]+)\\b', '\\1-\\2', author)	# convert SparckJones to Sparck-Jones (that's how they encode it)
#	print author
#	return 0

	#debug test auto-byline-o-matic
#	print ukmedia.ExtractAuthorFromParagraph('The A380, the world\'s largest passenger plane, touched down in Sydney this morning, reports Francisca Kellett.')
#	return 0
	
	DEBUG_OUTPUT_TO_DIR = False#True
	if DEBUG_OUTPUT_TO_DIR:
		if not os.path.exists("output"):
			os.mkdir("output")
		sys.stdout = open("output/news_"+"telegraph"+".txt", 'w')
		sys.stderr = sys.stdout

	# DEBUG just one:
#	rssfeeds = {
#   "Telegraph | Glbal":
#    "http://www.telegraph.co.uk/newsfeed/rss/global.xml",
#	}


	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'telegraph', ScrubFunc )

	if False:#True:#debug
		store = ArticleDB.DummyArticleDB()	# testing
	else:
		store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0



if __name__ == "__main__":
    sys.exit(main())

