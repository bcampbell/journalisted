#
#
#

from optparse import OptionParser
import sys

import ukmedia, ArticleDB


def RunMain( findarticles_fn, contextfromurl_fn, extract_fn, post_fn=None, maxerrors=20,
	         prepare_parser_fn=None, after_parsing_fn=None ):
	"""A generic(ish) main function that all scrapers can use

	Scrapers pass in callbacks:
	findarticles_fn: return a list of article contexts for a full scrape
	contextfromurl_fn: create an article context from a bare url
	extract_fn: function to process an HTML page and return an article
	post_fn(id, context): function to call after inserting an article into the database
	prepare_parser_fn(parser): function that adds any additional options to parser
	after_parsing_fn(options, args): function that returns adjusted (options, args).
	"""

	parser = OptionParser()
	parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
	parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")

	if prepare_parser_fn:
		prepare_parser_fn(parser)
	
	(options, args) = parser.parse_args()
	if after_parsing_fn:
		(options, args) = after_parsing_fn(options, args)

	found = []
	if options.url:
		context = contextfromurl_fn( options.url )
		found.append( context )
	else:
		found = found + findarticles_fn()

	if options.dryrun:
		store = ArticleDB.ArticleDB( dryrun=True, reallyverbose=True )	# testing
	else:
		store = ArticleDB.ArticleDB()

	# Hack: publish the store so that we can use its DB connection
	global article_store
	article_store = store
	
	ukmedia.ProcessArticles( found, store, extract_fn, post_fn, maxerrors )

	return 0

article_store = None
