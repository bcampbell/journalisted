import re

class UnresolvedPublication(Exception):
    pass


def resolve( conn, domain, name ):
    """ look up a publication, return publication id or None """

    # use domain to look them up
    # want to look for both www. and bare versions
    domain = domain.lower().strip().encode( 'ascii' )
    candidates = [ domain ]
    if domain.startswith( 'www.' ):
        candidates.append( re.sub( '^www.','',domain ) )
    else:
        candidates.append( 'www.' + domain )

    c = conn.cursor()
    c.execute( "SELECT pub_id FROM pub_domain WHERE domain in ( %s,%s )",
        (candidates[0], candidates[1]) )
    matched_domains = [ row['pub_id'] for row in c.fetchall() ]

    if len( matched_domains ) == 1:
        # got it!
        return matched_domains[0]

    elif len( matched_domains ) == 0:
        # no matching domain - try looking up by name instead
        n = name.strip().encode( 'utf-8' )
        c.execute( """SELECT pub_id FROM pub_alias a WHERE LOWER(alias)=LOWER(%s)""", (n,) )
        matched_names = [ row['pub_id'] for row in c.fetchall() ]
        if len(matched_names) == 0:
            return None     # give up
        if len(matched_names) == 1:
            return matched_names[0]
        raise UnresolvedPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - no domains, but multiple names" % (domain,name) )

    elif len( matched_domains ) > 1:
        # more than one matching domain - try to disambiguate using name
        n = name.strip().encode( 'utf-8' )
        if n== '':
            return matched_domains[0]   # no name - just assume first match.

        sql = """SELECT pub_id FROM pub_alias WHERE LOWER(alias)=LOWER(%s) AND pub_id IN ( SELECT pub_id FROM pub_domain WHERE domain IN (%s,%s) )"""
        c.execute( sql, (n,candidates[0], candidates[1]) )
        matched_names = [ row['pub_id'] for row in c.fetchall() ]
        if len(matched_names) == 1:
            return matched_names[0]
        if len(matched_names) == 0:
            raise UnresolvedPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - multiple domains, no names" % (domain,name) )
        if len(matched_names) > 1:
            raise UnresolvedPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - multiple domains, multiple names" % (domain,name) )

    assert False    # shouldn't get this far




def create( conn, domain, publication ):
    if publication.strip() == u'':
        # use domain for missing publication names
        publication = unicode( domain )
        publication = re.sub( u'^www.',u'',publication )

    c = conn.cursor()
    c.execute( """INSERT INTO organisation (id,shortname,prettyname,home_url) VALUES (DEFAULT, %s,%s,%s) RETURNING id""",
        ( domain, publication, "http://" + domain ) )
    pub_id = c.fetchone()[0]

    c.execute( """INSERT INTO pub_domain (pub_id,domain) VALUES (%s,%s)""", (pub_id,domain) )
    c.execute( """INSERT INTO pub_alias (pub_id,alias) VALUES (%s,%s)""", (pub_id,publication) )

    return pub_id

