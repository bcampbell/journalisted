#
#
#

from optparse import OptionParser
import sys

import ukmedia, ArticleDB


def RunMain( findarticles_fn, contextfromurl_fn, extract_fn, post_fn=None, maxerrors=20  ):
	"""A generic(ish) main function that all scrapers can use

	Scrapers pass in callbacks:
	findarticles_fn: return a list of article contexts for a full scrape
	contextfromurl_fn: create an article context from a bare url
	extract_fn: function to process an HTML page and return an article
	"""

	parser = OptionParser()
	parser.add_option( "-u", "--url", dest="url", help="scrape a single article from URL", metavar="URL" )
	parser.add_option("-d", "--dryrun", action="store_true", dest="dryrun", help="don't touch the database")

	(options, args) = parser.parse_args()

	found = []
	if options.url:
		context = contextfromurl_fn( options.url )
		found.append( context )
	else:
		found = found + findarticles_fn()

	if options.dryrun:
		store = ArticleDB.DummyArticleDB()	# testing
	else:
		store = ArticleDB.ArticleDB()

	ukmedia.ProcessArticles( found, store, extract_fn, post_fn, maxerrors )

	return 0

