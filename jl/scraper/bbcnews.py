#!/usr/bin/env python
#
# Scraper for BBC News site
#
# TODO:
#

import getopt
import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ArticleDB,ukmedia

# sources used by FindArticles
rssfeeds = {
	'News Front Page': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/front_page/rss.xml',
	'World': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/world/rss.xml',
	'UK': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk/rss.xml',
	'England': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/england/rss.xml',
	'Northern Ireland': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/northern_ireland/rss.xml',
	'Scotland': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/scotland/rss.xml',
	'Business': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/business/rss.xml',
	'Politics': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/uk_politics/rss.xml',
	'Health': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/health/rss.xml',
	'Education': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/education/rss.xml',
	'Science/Nature': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/sci/tech/rss.xml',
	'Technology': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/technology/rss.xml',
	'Entertainment': 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/entertainment/rss.xml'
}



def Extract( html, context ):
	"""Parse the html of a single article

	html -- the article html
	context -- any extra info we have about the article (from the rss feed)
	"""

	art = context

	soup = BeautifulSoup( html )

	meta = soup.find( 'meta', { 'name': 'Headline' } )
	art['title'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

	meta = soup.find( 'meta', { 'name': 'OriginalPublicationDate' } )
	art['pubdate'] = ukmedia.ParseDateTime( meta['content'] )

	# TODO: could use first paragraph for a more verbose description
	meta = soup.find( 'meta', { 'name': 'Description' } )
	art['description'] = ukmedia.DescapeHTML( meta[ 'content' ] )

	# byline
	byline = u''
	spanbyl = soup.find( 'span', {'class':'byl'} )
	if spanbyl:	# eg "By Paul Rincon"
		byline = spanbyl.renderContents(None).strip()
	spanbyd = soup.find( 'span', {'class':'byd'} )
	if spanbyd:	# eg "Science reporter, BBC News, Houston"
		byline = byline + u', ' + spanbyd.renderContents(None).strip()
	art['byline'] = byline

	# just use regexes to extract the article text
	txt = soup.renderContents(None)
	m = re.search( u'<!--\s*S BO\s*-->(.*)<!--\s*E BO\s*-->', txt, re.UNICODE|re.DOTALL )
	txt = m.group(1)

	# zap assorted extra blocks from the text
	# (could be problems with nesting... but seems ok)
	# IIMA - image?
	# IINC - shared image?
	# IBOX - quote?
	# IBYL - byline
	# IANC - anchor
	# ILIN
	# IFOR - form
	# ICOL?
	blockkillerpat = re.compile( u'<!--\s*S (IIMA|IINC|IBOX|IBYL|IANC|ILIN|IFOR)\s*-->.*?<!--\s*E \\1\s*-->', re.UNICODE|re.DOTALL )
	txt = blockkillerpat.sub( u'', txt )

	# sanity check (might not know all block types)
	m = re.search( u'<!--\s*S (\w+)\s*-->', txt, re.UNICODE )
	if m:
		if m.group(1) != 'SF' and m.group(1) != 'BO':
			raise Exception, ("unknown block type encountered ('%s')" % m.group(1))

	txt = ukmedia.SanitiseHTML( txt )
	art['content'] = txt

	return art




# bbc news rss feeds have lots of blogs and other things in them which
# we don't parse here. We identify news articles by the number in their
# url.
idpat = re.compile( '/(\d+)\.stm$' )

def ScrubFunc( context, entry ):
	m = idpat.search( context['srcurl'] )
	if not m:
		ukmedia.DBUG2( "SUPPRESS " + context['title'] + " -- " + context['srcurl'] )
		return None		# suppress this article (probably a blog)

	# Also we use this number as the unique id for the beeb, as a story
	# can have multiple paths (eg uk vs international version)
	context['srcid'] = m.group(1)

	return context


def main():
	opts, args = getopt.getopt(sys.argv[1:], "h", ["help"])

	# TODO: filter out "Your Stories" page
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'bbcnews', ScrubFunc )

#	for f in found:
#		print ("%s" % ( f['title'] )).encode( "utf-8" )
	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

