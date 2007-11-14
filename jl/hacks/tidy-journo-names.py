#!/usr/bin/env python2.4
#
# Tidy the database by merging journalists with similar names
#

import sys
import csv
import os
import difflib
import unicodedata
import re
sys.path.append("../pylib")
sys.path.append("../../pylib")
from JL import DB
from JL import Journo
from JL import Tags


DEBUG_NO_COMMITS = False

places_cached = None
def getPlaces():
	"""load list of places"""
	global places_cached
	if places_cached != None:
		return places_cached
	places_cached = []
	
	# TOWNS (UK only right now), from http://en.wikipedia.org/wiki/List_of_post_towns_in_the_United_Kingdom
	towndatafile = os.path.join(os.path.dirname(__file__),'../pylib/JL/towns.txt')
	f = open( towndatafile, "rb" )
	reader = csv.reader( f )
	for row in reader:
		c = row[0].decode( 'utf-8' ).lower()
		# get rid of accents because we'll compare this way:
		c = unicodedata.normalize('NFKD',c).encode('ascii','ignore')
		places_cached.append( c )

	# CITIES (worldwide): from http://www.world-gazetteer.com/wg.php?x=&men=gcis&lng=en&dat=32&srt=pnan&col=aohdq&pt=c&va=x
	citydatafile = os.path.join(os.path.dirname(__file__),'../pylib/JL/cities.csv')
	f = open( citydatafile, "rb" )
	reader = csv.reader( f )
	for row in reader:
		c = row[1].decode( 'utf-8' ).lower()
		# get rid of accents because we'll compare this way:
		c = unicodedata.normalize('NFKD',c).encode('ascii','ignore')
		places_cached.append( c )
	return places_cached


def mergeJourno(conn, fromRef, intoRef):
	c = conn.cursor()
	
	# FROM
	c.execute("SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=\'"+fromRef+"\'")
	row = c.fetchone()
	assert row, "fromRef doesn't exist:"+fromRef
	fromId = row[0]
	fromPrettyname = row[2]
	
	# INTO
	c.execute("SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=\'"+intoRef+"\'")
	row = c.fetchone()
	if not row:
		print "> Renaming Journo    ",fromRef,"->",intoRef
		# INTO REF DOESN'T EXIST, SO JUST RENAME:
		sqlTxt = u'UPDATE journo SET ref=\''+intoRef+u'\' WHERE ref=\''+fromRef+u'\''
		c.execute(sqlTxt)
	else:
		intoId = row[0]
		intoPrettyname = row[2]		
	#	print fromId
	#	print toId
	
		fromN = Journo.GetNoOfArticlesWrittenBy(conn,fromRef)
		intoN = Journo.GetNoOfArticlesWrittenBy(conn,intoRef)
		print "* Merging Journo     ",fromRef,"(%d)"%fromN,"->",intoRef,"(%d)"%intoN

		c.execute( "UPDATE journo_attr     SET journo_id=%d WHERE journo_id=%d" % (intoId, fromId) )
		c.execute( "UPDATE journo_alias    SET journo_id=%d WHERE journo_id=%d" % (intoId, fromId) )
		c.execute( "UPDATE journo_jobtitle SET journo_id=%d WHERE journo_id=%d" % (intoId, fromId) )
		c.execute( "UPDATE journo_weblink  SET journo_id=%d WHERE journo_id=%d" % (intoId, fromId) )
		c.execute( "DELETE FROM journo WHERE id=%d" % fromId )

	c.close()
	if not DEBUG_NO_COMMITS:
		conn.commit()

# n.b. allowing splits on hyphen (for refs) or space
# group=1 gets first name, =2 gets rest
def getFirstNameAndRestOf(name,groupId):
	# split into first name and rest:
	m = re.match('^(.*?)[ -](.*?)$',name)
	if not m:
		return ''
	return m.group(groupId)
	
# group=1 gets rest, =2 gets last name:
def getRestAndLastNameOf(name,groupId):
	# split into rest and last name:
	m = re.match('^(.*)[ -](.*?)$',name)
	if not m:
		return ''
	return m.group(groupId)
	
# only using spaces, not hyphens:
def getMiddleName(name):
	# split into rest and last name:
	m = re.match('^(.*?) (.*?) (.*?)$',name)
	if not m:
		return ''
	return m.group(2)
	
	
	

def getCloseMatches(conn, ref, journoRefs):
#	print 'getCloseMatches'
	likesSrc = difflib.get_close_matches(ref,journoRefs,9999,0.9) # was .9 for tidy5 # .95 does one character different
	likes = []

	for like in likesSrc:
