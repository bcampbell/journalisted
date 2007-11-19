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

import Journo
import DB

import feedparser

class NonFatal(Exception):
	"""A NonFatal article-extraction error (eg article is subscription-only)

	A NonFatal error thrown during article extraction will still be logged
	as an error, but won't cause processing to cease. The offending article
	will just be skipped.
	"""
	pass

OFFLINE = False#True	# gtb
USE_CACHE = True	# gtb

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
	re.compile( """((?P<month>[A-Z]\w{2}) (?P<day>\d+), (?P<year>\d{4}) (?P<hour>\d\d):(?P<min>\d\d) ((?P<am>AM)|(?P<pm>PM)))""", re.UNICODE ),

	# "09-Apr-2007 00:00" (times, sundaytimes)
	re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{4}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

	# "09-Apr-07 00:00" (scotsman)
	re.compile( """(?P<day>\d\d)-(?P<month>\w+)-(?P<year>\d{2}) (?P<hour>\d\d):(?P<min>\d\d)""", re.UNICODE ),

	# "Friday    August    11, 2006" (express, guardian/observer)
	re.compile( """\w+\s+(?P<month>\w+)\s+(?P<day>\d+),?\s*(?P<year>\d{4})""", re.UNICODE ),

	# "26 May 2007, 02:10:36 BST" (newsoftheworld)
	re.compile( """(?P<day>\d\d) (?P<month>\w+) (?P<year>\d{4}), (?P<hour>\d\d):(?P<min>\d\d):(?P<sec>\d\d) BST""", re.UNICODE ),

	# for BLOGS:
	
	# "22 Oct 2007 (weird non-ascii characters) at(weird non-ascii characters)11:23" (telegraph blogs)
	re.compile( """(?P<day>\d{1,2}) (?P<month>\w+) (?P<year>\d{4}).*?at.*?(?P<hour>\d{1,2}):(?P<min>\d\d)""", re.UNICODE|re.DOTALL ),
	
	# "18 Oct 07, 04:50 PM" (BBC blogs)
	# "02 August 2007  1:21 PM" (Daily Mail blogs)
	re.compile( """(?P<day>\d{1,2}) (?P<month>\w+) (?P<year>\d{2,4}),?\s+(?P<hour>\d{1,2}):(?P<min>\d\d) ((?P<am>AM)|(?P<pm>PM))""", re.UNICODE ),

	# 'October 22, 2007  5:31 PM' (Guardian blogs)
	re.compile( """((?P<month>\w+)\s+(?P<day>\d+),\s+(?P<year>\d{4})\s+(?P<hour>\d{1,2}):(?P<min>\d\d)\s+((?P<am>AM)|(?P<pm>PM)))""", re.UNICODE ),

	# 'October 15, 2007' (Times blogs)
	re.compile( """(?P<month>\w+) (?P<day>\d+), (?P<year>\d{4})""", re.UNICODE ),
	
	# 'Monday, 22 October 2007' (Independent blogs)
	re.compile( """\w+,\s+(?P<day>\d+)\s+(?P<month>\w+)\s+(?P<year>\d{4})""", re.UNICODE ),
	
	# '22 October 2007' (Sky News blogs)
	re.compile( """(?P<day>\d+)\s+(?P<month>\w+)\s+(?P<year>\d{4})""", re.UNICODE ),
	# 03/09/2007' (Sky News blogs)
	re.compile( """(?P<day>\d\d)/(?P<month>\d\d)/(?P<year>\d{4})""", re.UNICODE )
	]


def GetGroup(m,nm):
	"""cheesy little helper for ParseDateTime()"""
	try:
		return m.group( nm )
	except IndexError:
		return None

def ParseDateTime( datestring ):
	"""Parse a date string in a variety of formats. Raises an exception if no dice"""

	#DEBUG:
	#print "DATE: "
	#print datestring
	#print "\n"
	
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

#print unientitydefs

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

	DBUG2( "*** %s ***: reading rss feeds...\n" % (srcorgname) )
	for feedname, feedurl in rssfeeds.iteritems():
		DBUG2( "feed '%s' (%s)\n" % (feedname,feedurl) )

		if USE_CACHE:
			FetchURL(feedurl, defaulttimeout, "rssCache\\"+srcorgname)
			r = feedparser.parse( "rssCache\\"+srcorgname+"\\"+GetCacheFilename(feedurl) )
		else:
			r = feedparser.parse( feedurl )
		
		#debug:		print r.version;

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

