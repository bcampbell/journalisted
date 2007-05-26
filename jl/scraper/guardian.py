#!/usr/bin/env python2.4
#
# Scraper for the guardian and observer
#

import re
from datetime import date,datetime
import time
import sys

sys.path.append("../pylib")
from BeautifulSoup import BeautifulSoup
from JL import ArticleDB,ukmedia


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






#
def Extract( html, context ):
	art = context

	soup = BeautifulSoup( html )
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


	text = ExtractText( articlediv )

	art[ 'content' ] = ukmedia.SanitiseHTML( text )

	return art


def ExtractText( articlediv ):
	bodydiv = articlediv.find( 'div', id='GuardianArticleBody' )

	# strip out embedded advertising rubbish
	advertdiv = bodydiv.find( 'div', id='spacedesc_mpu_div' )
	if advertdiv:
		advertdiv.extract()
	s = bodydiv.find( 'script' )
	if s:
		s.extract()

	return bodydiv.renderContents( None )





urltrimpat = re.compile( u'\?gusrc=rss&feed=[0-9]+$', re.UNICODE )

def ScrubFunc( context, entry ):
	url = urltrimpat.sub( '', context['permalink'] )
	context['permalink'] = url;
	context['srcurl'] = url;
	context['srcid'] = url;


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


def main():
	found = ukmedia.FindArticlesFromRSS( rssfeeds, None, ScrubFunc )

	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

	return 0

if __name__ == "__main__":
    sys.exit(main())

