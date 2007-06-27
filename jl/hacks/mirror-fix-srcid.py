#!/usr/bin/env python2.4
#
# hack to fix up mirror/sunday mirror srcid.
# We were storing entire URL, but some dupes crept in.
#
# This script should fix all the srcids.
#
# After urls are trimmed, this SQL will return all the dupes
# to delete (keeps the ones with the highest article id):
#
# SELECT distinct bad.id FROM article AS good INNER JOIN article AS bad ON good.srcid=bad.srcid AND good.srcorg=bad.srcorg AND (good.srcorg=5 OR good.srcorg=12) AND bad.id<good.id;
#
# then:
# DELETE FROM article WHERE id IN (SELECT........)
# to actually delete them!
 


import sys
import re

sys.path.append( "../pylib" )
from JL import DB,Byline,Journo



def FixSrcIDs():
	# trim srcids down from whole url to just unique id part ("objectid=nnnnnnn")
	# some articles have the stupid mediafed.com redirect url (DOH!), so we'll just
	# use the mediafed id in that case.

	conn = DB.Connect()

	idpat = re.compile( """%26objectid=([0-9]+)%26""" )

	# backup extractor for ones with the dodgy mediafed.com urls...
	idpat2 = re.compile( """link=([0-9a-f]+)$""" )

	c1= conn.cursor()
	# 5=mirror, 12=sundaymirror
	c1.execute( "SELECT id,title,srcorg,srcid FROM article WHERE (srcorg=5 OR srcorg=12)" )
	cnt = 0;
	while 1:
		row = c1.fetchone()
		if not row:
			break
		id = row[0]
		srcid = row[3]

		m = idpat.search( srcid )
		newsrcid = None	
		if m:
			newsrcid = m.group(1)
		else:
			# treat as crappy mediafed.com redirect url
			m=idpat2.search( srcid )
			if m:
				newsrcid = 'mediafed_'+m.group(1)

		if not newsrcid:
			raise Exception, "Couldn't get srcid out of '%s'" %(srcid)
	
		print newsrcid
		c2 = conn.cursor()
		c2.execute( "UPDATE article SET srcid=%s WHERE id=%s", newsrcid, id )
		cnt = cnt + 1
	conn.commit()
	print "updated srcid for " + str(cnt) + " articles."


FixSrcIDs()