#		print "Try like: ",like
		veto = False
		# if they only differ on first name:
		if getFirstNameAndRestOf(ref,2)==getFirstNameAndRestOf(like,2):
#			print "only differ on first name"
			# then don't allow a like if both are valid names:
			if Journo.IsReasonableFirstName(conn,getFirstNameAndRestOf(ref,1)) and Journo.IsReasonableFirstName(conn,getFirstNameAndRestOf(like,1)):
#				print "veto"
				veto = True
		# ditto for last names:
		if getRestAndLastNameOf(ref,1)==getRestAndLastNameOf(like,1):
			if Journo.IsReasonableLastName(conn,getRestAndLastNameOf(ref,2)) and Journo.IsReasonableLastName(conn,getRestAndLastNameOf(like,2)):
				veto = True
		if ref==like or (not veto):
			likes.append(like)

	# don't allow Ben to be similar to Ken:
	# allow for Moggon to be similar to Mogg
	return likes

def main():
#	print getPlaces()
#	return 0
	
	conn = DB.Connect()

	# Some unit tests:
	if False:  # True
		print "IsReasonableLastName('Ramsay')", Journo.IsReasonableLastName(conn,'Ramsay',1)
		print "IsReasonableLastName('Nabanga')",Journo.IsReasonableLastName(conn,'Nabanga')
		# Now guess place names for 3-word names, e.g. Stuart Ramsay Nabanga => Stuart Ramsay, Nabanga
		newPrettyname = u'Stuart Ramsay Nabanga'
		words = newPrettyname.split()
		if len(words)==3 and Journo.IsReasonableLastName(conn,words[1]) and not Journo.IsReasonableLastName(conn,words[2]):
			print "Maybe should be shortened? ", newPrettyname

		print "getFirstNameAndRestOf(\'James\',1) = ",getFirstNameAndRestOf('James',1)
		print "getFirstNameAndRestOf(\'James Bernard Shaw\',1) = ",getFirstNameAndRestOf('James Bernard Shaw',1)
		print "getFirstNameAndRestOf(\'James Bernard Shaw\',2) = ",getFirstNameAndRestOf('James Bernard Shaw',2)
		print "getRestAndLastNameOf( \'James Bernard Shaw\',1) = ",getRestAndLastNameOf( 'James Bernard Shaw',1)
		print "getRestAndLastNameOf( \'James Bernard Shaw\',2) = ",getRestAndLastNameOf( 'James Bernard Shaw',2)
		
		print "IsReasonableFirstName('sam')",Journo.IsReasonableFirstName(conn,'sam')
		print "IsReasonableFirstName('amy')",Journo.IsReasonableFirstName(conn,'amy')
		print "IsReasonableFirstName('bob')",Journo.IsReasonableFirstName(conn,'bob')
		print "IsReasonableFirstName('rob')",Journo.IsReasonableFirstName(conn,'rob')
		print "IsReasonableFirstName('lob')",Journo.IsReasonableFirstName(conn,'lob')
		print "IsReasonableLastName('davies')",Journo.IsReasonableLastName(conn,'davies')
		print "IsReasonableLastName('davis')",Journo.IsReasonableLastName(conn,'davis')
		print "IsReasonableLastName('zavis')",Journo.IsReasonableLastName(conn,'zavis')
		
		
		print getCloseMatches(conn,'amy-turner',['sam-turner'])
		print getCloseMatches(conn,'sam-turner',['amy-turner'])
		
	#	return 0
		
		print getCloseMatches(conn,'bob smith',['bob smith'])
		print getCloseMatches(conn,'bob smith',['rob smith'])#false
		print getCloseMatches(conn,'rob davies',['rob davis'])#false
		print getCloseMatches(conn,'rob davis',['rob davies'])#false
		print getCloseMatches(conn,'rob lavis',['rob lavies'])#true
		print "getCloseMatches(abdul qodous): ",getCloseMatches(conn,'abdul qodous',['abdul qudoos'])#true

		#	print getPlaces()
		#	return 0

		# Test PrettyCaseName:
		testJournos = [
			'dr barbara mcmahon',
			'dr barbara mcmahon mp',
			'barbara mcmahon mp',
			'barbara mcmahon',
			'barbara macmahon',
			'barbara smith',
			'barbara macmahon',
			'barbara o\'connor',
			'rachell campbell-johnston',
			'rachell de ville',
			'rachell van vark'
		]
		for journo in testJournos:
			m = re.match(u'(?:(with|by) )?(?:(col|sir|lieutenant-colonel|lieutenant|colonel|sgt|mr|dr|professor|cardinal|chef) )?(.*?)(?: (mp))?$', journo, re.UNICODE|re.IGNORECASE)
			assert m, "Can't process journo: "+m
		#		print m.group(1),"+",m.group(2),"+",m.group(3),"+",m.group(4)
				
		#		print journo," -> ",Journo.PrettyCaseName(journo)
		#	return 0	
		#						if Journo.FindJourno(conn,possibleAuthor):
		#	print "GetOverallTagFrequency",   Tags.GetOverallTagFrequency(conn, "Russell Brand".lower())
		#	print "GetNoOfArticlesWrittenBy", Journo.GetNoOfArticlesWrittenBy(conn, Journo.FindJourno(conn,"Russell Brand"))
		return 0



	# DOCUMENTATION OF PROCESS:
	# prettyname  is 'Professor He[accent]le[accent]ne Mulholland MP'
	#        ref  is  helene-mulholland
	# firstname   is 'helene' (wrong at the moment... either uses accents or Professor)
	# lastname    is 'mulholland'
	# [alias table now deprecated, we just use the ref]


	# First, get our list of journalists, their ref, prettyname and id:
	c = conn.cursor()
	c.execute("SELECT prettyname,ref,id FROM journo ORDER BY prettyname")
	journos = []
	rawJournoRefs = []
	while 1:
		row=c.fetchone()
		if not row:
			break
		#print row[0]
		journo = {}
		journo['prettyname'] = row[0]
		journo['ref'] = row[1]
		journo['id'] = row[2]
		journos.append(journo)
		rawJournoRefs.append(journo['ref'])
	c.close()
		
	print "\nPASS 1/2 - MAIN CHECKING:"		
	# Now, the work checkign each journo:
	journoRefs = []
	for journo in journos:
		sys.stdout.flush() # useful when writing to files
		
		prettyname = journo['prettyname']
		ref = journo['ref']
		id = journo['id']
