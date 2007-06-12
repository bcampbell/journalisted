#!/usr/bin/env python2.4
#
#
#

import sys
import re

sys.path.append( "../pylib" )
from JL import DB,Byline,Journo



def TrimSrcIDs():
	conn = DB.Connect()

	idpat = re.compile( ".*[/](.*)[.]html$" )

	c1= conn.cursor()
	c1.execute( "SELECT id,title,srcorg,srcid FROM article WHERE (srcorg=4 OR srcorg=11)" )
	cnt = 0;
	while 1:
		row = c1.fetchone()
		if not row:
			break
		id = row[0]
		srcid = row[3]

		m = idpat.search( srcid )
		newsrcid = m.group(1)

		c2 = conn.cursor()
		c2.execute( "UPDATE article SET srcid=%s WHERE id=%s", newsrcid, id )
		cnt = cnt + 1
	conn.commit()
	print "updated srcid for " + str(cnt) + " articles."


def FindDupeSrcIDs():
	conn = DB.Connect()
	c = conn.cursor()
	c.execute( "SELECT srcid, srcorg, COUNT(*) FROM article GROUP BY srcid, srcorg HAVING COUNT(*)>1 AND (srcorg=4 OR srcorg=11)" )

	dupes = []
	while 1:
		row = c.fetchone()
		if not row:
			break
		dupes.append( { 'srcid': row[0],
			'srcorg': row[1],
			'dupecnt': row[2] } )
	return dupes


# make sure all the dupes with matching srcid and srcorg are the same
def CheckDupe( srcid, srcorg ):
	conn = DB.Connect()
	c = conn. cursor()
	c.execute( "SELECT id, lastscraped,srcorg, title, byline, description, content FROM article WHERE srcid=%s and srcorg=%s ORDER BY lastscraped DESC", srcid, srcorg );

	dupes = c.fetchall()

	first = dupes[0]
	for d in dupes[1:]:
		for field in ( 'title','byline'):	#, 'description', 'content' ):
			if first[field] != d[field]:
				print "=======\n%s differs (%s)" % (field,srcid)
				for r in dupes:
					print "%s (%s): %s" % (r['id'],r['lastscraped'],r[field])
#				raise Exception, 'Dupes have differing %s field (srcid=%s' % (field,srcid)
#		print d['srcorg'],d['id'], d['title']


def DeDupe( srcid, srcorg, mode='dryrun' ):
	conn = DB.Connect()
	c = conn.cursor()
	c.execute( "SELECT id, lastscraped,srcorg, title, byline, description, content FROM article WHERE srcid=%s and srcorg=%s ORDER BY lastscraped DESC", srcid, srcorg );

	dupes = c.fetchall()

	master = dupes[0]
	print "%s: %s (%s) [%s]" % ( master['id'], master['title'], master['byline'], master['lastscraped'] )

	c2 = conn.cursor()
	for d in dupes[1:]:
		c2.execute( "UPDATE article SET status='h' WHERE id=%s", d['id'] )
		c2.execute( "INSERT INTO article_dupe (article_id,dupeof_id) VALUES (%s,%s)", d['id'], master['id'] )
		conn.commit();
		print " =>DUPE %s: %s (%s) [%s]" % ( d['id'], d['title'], d['byline'], d['lastscraped'] )
	print


TrimSrcIDs()

dupes = FindDupeSrcIDs()
for d in dupes:
	DeDupe(d['srcid'],d['srcorg'] )



