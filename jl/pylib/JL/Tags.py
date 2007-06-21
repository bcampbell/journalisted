#
# Tags - cheesy term-extraction until we get something better.
#

import re

blacklist = [
	'that',
	'there',
	'these',
	'they',
	'this',
	'what',
	'when',
	'while',
	'with',
	'according',
	'after',
	'although',
	'however',
	'more',
	'many',
	'last',
	'here',
	]


def ExtractFromText( txt ):

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

		if t.lower() in blacklist:
			continue

		# trim off any title prefixes (mr, mrs, ms, dr)
		t = prefixpat.sub( u'', t )

		tags[t] = tags.get(t,0) + 1

	return tags

