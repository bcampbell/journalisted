#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# TODO:
# - extract better srcids, instead of using whole url
#

import re
from datetime import datetime
import sys
import os

sys.path.append("../pylib")
import BeautifulSoup
from JL import ukmedia, ScraperUtils

# NOTES:
#
# The Times website seems a little crap and stalls regularly, so
# we timeout. Should handle it a bit more gracefully...
#
# The Times RSS feeds seem a bit rubbish, and only have 5 articles
# each.
# So we scrape links from the html pages. The Times has a page for
# each days edition which contains links to all the headlines for that day.
# That's what we want.


linkpat = re.compile( '^/.*?/article[0-9]+\.ece$' )

# lots of links on the page which we don't want, so we'll
# look for sections with links we _do_ want...
sectionnames = ('News',
		'incomingFeeds',
		'Comment',
		'Business',
		'Sport',
		'Life & Style',
		'Arts & Entertainment',
		)

siteroot = "http://timesonline.co.uk"


def FindArticles():

	ukmedia.DBUG2( "*** times ***: looking for articles...\n" )
	foundarticles = []

	# hit the page which shows the covers of the papers for the week
	# and extract a link to each day
	ukmedia.DBUG2( "fetching /tol/newspapers/the_times...\n" )
	html = ukmedia.FetchURL( siteroot + '/tol/newspapers/the_times' )
#	ukmedia.DBUG2( "  got it.\n" )
	soup = BeautifulSoup.BeautifulSoup(html)

	# (one day of the week will always be missing, as it'll have
	# been renamed 'Today')
	days = ( 'Today', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'The Sunday Times' )

	daypages = {}
	for link in soup.findAll( 'a', {'class':"link-06c"} ):
		day = link.renderContents().strip()
		if day in days:
			daypages[day] = siteroot + link['href']

	# go through each days page and extract links to articles
	for day, url in daypages.iteritems():

		ukmedia.DBUG2( "fetching " + day + "\n" )
		html = ukmedia.FetchURL( url )
#		ukmedia.DBUG( " got " + day + "\n" )
		fetchtime = datetime.now()
		soup = BeautifulSoup.BeautifulSoup(html)


		# Which newspaper?
		if re.search( "/?days=Sunday$", url ):
			srcorgname = "sundaytimes"
		else:
			srcorgname = "times"
		ukmedia.DBUG2( "** PAPER: " + srcorgname + "\n" )

		# go through by section
		for heading in soup.findAll( 'h3', {'class': 'section-heading' } ):
			sectionname = heading.find( text = sectionnames )
			if not sectionname:
#				print "Ignoring section ",heading
				continue

			ukmedia.DBUG2( "  " + sectionname + "\n" )

			ul = heading.findNextSibling( 'ul' )
			for a in ul.findAll( 'a' ):
				title = ukmedia.DescapeHTML( a.renderContents(None) )
				url = siteroot + a['href']

				context = {
					'title': title,
					'srcurl': url,
					'srcid': CalcSrcID(url),
					'permalink': url,
					'lastseen': fetchtime,
					'srcorgname' : srcorgname,
					}

				foundarticles.append( context )

	ukmedia.DBUG2( "Found %d articles\n" % ( len(foundarticles) ) )
	return foundarticles



def CalcSrcID( url ):
	""" work out a unique id for this url (must be unique across the times)"""

	# TODO: work out proper times srcids
	# "http://www.timesonline.co.uk/tol/news/uk/article469471.ece"
	return url


def Extract( html, context ):

	art = context
	soup = BeautifulSoup.BeautifulSoup( html )

	h1 = soup.find( 'h1', {'class':'heading'} )
	art['title'] = h1.renderContents(None).strip()
	art['title'] = ukmedia.DescapeHTML( ukmedia.StripHTML( art['title'] ) )

	byline = u''
	# times stuffs up bylines for obituaries (used for date span instead)
	if art['srcurl'].find( '/obituaries/' ) == -1:
		authdiv = soup.find( 'div', {'class':'article-author'} )
		if authdiv:
			bylinespan = authdiv.find( 'span', { 'class': 'byline' } )
			if bylinespan:
				byline = bylinespan.renderContents( None )
				byline = ukmedia.StripHTML( byline )
				byline = ukmedia.DescapeHTML( byline )
				byline = u' '.join( byline.split() )
	art['byline'] = byline

	paginationstart = soup.find( text=re.compile('^\s*Pagination\s*$') )
	paginationend = soup.find( text=re.compile('^\s*End of pagination\s*$') )

	if not paginationstart:
		raise Exception, "couldn't find start of main text!"
	if not paginationend:
		raise Exception, "couldn't find end of main text!"



	contentsoup = BeautifulSoup.BeautifulSoup()
	p = paginationstart.nextSibling
	while p != paginationend:
		next = p.nextSibling
		if not isinstance( p, BeautifulSoup.Comment ):
			contentsoup.insert( len(contentsoup.contents), p )
		p = next


	for cruft in contentsoup.findAll( 'div', {'class':'float-left related-attachements-container' } ):
		cruft.extract()
	for cruft in contentsoup.findAll( 'script' ):
		cruft.extract()
	#...more?

	art['content'] = ukmedia.SanitiseHTML( contentsoup.prettify(None) )

	# skip crossword solutions etc...
	if art['content'].strip() == u'' and art['srcurl'].find( "games_and_puzzles" ) != -1:
		ukmedia.DBUG2( "IGNORE puzzle solution: '%s' (%s)\n" % (art['title'], art['srcurl']) );
		return None

	# description is in a meta tag
	descmeta = soup.find('meta', {'name':'Description'} )
	desc = descmeta['content']
	desc = ukmedia.DescapeHTML( desc )
	desc = ukmedia.RemoveTags( desc )
	art['description' ] = desc

	# There is some javascript with a likely-looking pubdate:
	# var tempDate="02-Jan-2006 00:00";

	datepat = re.compile( u"\s*var tempDate=\"(.*?)\";", re.UNICODE )

	m = datepat.search(html)
	art['pubdate'] = ukmedia.ParseDateTime( m.group(1) )

	return art


def ContextFromURL( url ):
	"""Build up an article scrape context from a bare url."""
	context = {}
	context['srcurl'] = url
	context['permalink'] = url
	context['srcid'] = CalcSrcID( url )
	context['srcorgname'] = u'times'	# TODO: or sundaytimes!
	context['lastseen'] = datetime.now()
	return context



if __name__ == "__main__":
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract )

