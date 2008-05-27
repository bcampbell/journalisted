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


def ParseTaggedByline( taggedbyline ):
    results = []

#    results = { 'n': [], 'l':[], 't':[], 'a':[], 'e':[] }

    person = None
    for m in tagpat.finditer( taggedbyline ):
        type = m.group(1)
        v = m.group(2)

        if( type == 'n' ):
            # finish old person...
            if person:
                results.append( person )
            # ...start new person
            person = { 'name': v }
        elif type == 'l' and person:
            person['loc'] = v
        elif type == 't' and person:
            person['title'] = v
        elif type == 'a' and person:
            person['agency'] = v
        elif type == 's' and person:
            person['subject'] = v
        elif type == 'e' and person:
            person['email'] = v
        #        else: warn about unknown tag?

    # store unclosed person
    if person:
        results.append( person )

    rawbyline = tagpat.sub( r"\2", taggedbyline )
    return ( rawbyline, results )


def FindName( name, results ):
    for j in results:
        if j['name'] == name:
            return j
    return None


def TestFunc( byline ):
    fixuppat = re.compile( ur"([a-z]{3,})([A-Z])", re.UNICODE )
    byline = fixuppat.sub( r"\1 \2", byline )


    extractpat = re.compile( ur".*(by|from|writes|reports|discovers|[:])\s+(.*)\s*$", re.UNICODE | re.IGNORECASE )

    foo = extractpat.sub( r"\2", byline )
    print "FOO: " ,foo
    return Byline.CrackByline( foo )


def RateResults( found, expected ):
    right=0
    wrong=0
    missed=0

    for f in found:
        if FindName( f['name'], expected ):
            right = right + 1
        else:
            # uh-oh... this is bad...
            # a wrong name is worse than missed one.
            wrong = wrong + 1

    for e in expected:
        if not FindName( e['name'], found ):
            missed = missed + 1

    return ( right, wrong, missed )



def Run():
    totalright = 0
    totalwrong = 0
    totalmissed = 0
    for l in sys.stdin:
        l = unicode( l, 'utf-8' )
        l = l.strip()
        if not l:
            continue
        if l.startswith( u'#' ):
            continue

        (raw,expected) = ParseTaggedByline(l)

        # call the fn we're testing with the raw byline
        found = TestFunc( raw )

        if found == None:
            found = []

        (right,wrong,missed) = RateResults( found, expected )

        if( right != len(expected) ):
            print "--------------"
            print l.encode( 'utf-8' )
            print found
            print "%d right, %d wrong, %d missed" % (right,wrong,missed)

        totalright = totalright + right
        totalwrong = totalwrong + wrong
        totalmissed = totalmissed + missed

    print "OVERALL: %d right, %d wrong, %d missed" % (totalright,totalwrong,totalmissed)



if __name__ == "__main__":
   Run()

 
