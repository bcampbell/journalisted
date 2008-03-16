#!/usr/bin/env python2.4
#
# Tool to scrape journo info from the guardian commentisfree site.
#

import re
import urllib2
import sys

from optparse import OptionParser
from datetime import datetime


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


# Comment Is Free uses several formats, and has overlaps with the Guardian
# and Observer blog formats. Extract falls back to Extract2 or Extract3 as needed,
# which change the 'guardian-format' appropriately.
#
def Extract( html, context ):
	""" extract a single comment-is-free article """
	art = context
	soup = BeautifulSoup( html )

	# div with headline and summary
	topdiv = soup.find( 'div', {'id':'twocolumnleftcolumninsiderightcolumntop'} )
	if topdiv is None:
		return Extract2(soup, context)  # a different format
	
	desc = topdiv.find( 'p',{'class':'standfirst'} ).renderContents( None )
	art['description'] = ukmedia.FirstPara(desc)
	art['title'] = topdiv.h1.renderContents(None).strip()


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
	cruftpat = re.compile( ur'\s*(?:<br\s*/>+\s*)*<p><small><a href="http:/+del\.icio\.us/post.*$', re.DOTALL )
	content = cruftpat.sub( u'', content )
	# alternative form of cruft:
	cruftpat = re.compile( ur'^\s*(?:<(?:br|p)(?:\s*/)?>+\s*)*<(?:strong|b)>.*$', re.DOTALL | re.MULTILINE )
	content = cruftpat.sub( u'', content )
	art['content'] = content

	return art

def Extract2(soup, context):
	'''
	A scraper for a rather simplistic format, probably blogging software. (Vignette?)
	'''
	div = soup.find('div', {'id': 'GuardianArticle'})
	if div is None:
		return Extract3(soup, context)
	descline = div.h1.findNext('font', {'size': '3'}) or u''
	if descline:
		marker = descline
		descline = descline.renderContents(None)
	else:
		marker = div.h1
	dateline = marker.findNext('font').b.renderContents(None)
	body = div.find('div', {'id': 'GuardianArticleBody'}).renderContents(None)
	try:
		body, bio = re.split(r'<p>\s*<b>\s*&(?:#183|middot);\s*</b>', body)  # end of article marker
		bio = '<p>' + bio.lstrip()  # put the <p> back
	except ValueError:
		bio = u''
	if not descline:
		descline = ukmedia.FirstPara(body)
	byline = ukmedia.FromHTML(descline)
	# But the javascript-generated sidebar may provide a more accurate byline
	for script_tag in soup.findAll('script'):
		src = dict(script_tag.attrs).get('src', '')
		if re.match(r'http://.*?/\d+_twocolumnleftcolumninsideleftcolumn.js$', src):
			js = urllib2.urlopen(src).read()
			if not isinstance(js, unicode):
				js = unicode(js, 'utf-8')
			m = re.search(ur'document\.createTextNode\("All (.*?) articles"\)', js, re.UNICODE)
			if m:
				byline = m.group(1)
			# And while we're at it, we might as well get a unique author id:
			m = re.search(ur'profilelinka\.setAttribute\("href", "(.*?)"\)', js, re.UNICODE)
			if m:
				context['author_id'] = m.group(1)

	art = context
	art['guardian-format'] = 'commentisfree.py (2)' ####### OVERRIDE ########
	art['title'] = ukmedia.FromHTML(div.h1.renderContents(None))
	art['description'] = ukmedia.FromHTML(descline)
	art['byline'] = byline
	art['pubdate'] = ukmedia.ParseDateTime(dateline.replace('<br />', '\n'))
	art['content'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(body))
	art['bio'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(bio))
	return art

def Extract3(soup, context):
	'''
	A scraper for a relatively recent version of this format (e.g. 6 Mar 2008).
	'''
	# e.g. http://www.guardian.co.uk/commentisfree/2008/mar/06/games
	ul = soup.find('div', id='content').ul  # class="article-attributes no-pic"
	byline = ul.find('li', {'class': 'byline'}).renderContents(None).strip()
	byline = ukmedia.FromHTML(byline)
	pubdate = ul.find('li', {'class': 'date'}).renderContents(None)
	publication = ul.find('li', {'class': 'publication'}).a.string
	assert publication in ('The Guardian', 'The Observer'), publication  # if not, we want to know
	historyByline = soup.find('div', id='history-byline')
	siblings = historyByline.parent.contents
	body_elements = siblings[siblings.index(historyByline)+1:]
	body = ''.join([unicode(x) for x in body_elements]).strip()
	assert 'About this article' not in body, body  # just in case
	descline = soup.find('meta', {'name':'description'})['content']
	descline = ukmedia.FromHTML(descline)
	if descline.startswith(byline + ': '):
		descline = descline[len(byline + ': '):]
	art = context
	art['guardian-format'] = 'commentisfree.py (3)' ####### OVERRIDE ########
	art['title'] = ukmedia.FromHTML(soup.h1.renderContents(None))
	art['description'] = descline
	art['byline'] = byline
	art['pubdate'] = ukmedia.ParseDateTime(pubdate)
	art['content'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(body))
	if publication=='The Observer':
		art['srcorgname'] = u'observer'
	return art

def ScrapeSingleURL( url ):
	html = ukmedia.FetchURL( url )
	context = {
		'srcurl': url,
		'permalink': url,
		'srcid': url,
		'srcorg': u'guardian',
		'srcorgname': u'The Guardian',
		'lastscraped': datetime.now()
	}

	art = Extract( html, context )
	ArticleDB.CheckArticle( art )
	return art

def PrettyDump( art ):
	for f in art:
		if f != 'content':
			print "%s: %s" % (f,art[f])
	print "---------------------------------"
	print art['content'].encode('latin-1', 'replace')
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

