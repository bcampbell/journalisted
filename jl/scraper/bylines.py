#!/usr/bin/env python

import MySQLdb
import ukmedia


conn = MySQLdb.connect(
	host = 'localhost',
	user = 'root',
	passwd = '',
	db = 'ukmedia' )

store = ukmedia.ArticleDBStore()

failed = 0
successful = 0
empty = 0


cursor = conn.cursor()

cursor.execute( 'DELETE FROM attributions' )
cursor.execute( 'DELETE FROM journalists' )

cursor.execute( 'SELECT id, byline FROM articles' )
articlecount = cursor.rowcount
while 1:
	row = cursor.fetchone()
	if row == None:
		break

	article_id = row[0]
	byline = row[1]
	if byline == None:
		empty = empty+1
		continue

	b = ukmedia.CrackByline( byline )
#	print byline
	if not b:
		failed = failed + 1
		print "FAILED: '%s'" % (byline)
	else:
		successful = successful + 1
		for foo in b:
			if foo.has_key('name'):
				journalist_id = store.FindJournalist( foo['name'] )
				if journalist_id == None:
					journalist_id = store.AddJournalist( foo['name'] )

				store.AttributeArticle( journalist_id, article_id )
#		print b
#	print

cursor.close()
print "%d failed, %d successful, %d empty, out of %d" % (failed,successful,empty,articlecount)

