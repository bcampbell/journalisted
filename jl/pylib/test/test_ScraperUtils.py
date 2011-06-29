#!/usr/bin/env python

"""Unit test for ScraperUtils.py"""

import unittest

import re
import urllib2

from JL import ScraperUtils


class CanonicalURLs(unittest.TestCase):
    snippets = [ ('<head><link rel="canonical" href="http://example.com/products" /></head>', "http://example.com/products" ),
        ('<head><link href="http://example.com/products" rel="canonical" /></head>', "http://example.com/products"),
        ("""<HEAD><LINK foo="wibble"
            HRef ="http://example.com/products" class="pibble"
            REL = "canonical" /   ></HEAD>""", "http://example.com/products"),
        ('<head><meta property="og:url" content="http://www.imdb.com/title/tt0117500/" /></head>', 'http://www.imdb.com/title/tt0117500/'),
        # a live BBC example:
#        (urllib2.urlopen('http://www.bbc.co.uk/news/world-africa-13058694').read(), 'http://www.bbc.co.uk/news/world-africa-13058694'),
        # and one from the mirror:
#        (urllib2.urlopen('http://www.mirror.co.uk/news/top-stories/2011/05/11/william-and-kate-to-get-around-on-old-bikes-during-their-luxury-honeymoon-115875-23121689/').read(),
#            "http://www.mirror.co.uk/news/royal-wedding/2011/05/11/royal-honeymoon-prince-william-and-kate-middleton-to-get-around-seychelles-island-on-rickety-old-bikes-115875-23121689/" ),
        ]

    def testSanity(self):
        for html,expected in self.snippets:
            got = ScraperUtils.extract_canonical_url(html)
            self.assertEqual(got,expected)

if __name__ == "__main__":
    unittest.main()   
