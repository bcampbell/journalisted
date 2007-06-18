import re

# byline cracking

# n=name
# l=location
# t=jobtitle
# a=agency
# s=subject (eg "editor's briefing", "cricket")
# e=email address
bylinecrackers = [
	{ 'fmt': '(nl)(nl)', 'pat': '(?:by |from |)(.+) in (.+) and (.+) in (.+)$' },
	{ 'fmt': '(nl)(n)', 'pat': '(?:by |from |)(.+) in (.+) and (.+)$' },
	{ 'fmt': '(n)(n)(n)(n)', 'pat': '(?:by |from |)(.+), (.+), (.+) and (.+)$' },
	{ 'fmt': '(n)(n)(n)', 'pat': '(?:by |from |)(.+), (.+) and (.+)$' },
	{ 'fmt': '(n)(n)(nl)', 'pat': '(?:by |from |)(.+), (.+) and (.+) in (.+)$' },
	{ 'fmt': '(n)(n)', 'pat': '(?:by |from |)(.+) and (.+)$' },
	# note: ignore email addr here as we don't know which person it's for
	{ 'fmt': '(n)(n)', 'pat': '(?:by |from |)(.+) and (\S+ \S+) ([A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4})$' },
	{ 'fmt': '(n)(nl)', 'pat': '(?:by |from |)(.+) and (.+) in (.+)$' },
	{ 'fmt': '(nal)', 'pat': '(?:by |from |)(.+), of (.+), in (.+)$' },
	{ 'fmt': '(nl)', 'pat': '(?:by |from |)(.+?)[,]? (?:in|at|reports from) (.+)$' },
	{ 'fmt': '(nt)', 'pat': '(?:by |from |)(.+?), (.+)$' },
	{ 'fmt': '(nte)', 'pat': """(?:by |from |)(\S+ \S+) (.+) ([A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4})""" },
	{ 'fmt': '(ne)', 'pat': """(?:by |from |)(.+) ([A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4})""" },
	{ 'fmt': '(n)(na)', 'pat': '(?:by |from |)(.+?) and (.+?), (.+)$' },
	{ 'fmt': '(na)', 'pat': '(?:by |from |)(.+?), (.+)$' },
	{ 'fmt': '(ns)', 'pat': '(?:by |from |)(.+?): (.+)$' },
	{ 'fmt': '(sn)', 'pat': '(?:by |from |)(.+?): (.+)$' },
	{ 'fmt': '(ntl)', 'pat': '(?:by |from |)(\S+ \S+?) (.+) in (.+)$' },
	{ 'fmt': '(ntl)', 'pat': '(?:by |from |)(\S+ \S+?) (.+), in (.+)$' },
#	{ 'fmt': '(nta)', 'pat': '(?:by |from |)(\S+ \S+?)[,]? (.+), (.+)$' },
	{ 'fmt': '(nt)', 'pat': '(?:by |from |)(\S+ \S+?)\s+(\S+ \S+)$' },
	{ 'fmt': '(nt)', 'pat': '(?:by |from |)(.+?): (.+)$' },
	{ 'fmt': '(nl)', 'pat': '(?:by |from |)(.+?), (.+)$' },
	{ 'fmt': '(nl)', 'pat': '(?:by |from |)(.+?)[:,] our man in (.+)$' },
	{ 'fmt': '(n)(nl)', 'pat': '(?:by |from |)(.+?) and (.+?), (.+)$' },
	{ 'fmt': '(a)', 'pat': '(?:by |from |)(.+)$' },
	{ 'fmt': '(t)', 'pat': '(?:by |from |)(.+)$' },
	{ 'fmt': '(n)', 'pat': """(?:by |from |)(.+)$""" },
	{ 'fmt': '(n)', 'pat': """.*?\s+(?:by|from)\s+(.+)$""" },
	]

for b in bylinecrackers:
	b['pat'] = re.compile( b['pat'], re.UNICODE|re.IGNORECASE )


#subject/column names
subjectpats = [
	re.compile( """\\bcommentary\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bbriefing\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bnotebook\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bdiary\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bview\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bviewpoint\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\banalysis\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bfeedback\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bcomment\\b""", re.IGNORECASE|re.UNICODE ),
	# these ones are pretty specific to various columns/blogs...
	re.compile( """\\bweather eye\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\btempus\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bthunderer\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bparliamentary sketch\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bcredo\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bmy week\\b""", re.IGNORECASE|re.UNICODE ),
	]

