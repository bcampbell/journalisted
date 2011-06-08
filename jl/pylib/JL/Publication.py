import re
import unicodedata

import DB

class AmbiguousPublication(Exception):
    pass


def find(domain, name=None):
    """ look up a publication, return publication id or None """

    # use domain to look them up
    # want to look for both www. and bare versions
    domain = domain.lower().strip().encode( 'ascii' )
    candidates = [ domain ]
    if domain.startswith( 'www.' ):
        candidates.append( re.sub( '^www.','',domain ) )
    else:
        candidates.append( 'www.' + domain )

    c = DB.conn().cursor()
    c.execute( "SELECT pub_id FROM pub_domain WHERE domain in ( %s,%s )",
        (candidates[0], candidates[1]) )
    matched_domains = [ row['pub_id'] for row in c.fetchall() ]

    if len( matched_domains ) == 1:
        # got it!
        return matched_domains[0]

    if len( matched_domains ) == 0:
        # no matching domain
        if name is None:
            return None

        # try looking up by name instead
        n = name.strip().encode( 'utf-8' )
        c.execute( """SELECT pub_id FROM pub_alias a WHERE LOWER(alias)=LOWER(%s)""", (n,) )
        matched_names = [ row['pub_id'] for row in c.fetchall() ]
        if len(matched_names) == 0:
            return None     # give up
        if len(matched_names) == 1:
            return matched_names[0]
        raise AmbiguousPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - no domains, but multiple names" % (domain,name) )
        

    if len( matched_domains ) > 1:
        # more than one matching domain
        if name is None:
            raise AmbiguousPublication( "Can't disambiguate publication (multiple publications for '%s')" % (domain,) )

        # try to disambiguate using name
        n = name.strip().encode( 'utf-8' )
        sql = """SELECT pub_id FROM pub_alias WHERE LOWER(alias)=LOWER(%s) AND pub_id IN ( SELECT pub_id FROM pub_domain WHERE domain IN (%s,%s) )"""
        c.execute( sql, (n,candidates[0], candidates[1]) )
        matched_names = [ row['pub_id'] for row in c.fetchall() ]
        if len(matched_names) == 1:
            return matched_names[0]     # yay
        if len(matched_names) == 0:
            raise AmbiguousPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - multiple domains, no names" % (domain,name) )
        if len(matched_names) > 1:
            raise AmbiguousPublication( "Can't disambiguate publication (domain: '%s' name: '%s') - multiple domains, multiple names" % (domain,name) )

    assert False    # shouldn't get this far



def strip_subdomain(domain):
    domain = domain.lower().encode('ascii').strip()
    parts = domain.split('.')
    return parts[0], '.'.join(parts[1:])



def slugify( fancytext ):
    slug = fancytext
    # replace accented chars if there is a good equivalent
    slug = unicodedata.normalize('NFKD',slug).encode('ascii','ignore')
    slug = slug.lower()
    slug = re.sub(r'[^a-z0-9]+', '-', slug )
    slug = slug.strip('-')
    return slug


def create(domain, prettyname=None):
    """ eg: create('www.dailymail.co.uk','The Daily Mail') """

    assert re.compile(r'[.](com|org|net|gov)|(co[.]\w{2})').match(domain) is None

    if prettyname is None:
        # use domain as prettyname name
        prettyname = unicode( domain )
        prettyname = re.sub( u'^www.',u'',prettyname )
        shortname = prettyname
    else:
        prettyname = prettyname.strip()
        shortname = prettyname.lower()
        # replace accented chars
        shortname = unicodedata.normalize('NFKD',shortname).encode('ascii','ignore')
        # get rid of non-alphas:
        shortname = re.sub(u'[^-a-z]',u'',shortname)

    # for more natural-seeming sort order...
    sortname = prettyname.lower()
    sortname = re.sub(u'^the\s+',u'',sortname)


    c = DB.conn().cursor()
    c.execute( """INSERT INTO organisation (id,shortname,prettyname,sortname,home_url) VALUES (DEFAULT, %s,%s,%s,%s) RETURNING id""",
        ( shortname, prettyname, sortname, "http://" + domain ) )
    pub_id = c.fetchone()[0]

    c.execute( """INSERT INTO pub_domain (pub_id,domain) VALUES (%s,%s)""", (pub_id,domain) )
    c.execute( """INSERT INTO pub_alias (pub_id,alias) VALUES (%s,%s)""", (pub_id,prettyname) )

    return pub_id


def find_or_create(domain,prettyname=None):
    pub_id = find(domain,prettyname)
    if pub_id is not None:
        return pub_id

    # try a less specific domain name
    # (eg "blogs.newspaper.com" should go under "www.newspaper.com")
    sub,parent = strip_subdomain(domain)
    if len(parent.split('.'))>=2:
        pub_id = find(parent,prettyname)
        if pub_id is not None:
            # add the new domain to the publication
            c = DB.conn().cursor()
            c.execute( """INSERT INTO pub_domain (pub_id,domain) VALUES (%s,%s)""", (pub_id,domain) )
            return pub_id

    pub_id = create(domain,prettyname)
    return pub_id

