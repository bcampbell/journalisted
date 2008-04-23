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

from guardian import ukmedia, ScraperUtils, FindArticles, ContextFromURL, DupeCheckFunc


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

def AddBlogLinks(art_id, art):
    '''
    Called after inserting a new article.
    '''
    DupeCheckFunc(art_id, art)
    if 'cifblog-url' in art and art['cifblog-url']:
        blogurl, feedurl = art['cifblog-url'], art['cifblog-feed']
        if hasattr(ScraperUtils.article_store, 'conn'):
            cur = [ScraperUtils.article_store.conn.cursor()]
            def execute(sql, *args):
                cur[0].close()
                cur[0] = ScraperUtils.article_store.conn.cursor()
                cur[0].execute(sql, *args)
                if sql.upper().startswith('SELECT'):
                    return cur[0].fetchall()
            def close():
                cur[0].close()
        else:
            # dry run
            def execute(sql, *args):
                ukmedia.DBUG2('SQL: %s\n' %
                    ', '.join([re.sub(r'\s+', ' ', sql)] + [`x` for x in args]))
                if 'journo_attr' in sql:
                    return [[1234]]
                else:
                    return []
            def close(): pass
        types = [
            ('cif:blog:html', blogurl, 'CIF articles by journo'),
            ('cif:blog:feed', feedurl, 'CIF articles by journo (rss/atom)'),
        ]
        rows = execute("SELECT journo_id FROM journo_attr WHERE article_id=%s",
                       [art_id])
        assert 0 <= len(rows) <= 1
        if rows:
            journo_id = rows[0][0]
            for type, url, description in types:
                rows = execute(
                    "SELECT id FROM journo_weblink WHERE journo_id=%s AND url=%s",
                    [journo_id, url])
                assert 0 <= len(rows) <= 1
                if not rows:
                    source = art['srcurl']
                    execute("""BEGIN""")
                    execute("""INSERT INTO journo_weblink(
                                        journo_id, url, description, source, type
                                   ) VALUES (%s, %s, %s, %s, %s)""",
                                [journo_id, url, description, source, type])
                    execute("""COMMIT""")
        close() # just in case

if __name__ == "__main__":
	ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract, post_fn=AddBlogLinks )