#		print "Journo: ",ref," ",id," ",prettyname

		# skip journos with no spaces - TODO should probably delete them?
		if not re.search(u' ', prettyname):
#			print "Skipping single-word: ",ref,"=",prettyname
			continue;		

		# Skip journalists known to be different people:
		numberMatch = re.search(u'^(.*?)-(\d)+$', ref)
		rawNameNoNumber = ref
		if numberMatch:
			rawNameNoNumber = numberMatch.group(1)
		if numberMatch or (ref+u'-1' in rawJournoRefs):
#			print "rawNameNoNumber: ",rawNameNoNumber
			# Assumption is that if it the name in the raw form doesn't exist it's a
			#   where we know there's actually two people with the same name:
			# Example: "david rose" doesn't exist, but "david-rose-1" and "david-rose-2" do
			if not rawNameNoNumber in rawJournoRefs:
				print "- Skipping journalists known to be different people: ",ref
				continue;
			# Should leave the multiple "Helene Mulholland"s to get merged later:

		# Now make sure the prettyname, and ref are good:
		newRef = ref # default
		if True:
			# CORRECT PRETTY NAMES:
			newPrettyname = prettyname

			# get rid of apostrophes: (or weird character in database masquerading as such):
			newPrettyname = re.sub("E28099".decode("hex"), '\'', newPrettyname)	#U+02BC

			# treat as unicode:
			newPrettyname = unicode(newPrettyname, 'utf-8')

			# check pretty name is correct, e.g. for Mcmahon:
			newPrettyname = Journo.PrettyCaseName(newPrettyname)

			# no dots after initials: (e.g. should be Gareth A Davies, not Gareth A. Davies, also
			#     get rid of weird characters like < >
			newPrettyname = re.sub('\s*[\.<>]\s*',' ', newPrettyname).strip()

			# get rid of spaces after hyphens:
			newPrettyname = re.sub('- ', '-', newPrettyname)
			# get rid of spaces after O's etc:
			newPrettyname = re.sub('\' ', '\'', newPrettyname)

			# get rid of punctuation on either side:
			newPrettyname = newPrettyname.strip('|.;:,!? ')

			# Warning... might need to merge?
			# get rid of extraneous With and By at the beginning:
			m = re.match(u'(?:(Eco-Worrier|according|with|by) )(.*?)$', newPrettyname, re.UNICODE|re.IGNORECASE)
			if m and m.group(1) and m.group(2):