agencypats = [
	re.compile( """the mail on sunday""", re.IGNORECASE|re.UNICODE ),
	re.compile( """the times""", re.IGNORECASE|re.UNICODE ),
	re.compile( """associated press""", re.IGNORECASE|re.UNICODE ),
	re.compile( """press association""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bap\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bpa\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """bbc news""", re.IGNORECASE|re.UNICODE ),
	re.compile( """bbc scotland""", re.IGNORECASE|re.UNICODE ),
	re.compile( """bbc wales""", re.IGNORECASE|re.UNICODE ),
	re.compile( """sunday telegraph""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bmirror[.]co[.]uk\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bagencies\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bagences\\b""", re.IGNORECASE|re.UNICODE ),
	re.compile( """\\bexpress.co.uk\\b""", re.IGNORECASE|re.UNICODE ),
	]

jobtitlepats = [
	re.compile( """editor$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """reporter$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """correspondent$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """corespondent$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """director$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """writer$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """commentator$""", re.IGNORECASE|re.UNICODE ),
	re.compile( """nutritionist""", re.IGNORECASE|re.UNICODE ),
	]

emailpat = re.compile( """\\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\\b""", re.UNICODE )

def CrackByline( byline ):
	if not isinstance( byline, unicode ):
		raise Exception, "byline not unicode"

	byline = byline.strip()

	# compress whitespace
	byline = u' '.join( byline.split() )

#	print "---------------------"
#	print byline
	for cracker in bylinecrackers:
		pat = cracker['pat']

#		print "try %s" %(cracker['fmt'])
		m = pat.match( byline )
		if not m:
			continue

#		print "match!"

		ret = []
		fmt = cracker['fmt']
		idx=1
		skip = False
		person = None
		for f in fmt:
			if f=='(':
				# tentatively start on a new person
				person = {}
				continue
			if f==')':
				# end-of-person
				# drop any entries without a name (eg "pa" "by ONLINE REPORTER" etc...)
				if person.has_key('name'):
					ret.append( person )
				person = None
				continue

			if f=='n':
				# sanity check
				nm = m.group(idx).strip()
				if not CouldBeName( nm ):
					skip = True
					#print "(fmt %s) reject name %s" % (fmt,nm)
					break
				person['name'] = nm

			if f=='l':
				person['loc'] = m.group(idx).strip()

			if f=='t':
				title = m.group(idx).strip()
				if not IsJobTitle( title ):
				#	print "(fmt %s) reject title %s" % (fmt,title)
					skip = True
					break
				person['title'] = title

			if f=='a':
				agency = m.group(idx).strip()
				if not IsAgency(agency):
					skip = True
					break
				person['agency'] = agency

			if f=='s':
				subj = m.group(idx).strip()
				if not IsSubject(subj):
					skip = True
					break
				person['subject'] = subj

			if f=='e':
				# email address
				person['email'] = m.group(idx)

			idx = idx +1

		if not skip:
			return ret

	# no matches
	return None





# return true if title not obviously bogus
def IsJobTitle( title ):
	for p in jobtitlepats:
		if p.search( title ):
			return True
	return False


# return false if name obviously bogus
def CouldBeName( nm ):
	numparts = len( nm.split() )
	if numparts>3 or numparts<2:
		return False
	if IsJobTitle( nm ):
		return False
	if IsSubject( nm ):
		return False
	if IsAgency( nm ):
		return False
	if emailpat.search( nm ):
		return False
	
	# check for stuff that shouldn't be in names
	for c in nm:
		if c in u',:@0123456789':
			return False
	if re.search( """\\b(a|by|and|the|staff|in)\\b""", nm, re.UNICODE|re.IGNORECASE ):
		return False

	return True



# Return true if agency is definitely an agency
def IsAgency( agency ):
	for a in agencypats:
		if a.search( agency ):
			return True
	return False




def IsSubject( subj ):
	"""Return true if subj is a subject (eg "editors briefing")"""
	for s in subjectpats:
		if s.search( subj ):
			return True
	return False


