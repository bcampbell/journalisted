#!/usr/bin/env python2.4
#
# Scraper for the guardian and observer
#
# NOTE: guardian unlimited is changing their backend. They're doing it
# section by section and new system looks a lot cleaner, so hopefully we
# can remove some of the hackery in this scraper one day!
#
# TODO:
# - add guardian blogs
# - detect subscription-only pages
# - extract journo names from description
#
import re
from datetime import date,datetime,timedelta
import time
import sys

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
#	'Media Guardian (registration required)': 'http://www.guardian.co.uk/rssfeed/0,,4,00.xml',
	'The Observer': 'http://www.guardian.co.uk/rssfeed/0,,15,00.xml',
	'Society Guardian': 'http://www.guardian.co.uk/rssfeed/0,,9,00.xml',
#	'Guardian Unlimited Business Insight': 'http://blogs.guardian.co.uk/businessinsight/index.rdf',
#	'Guardian Unlimited Gamesblog': 'http://blogs.guardian.co.uk/games/index.rdf',
#	'Guardian Unlimited Newsblog': 'http://blogs.guardian.co.uk/news/index.rdf',
#	'Guardian Unlimited Onlineblog': 'http://blogs.guardian.co.uk/technology/index.xml',
#	'Guardian Abroad': 'http://www.guardianabroad.co.uk/rss.xml',
	}




def Extract( html, context ):

	# quick check for subscription-only mediaguardian page
	if html.find( '<title>Media registration promo' ) != -1:
		ukmedia.DBUG2( "SUPPRESS subscription-only page '%s' (%s)\n" % (context['title'], context['srcurl']) )
		return None


	art = context
	soup = BeautifulSoup( html )

	# header contains headline, strapline
	headerdiv = soup.find( 'div', id="article-header" )
	if not headerdiv:
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
	if art['srcorgname'] == u'observer' and publication != u'The Observer':
#		raise Exception, ("Observer article not actually from observer?" )
		ukmedia.DBUG2( "WARNING: Observer article not actually from observer? '%s' (%s)\n" %( art['title'], art['srcurl'] ) );

	# now strip out all non-text bits of content div
	attrsdiv.extract()
	contentdiv.find('ul', id='article-toolbox').extract()

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
			if art['srcorgname'] != u'observer':
				raise Exception, ("Observer article found with wrong srcorgname" )
		else:
			# article not from observer
			if art['srcorgname'] != u'guardian':
				raise Exception, ("Guardian article found with wrong srcorgname" )
			
	else:
		art[ 'byline' ] = u''
		# couldn't find name + date, settle for just the date
		datepat = re.compile( "(.*?)<br />", re.UNICODE )
		m = datepat.search( preamble )
		# TODO: search for "More articles by [name]" link to get author
		art[ 'pubdate' ] = ukmedia.ParseDateTime( m.group(1) )


	text = OldExtractText( articlediv )

	art[ 'content' ] = ukmedia.SanitiseHTML( text )

	# we just use the description passed in (from the RSS feed)
	art[ 'description' ] = ukmedia.FromHTML( art['description'] )

	return art


def OldExtractText( articlediv ):
	bodydiv = articlediv.find( 'div', id='GuardianArticleBody' )

	# strip out embedded advertising rubbish
	advertdiv = bodydiv.find( 'div', id='spacedesc_mpu_div' )
	if advertdiv:
		advertdiv.extract()
	s = bodydiv.find( 'script' )
	if s:
		s.extract()

	return bodydiv.renderContents( None )





urltrimpat = re.compile( u'\?gusrc=rss&feed=.*$', re.UNICODE )
idpat = re.compile( u'.*[/](.*)[.]html$', re.UNICODE )

def ScrubFunc( context, entry ):
	# trim off the rss bits
	url = urltrimpat.sub( '', context['permalink'] )
	context['permalink'] = url;
	context['srcurl'] = url;

	m = idpat.search( url )
	if m:
		context['srcid'] = m.group(1)	# storyserver format
	else:
		context['srcid'] = context['srcurl']		# new format

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

	# we don't handle subscription-only pages (media guardian)
	# sigh... this doesn't work because not all mediaguardian pages are subscription-only...
#	if url.find( 'http://media.guardian.co.uk/') == 0 or url.find( '/mediaguardian/' ) != -1:
#		ukmedia.DBUG2( "IGNORE subscription-only page '%s' (%s)\n" % (context['title'], url) );
#		return None


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

def main():
	#Test( sys.argv[1] )
	found = ukmedia.FindArticlesFromRSS( rssfeeds, None, ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract, DupeCheckFunc )

	return 0


def Test( url ):
	html = ukmedia.FetchURL( url )
	context = { 'srcurl': url }
	art = Extract( html, context )
	PrettyDump( art )



def PrettyDump( art ):
	for f in art:
		if f != 'content':
			print "%s: %s" % (f,art[f])
	print "---------------------------------"
	print art['content']
	print "---------------------------------"


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



