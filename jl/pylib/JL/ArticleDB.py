import sys
import re

import DB


class Error(Exception):
	pass

class FieldNotUnicodeError(Error):
	def __init__(self, fieldname):
		self.fieldname = fieldname
	def __str__(self):
		return repr(self.fieldname)


class ArticleDB:
	"""Interface to Articles database

	Fields passed for an article:
	permalink - permalink to original article (hopefully!)
	pubdate - when article was posted (datetime)
	title - headline (raw text, no HTML)
	content - main text (basic HTML, escaped)
	description - summary of article (raw text, no HTML)
	byline - full byline text (optional, raw text, no HTML)
	srcorgname - which organisation published the article
	srcid - unique identifier within organisation (eg url)
	firstseen -
	lastseen -
	text, title, content, description, byline should all be unicode
	"""

	def __init__(self):
		self.conn = DB.Connect()

		c = self.conn.cursor()
		c.execute( "SELECT id,shortname FROM organisation" )
		self.orgmap = {}
		while 1:
			row=c.fetchone()
			if not row:
				break
			self.orgmap[ row[1] ] = row[0]

	def Add( self, art ):
		"""Store an article in the DB

		returns id of newly-added article
		"""

		CheckArticle( art )

		# send text to the DB as utf-8
		title = art['title'].encode( 'utf-8' )
		byline = art[ 'byline' ].encode( 'utf-8' )
		description = art['description'].encode( 'utf-8' )
		pubdate = "%s" %(art['pubdate'])
		lastscraped = "%s" % (art['lastscraped'])
		lastseen = "%s" % (art['lastseen'])
		firstseen = lastseen	# it's a new entry
		content = art['content'].encode( 'utf-8' )
		srcurl = art['srcurl']
		permalink = art['permalink']
		srcorg = self.orgmap[ art[ 'srcorgname' ] ]
		srcid = art['srcid']

		# send to db!
		cursor = self.conn.cursor()
		q = 'INSERT INTO article (title, byline, description, lastscraped, pubdate, firstseen, lastseen, content, permalink, srcurl, srcorg, srcid) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)'
		cursor.execute( q, ( title, byline, description, lastscraped, pubdate, firstseen, lastseen, content, permalink, srcurl, srcorg, srcid ) )

		cursor.execute( "select currval('article_id_seq')" )
		id = cursor.fetchone()[0]
		cursor.close()
		self.conn.commit()

		# TODO: rollback on error!
		return id


	def ArticleExists( self, srcorgname, srcid ):
		"""returns non-zero if article is already in the DB"""

		srcorg = self.orgmap[ srcorgname ]
		cursor = self.conn.cursor()
		q = 'SELECT count(*) FROM article WHERE srcorg=%s AND srcid=%s'
		cursor.execute( q, ( srcorg, srcid ) )
		r = cursor.fetchone()[0]
		cursor.close()

		return r


	def FindJournalist( self, journo ):
		cursor = self.conn.cursor()
		q = 'SELECT id FROM journalists WHERE name=%s'
		cursor.execute( q, ( journo ) )
		row = cursor.fetchone()
		id = None
		if row:
		#	print "journo exists! ('%s')" % (journo)
			id = row[0]
		cursor.close()
		return id

	def AddJournalist( self, fullname ):
		cursor = self.conn.cursor()
		q = 'INSERT INTO journalists (fullname) values (%s)'
		cursor.execute( q, ( fullname ) )
		id = cursor.lastrowid
		cursor.close()

		#print "add journalist '%s' (%s)" % (name, id)
		return id

	def AttributeArticle( self, journo_id, article_id ):
		cursor = self.conn.cursor()
		q = 'INSERT INTO attribution (journalist_id,article_id) VALUES(%s,%s)'
		cursor.execute( q, (journo_id, article_id) )
		cursor.close()


class DummyArticleDB:
	"""stub for testing"""

	def __init__(self):
		self.id = 1

	def Add( self, art ):
		CheckArticle( art )
		artid = self.id
		self.id = artid + 1
		return artid

	def ArticleExists( self, srcorgname, srcid ):
		return 0


tagpat = re.compile( "<.*?>", re.UNICODE )
entpat = re.compile( "&(([a-zA-Z0-9]+)|([0-9]+)|([xX][0-9a-fA-F]));", re.UNICODE )

def CheckArticle(art):

	# make sure assorted fields are unicode
	for f in ( 'title', 'byline', 'description',
			'content', 'permalink', 'srcurl','srcid' ):
		if not isinstance( art[f], unicode ):
			raise FieldNotUnicodeError(f)

	# check title and byline are single-line
	for f in ( 'title','byline' ):
		s = art[f]
		if s != s.strip():
			raise Exception, ( "%s has leading/trailing whitespace" % (f) )
		if s.find("\n") != -1:
			raise Exception, ( "multi-line %s" % (f) )

	# check for miisng/blank fields
	for f in ('title','description','content' ):
		s= art[f]
		if s.strip() == '':
			raise Exception, ( "missing '%s' field!" % (f) )

	# check for unwanted html tags & entities
	for f in ( 'title','byline','description' ):
		s = art[f]
		if entpat.search( s ):
			raise Exception, ( "%s contains html entities" % (f) )
		if tagpat.search( s ):
			raise Exception, ( "%s contains html tags" % (f) )


