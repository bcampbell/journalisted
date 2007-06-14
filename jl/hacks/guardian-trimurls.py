#!/usr/bin/env python2.4
#
# trim off "?gusrc=rss..." cruft from end of guardian and observer urls.
#

import sys
import re

sys.path.append( "../pylib" )
from JL import DB,Byline,Journo


def TrimURLs():
	conn = DB.Connect()

	urlpat = re.compile( "(.*/.*[.]html)[?]gusrc=.*?$" )

	c1= conn.cursor()
	c1.execute( "SELECT id,title,srcurl,permalink,srcid FROM article WHERE (srcorg=4 OR srcorg=11)" )
	cnt = 0;
	c2 = conn.cursor()
	while 1:
		row = c1.fetchone()
		if not row:
			break
		id = row['id']
		title = row['title']
		srcurl = row['srcurl']
		permalink = row['permalink']
		if srcurl != permalink:
			raise Exception, "srcurl != permalink (id %s)"%(id)
		m = urlpat.search( srcurl )
		if m:
			newurl = m.group(1)
			c2.execute( "UPDATE article SET srcurl=%s, permalink=%s WHERE id=%s", newurl,newurl, id )
			cnt = cnt + 1
	conn.commit()
	print "updated urls for " + str(cnt) + " articles."


TrimURLs()