#				print m.group(1),"+",m.group(2)
				newPrettyname = m.group(2)
			# get rid of extraneous words at the end:
			m = re.match(u'^(.*?)(\'s? sketch|\'s? ?Week| Chief| Science| International| Interview| Stays| Discovers| Reports| Writes)$', newPrettyname, re.UNICODE|re.IGNORECASE)
			if m and m.group(1) and m.group(2):
#				print m.group(1),"+",m.group(2)
				newPrettyname = m.group(1)
				
			# Now get rid of place names after the name if need be, like:
			# | Washington, Beijing, Berlin, Boston, Colombo, Delhi, Dublin
			places = getPlaces()
			for place in places:
				# TODO get rid of accents in pretty name for sake of comparison
				# SLOW MATCH:
#				m = re.search(u'(.*?) '+place+u'$', newPrettyname, re.UNICODE|re.IGNORECASE)
#				if m:
				# faster match:
#				print "<"+newPrettyname[-(len(place)+1):]+">"+place
				if newPrettyname.lower()[-len(place):]==place:
					possibleNewPrettynameUnstripped = newPrettyname[:-len(place)]
					possibleNewPrettyname = possibleNewPrettynameUnstripped.strip()	#m.group(1)
					# only remove without a space if the placename is >=N characters 
					#  (to stop Pritchard -> Prit/Chard and Lively -> Liv/Ely)
					#  also Enfield in Greenfield
					if possibleNewPrettyname==possibleNewPrettynameUnstripped and len(place)<=7:	
#						print "Skipped ",place
						continue
					# only remove the name if we'd leave at least 2 words behind
					# (this stops getting rid of e.g. Hamilton which is a common surname, and also a place)
					#   also surname must be 3 letters or more long (stops Rachel de Thame -> Rachel de)
					if possibleNewPrettyname.find(u' ')!=-1:
						lastName = getRestAndLastNameOf(possibleNewPrettyname,2)
						# sort: Glenn Moorein Moscow
#						print "testing ",possibleNewPrettyname
						if possibleNewPrettyname[-2:]==u'in' and not Journo.IsReasonableLastName(conn,lastName):
							#print "take off in ",possibleNewPrettyname
							possibleNewPrettyname = possibleNewPrettyname[:-2]	# take off the 'in'
							#print "taken off in ",possibleNewPrettyname
						if len(lastName)>3:
							#print "last name ok"
							# actually hardcode... otherwise Paris gets treated as a possible name which is bad:
							if place==u'Wells':#Journo.IsReasonableLastName(conn,place):		# e.g. assume Wells is a surname, not a place (be conservative)
								continue
							print "! Place match        ",newPrettyname.encode('latin-1','replace'),"->",possibleNewPrettyname.encode('latin-1','replace')
							newPrettyname = possibleNewPrettyname

			nameToProcessForRef = newPrettyname

			# could add more from http://en.wikipedia.org/wiki/Title
			# get Journo without prefixes and suffixes:
			m = re.match(u'(?:(sir|lady|dame|prof|founder|chancellor|lieutenant-colonel|lieutenant|colonel|sgt|mr|dr|professor|cardinal|chef) )?(.*?)(?: (mp))?$', newPrettyname, re.UNICODE|re.IGNORECASE)
			assert m, "Can't process journo: "+m
			if m.group(1) or m.group(3):
				newPrettyname = m.group(2)
				if m.group(1):
					newPrettyname = m.group(1)+u" "+newPrettyname
				if m.group(3):
					newPrettyname = newPrettyname+u" "+m.group(3).upper() # capitalise suffixes, like MP
				nameToProcessForRef = m.group(2)

			# Now guess place names for 3-word names, e.g. Stuart Ramsay Nabanga => Stuart Ramsay, Nabanga
			words = nameToProcessForRef.split()
			# (can allow only one match for middle word because this name won't be counted anyway:)
			if len(words)==3 and Journo.IsReasonableLastName(conn,words[1],1) and not Journo.IsReasonableLastName(conn,words[2]):
				print "? Maybe should be shortened? ", newPrettyname

			# Update the firstname and lastname fields:
			parts = nameToProcessForRef.lower().split()
			if len(parts) == 0:
				raise "Empty journo name!"
			elif len(parts) == 1:
				firstname = parts[0]
				lastname = parts[0]
			else:
				firstname = parts[0]
				lastname = parts[-1]
			if Journo.GetFirstName(conn,ref)!=firstname:
				firstnameEscaped = re.sub(u"\'", u'\\\'', firstname, re.UNICODE)
				sqlTxt = u'UPDATE journo SET firstname=\''+firstnameEscaped+u'\' WHERE id=%d' % id
				sqlTxt = sqlTxt.encode('utf-8')
				print u"  + New firstname for",ref,u"->",firstname.encode('latin-1','replace')#,u": ",sqlTxt.encode('latin-1','replace')
				c2 = conn.cursor()
				c2.execute(sqlTxt)
				c2.close()
				if not DEBUG_NO_COMMITS:
					conn.commit()
			if Journo.GetLastName(conn,ref)!=lastname:
				lastnameEscaped = re.sub(u"\'", u'\\\'', lastname, re.UNICODE)
				sqlTxt = u'UPDATE journo SET lastname=\''+lastnameEscaped+u'\' WHERE id=%d' % id
				sqlTxt = sqlTxt.encode('utf-8')
				print u"  + New lastname for ",ref,u"->",lastname.encode('latin-1','replace')#,u": ",sqlTxt.encode('latin-1','replace')
				c2 = conn.cursor()
				c2.execute(sqlTxt)
				c2.close()
				if not DEBUG_NO_COMMITS:
					conn.commit()
			
			
			# FINALLY, TRY FIX THE PRETTY NAME:	
			if newPrettyname!=unicode(prettyname, 'utf-8'):
				#newPrettyname = newPrettyname.encode('utf-8','replace') # 
				newPrettyNameEscaped = re.sub(u"\'", u'\\\'', newPrettyname, re.UNICODE)
				# .encode('utf-8')
				sqlTxt = u'UPDATE journo SET prettyname=\''+newPrettyNameEscaped+u'\' WHERE id=%d' % id
				sqlTxt = sqlTxt.encode('utf-8')
