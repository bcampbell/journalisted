#!/usr/bin/env python2.4
#
# Tool to scrape journo info from the guardian commentisfree site.
#

import re
import urllib2
import sys
from optparse import OptionParser



sys.path.append( "../pylib" )
from BeautifulSoup import BeautifulSoup
from JL import ukmedia,ArticleDB

# overall rss feed has last 15 articles:
# http://commentisfree.guardian.co.uk/index.xml
#
# Each individual contributor has their own feed too.
#


# main html page with list of contributors
contributors_url = 'http://commentisfree.guardian.co.uk/contributors_a-z.html'


def ScrapeContributors():
	"""parse the CIF contributors a-z page
	returns a list of entries holding info on each contributor"""

	#
	f = urllib2.urlopen( contributors_url )
	html = f.read()
	f.close()

	soup = BeautifulSoup( html )

	profiles = []
	for authordiv in soup.findAll( 'div', { 'class':'authorazentry' } ):
		entry = {}

		# get pretty version of name (eg "Washington Irving")
		name = authordiv.h1.a.string
		name = ukmedia.DescapeHTML( name )
		entry['name'] = name

		# get c-i-f ref (eg "washington_irving"
		entry['cif_ref'] = authordiv.h1.a['href']

		# get url of profile page
		a = authordiv.find( text="Profile" ).parent;
		entry['profile_url'] = a['href']

		# rss feed for this individual
		entry['rss_feed'] = 'http://commentisfree.guardian.co.uk/%s/index.xml' % (entry['cif_ref'])

		profiles.append( entry )

	return profiles


def Output( profiles, fout ):
	encoding = 'utf-8'
	fout.write( "<?xml version=\"1.0\" encoding=\"%s\"?>\n" %(encoding) )
	fout.write( " <journodata>" )
	for p in profiles:
		fout.write( "  <journo>\n" )
		fout.write( "   <name>%s</name>\n" %( p['name'].encode(encoding) ) )
		fout.write( "   <weblink url=\"%s\">%s</weblink>\n" % ( p['profile_url'], 'Profile page on Comment Is Free' ) )
		fout.write( "  </journo>\n" )
	fout.write( " </journodata>\n" )
	fout.write( "</xml>\n" );

#profiles = GetContributors()
#print profiles
#Output( profiles, sys.stdout )


# NOTE: it looks like comment-is-free actually uses the same format as
# the guardian blogs... but I didn't realise that until I'd written it
# Oh well.
# Ben
def Extract( html, context ):
	""" extract a single comment-is-free article """
	art = context
	soup = BeautifulSoup( html )

	# div with headline and summary
	topdiv = soup.find( 'div', {'id':'twocolumnleftcolumninsiderightcolumntop'} );

	art['description'] = topdiv.find( 'p',{'class':'standfirst'} ).renderContents( None )
	art['title'] = topdiv.h1.renderContents(None)


	# left column has author
	leftdiv = soup.find( 'div', {'id':'twocolumnleftcolumninsideleftcolumn'} );
	art['byline'] = ukmedia.FromHTML( leftdiv.h2.a.renderContents(None) )

	# right column has most other stuff (including main content)
	rightdiv = soup.find( 'div', {'id':'twocolumnleftcolumninsiderightcolumn'} )
	baselinediv = rightdiv.find( 'div', {'id':'twocolumnleftcolumntopbaselinetext' } )
	baselinediv.a.extract()
	datetext = baselinediv.renderContents(None)
	datetext = re.sub( u'\s*\|.*$', u'', datetext )
	# date format is: "November 16, 2007 10:00 PM"

	art['pubdate'] = ukmedia.ParseDateTime( datetext )
	baselinediv.extract()
	content = rightdiv.renderContents(None)
	# strip off cruft at end - links to del.icio.us and digg and stuff...
	cruftpat = re.compile( u'<p><small><a href="http://del\\.icio\\.us/post.*$', re.DOTALL )
	content = cruftpat.sub( u'', content )
	art['content'] = content

	return art


def ScrapeSingleURL( url ):
	html = ukmedia.FetchURL( url )
	context = {
		'srcurl': url,
		'permalink': url,
		'srcid': url,
		'srcorg': u'guardian'
	}

	art = Extract( html, context )
	ArticleDB.CheckArticle( art )
	return art

def PrettyDump( art ):
	for f in art:
		if f != 'content':
			print "%s: %s" % (f,art[f])
	print "---------------------------------"
	print art['content']
	print "---------------------------------"


def main():
	parser = OptionParser()
	parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
#	parser.add_option("-q", "--quiet", action="store_false", dest="verbose", default=True, help="don't print status messages to stdout")
	parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")
	(options, args) = parser.parse_args()

	if options.url:
		art = ScrapeSingleURL( options.url )
		PrettyDump( art )

	return


#	ScrapeSingleURL( "http://commentisfree.guardian.co.uk/tim_watkin/2007/11/learn_to_swim.html" )
#	return

	rssfeeds = { 'overall':	'http://commentisfree.guardian.co.uk/index.xml' }
	found = ukmedia.FindArticlesFromRSS( rssfeeds, u'guardian', None )
	store = ArticleDB.ArticleDB()
	ukmedia.ProcessArticles( found, store, Extract )

# TODO: modes of operation, selected by commandline options
#
# 1) Scrape a weeks worth of comment-is-free articles (default?)
# 2) Scrape using _all_ contributors RSS feeds
# 3) scrape a single url
# 4) download contributor data (no article scraping)
#
# options:
# - rescrape (will need to eliminate dupes in foundlist first!)
# - dryrun (don't change db)

if __name__ == "__main__":
    sys.exit(main())

