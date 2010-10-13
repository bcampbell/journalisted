# ukmedia.py
# Assorted helper routines for scraping.
#
# TODO:
# - ParseDateTime should be more generic. Just look for common
# date and time formats anywhere in a string...
# (replace with python-dateutil)
# - rename "ukmedia" to something more appropriate
#

import codecs
import htmlentitydefs
import re
import cgi
import os
import sys
import traceback
import urllib2
import socket
from datetime import datetime
import time
import hashlib

import Journo
import DB


class NonFatal(Exception):
    """A NonFatal article-extraction error (eg article is subscription-only)

    A NonFatal error thrown during article extraction will still be logged
    as an error, but won't cause processing to cease. The offending article
    will just be skipped.
    """
    pass

OFFLINE = False#True    # gtb
USE_CACHE = False
if os.getenv('JL_USE_CACHE','false').lower() in ( '0', 'false','off' ):
    USE_CACHE = False
else:
    USE_CACHE = True


# min time between http requests in FetchURL()
# (so we don't hammer servers and get banned)
# in seconds (can be a fraction)
FETCH_INTERVAL = 1

debuglevel = int( os.getenv( 'JL_DEBUG' ,'0' ) )

defaulttimeout=120  # socket timeout, in secs

def MonthNumber( name ):
    monthlookup = {
        '01': 1, '1':1, 'jan': 1, 'january': 1,
        '02': 2, '2':2, 'feb': 2, 'february': 2,
        '03': 3, '3':3, 'mar': 3, 'march': 3,
        '04': 4, '4':4, 'apr': 4, 'april': 4,
        '05': 5, '5':5, 'may': 5, 'may': 5,
        '06': 6, '6':6, 'jun': 6, 'june': 6,
        '07': 7, '7':7, 'jul': 7, 'july': 7,
        '08': 8, '8':8, 'aug': 8, 'august': 8,
        '09': 9, '9':9, 'sep': 9, 'september': 9,
        '10': 10, '10':10, 'oct': 10, 'october': 10,
        '11': 11, '11':11, 'nov': 11, 'november': 11,
        '12': 12, '12':12, 'dec': 12, 'december': 12 }
    return monthlookup[ name.lower() ]


