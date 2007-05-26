#!/usr/bin/env python

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

import feedparser


defaulttimeout=120	# socket timeout, in secs

def MonthNumber( name ):
	monthlookup = {
		'01': 1, 'jan': 1, 'january': 1,
		'02': 2, 'feb': 2, 'february': 2,
		'03': 3, 'mar': 3, 'march': 3,
		'04': 4, 'apr': 4, 'april': 4,
		'05': 5, 'may': 5, 'may': 5,
		'06': 6, 'jun': 6, 'june': 6,
		'07': 7, 'jul': 7, 'july': 7,
		'08': 8, 'aug': 8, 'august': 8,
		'09': 9, 'sep': 9, 'september': 9,
		'10': 10, 'oct': 10, 'october': 10,
		'11': 11, 'nov': 11, 'november': 11,
		'12': 12, 'dec': 12, 'december': 12 }
	return monthlookup[ name.lower() ]


# various different datetime formats
datecrackers = [
	# "2007/03/18 10:59:02"
	re.compile( """(?P<year>\d{4})/(?P<month>\d\d)/(?P<day>\d\d) (?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d)""", re.UNICODE ),

	# "Mar 3, 2007 12:00 AM"
	re.compile( """((?P<month>\w{3}) (?P<day>\d+), (?P<year>\d{4}) (?P<hour>\d\d):(?P<min>\d\d) ((?P<am>AM)|(?P<pm>PM)))""", re.UNICODE ),

	# "09-Apr-2007 00:00"
	re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{4}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

	# "09-Apr-07 00:00" (scotsman)
	re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{2}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

	# "Friday    August    11, 2006" (guardian/observer, express)
	re.compile( """\w+\s+(?P<month>\w+)\s+(?P<day>\d+),\s*(?P<year>\d{4})""", re.UNICODE ),

	# "26 May 2007, 02:10:36 BST" (newsoftheworld)
	re.compile( """(?P<day>\d\d) (?P<month>\w+) (?P<year>\d{4}), (?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d) BST""", re.UNICODE ),

	]


def GetGroup(m,nm):
	"""cheesy little helper for ParseDateTime()"""
	try:
		return m.group( nm )
	except IndexError:
		return None

def ParseDateTime( datestring ):
	"""Parse a date string in a variety of formats. Raises an exception if no dice"""

	for c in datecrackers:
		m = c.search( datestring )
		if not m:
			continue

		day = int( m.group( 'day' ) )
		month = MonthNumber( m.group( 'month' ) )
		year = int( m.group( 'year' ) )


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
acceptabletags = [ 'p', 'h1','h2','h3','h4','h5','br','b','i','em','li','ul','ol','strong' ]

commentkillpat = re.compile( u"<!--.*?-->", re.UNICODE|re.DOTALL )

def SanitiseHTML_handleopen(m):
	tag = m.group(1).lower()
	if tag in acceptabletags:
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
	html = tagopenpat.sub( SanitiseHTML_handleopen, html )
	html = tagclosepat.sub( SanitiseHTML_handleclose, html )
	html = commentkillpat.sub( u'', html )
	return html




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

# build up a unicode version of the htmlentitydefs.entitydefs table
# for DescapeHTML()

unientitydefs = {}

for (name, codepoint) in htmlentitydefs.name2codepoint.iteritems():
	unientitydefs[name] = unichr(codepoint)

del name, codepoint



#----------------------------------------------------------------------------
# DEBUGGING STUFF

debuglevel = int( os.getenv( 'JL_DEBUG' ,'0' ) )

def DBUG( msg ):
	if debuglevel > 0:
		print msg.encode( 'utf-8' ),

def DBUG2( msg ):
	if debuglevel > 1:
		print msg.encode( 'utf-8' ),




def FindArticlesFromRSS( rssfeeds, srcorgname, mungefunc=None ):
	""" Get a list of current articles """

	foundarticles = []

	socket.setdefaulttimeout( defaulttimeout )	# in seconds

	DBUG2( "*** %s ***: reading rss feeds..." % (srcorgname) )
	for feedname, feedurl in rssfeeds.iteritems():

		r = feedparser.parse( feedurl )
		lastseen = datetime.now()
		for entry in r.entries:
			#Each item is a dictionary mapping properties to values
			url = entry.link		# will be guid if no link attr
			title = entry.title
			desc = entry.summary
			if hasattr(entry, 'updated_parsed'):
				pubdate = datetime.fromtimestamp(time.mktime(entry.updated_parsed))
			else:
				pubdate = None

			title = DescapeHTML( title )
			desc = DescapeHTML( desc )

			context = {
				'srcid': url,
				'srcurl': url,
				'permalink': url,
				'description': desc,
				'title' :title,
				'srcorgname' : srcorgname,
				'lastseen': lastseen,
				}

			if pubdate:
				context['pubdate'] = pubdate

			if mungefunc:
				context = mungefunc( context, entry )
				# mungefunc can suppress by returning None.
				if not context:
					continue

			foundarticles.append( context )
	DBUG2( "found %d articles.\n" % ( len(foundarticles) ) )
	return foundarticles



def ProcessArticles( foundarticles, store, extractfn ):
	"""Download, scrape and load a list of articles

	Each entry in foundarticles must have at least:
		srcurl, srcorgname, srcid
	Assumes entire article can be grabbed from srcurl.
	"""

	errorcount = 0
	maxerrors = 10
	newcount = 0

	for context in foundarticles:
		try:
			if store.ArticleExists( context['srcorgname'], context['srcid'] ):
				continue;	# skip it - we've already got it

			html = FetchURL( context['srcurl'] )

			# some extra, last minute context :-)
			context[ 'lastscraped' ] = datetime.now()

			art = extractfn( html, context )

			if art:
				store.Add( art )
				DBUG2( "%s: '%s' (%s)\n" % (art['srcorgname'], art['title'], art['byline']) );
				newcount = newcount + 1

		except Exception, err:
			if context.has_key( 'title' ):
				msg = u"FAILED: '%s' (%s):" % (context['title'], context['srcurl'])
			else:
				msg = u"FAILED: (%s):" % (context['srcurl'])

			print >>sys.stderr, msg.encode( 'utf-8' )

			if isinstance( err, KeyboardInterrupt ):
				raise

			print >>sys.stderr, '-'*60
			traceback.print_exc()
			print >>sys.stderr, '-'*60


			errorcount = errorcount + 1
			if errorcount >= maxerrors:
				print >>sys.stderr, "Too many errors - ABORTING"
				raise
			#else just skip this one and go on to the next...

	DBUG( "%s: %d new, %d failed\n" % (sys.argv[0], newcount, errorcount ) )
	return (newcount,errorcount)


def FetchURL( url, timeout=defaulttimeout ):
	socket.setdefaulttimeout( timeout )

	f = urllib2.urlopen(url)
	dat = f.read()
	return dat



def UncapsTitle( title ):
	"""Try and produce a prettier version of AN ALL CAPS TITLE"""
	title = title.title()

	# "Title'S Apostrophe Badness" => "Title's Apostrophe Badness"
	# I'Ll I'M I'D Don'T We'Ll I'Ve...
	for suffix in ( u'Ve', u'S', u'L', u'T', u'Ll', u'M', u'D' ):
		pat = re.compile( "(\w)('%s\\b)" % (suffix), re.UNICODE );
		title = pat.sub( "\\1'%s" % (suffix.lower() ), title );
	return title.strip()




