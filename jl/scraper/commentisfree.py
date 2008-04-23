#!/usr/bin/env python2.4
#
# Tool to scrape journo info from the guardian commentisfree site.
#

import re
import urllib2
import sys

from optparse import OptionParser
from datetime import datetime


import site
site.addsitedir( "../pylib" )
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
	'''
	Extract a single comment-is-free article.
	
	Some failures fall back to Extract2.
	'''
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
	art['byline'] = ukmedia.ExtractAuthorFromParagraph( leftdiv.h2.a.renderContents(None) )

	pattern = (ur'<h2><a href="http://commentisfree\.guardian\.co\.uk/[a-z_]+/profile\.html">'
	           ur'\s*([A-Za-z \-]+)\s*</a></h2>')
	if not art['byline']:
		try:
			art['byline'] = unicode(re.compile(pattern, re.DOTALL).findall(html)[0], 'utf-8')
		except IndexError:
			pass

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

	Some failures fall back to Extract3.
	'''
	###
	# This duplicates guardian.py's OldExtract for a lot of articles.
	# I don't currently have a good way to decide which to use,
	# the two should probably be united.
	###
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
		bits = re.split(r'<p>\s*<b>\s*&(?:#183|middot);\s*</b>', body)  # end of article marker
		body, bios = bits[0], bits[1:]
		for bio in list(bios):
			if 'will be appearing' in bio:
				bios.remove(bio)
		if bios:
			bio = u'<p>' + u'\n\n<p>'.join([bio.lstrip() for bio in bios])  # put the <p> back
		else:
			bio = u''
	except ValueError:
		bio = u''
	# They've taken to inserting section breaks in a really unpleasant way, as in
	# http://lifeandhealth.guardian.co.uk/family/story/0,,2265583,00.html
	body = re.compile(r'<p>\s*<script.*?<a name="article_continue"></a>\s*</div>\s*',
	                  flags=re.UNICODE | re.DOTALL).sub(' ', body)
	if not descline:
		descline = ukmedia.FirstPara(body)
	
	byline = None
	pos = dateline.find('<br />')
	if pos > -1:
		# Check that format appears to be "AUTHOR<br />DATE ..."
		days = 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
		for day in days:
			if dateline[pos+len('<br />'):].startswith(day):
				byline = dateline[:pos]
	if not byline:
		byline = ukmedia.ExtractAuthorFromParagraph(descline)
	
	# But the javascript-generated sidebar may provide a more accurate byline,
	# and provides the all-important "blog" URL, where "blog"="articles by this author"
	# in this rather odd field.
	cifblog_url, cifblog_feed = None, None
	for script_tag in soup.findAll('script'):
		src = dict(script_tag.attrs).get('src', '')
		if re.match(r'http://.*?/\d+_twocolumnleftcolumninsideleftcolumn.js$', src):
			js = urllib2.urlopen(src).read()
			if not isinstance(js, unicode):
				js = unicode(js, 'utf-8')
			
			m = re.search(ur'articleslistitemshowalllink.setAttribute\("href", "(.*?)"\);', js, re.UNICODE)
			if m:
				cifblog_url = m.group(1)

			m = re.search(ur'webfeedfirstlink.setAttribute\("href", "(.*?)"\);', js, re.UNICODE)
			if m:
				cifblog_feed = m.group(1)

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
	art['byline'] = byline.strip()
	art['pubdate'] = ukmedia.ParseDateTime(dateline.replace('<br />', '\n'))
	art['content'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(body))
	art['bio'] = ukmedia.SanitiseHTML(ukmedia.DescapeHTML(bio))
	if cifblog_url:
		art['cifblog-url'] = cifblog_url
	if cifblog_feed:
		art['cifblog-feed'] = cifblog_feed  #RSS/Atom equivalent of cifblog-url
	return art

def Extract3(soup, context):
	'''
	A scraper for a relatively recent version of this format (e.g. 6 Mar 2008).
	'''
	# e.g. http://www.guardian.co.uk/commentisfree/2008/mar/06/games
	ul = soup.find('div', id='content').ul  # class="article-attributes no-pic"
	byline = ul.find('li', {'class': 'byline'}) or u''
	if byline:
		byline = byline.renderContents(None).strip()
		byline = ukmedia.ExtractAuthorFromParagraph(byline)
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