# various different datetime formats
datecrackers = [
    # "2010-04-02T12:35:44+00:00" (iso8601, bbc blogs)
    re.compile( r"(?P<year>\d{4})-(?P<month>\d\d)-(?P<day>\d\d)T(?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d)", re.UNICODE ),
    # "2008-03-10 13:21:36 GMT" (technorati api)
    re.compile( """(?P<year>\d{4})-(?P<month>\d\d)-(?P<day>\d\d)\s+(?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d)""", re.UNICODE ),
    # "9 Sep 2009 12.33" (heraldscotland blogs)
    re.compile( r"(?P<day>\d{1,2})\s+(?P<month>\w+)\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2})[.:](?P<min>\d\d)", re.UNICODE ),
    # "May 25 2010 3:34PM" (thetimes.co.uk)
    # "Thursday August 21 2008 10:42 am" (guardian blogs in their new cms)
    re.compile( r'\w+\s+(?P<month>\w+)\s+(?P<day>\d{1,2})\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2}):(?P<min>\d\d)\s*((?P<am>am)|(?P<pm>pm))', re.UNICODE|re.IGNORECASE ),
    # 'Tuesday October 14 2008 00.01 BST' (Guardian blogs in their new cms)
    re.compile( r'\w+\s+(?P<month>\w+)\s+(?P<day>\d{1,2})\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2})[:.](?P<min>\d\d)\s+', re.UNICODE|re.IGNORECASE ),
    # 'Tuesday 16 December 2008 16.23 GMT' (Guardian blogs in their new cms)
    re.compile( r'\w+\s+(?P<day>\d{1,2})\s+(?P<month>\w+)\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2})[:.](?P<min>\d\d)\s+', re.UNICODE|re.IGNORECASE ),
    
    # 3:19pm on Tue 29 Jan 08 (herald blogs)
    re.compile( """(?P<hour>\d+):(?P<min>\d\d)\s*((?P<am>am)|(?P<pm>pm))\s+(on\s+)?(\w+)\s+(?P<day>\d+)\s+(?P<month>\w+)\s+(?P<year>\d+)""", re.UNICODE|re.IGNORECASE ),
    # "2007/03/18 10:59:02"
    re.compile( """(?P<year>\d{4})/(?P<month>\d\d)/(?P<day>\d\d) (?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d)""", re.UNICODE ),

    # "Mar 3, 2007 12:00 AM"
    # "Jul 21, 08 10:00 AM" (mirror blogs)
    re.compile( """((?P<month>[A-Z]\w{2}) (?P<day>\d+), (?P<year>\d{2,4}) (?P<hour>\d\d):(?P<min>\d\d) ((?P<am>AM)|(?P<pm>PM)))""", re.UNICODE ),

    # "09-Apr-2007 00:00" (times, sundaytimes)
    re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{4}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

    # "4:48PM GMT 22/02/2008" (telegraph html articles)
    re.compile( "(?P<hour>\d{1,2}):(?P<min>\d\d)\s*((?P<am>am)|(?P<pm>pm))\s+GMT\s+(?P<day>\d{1,2})/(?P<month>\d{1,2})/(?P<year>\d{2,4})", re.UNICODE|re.IGNORECASE ),

    # "09-Apr-07 00:00" (scotsman)
    re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{2}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

    # "Friday    August    11, 2006" (express, guardian/observer)
    re.compile( """\w+\s+(?P<month>\w+)\s+(?P<day>\d+),?\s*(?P<year>\d{4})""", re.UNICODE ),

    # "26 May 2007, 02:10:36 BST" (newsoftheworld)
    re.compile( """(?P<day>\d\d) (?P<month>\w+) (?P<year>\d{4}), (?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d) BST""", re.UNICODE ),

    # "2:43pm BST 16/04/2007" (telegraph, after munging)
    re.compile( "(?P<hour>\d{1,2}):(?P<min>\d\d)\s*((?P<am>am)|(?P<pm>pm))\s+BST\s+(?P<day>\d{1,2})/(?P<month>\d{1,2})/(?P<year>\d{2,4})", re.UNICODE|re.IGNORECASE ),

    # "20:12pm 23rd November 2007" (dailymail)
    # "2:42 PM on 22nd May 2008" (dailymail)
    re.compile( r"(?P<hour>\d{1,2}):(?P<min>\d\d)\s*((?P<am>am)|(?P<pm>pm))\s+(?:on\s+)?(?P<day>\d{1,2})\w+\s+(?P<month>\w+)\s+(?P<year>\d{4})", re.UNICODE|re.IGNORECASE),
    # "February 10 2008 22:05" (ft)
    re.compile( """(?P<month>\w+)\s+(?P<day>\d+)\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2}):(?P<min>\d\d)""", re.UNICODE ),


    # "22 Oct 2007 (weird non-ascii characters) at(weird non-ascii characters)11:23" (telegraph blogs OLD!)
#    re.compile( """(?P<day>\d{1,2}) (?P<month>\w+) (?P<year>\d{4}).*?at.*?(?P<hour>\d{1,2}):(?P<min>\d\d)""", re.UNICODE|re.DOTALL ),
    # 'Feb 2, 2009 at 17:01:09' (telegraph blogs)
    re.compile( r"(?P<month>\w+)\s+(?P<day>\d{1,2}), (?P<year>\d{4}).*?at.*?(?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d)", re.UNICODE|re.DOTALL ),
 
    # "18 Oct 07, 04:50 PM" (BBC blogs)
    # "02 August 2007  1:21 PM" (Daily Mail blogs)
    re.compile( """(?P<day>\d{1,2}) (?P<month>\w+) (?P<year>\d{2,4}),?\s+(?P<hour>\d{1,2}):(?P<min>\d\d) ((?P<am>AM)|(?P<pm>PM))?""", re.UNICODE ),

    # 'October 22, 2007  5:31 PM' (old Guardian blogs, ft blogs)
    re.compile( """((?P<month>\w+)\s+(?P<day>\d+),\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2}):(?P<min>\d\d)\s*((?P<am>AM)|(?P<pm>PM)))""", re.UNICODE|re.IGNORECASE ),

    # 'October 15, 2007' (Times blogs)
    # 'February 12 2008' (Herald)
    re.compile( """(?P<month>\w+)\\s+(?P<day>\d+),?\\s+(?P<year>\d{4})""", re.UNICODE ),
    
    # 'Monday, 22 October 2007' (Independent blogs, Sun (page date))
    re.compile( """\w+,\s+(?P<day>\d+)\s+(?P<month>\w+)\s+(?P<year>\d{4})""", re.UNICODE ),
    
    # '22 October 2007' (Sky News blogs)
    # '11 Dec 2007' (Sun (article date))
    # '12 February 2008' (scotsman)
    re.compile( """(?P<day>\d+)\s+(?P<month>\w+)\s+(?P<year>\d{4})""", re.UNICODE ),
    # '03/09/2007' (Sky News blogs, mirror)
    re.compile( """(?P<day>\d{1,2})/(?P<month>\d{1,2})/(?P<year>\d{4})""", re.UNICODE ),

    #'Tuesday, 21 January, 2003, 15:29 GMT' (historical bbcnews)
    re.compile( r"(?P<day>\d{1,2})\s+(?P<month>\w+),?\s+(?P<year>\d{4}),?\s+(?P<hour>\d{1,2}):(?P<min>\d\d)", re.UNICODE ),
    # '2003/01/21 15:29:49' (historical bbcnews (meta tag))
    re.compile( r"(?P<year>\d{4})/(?P<month>\d{1,2})/(?P<day>\d{1,2})\s+(?P<hour>\d{1,2}):(?P<min>\d\d):(?P<sec>\d\d)", re.UNICODE ),
    # '2010-07-01'
    # '2010/07/01'
    re.compile( """(?P<year>\d{4})[-/](?P<month>\d{1,2})[-/](?P<day>\d{1,2})""", re.UNICODE ),

    ]

def GetGroup(m,nm):
    """cheesy little helper for ParseDateTime()"""
    try:
        return m.group( nm )
    except IndexError:
        return None

def ParseDateTime( datestring, usa_format=False ):
    """Parse a date string in a variety of formats. Raises an exception if no dice"""

    #DEBUG:
    #print "DATE: "
    #print datestring
    #print "\n"

    if usa_format:
        # swap day and month if both are numeric
        datestring = re.sub( r'(\d{1,2})([-/])(\d{1,2})([-/])(\d{2,4})', r'\3\2\1\4\5', datestring )


    for c in datecrackers:
        m = c.search( datestring )
        if not m:
            continue

        #DEBUG:
        #print "MONTH: "
        #print m.group( 'month' )
        #print "\n"

        day = int( m.group( 'day' ) )
        month = MonthNumber( m.group( 'month' ) )
        year = int( m.group( 'year' ) )
        if year < 100:
            year = year+2000

        hour = GetGroup(m,'hour')
        if not hour:
            return datetime( year,month,day )
        hour = int( hour )

        # convert to 24 hour time
        # if no am/pm, assume 24hr
        if GetGroup(m,'pm') and hour>=1 and hour <=11:
            hour = hour + 12
        if GetGroup(m,'am') and hour==12:
            hour = hour - 12

        # if hour present, min will be too
        min = int( m.group( 'min' ) )

        # sec might be missing
        sec = GetGroup( m,'sec' )
        if not sec:
            return datetime( year,month,day,hour,min )
        sec = int( sec )

        return datetime( year,month,day,hour,min,sec )

    raise Exception, ("Can't extract date from '%s'" %(datestring) )

#----------------------------------------------------------------------------



tagopenpat = re.compile( "<(\w+)(\s+.*?)?\s*(/\s*)?>", re.UNICODE|re.DOTALL )
tagclosepat = re.compile( "<\s*/\s*(\w+)\s*>", re.UNICODE|re.DOTALL )
emptylinkpat = re.compile ( "<a[^>]*?>\s*</a>", re.UNICODE )
emptylinkpat2 = re.compile ( "<a\s*>(.*?)</a>", re.UNICODE|re.DOTALL )
acceptabletags = [ 'p', 'h1','h2','h3','h4','h5','br','b','i','em','li','ul','ol','strong', 'blockquote', 'a' ]

commentkillpat = re.compile( u"<!--.*?-->", re.UNICODE|re.DOTALL )
emptyparapat = re.compile( u"<p>\s*</p>", re.IGNORECASE|re.UNICODE|re.DOTALL )

def SanitiseHTML_handleopen(m):
    tag = m.group(1).lower()

    if tag in acceptabletags:
        # special case - allow <a> to keep href attr:
        if tag == 'a':
            m2 = re.search( ('(href=\".*?\")'), m.group(2) or '')
            if m2:
                return u"<a %s>" % (m2.group(1) )

        return u"<%s>" % (tag)
    else:
        return u''

def SanitiseHTML_handleclose(m):
    tag = m.group(1).lower()
    if tag in acceptabletags:
        return u"</%s>" % (tag)
    else:
        return u' '

def SanitiseHTML( html ):
    """Strip out all non-essential tags and attrs"""
    html = html.replace('>>', '>')

    # some tags we want to excise completely:
    for tag in ('script','noscript','style' ):
        pattxt = r'<\s*' + tag + r'\b.*?\s*>.*?</\s*' + tag + r'\s*>'
        pat = re.compile(pattxt, re.DOTALL|re.IGNORECASE )
        html = pat.sub('',html)
    # others, we might want to kill but keep the content
    html = tagopenpat.sub( SanitiseHTML_handleopen, html )
    html = tagclosepat.sub( SanitiseHTML_handleclose, html )
    html = emptyparapat.sub( u'', html )
    html = commentkillpat.sub( u'', html )
    html = emptylinkpat.sub( u'', html )
    html = emptylinkpat2.sub( ur'\1', html )
    return html.lstrip()




#----------------------------------------------------------------------------

# match html entities
descapepat = re.compile( "&([#\w][\w]+?);", re.UNICODE )
descape_hexpat = re.compile( u'#x([0-9a-fA-F]+)', re.UNICODE )
descape_decpat = re.compile( u'#([0-9]+)', re.UNICODE )

# callback for DescapeHTML()
def descape_entity(m):
    #print "BING: '%s'" %( m.group(0) )
    try:
        return unientitydefs[ m.group(1) ]
    except KeyError:
        # is it hex?
        numm = descape_hexpat.match( m.group(1) )
        if numm:
            return unichr( int( numm.group(1), 16 ) )

        # is it decimal? 
        numm = descape_decpat.match( m.group(1) )
        if numm:
            return unichr( int( numm.group(1) ) )
        #print( u"defeated on '%s'" % (m.group(0) ) )
        return m.group(0) # use as is

# un-escape HTML entities
#  &amp; => '&'
#  #nnnnn; => unicode char
# etc
def DescapeHTML(s):

    return descapepat.sub(descape_entity, s)

#escape HTML entities ('<' => '&gt;' etc...)
def EscapeHTML(s):
    return cgi.escape(s)


strippat = re.compile( u'<.*?>', re.UNICODE|re.DOTALL )
# strip out all HTML tags
def StripHTML(s):
    return strippat.sub( u' ', s )

# TODO: Strip? Remove? kill one!
def RemoveTags( html ):
    removetagpat = re.compile( u"(\s*<.*?>\s*)+", re.UNICODE | re.DOTALL );
    return removetagpat.sub( u' ', html ).strip()



def FromHTML( s ):
    """Convert from HTML to plain unicode string (strip tags, convert entities etc)"""
    s = RemoveTags(s)
    s = DescapeHTML(s)
    s = s.strip()
    if s == '':
        s=u''
    return s

def FromHTMLOneLine( s ):
    """return html as a single line unicode string"""
    return u' '.join( FromHTML(s).split() )

# build up a unicode version of the htmlentitydefs.entitydefs table
# for DescapeHTML()

unientitydefs = {}

for (name, codepoint) in htmlentitydefs.name2codepoint.iteritems():
    unientitydefs[name] = unichr(codepoint)

#print unientitydefs

del name, codepoint



#----------------------------------------------------------------------------
# DEBUGGING STUFF


def DBUG( msg ):
    if debuglevel > 0:
        print msg.encode( 'utf-8' ),

def DBUG2( msg ):
    if debuglevel > 1:
        print msg.encode( 'utf-8' ),



#-------------------------------------------------------------------------

