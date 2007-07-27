#
# Tags - cheesy term-extraction until we get something better.
#

import re

import DB
import ukmedia

#blacklist = [
#	'that',
#	'there',
#	'these',
#	'they',
#	'this',
#	'what',
#	'when',
#	'while',
#	'with',
#	'according',
#	'after',
#	'although',
#	'however',
#	'more',
#	'many',
#	'last',
#	'here',
#	'just',
#	]


def GetBlacklist( conn ):
	"""get the list of banned tags from the db"""

	c = conn.cursor()
	c.execute( "SELECT bannedtag FROM tag_blacklist" )

	blacklist = []
	while 1:
		r = c.fetchone()
		if not r:
			break
		tag = r[0].decode('utf-8')
		blacklist.append( tag )

	c.close()
	return blacklist


def ExtractFromText( txt, blacklist, synonyms ):

	# extract phrases with the first letter of each word capitalised,
	# but not at the beginning of a sentance.
	tagpat = re.compile( u'[^.\\s]\\s*(([A-Z]\\w+)(\\s+([A-Z]\\w+))*)', re.UNICODE|re.DOTALL )

	# prefixes we'll trim off
	prefixpat = re.compile( u'^(mr|dr|ms|mrs)\\s+',re.UNICODE|re.IGNORECASE )

	# calculate tags using noddy Crapitisation algorithm
	tags = {}
	for m in tagpat.findall(txt):
		# compress whitespace
		t = ' '.join( m[0].split() )
		# ignore short tags unless they look like acronymns
		if len(t)<=3 and t != t.upper():
			continue

		t = t.lower()
		if t in blacklist:
			continue

		# trim off any title prefixes (mr, mrs, ms, dr)
		t = prefixpat.sub( u'', t )

		# is there a synonym to remap this key?
		if synonyms.has_key(t):
			t=synonyms[t]

		tags[t] = tags.get(t,0) + 1

	return tags


def Generate( conn, article_id, article_content ):
	""" Generate tags for an article """

	# TODO: module should cache blacklist & synonyms
	blacklist = GetBlacklist( conn )
	synonyms = {}

	txt = ukmedia.StripHTML( article_content )

	tags = ExtractFromText( txt, blacklist, synonyms )

	# write the tags into the DB
	c2 = conn.cursor()
	for tag,freq in tags.items():
		tag = tag.encode('utf-8')
		c2.execute( "INSERT INTO article_tag (article_id, tag, freq) VALUES (%s,%s,%s)",
			article_id, tag, freq )
	c2.close()

