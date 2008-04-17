#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the guardian and observer
#
# NOTE: guardian unlimited has updated their site. They were using
# vignette storyserver, but have now written their own I think.
#
# Main RSS page doesn't seem be be updated with feeds from new sections
# (presumably it'll be rejigged once the transition is complete)
# For the new-style sections, there is usually one feed for the main
# section frontpage, and then an extra feed for each subsection. Just
# click through all the subsection frontpages and look for the RSS link.
#
# TODO:
# - Update RSS feed list - currently a mix of old and new ones. Probably
#   should scrape the list from their site, but can't do that until they
#   have a proper rss index, if they ever do...
# - extract journo names from descriptions if possible...
# - sort out guardian/observer from within Extract fn
# - For new-format articles, could use class attr in body element to
#   ignore polls and other cruft. <body class="article"> is probably
#   the only one we should accept...

import re
from datetime import date,datetime,timedelta
import time
import sys
import urlparse

import site
site.addsitedir("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import DB,ScraperUtils,ukmedia


rssfeeds = {

	# these two _should_ be everything from the printed editions of the guardian and observer...
	'Latest from the Guardian': 'http://www.guardian.co.uk/theguardian/all/rss',
	'Latest from the Observer':	'http://www.guardian.co.uk/theobserver/all/rss',

	# front page
	'guardian.co.uk home | guardian.co.uk': 'http://www.guardian.co.uk/rss',

	# NEWS
	'guardian.co.uk UK news': 'http://www.guardian.co.uk/uk/rss',
	'guardian.co.uk World news': 'http://www.guardian.co.uk/world/rss',
	'Guardian America | guardian.co.uk': 'http://www.guardian.co.uk/america/rss',
	'guardian.co.uk Politics': 'http://www.guardian.co.uk/politics/rss',
	'guardian.co.uk Media': 'http://www.guardian.co.uk/media/rss',

	# (section names IN CAPS below are new-style sections, with new rss feeds)

	# BUSINESS - http://www.guardian.co.uk/business
	'guardian.co.uk Business': 'http://www.guardian.co.uk/business/rss',
	'guardian.co.uk Business: Banking sector': 'http://www.guardian.co.uk/business/banking/rss',
	'guardian.co.uk Business: Pharmaceuticals industry': 'http://www.guardian.co.uk/business/pharmaceuticals/rss',
	'guardian.co.uk Business: Retail industry': 'http://www.guardian.co.uk/business/retail/rss',
	'guardian.co.uk Business: Mining': 'http://www.guardian.co.uk/business/mining/rss',
	'guardian.co.uk Business: Oil and gas industry': 'http://www.guardian.co.uk/business/oil/rss',
	'guardian.co.uk Business: Automotive industry': 'http://www.guardian.co.uk/business/automotive/rss',
	'guardian.co.uk Business: Construction industry': 'http://www.guardian.co.uk/business/construction/rss',
	'guardian.co.uk Business: Healthcare industry': 'http://www.guardian.co.uk/business/healthcare/rss',
	'guardian.co.uk Business: Insurance industry': 'http://www.guardian.co.uk/business/insurance/rss',
	'guardian.co.uk Business: Mergers and acquisitions': 'http://www.guardian.co.uk/business/mergersandacquisitions/rss',
	'guardian.co.uk Business: Technology': 'http://www.guardian.co.uk/business/technology/rss',
	'guardian.co.uk Business: Travel & leisure': 'http://www.guardian.co.uk/business/travelleisure/rss',
	'guardian.co.uk Business: Utilities': 'http://www.guardian.co.uk/business/utilities/rss',
	'guardian.co.uk Business: Market forces column': 'http://www.guardian.co.uk/business/marketforces/rss',
	'guardian.co.uk Business: Credit crunch': 'http://www.guardian.co.uk/business/creditcrunch/rss',
	'guardian.co.uk Business: Economics': 'http://www.guardian.co.uk/business/economics/rss',
	'guardian.co.uk Business: Interest rates': 'http://www.guardian.co.uk/business/interestrates/rss',
	'guardian.co.uk Business: US economy': 'http://www.guardian.co.uk/business/useconomy/rss',
	'guardian.co.uk Business: Viewpoint column': 'http://www.guardian.co.uk/business/series/viewpointcolumn/rss',
	'guardian.co.uk Business: Private equity': 'http://www.guardian.co.uk/business/privateequity/rss',
	'guardian.co.uk Business: Andrew Clark on America': 'http://www.guardian.co.uk/business/series/andrewclarkonamerica/rss',
	'guardian.co.uk Business: David Gow on Europe': 'http://www.guardian.co.uk/business/series/davidgowoneurope/rss',


	# ENVIRONMENT - http://www.guardian.co.uk/environment
	'Guardian Unlimited Environment': 'http://www.guardian.co.uk/environment/rss',
	'Guardian Unlimited Environment: Climate change': 'http://www.guardian.co.uk/environment/climatechange/rss',
	'Guardian Unlimited Environment: Conservation': 'http://www.guardian.co.uk/environment/conservation/rss',
	'Guardian Unlimited Environment: Energy': 'http://www.guardian.co.uk/environment/energy/rss',
	'Guardian Unlimited Environment: Ethical Living': 'http://www.guardian.co.uk/environment/ethicalliving/rss',
	'Guardian Unlimited Environment: Recycling': 'http://www.guardian.co.uk/environment/recycling/rss',
	'Guardian Unlimited Environment: Travel and transport': 'http://www.guardian.co.uk/environment/travelandtransport/rss',
	# missing "what can I do?" feed - url changes daily

	# SCIENCE - http://www.guardian.co.uk/science
	'Guardian Unlimited Science': 'http://www.guardian.co.uk/science/rss',
	'Guardian Unlimited Science: Science news': 'http://www.guardian.co.uk/science/sciencenews/rss',
	'Guardian Unlimited Science: Comment': 'http://www.guardian.co.uk/science/comment/rss',
	#	'Science podcasts | Guardian Unlimited': 'http://www.guardian.co.uk/science/podcast/rss',
	'Guardian Unlimited Science: Bad science': 'http://www.guardian.co.uk/science/series/badscience/rss',

	# TECHNOLOGY - http://www.guardian.co.uk/technology
	'Guardian Unlimited Technology': 'http://www.guardian.co.uk/technology/rss',
	'Guardian Unlimited Technology: News': 'http://www.guardian.co.uk/technology/news/rss',
	'Guardian Unlimited Technology: Comment': 'http://www.guardian.co.uk/technology/comment/rss',
	'Guardian Unlimited Technology: Games': 'http://www.guardian.co.uk/technology/games/rss',
	'Guardian Unlimited Technology: Gadgets': 'http://www.guardian.co.uk/technology/gadgets/rss',
	'Guardian Unlimited Technology: Internet': 'http://www.guardian.co.uk/technology/internet/rss',
	'Guardian Unlimited Technology: Inside IT': 'http://www.guardian.co.uk/technology/it/rss',
	'Guardian Unlimited Technology: Telecoms': 'http://www.guardian.co.uk/technology/telecoms/rss',
	#'Ask Jack': 'http://blogs.guardian.co.uk/askjack/atom.xml',

	#TRAVEL - http://www.guardian.co.uk/travel
	'Guardian Unlimited Travel': 'http://www.guardian.co.uk/travel/rss',
	'Guardian Unlimited Travel: Short breaks': 'http://www.guardian.co.uk/travel/shortbreaks/rss',
	'Guardian Unlimited Travel: Hotels': 'http://www.guardian.co.uk/travel/hotels/rss',
	'Guardian Unlimited Travel: Restaurants': 'http://www.guardian.co.uk/travel/restaurants/rss',


	# MEDIA
	'Guardian Unlimited Media': 'http://www.guardian.co.uk/media/rss',
	'Guardian Unlimited Media: Press and publishing': 'http://www.guardian.co.uk/media/pressandpublishing/rss',
	'Guardian Unlimited Media: Digital media': 'http://www.guardian.co.uk/media/digitalmedia/rss',
	'Guardian Unlimited Media: Advertising': 'http://www.guardian.co.uk/media/advertising/rss',
	'Guardian Unlimited Media: Television': 'http://www.guardian.co.uk/media/television/rss',
	'Guardian Unlimited Media: Radio': 'http://www.guardian.co.uk/media/radio/rss',
	'Guardian Unlimited Media: Marketing and PR': 'http://www.guardian.co.uk/media/marketingandpr/rss',
	'Guardian Unlimited Media: Media business': 'http://www.guardian.co.uk/media/mediabusiness/rss',


	# OLD STYLE FEEDS FROM HERE ON:

	'Guardian Unlimited front page':'http://www.guardian.co.uk/rssfeed/0,,1,00.xml',
	'UK latest': 'http://www.guardian.co.uk/rssfeed/0,,11,00.xml',
	'World latest': 'http://www.guardian.co.uk/rssfeed/0,,12,00.xml',
	'Guardian Unlimited Football': 'http://www.guardian.co.uk/rssfeed/0,,5,00.xml',
#	'Guardian Unlimited Business': 'http://www.guardian.co.uk/rssfeed/0,,24,00.xml',
	'Education Guardian': 'http://www.guardian.co.uk/rssfeed/0,,8,00.xml',
	'Guardian Unlimited Books': 'http://www.guardian.co.uk/rssfeed/0,,10,00.xml',
	'Guardian Unlimited Comment': 'http://www.guardian.co.uk/rssfeed/0,,27,00.xml',
	'Guardian Unlimited Environment': 'http://www.guardian.co.uk/rssfeed/0,,29,00.xml',
	'Guardian Unlimited Film news': 'http://www.guardian.co.uk/rssfeed/0,,16,00.xml',
	'Guardian Unlimited Leaders': 'http://www.guardian.co.uk/rssfeed/0,,28,00.xml',
	'Guardian Unlimited Politics': 'http://www.guardian.co.uk/rssfeed/0,15065,19,00.xml',
	'Guardian Unlimited Science': 'http://www.guardian.co.uk/rssfeed/0,,18,00.xml',
	'Guardian Unlimited Shopping': 'http://www.guardian.co.uk/rssfeed/0,,22,00.xml',
	'Guardian Unlimited Sport': 'http://www.guardian.co.uk/rssfeed/0,,7,00.xml',
	'Guardian Unlimited Technology': 'http://www.guardian.co.uk/rssfeed/0,,20,00.xml',
	'Guardian Unlimited The Guide': 'http://www.guardian.co.uk/rssfeed/0,,21,00.xml',
	# OLD media guardian rss
	'Media Guardian': 'http://www.guardian.co.uk/rssfeed/0,,4,00.xml',
	'The Observer': 'http://www.guardian.co.uk/rssfeed/0,,15,00.xml',
	'Society Guardian': 'http://www.guardian.co.uk/rssfeed/0,,9,00.xml',

	# education guardian
	'Education Guardian': 'http://www.guardian.co.uk/rssfeed/0,,8,00.xml',
	'Education Guardian TEFL news': 'http://www.guardian.co.uk/rssfeed/0,,30,00.xml',

	# Life and style
	'Guardian Unlimited Life and Style': 'http://www.guardian.co.uk/rssfeed/0,,44,00.xml',
	'Guardian Unlimited Life and Style Food': 'http://www.guardian.co.uk/rssfeed/0,,46,00.xml',


	# http://arts.guardian.co.uk/
	'Guardian Unlimited Art': 'http://www.guardian.co.uk/rssfeed/0,,40,00.xml',
#	'Guardian Unlimited: Arts blog':'http://blogs.guardian.co.uk/arts/atom.xml',
	# already got books

	# these blogs covered by separate scraper (blogs.py)
#	'Media Monkey': 'http://blogs.guardian.co.uk/mediamonkey/atom.xml',
#	'Guardian Unlimited: Organ Grinder': 'http://blogs.guardian.co.uk/organgrinder/atom.xml',

	# mediaguardian blogs
	# 'PDA': 'http://blogs.guardian.co.uk/digitalcontent/atom.xml',
	# 'Guardian Unlimited: Organ Grinder': 'http://blogs.guardian.co.uk/organgrinder/atom.xml',
	# 'Greenslade': 'http://blogs.guardian.co.uk/greenslade/atom.xml',

	'Guardian Unlimited Music': 'http://www.guardian.co.uk/rssfeed/0,,39,00.xml',
	'Guardian Unlimited Theatre & performance art': 'http://www.guardian.co.uk/rssfeed/0,,41,00.xml',

	# these I found by trying out URLs - Ben

	# 31 invalid
	'Guardian Unlimited Family': 'http://www.guardian.co.uk/rssfeed/0,,32,00.xml',
	'Guardian Unlimited Money expat finance news': 'http://www.guardian.co.uk/rssfeed/0,,33,00.xml',
	'Guardian Unlimited Health news': 'http://www.guardian.co.uk/rssfeed/0,,34,00.xml',
	'Guardian Unlimited Money property abroad news': 'http://www.guardian.co.uk/rssfeed/0,,35,00.xml',	
	# 36,37 invalid
	# 'testRssFeed': 'http://www.guardian.co.uk/rssfeed/0,,38,00.xml',
	# 'Nokia mobile tips': 'http://www.guardian.co.uk/rssfeed/0,,42,00.xml',		# short term promo?
	# 'Money Business news': 'http://www.guardian.co.uk/rssfeed/0,,43,00.xml',	# empty/unused?
	# 'Observer Food Monthly': 'http://www.guardian.co.uk/rssfeed/0,,45,00.xml',	# empty/unused?
	# 46-60 invalid
	}




# eg "http://www.guardian.co.uk/crime/article/0,,2212646,00.html"
urlpat_storyserver = re.compile( u".*/\w*,\w*,\w*,\w*\.html", re.UNICODE )

# eg "http://www.guardian.co.uk/environment/2007/nov/17/climatechange.carbonemissions1"
urlpat_newformat = re.compile(	u".*/.*(?!\.html)", re.UNICODE )


def WhichFormat( url ):
	""" figure out which format the article is going to be in """
	if urlpat_storyserver.match( url ):
		return 'storyserver'

	if urlpat_newformat.match( url ):
		return 'newformat'

	return 'UNKNOWN'


def Extract( html, context ):
	art = context
	soup = BeautifulSoup( html )

	# header contains headline, strapline
	headerdiv = soup.find( 'div', id="article-header" )
	if not headerdiv:
		# it's storyserver format
		return OldExtract( soup, context )

	# find title
	title = headerdiv.h1.renderContents(None)
	title = ukmedia.FromHTML(title)
	art[ 'title' ] = title


	contentdiv = soup.find( 'div', id="content" )


	# article-attributes
	# contains byline, date, publication...
	attrsdiv = contentdiv.find( 'ul', {'class':re.compile("""\\barticle-attributes\\b""")} )

	# byline
	byline = attrsdiv.find( 'li', { 'class':'byline' } )
	if byline:
		art['byline'] = ukmedia.FromHTML( byline.renderContents(None) )
	else:
		# TODO: could search for journo in description or "stand-first"
		# para in article-header div.
		art['byline'] = u''

	# date
	pubdate = attrsdiv.find( 'li', { 'class':'date' } ).renderContents(None).strip()
	art['pubdate'] = ukmedia.ParseDateTime( pubdate )

	# quick sanity check on publication
	publication = attrsdiv.find( 'li', { 'class':'publication' } ).a.string
	if 'srcorgname' in art:
		if art['srcorgname'] == u'observer' and publication != u'The Observer':
	#		raise Exception, ("Observer article not actually from observer?" )
			ukmedia.DBUG2( "WARNING: Observer article not actually from observer? '%s' (%s)\n" %( art['title'], art['srcurl'] ) );
	else:
		if publication == u'The Observer':
			art['srcorgname'] = u'observer'
		else:
			art['srcorgname'] = u'guardian'

	if 'srcid' not in art and '--dryrun' in sys.argv or '-d' in sys.argv:
		art['srcid'] = 'DRYRUN'
	
	# now strip out all non-text bits of content div
	attrsdiv.extract()
	cruft = contentdiv.find('ul', id='article-toolbox')
	if cruft:
		cruft.extract()
	cruft = contentdiv.find('div', id='contact')
	if cruft:
		cruft.extract()
	for cruft in contentdiv.findAll( 'div', {'class': 'send'} ):
		cruft.extract()

	# images
	for cruft in contentdiv.findAll( 'div', {'class':re.compile("""\\bimage\\b""") } ):
		cruft.extract()

	# long articles have a folding part

	# 1) 'shower' para to control folding
	showerpara = contentdiv.find( 'p', {'class':'shower'} )
	if showerpara:
		showerpara.extract()
	# 2) the extra text is inside the 'more-article' div
	morediv = contentdiv.find( 'div', id='more-article' );
	if morediv:
		morediv.extract()

	# move all the remaining elements into a fresh soup
	textpart = BeautifulSoup()
	for element in list(contentdiv.contents):
		textpart.append(element)  # removes from contentdiv!

	# if there was a folding bit, add its contents to the new soup too
	if morediv:
		for element in list(morediv.contents):
			textpart.append(element)  # removes from morediv!

	# Description
	desc = None

	# look for first-stand para first (appears in 'article-header')
	descpara = headerdiv.find( 'p', {'id':'stand-first'} )
	if descpara:
		desc = ukmedia.FromHTML( descpara.prettify(None) )

	if not desc:  # long first para
		# use <meta name="description" content="XXXXX">
		meta_desc = soup.head.find('meta', {'name': 'description'})
		if meta_desc and 'content' in dict(meta_desc.attrs):
			desc = meta_desc['content']

	if not desc:
		descpara = textpart.p  # no? just use first para of text instead.
		desc = ukmedia.FromHTML( descpara.prettify(None) )

	art['description'] = ukmedia.DescapeHTML(desc)

	# that's it!
	art['content'] = ukmedia.SanitiseHTML( textpart.prettify(None) )

	if not art['description']:
		art['description'] = ukmedia.FirstPara( art['content'] );
	return art





# extractor for old style articles...
def OldExtract( soup, context ):
	# Phasing this scraper out, it's the only way to solve the scraper duplication.
	from commentisfree import Extract2
	return Extract2(soup, context)
	
	art = context
	art['guardian-format'] = 'guardian.py (2)'  ###### OVERRIDE #######

#	soup = BeautifulSoup( html )
	articlediv = soup.find( 'div', id='GuardianArticle' )

	# find title
	t = articlediv.find( 'h1' )
	art[ 'title' ] = t.renderContents(None)
	art['title'] = ukmedia.DescapeHTML(art['title']).strip()

	# find block containing byline, date and publication
	a = t.findNextSibling( 'font', size='2' )
	namedatepat = re.compile( "(.*?)<br />(.*?)<br /><a href=\".*?\">(.*?)</a>", re.UNICODE )



	preamble = a.b.renderContents(None)
	m = namedatepat.search( preamble )
	if m:
		art[ 'byline' ] = m.group(1)
		art[ 'byline' ] = ukmedia.DescapeHTML( art['byline'] ).strip()
		art[ 'pubdate' ] = ukmedia.ParseDateTime( m.group(2) )

		# sanity check - check that we've guessed correct newspaper!
		publication = m.group(3)
		publication = publication.lower()
		if publication.find( 'observer' ) != -1:
			# article says it's from observer
			art['srcorgname'] = u'observer';
		else:
			# article not from observer
			art['srcorgname'] = u'guardian';

	else:
		art[ 'byline' ] = u''
		# couldn't find name + date, settle for just the date
		datepat = re.compile( "(.*?)<br />", re.UNICODE )
		m = datepat.search( preamble )
		# TODO: search for "More articles by [name]" link to get author
		art[ 'pubdate' ] = ukmedia.ParseDateTime( m.group(1) )

	# sometimes there's an intro/summary para just before the byline...
	# can look for authors in here
	if art['byline'] == u'':
		intro = a.findPreviousSibling( 'font', size='3' )
		if intro:
			introtext = intro.renderContents(None)
			art['byline'] = ukmedia.ExtractAuthorFromParagraph( introtext )

	bodydiv = articlediv.find( 'div', id='GuardianArticleBody' )

	# strip out embedded advertising rubbish
	advertdiv = bodydiv.find( 'div', id='spacedesc_mpu_div' )
	if advertdiv:
		advertdiv.extract()
	s = bodydiv.find( 'script' )
	if s:
		s.extract()

	# cull out embedded video player
	for cruft in bodydiv.findAll( 'div', { 'class': 'embed' } ):
		cruft.extract()

	text = bodydiv.renderContents( None )

	art[ 'content' ] = ukmedia.SanitiseHTML( text )

	desc = u''
	if 'description' in art:
		# we just use the description passed in (from the RSS feed)
		desc = ukmedia.FromHTML( art['description'] )
	else:
		# try using the first para as description
		desc = unicode( bodydiv.find( text=True ) )
		desc = ukmedia.FromHTML( desc )

	if desc == u'':	# still no luck? try the intro block
		intro = a.findPreviousSibling( 'font', size='3' )
		if intro:
			introtext = intro.renderContents(None)
			desc = ukmedia.FromHTML( introtext )

	art[ 'description' ] =  desc

	return art



urltrimpat = re.compile( u'\?gusrc=rss&feed=.*$', re.UNICODE )

def TidyURL( url ):
	""" Tidy up URL - trim off any extra cruft (eg rss tracking stuff) """
	url = urltrimpat.sub( '', url )
	return url


# patterns to extract srcids
# (just match against path part, as there could be params (eg "?page=all")

# "http://education.guardian.co.uk/schools/story/0,,2261002,00.html"
srcidpat_storyserver = re.compile( u'.*[/]([0-9,-]+)[.]html$' )

# "http://www.guardian.co.uk/world/2008/feb/29/afghanistan.terrorism"
srcidpat_newformat = re.compile( u'.*/(\d{4}/.*?/\d+/.*(?![.]html))$' )

def CalcSrcID( url ):
	""" Extract a unique srcid from the URL """
	o = urlparse.urlparse( url )
	if not re.search( "(.*[.])?guardian.co.uk$", o[1] ) and not re.search( "(.*[.])?observer.co.uk$", o[1] ) and not re.search( "(.*[.])?guardianunlimited.co.uk$", o[1] ):
		return None

	if 'blogs' in o[1]:
		return None		# blogs handled by blogs.py...

	m = srcidpat_storyserver.search( o[2] )
	if m:
		# old (storyserver) format
		return 'guardian_' + m.group(1)
	
	m = srcidpat_newformat.search( o[2] )
	if m:
		# new format
		return 'guardian_' + m.group(1)

	return None


def ScrubFunc( context, entry ):
	""" fn to massage info from RSS feed """

	url = TidyURL( context['permalink'] )
	context['permalink'] = url;
	context['srcid'] = CalcSrcID( url )

	if WhichFormat( url ) == 'newformat' and not url.startswith('file:'):
		# force whole article on single page
		context['srcurl'] = url + '?page=all'
	else:
		context['srcurl'] = url;

	# some items don't have pubdate
	# (they're probably special-case duds (eg flash pages), but try and
	# parse them anyway)
	if not context.has_key( 'pubdate' ):
		context['pubdate'] = datetime.now()

	# just take all articles on a sunday as being in the observer
	# (article itself should be able to tell us, but we'd like to know
	# _before_ we download the article, so we can see if it's already in the
	# DB)
	if context['pubdate'].strftime( '%a' ).lower() == 'sun':
		context['srcorgname'] = u'observer'
	else:
		context['srcorgname'] = u'guardian'

	# ---------------------
	# Some pages to ignore:
	#----------------------

	if url in ( 'http://www.guardian.co.uk/travel/typesoftrip', 'http://www.guardian.co.uk/travel/places' ):
		ukmedia.DBUG2( "IGNORE travel section link '%s' (%s)\n" % (context['title'], url) );
		return None

	for bad in ( 'gallery', 'video', 'quiz', 'slideshow', 'poll', 'cartoon' ):
		s = "/%s/" % (bad)
		if s in url:
			ukmedia.DBUG2( "IGNORE %s page '%s' (%s)\n" % ( bad, context['title'], url) );
			return None

	return context


# this fn is called after the article is added to the db.
# it looks for dupes, and keeps only the one with the highest
# srcid (which is probably the latest revsion in the guardian db)
#
# TODO: this could be made a lot more elegant by adding it to the
# transaction where the article is actually added to the db (in
# ArticleDB).
#
def DupeCheckFunc( artid, art ):
	srcorg = orgmap[ art['srcorgname'] ]
	pubdatestr = '%s' % (art['pubdate'])

	c = myconn.cursor()
	# find any articles with the same title published a day either
	# side of this one
	s = art['pubdate'] - timedelta(days=1)
	e = art['pubdate'] + timedelta(days=1)
	c.execute( "SELECT id,srcid FROM article WHERE status='a' AND "
		"srcorg=%s AND title=%s AND pubdate > %s AND pubdate < %s "
		"ORDER BY srcid DESC",
		srcorg,
		art['title'].encode('utf-8'),
		str(s), str(e) )

	rows = c.fetchall()
	if len(rows) > 1:
		# there are dupes!
		for dupe in rows[1:]:
			c.execute( "UPDATE article SET status='d' WHERE id=%s",
				dupe['id'] )
			myconn.commit()
			ukmedia.DBUG2( " hide dupe id=%s (srcid='%s')\n" % (dupe['id'],dupe['srcid']) )




def ContextFromURL( url ):
	"""get a context ready for scraping a single url"""
	url = TidyURL( url )

	context = {}
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )

	# not a 100% reliable test...
	if url.find( "observer.guardian.co.uk" ) == -1:
		context['srcorgname'] = u'guardian'
	else:
		context['srcorgname'] = u'observer'

	if WhichFormat( url ) == 'newformat' and not url.startswith('file:'):
		# force whole article on single page
		context['srcurl'] = url + '?page=all'
	else:
		context['srcurl'] = url

	context['lastseen'] = datetime.now()

	return context





def FindArticles():
	""" get current active articles via RSS feeds """
	return ukmedia.FindArticlesFromRSS( rssfeeds, u'guardian', ScrubFunc )




# connection and orgmap used by DupeCheckFunc()
myconn = DB.Connect()

orgmap = {}
c = myconn.cursor()
c.execute( "SELECT id,shortname FROM organisation" )
while 1:
	row=c.fetchone()
	if not row:
		break
	orgmap[ row[1] ] = row[0]
c.close()
c=None



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, DupeCheckFunc )