# Byline-o-matic, gtb
def ExtractAuthorFromParagraph(para):
#   print "ExtractAuthorFromParagraph"

    para = RemoveTags(para).strip()
    
    if (para==u''):
        return u''


    # TODO
    # Rosanna de Lisle
    
    # shame to throw away information, like the author name might be in bold, but in practice it doesn't seem to matter that much
    
    # gtb
    # Deal with complex bylines:
    # For now assume the author's name is two capitalised words
    verbsIndicatingJournalistInOrderOfLikelihood = (
        # "Roger Highfield outlines the verdict of former science minister, Lord Sainsbury"
        u'(?:review|discover|choose|tour|insist|tackle|head|think|report|stay|ask|warn|outline|report|explain|write|look|answer|argue|examine|advise|wonder|unravel|By|say|investigate)(?:d|ed|s|)',
        #     Andrew Cave becomes 'Telegraphman Boozehound' on Second Life to see how well it works
        u'(?:caught up|meets|catches up|becomes|is|was|find|select|takes|makes|at large)',
        u'(?:[a-z]+s)', # any word ending with -s
        u'[a-z]+'       # anything at all (but must be lowercase)
    )

    # deals with double-barrelled names like Jessica Gorst-Williams, also names like McGreal
    journalistNamePattern_one = u'[A-Z][a-z]+ (?:[A-Z][a-z]+-?)?[A-Z][a-z]+'    
    # allows Aaa Bee and Cee Dee:
    journalistNamePattern = u'(?:'+journalistNamePattern_one+')(?: and '+journalistNamePattern_one+')?'
    
    author = u'';
    confidence = 0
    conn = 0
    for verbs in verbsIndicatingJournalistInOrderOfLikelihood:
        if confidence<=2:
            #  pick out where at beginning or end of sentence:
            searchPatterns = [
                u'(?:^|[\.\!\?\'\"])\s*('+journalistNamePattern+') '+verbs+u'\\b',          # "Joe Bloggs writes..."
                u'\\b'+verbs+u' ('+journalistNamePattern+')(?:$|[\.\!\?\'\"])'              # "... writes Joe Bloggs"
            ]
        else:
            # pick out anywhere with less confidence:
            searchPatterns = [
                u'\\b('+journalistNamePattern+') '+verbs+u'\\b',            # "Joe Bloggs writes..."
                u'\\b'+verbs+u' ('+journalistNamePattern+')\\b'             # "... writes Joe Bloggs"
            ]
        confidence=confidence+1
#       print "confidence: ",confidence
        for searchPattern in searchPatterns:
            authorFromDescriptionMatches = re.findall(searchPattern, para)
            if authorFromDescriptionMatches:
#               print "Got authorFromDescriptionMatches"
                # First two (high confidence patterns) are allowed to create new journalists:
                if confidence<=2:
                    author = authorFromDescriptionMatches[0]#-1]#.group(1)
                    break
                # Last two (low confidence patterns) are only allowed to match journalists we already know about:
                else:
                    if conn==0:
                        conn = DB.Connect()
                    for possibleAuthor in authorFromDescriptionMatches:
#                       print "possibleAuthor: ",possibleAuthor
                        if Journo.FindJourno(conn,possibleAuthor):
                            author = possibleAuthor
                            break
                    if author:
                        break
        if author:
            break

# BenC - disabled bylineomatic2 until we do some proper testing...
#   if not author:
#       author = BylineOMatic2(para)
#       confidence = 'FALLBACK'

    if author:
        DBUG2( u"  Byline-o-matic: %s %s <- \"%s\"\n" % ( confidence,author,para ) )
    
    if not author:
        DBUG2( u"  Byline-o-matic failed on: \"%s\"\n" %(para) )

    return author or u''


def BylineOMatic2(para):
    '''Fallback implementation for things that byline-o-matic would otherwise fail on.'''
    patterns = [
        # || indicates full-stop, question mark, comma, hyphen or start or end.
        r" talks to %s about ",
        r" writes %s||",
        r" argues %s||",
        r" says %s||",
        r" asks %s||",
        r", finds %s||",
        r"||%s(?:,(?: \w+)?,)? finds out||",
        r"||%s(?:,(?: \w+)?,)? finds out ",
        r"||%s(?:,(?: \w+)?,)? meets ",
        r"||%s(?:,(?: \w+)?,)? looks at ",
        r"||%s(?:,(?: \w+)?,)? asks ",
        r"||%s(?:,(?: \w+)?,)? has fun at ",
        r"||%s on ",
        r"||%s tries ",
        r"||%s unpicks ",
        r"^%s has ",
        # remember to add a comma after each pattern!
    ]
    regexps = []
    for pattern in patterns:
        pattern = pattern % '(?P<author>[A-Z][a-zA-Z\-]+(?: [A-Z]\.)*(?: [A-Z][a-zA-Z\-]+)+)'
        pattern = pattern.replace(' ', r'\s+').replace('||', r'(?:[\.,\-\?]|^|$)\s*')
        regexps.append(pattern)
    for regexp in regexps:
        match = re.search(regexp, para)
        if match:
            return match.group('author')
    #import pdb; pdb.set_trace()  # for debugging failures


def GetCacheFilename(url):
    """ use a md5 hash to cache files, as some urls are waaaaay too long for the filesystem """
    hash = hashlib.md5()
    hash.update(url)
    return hash.hexdigest()


indyurlpat = re.compile( '^(http://)?[^/]*independent[^/]*' )

lastfetchtime = 0.0

