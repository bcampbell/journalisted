#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Scraper for BBC News site
#
# TODO:
#

import re
from datetime import datetime
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup, Comment
from JL import ukmedia, ScraperUtils

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
	art['description'] = ukmedia.DescapeHTML( meta[ 'content' ] ).strip()

	# byline
	byline = u''
	spanbyl = soup.find( 'span', {'class':'byl'} )
	if spanbyl:	# eg "By Paul Rincon"
		byline = spanbyl.renderContents(None).strip()
	spanbyd = soup.find( 'span', {'class':'byd'} )
	if spanbyd:	# eg "Science reporter, BBC News, Houston"
		byline = byline + u', ' + spanbyd.renderContents(None).strip()
	art['byline'] = ukmedia.FromHTML( byline )

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

def CalcSrcID( url ):
	""" returns None if it's not an article (probably a blog) """

	m = idpat.search( url )
	if not m:
		return None		# suppress this article (probably a blog)
	return m.group(1)



def ScrubFunc( context, entry ):
	""" per-article callback for processing RSS feeds """
	# a story can have multiple paths (eg uk vs international version)
	srcid = CalcSrcID( context['srcurl'] )
	if not srcid:
		return None	# suppress it

	context['srcid'] = srcid
	return context


def FindArticles():
	""" get a set of articles to scrape from the bbc rss feeds """
	# TODO: filter out "Your Stories" page
	return ukmedia.FindArticlesFromRSS( rssfeeds, u'bbcnews', ScrubFunc )


def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'bbcnews'
	context['lastseen'] = datetime.now()
	return context


if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

