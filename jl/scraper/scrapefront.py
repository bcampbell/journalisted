#!/usr/bin/env python2.4
#
# front end to present a single interface for using the
# news outlet scrapers.
# CalcSrcID(url) - 
#
# TODO:
#
# - alter blogs.py to work with this (or move blog
#   functionality out into the other scrapers)
#
# - add interface to scrape a single url
#   a) figure out which scraper should handle it (use CalcSrcID())
#   b) context = scraper.ContextFromURL( url )
#   c) fetch the page
#   d) scraper.Extract( html, context)
#   This stuff is already supported by the scrapers.
#

scrapers = ( 'bbcnews', 'dailymail', 'express', 'ft',
	'guardian', 'herald', 'independent', 'mirror',
	'scotsman', 'sun', 'telegraph', 'times', 'notw' )

modules = []
for s in scrapers:
	modules.append( __import__( s ) )

def CalcSrcID( url ):
	"""determine a unique srcid from the url. returns None if no scrapers handle this url"""
	for m in modules:
		srcid = m.CalcSrcID( url )
		if srcid:
			return srcid

	# if we get this far, we can't determine srcid.
	# actually, blogs.py might be able to handle this url,
	# but that isn't integrated yet...
	return None


def PickScraper( url ):
	""" returns the scraper which can handle the given url, or None """
	for scraper in modules:
		srcid = scraper.CalcSrcID( url )
		if srcid:
			return scraper
	return None