def FetchURL( url, timeout=defaulttimeout, cacheDirName='cache' ):
    global lastfetchtime

    socket.setdefaulttimeout( timeout )
    # some URLs are down as https erroneously, fix this:
    url = re.sub(u'\\bhttps\\b',u'http',url)

    attempt = 0
    while 1:
        try:
            if USE_CACHE:
                if not os.path.exists(cacheDirName):
                    os.makedirs(cacheDirName)
                cachedFilename = os.path.join( cacheDirName, GetCacheFilename(url) )
            if USE_CACHE and os.path.exists(cachedFilename):
                # read from cache instead of from the internet:
                f = open(cachedFilename,'r')
                dat = f.read()
            else:
                if OFFLINE:
                    return None
                if not url.startswith('file:'):
                    # throttle the fetch rate
                    now = time.clock()
                    elapsed = now-lastfetchtime
                    if elapsed < FETCH_INTERVAL:
                        time.sleep( FETCH_INTERVAL-elapsed )
                    lastfetchtime = time.clock()

                req = urllib2.Request(url, headers={'User-Agent': 'JournalistedBot'})
                f = urllib2.urlopen(req)
                dat = f.read()
                # cache it:
                if USE_CACHE:
                    f = open(cachedFilename,'w')
                    f.write(dat)
            return dat
        except urllib2.HTTPError, e:
            if not indyurlpat.match( url ):
                raise
            if e.code!=500:
                raise

            DBUG2( "FetchURL INDY500 error (%s)\n" % (url) )

            attempt = attempt + 1
            if attempt >= 5:
                DBUG2( "  aborting - too many retries\n" )
                raise

            # give server a few seconds to get its act together and retry!
            time.sleep( 10 )



def UncapsTitle( title ):
    """Try and produce a prettier version of AN ALL CAPS TITLE"""
    title = title.title()

    # "Title'S Apostrophe Badness" => "Title's Apostrophe Badness"
    # I'Ll I'M I'D Don'T We'Ll I'Ve...
    for suffix in ( u'Ve', u'S', u'L', u'T', u'Ll', u'M', u'D' ):
        pat = re.compile( u"(\w)(['\u2019]%s\\b)" % (suffix), re.UNICODE )
        title = pat.sub( "\\1'%s" % (suffix.lower() ), title )
    return title.strip()



def PrettyDump( art ):
    """ dump an article out to stdout, in a readable(ish) form """
    print "=== %s ===" % (art['srcurl'])
    for f in art:
        if f not in ( 'content', 'images', 'commentlinks' ):
            print "%11s: %s" % (f,unicode( art[f] ).encode('utf-8') )
    if 'images' in art:
        for i in art['images']:
            print "image: %s ('%s') [%s]" % ( i['url'].encode('utf-8'), i['caption'].encode('utf-8'), i['credit'].encode('utf-8') )
    if 'commentlinks' in art:
        for c in art['commentlinks']:
            cnt = "<unknown>"
            if c['num_comments'] is not None:
                cnt = str(c['num_comments'])
            print "comments: %s at %s" % ( cnt, c['comment_url'])
    print "-----------content----------------------"
    if art['content'] is None:
        print "-- no content --"
    else:
        print art['content'].encode('utf-8')
    print "----------------------------------------"
    print



def FirstPara( html ):
    """ try and extract the first paragraph from some html
    
    result is single line of unicode text with all html tags stripped
    """


    # first try text before first <p> (or </p>, because it might be broken)
    m = re.match( "\\s*(.*?)\\s*<([/])?p>", html, re.IGNORECASE|re.DOTALL )
    if m:
        p = FromHTMLOneLine(m.group(1))
        if len(p) > 10:
            return p

    # get first non-empty para
    cnt=0
    for m in re.finditer( "<p>\\s*(.*?)\\s*</p>", html, re.IGNORECASE|re.DOTALL ):
        p = FromHTMLOneLine( m.group(1) )
        if len(p) > 0:
            return p;

    # no joy - just try and return the first 50 words
    words = FromHTMLOneLine(html).split()
    if len( words ) > 0:
        return u' '.join(words[:50] ) + "..."

    return u''



def decap_repl(m):
    """ helper for DecapNames() """
    return m.group(0).title()

def DecapNames( txt ):
    """ transform any ALL-CAPS words (>=3 letters) into titlecase """
    pat = re.compile( '\\b[A-Z]{3,}\\b' )
    return pat.sub( decap_repl, txt )