#				print "Basename: ",Journo.BaseRef(newPrettyname)
#				print "newPrettyNameEscaped: ",newPrettyNameEscaped
				print u" + New prettyname for",ref,u"->",newPrettyname.encode('latin-1','replace')#,u": ",sqlTxt.encode('latin-1','replace')
				# TODO Journo.BaseRef(newPrettyname)
				c2 = conn.cursor()
				c2.execute(sqlTxt)
				c2.close()
				if not DEBUG_NO_COMMITS:
					conn.commit()

			# Posit a new ref based on the pretty name:
			#		print nameToProcessForRef
			newRef = Journo.BaseRef(nameToProcessForRef)

		
		# NOW TRY FIX THE REF:
		if newRef!=ref:
			if ref in journoRefs:	# pretty unlikely it's already in there but could be if we've had to merge twice
				journoRefs.remove(ref)
#			print "New ref for",prettyname,":",ref,"->",newRef
			mergeJourno(conn,ref,newRef)
			
#		print journo # journo.encode('utf-8','replace')		
		# convert to unicode (actually it is already, but we need to let python know that)
#		journo = unicode(journo, 'utf-8')

		if not (newRef in journoRefs):
			journoRefs.append(newRef)
		

	
	
	# TODO later: fix up first names and last names		
	journosToTry = [
		'Stephen Jackson',
		'Helene Mulholland',
#		unicodedata.normalize('NFKD',u'He/le/ne Mulholland').encode('ascii','ignore'),
		'Gabriel Rozenberg',
		'Christopher Hopehome',
		'Tom Stuartsmith',
		'Elspeth Thompson',
	]
	print "PASS 2/2 - FUZZY MATCHING:"		
	for ref in journoRefs:
#		print journo
		likes = getCloseMatches(conn,ref,journoRefs)
		if len(likes)>1:
#			print ref,": "
			nextHighestNoOfArticlesWritten = -1
			highestNoOfArticlesWritten = -1
			highestNoOfArticlesWasWrittenBy = None;
			for like in likes:
				journoRefs.remove(like) # don't think about this one again
				
				n = Journo.GetNoOfArticlesWrittenBy(conn, like)
				if n>highestNoOfArticlesWritten:
					nextHighestNoOfArticlesWritten = highestNoOfArticlesWritten
					highestNoOfArticlesWritten = n
					highestNoOfArticlesWasWrittenBy = like
				elif n>nextHighestNoOfArticlesWritten:
					nextHighestNoOfArticlesWritten = n
			if nextHighestNoOfArticlesWritten<=2:
				for like in likes:
					if like==highestNoOfArticlesWasWrittenBy:
						continue;
					mergeJourno(conn,like,highestNoOfArticlesWasWrittenBy)
#				print "+ ",like," ",n
	
	return 0
	
if __name__ == "__main__":
    sys.exit(main())

