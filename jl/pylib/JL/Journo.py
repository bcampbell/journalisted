import DB

# table to convert various latin accented chars into rough ascii
# equivalents
xlate_delatinise = {
	u'\N{LATIN CAPITAL LETTER A WITH ACUTE}': u'A',
	u'\N{LATIN CAPITAL LETTER A WITH CIRCUMFLEX}': u'A',
	u'\N{LATIN CAPITAL LETTER A WITH DIAERESIS}': u'A',
	u'\N{LATIN CAPITAL LETTER A WITH GRAVE}': u'A',
	u'\N{LATIN CAPITAL LETTER A WITH RING ABOVE}': u'A',
	u'\N{LATIN CAPITAL LETTER A WITH TILDE}': u'A',
	u'\N{LATIN CAPITAL LETTER AE}': u'Ae',
	u'\N{LATIN CAPITAL LETTER C WITH CEDILLA}': u'C',
	u'\N{LATIN CAPITAL LETTER E WITH ACUTE}': u'E',
	u'\N{LATIN CAPITAL LETTER E WITH CIRCUMFLEX}': u'E',
	u'\N{LATIN CAPITAL LETTER E WITH DIAERESIS}': u'E',
	u'\N{LATIN CAPITAL LETTER E WITH GRAVE}': u'E',
	u'\N{LATIN CAPITAL LETTER ETH}': u'Th',
	u'\N{LATIN CAPITAL LETTER I WITH ACUTE}': u'I',
	u'\N{LATIN CAPITAL LETTER I WITH CIRCUMFLEX}': u'I',
	u'\N{LATIN CAPITAL LETTER I WITH DIAERESIS}': u'I',
	u'\N{LATIN CAPITAL LETTER I WITH GRAVE}': u'I',
	u'\N{LATIN CAPITAL LETTER N WITH TILDE}': u'N',
	u'\N{LATIN CAPITAL LETTER O WITH ACUTE}': u'O',
	u'\N{LATIN CAPITAL LETTER O WITH CIRCUMFLEX}': u'O',
	u'\N{LATIN CAPITAL LETTER O WITH DIAERESIS}': u'O',
	u'\N{LATIN CAPITAL LETTER O WITH GRAVE}': u'O',
	u'\N{LATIN CAPITAL LETTER O WITH STROKE}': u'O',
	u'\N{LATIN CAPITAL LETTER O WITH TILDE}': u'O',
	u'\N{LATIN CAPITAL LETTER THORN}': u'th',
	u'\N{LATIN CAPITAL LETTER U WITH ACUTE}': u'U',
	u'\N{LATIN CAPITAL LETTER U WITH CIRCUMFLEX}': u'U',
	u'\N{LATIN CAPITAL LETTER U WITH DIAERESIS}': u'U',
	u'\N{LATIN CAPITAL LETTER U WITH GRAVE}': u'U',
	u'\N{LATIN CAPITAL LETTER Y WITH ACUTE}': u'Y',
	u'\N{LATIN SMALL LETTER A WITH ACUTE}': u'a',
	u'\N{LATIN SMALL LETTER A WITH CIRCUMFLEX}': u'a',
	u'\N{LATIN SMALL LETTER A WITH DIAERESIS}': u'a',
	u'\N{LATIN SMALL LETTER A WITH GRAVE}': u'a',
	u'\N{LATIN SMALL LETTER A WITH RING ABOVE}': u'a',
	u'\N{LATIN SMALL LETTER A WITH TILDE}': u'a',
	u'\N{LATIN SMALL LETTER AE}': u'ae',
	u'\N{LATIN SMALL LETTER C WITH CEDILLA}': u'c',
	u'\N{LATIN SMALL LETTER E WITH ACUTE}': u'e',
	u'\N{LATIN SMALL LETTER E WITH CIRCUMFLEX}': u'e',
	u'\N{LATIN SMALL LETTER E WITH DIAERESIS}': u'e',
	u'\N{LATIN SMALL LETTER E WITH GRAVE}': u'e',
	u'\N{LATIN SMALL LETTER ETH}': u'th',
	u'\N{LATIN SMALL LETTER I WITH ACUTE}': u'i',
	u'\N{LATIN SMALL LETTER I WITH CIRCUMFLEX}': u'i',
	u'\N{LATIN SMALL LETTER I WITH DIAERESIS}': u'i',
	u'\N{LATIN SMALL LETTER I WITH GRAVE}': u'i',
	u'\N{LATIN SMALL LETTER N WITH TILDE}': u'n',
	u'\N{LATIN SMALL LETTER O WITH ACUTE}': u'o',
	u'\N{LATIN SMALL LETTER O WITH CIRCUMFLEX}': u'o',
	u'\N{LATIN SMALL LETTER O WITH DIAERESIS}': u'o',
	u'\N{LATIN SMALL LETTER O WITH GRAVE}': u'o',
	u'\N{LATIN SMALL LETTER O WITH STROKE}': u'o',
	u'\N{LATIN SMALL LETTER O WITH TILDE}': u'o',
	u'\N{LATIN SMALL LETTER SHARP S}': u'ss',
	u'\N{LATIN SMALL LETTER THORN}': u'th',
	u'\N{LATIN SMALL LETTER U WITH ACUTE}': u'u',
	u'\N{LATIN SMALL LETTER U WITH CIRCUMFLEX}': u'u',
	u'\N{LATIN SMALL LETTER U WITH DIAERESIS}': u'u',
	u'\N{LATIN SMALL LETTER U WITH GRAVE}': u'u',
	u'\N{LATIN SMALL LETTER Y WITH ACUTE}': u'y',
	u'\N{LATIN SMALL LETTER Y WITH DIAERESIS}': u'y',
}


