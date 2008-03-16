#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#

'''
Scrapes all of the many Guardian article formats (by calling other scrapers).
Handles The Observer and Comment Is Free articles too.

To test scraping a single URL (includes file: URLS for local files):

	python guardianfront.py --dryrun --url URL
'''

import sys, os, re
import urllib2

import blogs
import commentisfree
import guardian

from guardian import ScraperUtils, FindArticles, ContextFromURL, DupeCheckFunc


def Extract(html, context):
	assert context.get('srcurl')
	if not context.get('srcid'):
		context['srcid'] = context['srcurl']
	
	formats = {
		'guardian.py':	  ('GuardianArticleBody', guardian.Extract),  #1
		'guardian.py (2)':  ('send-inner', guardian.Extract),  #2
		'blogs.py':		 ('class="blogs-article"', blogs.Extract),  # 3
		'commentisfree.py': ('twocolumnleftcolumninsiderightcolumntop', commentisfree.Extract), # 3b
			# or 'commentisfree.py (2)' - this scraper overrides guardian-format.
	}
	
	for format_id, (pattern, extractor) in formats.iteritems():
		if pattern in html:
			context['guardian-format'] = format_id
			context = extractor(html, context)
			return context


if __name__ == "__main__":
	ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, DupeCheckFunc )