#			print "New desc: ",desc.encode('latin-1','replace')

			title = DescapeHTML( title )
			desc = FromHTML( desc )
			

			context = {
				'srcid': url,
				'srcurl': url,
				'permalink': url,
				'description': desc,
				'title' :title,
				'srcorgname' : srcorgname,
				'lastseen': lastseen,
				'feedname': feedname #gtb
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



def ProcessArticles( foundarticles, store, extractfn, postfn=None ):
	"""Download, scrape and load a list of articles

	Each entry in foundarticles must have at least:
		srcurl, srcorgname, srcid
	Assumes entire article can be grabbed from srcurl.

	extractfn - function to extract an article from the html
	postfn - option fn to call after article is added to db
	"""
	failcount = 0
	abortcount = 0
	maxerrors = 10
	newcount = 0

	for context in foundarticles:
		try:
			if store.ArticleExists( context['srcorgname'], context['srcid'] ):
				continue;	# skip it - we've already got it

			# gtb!debug, for debugging tricky cases:
#			if context['srcurl']!='http://www.telegraph.co.uk/money/main.jhtml?xml=/money/2007/11/03/cmjessica03.xml':
#				continue;

			html = FetchURL( context['srcurl'], defaulttimeout, "cache\\"+context['srcorgname'] )
			
			# some extra, last minute context :-)
			context[ 'lastscraped' ] = datetime.now()

			art = extractfn( html, context )

			if art:
				artid = store.Add( art )
				DBUG2( "%s: '%s' (%s)\n" % (art['srcorgname'], art['title'], art['byline']) );
				newcount = newcount + 1
				# if there is a post-processing fn, call it
				if postfn:
					postfn( artid, art )

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

			failcount = failcount+1
			# if it's a NonFatal don't increase abortcount)
			if not isinstance( err, NonFatal ):
				abortcount = abortcount + 1
				if abortcount >= maxerrors:
					print >>sys.stderr, "Too many errors - ABORTING"
					raise
				#else just skip this one and go on to the next...

	DBUG( "%s: %d new, %d failed\n" % (sys.argv[0], newcount, failcount ) )
	return (newcount,failcount)


# Byline-o-matic, gtb
def ExtractAuthorFromParagraph(para):
#	print "ExtractAuthorFromParagraph"

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
		u'(?:review|discover|choose|tour|insist|tackle|head|think|report|stay|ask|warn|outline|report|explain|write|look|answer|argue|examine|advise|wonder|unravel|By|say)(?:d|ed|s|)',
		#     Andrew Cave becomes 'Telegraphman Boozehound' on Second Life to see how well it works
		u'(?:caught up|meets|catches up|becomes|is|was|find|select|takes|makes|at large)',
		u'(?:[a-z]+s)',	# any word ending with -s
		u'[a-z]+'		# anything at all (but must be lowercase)
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
				u'(?:^|[\.\!\?\'\"])\s*('+journalistNamePattern+') '+verbs+u'\\b',			# "Joe Bloggs writes..."
				u'\\b'+verbs+u' ('+journalistNamePattern+')(?:$|[\.\!\?\'\"])'				# "... writes Joe Bloggs"
			]
		else:
			# pick out anywhere with less confidence:
			searchPatterns = [
				u'\\b('+journalistNamePattern+') '+verbs+u'\\b',			# "Joe Bloggs writes..."
				u'\\b'+verbs+u' ('+journalistNamePattern+')\\b'				# "... writes Joe Bloggs"
			]
		confidence=confidence+1
#		print "confidence: ",confidence
		for searchPattern in searchPatterns:
			authorFromDescriptionMatches = re.findall(searchPattern, para)
			if authorFromDescriptionMatches:
#				print "Got authorFromDescriptionMatches"
				# First two (high confidence patterns) are allowed to create new journalists:
				if confidence<=2:
					author = authorFromDescriptionMatches[0]#-1]#.group(1)
					break
				# Last two (low confidence patterns) are only allowed to match journalists we already know about:
				else:
					if conn==0:
						conn = DB.Connect()
					for possibleAuthor in authorFromDescriptionMatches:
#						print "possibleAuthor: ",possibleAuthor
						if Journo.FindJourno(conn,possibleAuthor):
							author = possibleAuthor
							break
					if author:
						break
		if author:
			break

	if author!=u'':
		print "    Byline-o-matic: ",confidence," ",author," <- ",para.encode('latin-1','replace'),""
	else:
		print "    Byline-o-matic failed on: ",para.encode('latin-1','replace')

	return author


def GetCacheFilename(url):
	return re.sub("\\W","_",url)

indyurlpat = re.compile( '^(http://)?[^/]*independent[^/]*' )

def FetchURL( url, timeout=defaulttimeout, cacheDirName='cache' ):
	socket.setdefaulttimeout( timeout )
	# some URLs are down as https erroneously, fix this:
	url = re.sub(u'\\bhttps\\b',u'http',url)
	#DEBUG	print "FetchURL: ",url

	attempt = 0
	while 1:
		try:
			if USE_CACHE:
				if not os.path.exists(cacheDirName):
					os.mkdir(cacheDirName)
				cachedFilename = cacheDirName+'\\'+GetCacheFilename(url)
		#	print cachedFilename
			if USE_CACHE and os.path.exists(cachedFilename):
				# read from cache instead of from the internet:
				f = open(cachedFilename,'r')
				dat = f.read()
			else:
				if OFFLINE:
					return None
				f = urllib2.urlopen(url)
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
		pat = re.compile( "(\w)('%s\\b)" % (suffix), re.UNICODE )
		title = pat.sub( "\\1'%s" % (suffix.lower() ), title )
	return title.strip()