def BaseRef( prettyname ):
	"""Generate reference for a journo, suitable as a URL part
	
	eg u"Fred Blogs" => u"fred-blogs"
	Mapping is not guaranteed to be unique
	"""

	ref = u''
	# translate european accented chars into ascii equivalents and
	# remove anything else that we don't want in our url.
	for ch in prettyname:
		if xlate_delatinise.has_key( ch ):
			ch = xlate_delatinise[ch]
		elif ch.lower() not in u'abcdefghijklmnopqrstuvwxyz ':
			ch = u'' 	# drop all other non-numeric-or-space chars
		ref += ch

	ref = ref.lower()
	ref = ref.strip()
	# replace spaces with hyphens
	ref = u'-'.join( ref.split() )
	return ref


def GenerateUniqueRef( conn, prettyname ):
	"""Generate a unique ref string for a journalist"""

	ref = BaseRef( prettyname )
	q = conn.cursor()
	q.execute( "SELECT id FROM journo WHERE ref=%s", ref )
	if not q.fetchone():
		q.close()
		return ref
	i = 1
	while 1:
		candidate = u'%s-%d' %(ref,i)
		q.execute( "SELECT id FROM journo WHERE ref=%s", candidate )
		if not q.fetchone():
			q.close()
			return candidate
		i = i + 1


def DefaultAlias( rawname ):
	""" compress whitespace, strip leading/trailing space, lowercase """
	alias = rawname.strip()
	alias = u' '.join( alias.split() )
	alias = alias.lower()
	return alias;


def FindJournoMultiple( conn, rawname ):
	alias = DefaultAlias(rawname)
	c = conn.cursor()
	c.execute( "SELECT journo_id FROM journo_alias WHERE alias=%s", alias.encode('utf-8') ) 
	found = []
	while 1:
		row = c.fetchone()
		if not row:
			break
		found.append( row[0] )

	c.close()
	return found


def FindJourno( conn, rawname ):
	found = FindJournoMultiple( conn, rawname )
	if len(found) > 1:
		raise Exception, "Multiple journos found matching '%s'"%(alias)

	if found:
		return found[0]
	else:
		return None


def CreateNewJourno( conn, rawname ):
	alias = DefaultAlias( rawname )
	prettyname = rawname.title()
#	(firstname,lastname) = prettyname.split(None,1) 

	parts = prettyname.lower().split()
	if len(parts) == 0:
		raise "Empty journo name!"
	elif len(parts) == 1:
		firstname = parts[0]
		lastname = parts[0]
	else:
		firstname = parts[0]
		lastname = parts[-1]

	ref = GenerateUniqueRef( conn, prettyname )

	# TODO: maybe need to filter out some chars from ref?
	q = conn.cursor()
	q.execute( "select nextval('journo_id_seq')" )
	(journo_id,) = q.fetchone()
	q.execute( "INSERT INTO journo (id,ref,prettyname,lastname,"
			"firstname) VALUES (%s,%s,%s,%s,%s)",
			journo_id,
			ref.encode('utf-8'),
			prettyname.encode('utf-8'),
			lastname.encode('utf-8'),
			firstname.encode('utf-8') )
	q.execute( "INSERT INTO journo_alias (journo_id,alias) VALUES (%s,%s)",
			journo_id,
			alias.encode('utf-8') )
	q.close()
	return journo_id


def AttributeArticle( conn, journo_id, article_id ):
	""" add a link to say that a journo wrote an article """

	q = conn.cursor()
	q.execute( "SELECT article_id FROM journo_attr WHERE journo_id=%s AND article_id=%s", journo_id, article_id )
	if not q.fetchone():
		q.execute( "INSERT INTO journo_attr (journo_id,article_id) VALUES(%s,%s)", journo_id, article_id )
	q.close()


def SeenJobTitle( conn, journo_id, jobtitle, whenseen ):
	""" add a link to assign a jobtitle to a journo """

	jobtitle = jobtitle.strip()

	q = conn.cursor()

	q.execute( "SELECT jobtitle, firstseen, lastseen "
		"FROM journo_jobtitle "
		"WHERE journo_id=%s AND LOWER(jobtitle)=LOWER(%s)",
		journo_id,
		jobtitle )

	row = q.fetchone()
	if not row:
		# it's new
		q.execute( "INSERT INTO journo_jobtitle (journo_id,jobtitle,firstseen,lastseen) VALUES (%s,%s,%s,%s)",
			journo_id,
			jobtitle,
			whenseen,
			whenseen )
	else:
		# already got it - extend out the time period
		q.execute( "UPDATE journo_jobtitle "
			"SET lastseen=%s "
			"WHERE journo_id=%s AND LOWER(jobtitle)=LOWER(%s)",
		whenseen,
		journo_id,
		jobtitle )

	q.close()

