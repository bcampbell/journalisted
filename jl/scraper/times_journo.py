#!/usr/bin/env python2.4

'''
Scrapes articles from The Times per-journo pages such as
http://www.timesonline.co.uk/tol/comment/columnists/anatole_kaletsky/
'''

import sys
import re
import urllib
import urlparse

from times import ScraperUtils, ContextFromURL, Extract, ukmedia


ARTICLE_LIST_URL = None

def FindArticles():
    ukmedia.DBUG2( "*** times_journo ***: looking for articles...\n" )
    foundarticles = []
    http, computer = urlparse.urlparse(ARTICLE_LIST_URL)[:2]
    BASEURL = '%s://%s/' % (http, computer)
    html = ukmedia.FetchURL(ARTICLE_LIST_URL)
    start_marker = '<!-- BEGIN: M85 - Article Teasers -->'
    end_marker = '<!-- END: M85 - Article Teasers -->'
    html = html.replace(end_marker + start_marker, '')
    start = html.find(start_marker)
    end = html.find(end_marker)
    assert start>-1 and end>-1
    html = html[start + len(start_marker):end]
    for url in re.findall(r"""<a(?:\s+class=".*?")?\s+href='(.*?)'""", html):
        url = urllib.basejoin(BASEURL, url)
        if url not in foundarticles:
            foundarticles.append(url)
    ukmedia.DBUG2( "Found %d articles\n" % len(foundarticles) )
    return [ContextFromURL(url) for url in foundarticles]

if __name__ == "__main__":
    def prep(parser):
        parser.remove_option('--url')
        parser.add_option( "-u", "--url", dest="url",
                           help="scrape articles listed at URL, "
                                "a Times columnists page")
    
    def handle_args(options, args):
        if not options.url:
            sys.exit('error: No Times columnist URL specified (with -u/--url)')
        if not options.url.startswith('http://www.timesonline.co.uk/tol/comment/columnists/'):
            sys.exit('error: Not a Times columnist article list URL -\n'
                     '       expected http://www.timesonline.co.uk/tol/comment/columnists/...')
        global ARTICLE_LIST_URL
        ARTICLE_LIST_URL = options.url
        options.url = ''
        return (options, args)
    
    ScraperUtils.RunMain( FindArticles, ContextFromURL, Extract,
                          prepare_parser_fn=prep, after_parsing_fn=handle_args)
