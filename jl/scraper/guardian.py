#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for the guardian and observer
#
# NOTE: guardian unlimited is changing their backend. They're doing it
# section by section and new system looks a lot cleaner, so hopefully we
# can remove some of the hackery in this scraper one day!
#
# Current sections using new system:
#  science
#  technology
#  environment
#  travel
#  media
#
# Main RSS page doesn't seem be be updated with feeds from new sections
# (presumably it'll be rejigged once the transition is complete)
# For the new-style sections, there is usually one feed for the main
# section frontpage, and then an extra feed for each subsection. Just
# click through all the subsection frontpages and look for the RSS link.
#
# TODO:
# - extract journo names from descriptions if possible...
# - REALLY need to sort out the guardian/observer issue - no reliable way
#   of telling which paper it's from using just the url. Likely to be a
#   problem with other papers too (particularly local papers): multiple
#   papers sharing a single CMS...

import re
from datetime import date,datetime,timedelta
import time
import sys
from optparse import OptionParser

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import DB,ArticleDB,ukmedia


rssfeeds = {
	'Guardian Unlimited front page':'http://www.guardian.co.uk/rssfeed/0,,1,00.xml',
	'UK latest': 'http://www.guardian.co.uk/rssfeed/0,,11,00.xml',
	'World latest': 'http://www.guardian.co.uk/rssfeed/0,,12,00.xml',
	'Guardian Unlimited Football': 'http://www.guardian.co.uk/rssfeed/0,,5,00.xml',
	'Guardian Unlimited Business': 'http://www.guardian.co.uk/rssfeed/0,,24,00.xml',
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
#	'Guardian Unlimited Business Insight': 'http://blogs.guardian.co.uk/businessinsight/index.rdf',
#	'Guardian Unlimited Gamesblog': 'http://blogs.guardian.co.uk/games/index.rdf',
#	'Guardian Unlimited Newsblog': 'http://blogs.guardian.co.uk/news/index.rdf',
#	'Guardian Unlimited Onlineblog': 'http://blogs.guardian.co.uk/technology/index.xml',
#	'Guardian Abroad': 'http://www.guardianabroad.co.uk/rss.xml',

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

	# http://business.guardian.co.uk/
	'Guardian Unlimited Business - more business news': 'http://www.guardian.co.uk/rssfeed/0,,25,00.xml',

	# (section names IN CAPS below are new-style sections, with new rss feeds)

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

	# just use description from context (from rss feed)
	# TODO: could also check 'stand-first' para?

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

	# now strip out all non-text bits of content div
	attrsdiv.extract()
	contentdiv.find('ul', id='article-toolbox').extract()
	contentdiv.find('div', id='send-share').extract()
	contentdiv.find('div', id='send-email').extract()
	contentdiv.find('div', id='contact').extract()

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
	for element in contentdiv.contents:
		textpart.append( element )

	# if there was a folding bit, add its contents to the new soup too
	if morediv:
		for element in morediv.contents:
			textpart.append( element )

	# that's it!
	art['content'] = textpart.prettify(None)

	return art





# extractor for old style articles...
def OldExtract( soup, context ):
	art = context

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
			if 'srcorgname' in art:
				if art['srcorgname'] != u'observer':
					raise Exception, ("Observer article found with wrong srcorgname" )
			else:
				art['srcorgname'] = u'observer';
		else:
			# article not from observer
			if 'srcorgname' in art:
				if art['srcorgname'] != u'guardian':
					raise Exception, ("Guardian article found with wrong srcorgname" )
			else:
				art['srcorgname'] = u'guardian';

	else:
		art[ 'byline' ] = u''
		# couldn't find name + date, settle for just the date
		datepat = re.compile( "(.*?)<br />", re.UNICODE )
		m = datepat.search( preamble )
		# TODO: search for "More articles by [name]" link to get author
		art[ 'pubdate' ] = ukmedia.ParseDateTime( m.group(1) )


	bodydiv = articlediv.find( 'div', id='GuardianArticleBody' )

	# strip out embedded advertising rubbish
	advertdiv = bodydiv.find( 'div', id='spacedesc_mpu_div' )
	if advertdiv:
		advertdiv.extract()
	s = bodydiv.find( 'script' )
	if s:
		s.extract()

	text = bodydiv.renderContents( None )

	art[ 'content' ] = ukmedia.SanitiseHTML( text )

	if 'description' in art:
		# we just use the description passed in (from the RSS feed)
		desc = ukmedia.FromHTML( art['description'] )
	else:
		# try using the first para as description
		desc = unicode( bodydiv.find( text=True ) )
		desc = ukmedia.FromHTML( desc )

	art[ 'description' ] =  desc

	return art



urltrimpat = re.compile( u'\?gusrc=rss&feed=.*$', re.UNICODE )

def TidyURL( url ):
	""" Tidy up URL - trim off any extra cruft (eg rss tracking stuff) """
	url = urltrimpat.sub( '', url )
	return url


# pattern to extract storyserver id from url
idpat = re.compile( u'.*[/](.*)[.]html$', re.UNICODE )

def CalcSrcID( url ):
	""" Extract a srcid from the URL.

	srcid should uniquely identify the article within the source organisation.
	If an outlet has obvious internal IDs in the URL, then we can use them.
	Otherwise we can just use the whole URL, although we need to be careful
	that the outlet doesn't have multiple urls for a single article, or
	we'll get dupes...
	srcorg and srcid together uniquely identify a single article in the DB.
	"""

	m = idpat.search( url )
	if m:
		# old (storyserver) format
		srcid = m.group(1)
	else:
		# new format, or comment-is-free
		# - use whole url as srcid
		srcid = url

	return srcid


def ScrubFunc( context, entry ):
	""" fn to massage info from RSS feed """

	url = TidyURL( context['permalink'] )
	context['permalink'] = url;
	context['srcid'] = CalcSrcID( url )

	if WhichFormat( url ) == 'newformat':
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

	# we don't handle gallery pages...
	if url.find( '/gallery/') != -1:
		ukmedia.DBUG2( "IGNORE gallery '%s' (%s)\n" % (context['title'], url) );
		return None
	# ... or videos....
	if url.find( '/video/') != -1:
		ukmedia.DBUG2( "IGNORE video page '%s' (%s)\n" % (context['title'], url) );
		return None
	# ...or quizes
	if url.find( '/quiz/') != -1:
		ukmedia.DBUG2( "IGNORE quiz '%s' (%s)\n" % (context['title'], url) );
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
	url = TidyURL( url )

	context = {}
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'guardian'		# hmmm... should handle observer too...

	if WhichFormat( url ) == 'newformat':
		# force whole article on single page
		context['srcurl'] = url + '?page=all'
	else:
		context['srcurl'] = url

	return context




def main():
	parser = OptionParser()
	parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
	parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")

	(options, args) = parser.parse_args()

	found = []
	if options.url:
		context = ContextFromURL( options.url )
		found.append( context )
	else:
		found = found + ukmedia.FindArticlesFromRSS( rssfeeds, u'guardian', ScrubFunc )

	if options.dryrun:
		store = ArticleDB.DummyArticleDB()	# testing
	else:
		store = ArticleDB.ArticleDB()

	ukmedia.ProcessArticles( found, store, Extract, DupeCheckFunc )

	return 0



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
    sys.exit(main())



