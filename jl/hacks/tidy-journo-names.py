#!/usr/bin/env python2.4
#
# Tidy the database by merging journalists with similar names
#
import sys
import unicodedata
import re
sys.path.append("../pylib")
sys.path.append("../../pylib")
from JL import DB
from JL import Journo
from JL import Tags

DEBUG_NO_COMMITS = False



	

def main():
#	print getPlaces()
#	return 0
	
	conn = DB.Connect()

	# Some unit tests:
	if False: # True:  # True
		print Journo.FindJournoMultiple(conn, "David Rose")
		
		print "IsReasonableLastName('Ramsay')", Journo.IsReasonableLastName(conn,'Ramsay',1)
		print "IsReasonableLastName('Nabanga')",Journo.IsReasonableLastName(conn,'Nabanga')
		# Now guess place names for 3-word names, e.g. Stuart Ramsay Nabanga => Stuart Ramsay, Nabanga
		newPrettyname = u'Stuart Ramsay Nabanga'
		words = newPrettyname.split()
		if len(words)==3 and Journo.IsReasonableLastName(conn,words[1]) and not Journo.IsReasonableLastName(conn,words[2]):
			print "Maybe should be shortened? ", newPrettyname

		print "getFirstNameAndRestOf(\'James\',1) = ",Journo.getFirstNameAndRestOf('James',1)
		print "getFirstNameAndRestOf(\'James Bernard Shaw\',1) = ",Journo.getFirstNameAndRestOf('James Bernard Shaw',1)
		print "getFirstNameAndRestOf(\'James Bernard Shaw\',2) = ",Journo.getFirstNameAndRestOf('James Bernard Shaw',2)
		print "getRestAndLastNameOf( \'James Bernard Shaw\',1) = ",Journo.getRestAndLastNameOf( 'James Bernard Shaw',1)
		print "getRestAndLastNameOf( \'James Bernard Shaw\',2) = ",Journo.getRestAndLastNameOf( 'James Bernard Shaw',2)
		
		print "IsReasonableFirstName('sam')",Journo.IsReasonableFirstName(conn,'sam')
		print "IsReasonableFirstName('amy')",Journo.IsReasonableFirstName(conn,'amy')
		print "IsReasonableFirstName('bob')",Journo.IsReasonableFirstName(conn,'bob')
		print "IsReasonableFirstName('rob')",Journo.IsReasonableFirstName(conn,'rob')
		print "IsReasonableFirstName('lob')",Journo.IsReasonableFirstName(conn,'lob')
		print "IsReasonableLastName('davies')",Journo.IsReasonableLastName(conn,'davies')
		print "IsReasonableLastName('davis')",Journo.IsReasonableLastName(conn,'davis')
		print "IsReasonableLastName('zavis')",Journo.IsReasonableLastName(conn,'zavis')
		
		
		print Journo.getCloseMatches(conn,'amy-turner',['sam-turner'])
		print Journo.getCloseMatches(conn,'sam-turner',['amy-turner'])
		
		return 0
		
		print Journo.getCloseMatches(conn,'bob smith',['bob smith'])
		print Journo.getCloseMatches(conn,'bob smith',['rob smith'])#false
		print Journo.getCloseMatches(conn,'rob davies',['rob davis'])#false
		print Journo.getCloseMatches(conn,'rob davis',['rob davies'])#false
		print Journo.getCloseMatches(conn,'rob lavis',['rob lavies'])#true
		print "Journo.getCloseMatches(abdul qodous): ",Journo.getCloseMatches(conn,'abdul qodous',['abdul qudoos'])#true

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
		sys.stderr.flush() # useful when writing to files
		
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
			newPrettyname = Journo.GetPrettyNameFromRawName(conn, prettyname)
			nameToProcessForRef = Journo.StripPrefixesAndSuffixes(newPrettyname)

			# TODO:
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
			Journo.MergeJourno(conn,ref,newRef)
			
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
		likes = Journo.getCloseMatches(conn,ref,journoRefs)
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
					Journo.MergeJourno(conn,like,highestNoOfArticlesWasWrittenBy)
#				print "+ ",like," ",n
	
	return 0
	
if __name__ == "__main__":
    sys.exit(main())
