#!/usr/bin/env python2.4
#
# Copyright (c) 2007 Media Standards Trust
# Licensed under the Affero General Public License
# (http://www.affero.org/oagpl.html)
#
# Test framework for evaluating the accuracy of byline parsing
# Works by comparing against manually-tagged datasets.
#
# Tagged files have one item per line, tagged by surrounding
# the interesting bits with square brackets, prefixed by a
# single lower case letter saying which type of thing it is.
# 
# eg:
# "Watchdog raises prospect of Scotland-only quotas to meet targets By j[Peter John Meiklem] t[Media Correspondent]"
#
# n Name (of journalist!)
# l Location
# t job Title
# a Agency
# e Email address
#

import re
from datetime import datetime
import time
import string
import sys
import urlparse

import site
site.addsitedir("../pylib")
from JL import ukmedia

from JL import Byline


tagpat = re.compile( r"([a-z])\[(.*?)\]" );


def ParseLine( line ):
    results = { 'n': [], 'l':[], 't':[], 'a':[], 'e':[] }

    for m in tagpat.finditer( line ):
        results[ m.group(1) ].append( m.group(2) )

    return ( tagpat.sub( r"\2", line ), results )




def Run():
    namecnt = 0
    correct = 0

    for l in sys.stdin:
        l = unicode( l, 'utf-8' )
        l = l.strip()
        if not l:
            continue
        if l.startswith( u'#' ):
            continue

        (raw,results) = ParseLine(l)

        namecnt = namecnt + len( results['n'] )

        t = Byline.CrackByline( raw )
        if t:
            for person in t:
                if person['name'] in results['n']:
                    correct = correct + 1

        print "--------------"
        print raw
        print "  actual: ", results
        print "  CrackByline: ", t
        print
        print

    print "got %d of %d names correct" % ( correct,namecnt )



if __name__ == "__main__":
   Run()

 
