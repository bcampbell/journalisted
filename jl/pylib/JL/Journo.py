import DB
import re
import csv
import os
import unicodedata
import difflib

from datetime import datetime

import Misc
import metaphone
import ukmedia

class FindJournoException(Exception):
    '''Base for exceptions raised by FindJourno logic.'''

class MultipleJournosException(FindJournoException):
    '''Couldn't find a unique journo with the given attributes.'''

class NoOrgJournoException(FindJournoException):
    '''No journo found with articles for this organisation.'''


DEBUG_NO_COMMITS = False



places_cached = None
def GetPlaces():
    """load list of places"""
    global places_cached
    if places_cached != None:
        return places_cached
    places_cached = []
    
    # TOWNS (UK only right now), from http://en.wikipedia.org/wiki/List_of_post_towns_in_the_United_Kingdom
    towndatafile = os.path.join(os.path.dirname(__file__),'towns.txt')
    f = open( towndatafile, "rb" )
    reader = csv.reader( f )
    for row in reader:
        c = row[0].decode( 'utf-8' ).lower()
        # get rid of accents because we'll compare this way:
        c = unicodedata.normalize('NFKD',c).encode('ascii','ignore')
        places_cached.append( c )

    # CITIES (worldwide): from http://www.world-gazetteer.com/wg.php?x=&men=gcis&lng=en&dat=32&srt=pnan&col=aohdq&pt=c&va=x
    citydatafile = os.path.join(os.path.dirname(__file__),'cities.csv')
    f = open( citydatafile, "rb" )
    reader = csv.reader( f )
    for row in reader:
        c = row[1].decode( 'utf-8' ).lower()
        # get rid of accents because we'll compare this way:
        c = unicodedata.normalize('NFKD',c).encode('ascii','ignore')
        places_cached.append( c )

    # CITIES (worldwide): from http://www.world-gazetteer.com/wg.php?x=1129163518&men=stdl&lng=en&gln=xx&dat=32&srt=npan&col=aohdq
    citydatafile = os.path.join(os.path.dirname(__file__),'capitalCities.txt')
    f = open( citydatafile, "rb" )
    reader = csv.reader( f )
    for row in reader:
        c = row[1].decode( 'utf-8' ).lower()
        # get rid of accents because we'll compare this way:
        c = unicodedata.normalize('NFKD',c).encode('ascii','ignore')
        places_cached.append( c )

    return places_cached


def ArrayIsSubset(a,b):
    for i in a:
        if not (i in b):
            return False
    return True



def BaseRef( prettyname ):
    """Generate reference for a journo, suitable as a URL part
    
    eg u"Fred Blogs" => u"fred-blogs"
    Mapping is not guaranteed to be unique
    """

    # convert to unicode (actually it is already, but we need to let python know that)
    assert isinstance(prettyname, unicode)

    # get rid of accents:
    ref = unicodedata.normalize('NFKD',prettyname).encode('ascii','ignore')
    
    # get rid of non-alphas:
    ref = re.sub(u'[^a-zA-Z ]',u'',ref)

    ref = ref.lower()
    ref = ref.strip()
    # replace spaces with hyphens
    ref = u'-'.join( ref.split() )
    return ref


def GenerateUniqueRef( conn, prettyname ):
    """Generate a unique ref string for a journalist"""

    nameToProcessForRef = StripPrefixesAndSuffixes(prettyname)
    ref = BaseRef( nameToProcessForRef )
    q = conn.cursor()
    q.execute( "SELECT id FROM journo WHERE ref=%s", (ref,) )
    if not q.fetchone():
        q.close()
        return ref
    i = 1
    while 1:
        candidate = u'%s-%d' %(ref,i)
        q.execute( "SELECT id FROM journo WHERE ref=%s", (candidate,) )
        if not q.fetchone():
            q.close()
            return candidate
        i = i + 1


#def DefaultAlias( rawname ):
#   """ compress whitespace, strip leading/trailing space, lowercase """
#   alias = rawname.strip()
#   alias = u' '.join( alias.split() )
#   alias = alias.lower()
#   return alias;


def GetNoOfArticlesWrittenBy( conn, journo_ref ):
    journo_id = GetJournoIdFromRef(conn,journo_ref)
    return GetNoOfArticlesWrittenById(conn, journo_id)

def GetNoOfArticlesWrittenById( conn, journo_id ):
#   if not journo_id:
#       return 
    assert journo_id, "Can't find journo: "+journo_ref

    c = conn.cursor()
    c.execute("SELECT COUNT(journo_id) FROM journo_attr WHERE journo_id=%s", (journo_id,))
    row = c.fetchone()
    c.close()
    if not row:
        return 0
    return row[0]

# reasonable if appears twice or more?
def IsReasonableFirstName( conn, firstName, mustFindNOrMore=2 ):
    firstName = firstName.lower()
    firstName = re.sub(u"\'", u'\\\'', firstName, re.UNICODE) # escape e.g. o\'connor
    c = conn.cursor()
    c.execute("SELECT COUNT(id) FROM journo WHERE firstname=%s", (firstName.encode('utf-8'),))
    row = c.fetchone()
    c.close()
    if not row:
        return False
    return row[0]>=mustFindNOrMore

# reasonable if appears twice or more?
def IsReasonableLastName( conn, lastName, mustFindNOrMore=2 ):
    lastName = lastName.lower()
    lastName = re.sub(u"\'", u'\\\'', lastName, re.UNICODE) # escape e.g. o\'connor
    c = conn.cursor()
    c.execute("SELECT COUNT(id) FROM journo WHERE lastname=%s", (lastName.encode('utf-8'),))
    row = c.fetchone()
    c.close()
    if not row:
        return False
    return row[0]>=mustFindNOrMore


def GetFirstName( conn, ref ):
    c = conn.cursor()
    c.execute("SELECT firstname FROM journo WHERE ref='%s'", (ref,))
    row = c.fetchone()
    c.close()
    if not row:
        return None
    return unicode(row[0], 'utf-8')

def GetLastName( conn, ref ):
    c = conn.cursor()
    c.execute("SELECT lastname FROM journo WHERE ref='%s'", (ref,))
    row = c.fetchone()
    c.close()
    if not row:
        return None
    return unicode(row[0], 'utf-8')


def GetJournoIdFromRef( conn, ref):
    c = conn.cursor()
    c.execute( "SELECT id FROM journo WHERE ref=%s", (ref,))
    while 1:
        row = c.fetchone()
        if not row:
            break
        return int( row[0] )
    c.close()
    return None


def GetOrgsFor( conn, id ):
    c = conn.cursor()
    c.execute("SELECT DISTINCT a.srcorg FROM ( journo_attr attr INNER JOIN article a ON a.id=attr.article_id ) WHERE attr.journo_id=%s", (id,))
    matching = c.fetchall()
    found = []
    # want to make sure that only one of our possible journos has written for this org
    for match in matching:
        found.append(match[0])
    return found


def FindJournoMultiple( conn, rawname ):
    # TODO return fuzzy match of journo-names:

    newPrettyname = GetPrettyNameFromRawName(conn, rawname)
    nameToProcessForRef = StripPrefixesAndSuffixes(newPrettyname)
    newRef = BaseRef(nameToProcessForRef)

    found = []
    id = GetJournoIdFromRef(conn, newRef)
    if id:
        found.append(id)
    i = 1
    while 1:
        candidate = u'%s-%d' %(newRef,i)
        id = GetJournoIdFromRef(conn, candidate)
        if not id:
            break
        found.append(id)
        i = i + 1
    return found




# TODO: use rel=author urls etc in journo to aid resolution
def FindJourno( conn, rawname, hint_art=None, expected_ref=None ):
    """Find a journo in the database, returns journo id or None if rawname can't be resolved

    If the name matches multiple journalists, the hint_art data is used.
    hint_art contains additional information known, usually the entire article
    that the name came from.
    If supplied, hint_art must have at least a 'srcorgname' item containing
    the shortname of an organisation the journo is known to have written for.
    """


    # handle any evil special cases for individual journos
    # (eg when two journos of the same name write for the same organisation)
    journo_id = EvilPerJournoSpecialCasesLookup( conn, rawname, hint_art )
    if journo_id:
        return journo_id

    # look up candidates by name
    candidates = FindJournoMultiple( conn, rawname )

    if len(candidates) == 0:
        return None

    c = conn.cursor()
    if expected_ref is not None:
        # we're expecting a certain journo. if found, bypass other checks
        c.execute("SELECT id FROM journo WHERE ref=%s", (expected_ref,))
        row = c.fetchone()
        if row is not None:
            expected_id = row['id']
            if expected_id in candidates:
                return expected_id      # yay!

    assert hint_art is not None

    # any candidates written for this publication before?
    if 'srcorg' in hint_art:
        srcorgid = hint_art['srcorg']
    else:
        srcorgid = Misc.GetOrgID(hint_art['srcorgname'])
    sql = "SELECT DISTINCT attr.journo_id FROM ( journo_attr attr INNER JOIN article a ON a.id=attr.article_id ) WHERE attr.journo_id IN (" + ','.join([str(j) for j in candidates]) + ") AND a.srcorg=%s"
    c.execute(sql, (srcorgid,))
    matching = [row['journo_id'] for row in c]

    if len(matching) == 0:
        return None
    if len(matching) == 1:
        return matching[0]

    # TODO: maybe pick journo with most recent publication?
    raise MultipleJournosException, "%d journos found called '%s', and %d have articles in srcorg %d" % (len(matching),rawname,len(matching),srcorgid)




# get Journo without prefixes and suffixes:
def StripPrefixesAndSuffixes(newPrettyname):
    # could add more from http://en.wikipedia.org/wiki/Title
    m = re.match(u'(?:(sir|lady|dame|prof|founder|major|general|chancellor|lieutenant-colonel|col|lieutenant|colonel|captain|corporal|sergeant|sgt|mr|dr|professor|cardinal|chef) )?(.*?)(?: (mp|vc|qc))?$', newPrettyname, re.UNICODE|re.IGNORECASE)
    # TODO standardise prefixes, e.g. Col->Colonel always!
    assert m, "Can't process journo: "+m
    return m.group(2)
#   if m.group(1) or m.group(3):
#       newPrettyname = m.group(2)
#       if m.group(1):
#           newPrettyname = m.group(1)+u" "+newPrettyname
#       if m.group(3):
#           newPrettyname = newPrettyname+u" "+m.group(3).upper() # capitalise suffixes, like MP
#       nameToProcessForRef = m.group(2)

# n.b. allowing splits on hyphen (for refs) or space
# group=1 gets first name, =2 gets rest
def getFirstNameAndRestOf(name,groupId):
    # split into first name and rest:
    m = re.match('^(.*?)[ -](.*?)$',name)
    if not m:
        return ''
    return m.group(groupId)
    
# group=1 gets rest, =2 gets last name:
def getRestAndLastNameOf(name,groupId):
    # split into rest and last name:
    m = re.match('^(.*)[ -](.*?)$',name)
    if not m:
        return ''
    return m.group(groupId)
    
# only using spaces, not hyphens:
def getMiddleName(name):
    # split into rest and last name:
    m = re.match('^(.*?) (.*?) (.*?)$',name)
    if not m:
        return ''
    return m.group(2)
    
    

def getCloseMatches(conn, ref, journoRefs):
#   print 'getCloseMatches'
    likesSrc = difflib.get_close_matches(ref,journoRefs,9999,0.9) # was .9 for tidy5 # .95 does one character different
    likes = []
    
    for like in likesSrc:
#       print "Try like: ",like
        veto = False
        
        # if they only differ on first name:
        if getFirstNameAndRestOf(ref,2)==getFirstNameAndRestOf(like,2):
#           print "only differ on first name"
            # don't allow e.g. Ben to be similar to Ken:
            # (i.e. don't allow a like if both are valid names):
            if IsReasonableFirstName(conn,getFirstNameAndRestOf(ref,1)) and IsReasonableFirstName(conn,getFirstNameAndRestOf(like,1)):
#               print "veto"
                veto = True
                
        # ditto for last names:
        if getRestAndLastNameOf(ref,1)==getRestAndLastNameOf(like,1):
            if IsReasonableLastName(conn,getRestAndLastNameOf(ref,2)) and IsReasonableLastName(conn,getRestAndLastNameOf(like,2)):
                veto = True
        if ref==like or (not veto):
            likes.append(like)
            
    return likes





def GetPrettyNameFromRawName(conn, rawName ):
    newPrettyname = rawName

    # normalize unicode apostrophes to ascii apostrophe
    newPrettyname = re.sub(u'[\u02BC\u2019]',"'", newPrettyname)

    # treat as unicode:
    assert isinstance( newPrettyname, unicode )
    # - O'Connor should be done by this:
    
    newPrettyname = newPrettyname.title()
    # handle:
    # - Mc, Mac prefixes
    def helperFn(s):
        return s.group(1)+s.group(2).title() 
    #s.group(1)+s.group(2).lower()
    newPrettyname = re.sub(u'\\b(Ma?c)([a-z]{3,})', helperFn, newPrettyname)
    newPrettyname = re.sub(u'\\bVan\\b', u'van', newPrettyname)
    newPrettyname = re.sub(u'\\bDe\\b', u'de', newPrettyname)
    #sometimes good sometimes bad:  n = re.sub(u'\\bD\'', u'd\'', n)

    # capitalise some suffixes like MP:
    newPrettyname = re.sub(u'\\bMp$', u'MP', newPrettyname)
    newPrettyname = re.sub(u'\\bQc$', u'QC', newPrettyname)
    
    # no dots after initials: (e.g. should be Gareth A Davies, not Gareth A. Davies, also
    #     get rid of weird characters like < >
    newPrettyname = re.sub('\s*[\.<>]\s*',' ', newPrettyname).strip()

    # get rid of spaces after hyphens:
    newPrettyname = re.sub('- ', '-', newPrettyname)
    # get rid of spaces after O's etc:
    newPrettyname = re.sub('\' ', '\'', newPrettyname)

    # get rid of punctuation on either side:
    newPrettyname = newPrettyname.strip('|.;:,!? ')

    # Warning... might need to merge?
    # get rid of extraneous With and By at the beginning:
    # (also: Minder's GARY WEBSTER)
    m = re.match(u'(?:((?:\w+\'S)|Eco-Worrier|from|Interview|reviewed|according|with|by) )(.*?)$', newPrettyname, re.UNICODE|re.IGNORECASE)
    if m and m.group(1) and m.group(2):
#               print m.group(1),"+",m.group(2)
        newPrettyname = m.group(2)
    # get rid of extraneous words at the end:
    m = re.match(u'^(.*?)(\'s? sketch|\'s? ?Week| Chief| Science| International| Interview| Stays| Discovers| Reports| Writes)$', newPrettyname, re.UNICODE|re.IGNORECASE)
    if m and m.group(1) and m.group(2):
#               print m.group(1),"+",m.group(2)
        newPrettyname = m.group(1)
        
    return newPrettyname


def create(rawname ):

    conn = DB.conn()

#gtb    alias = DefaultAlias( rawname )
    prettyname = GetPrettyNameFromRawName( conn, rawname )
#   (firstname,lastname) = prettyname.split(None,1) 

    # gtb, this is a hack! until we sort out what we are doing with journalists who want to opt out of being in the database:
    if prettyname==u'Jini Reddy':
        raise Exception, "Not creating New Journo who has opted out"

    parts = prettyname.lower().split()
    if len(parts) == 0:
        raise "Empty journo name!"
    elif len(parts) == 1:
        firstname = parts[0]
        lastname = parts[0]
    else:
        firstname = parts[0]
        lastname = parts[-1]


    # get metaphone versions of names (as calculated by php metaphone())
    # 4 chars seems like the magic length for fuzzy matching.
    # (utf-8 encoding a little silly, but consistent assumptions on the web side of things)
    firstname_metaphone = metaphone.php_metaphone( firstname.encode('utf-8') )[:4]
    lastname_metaphone = metaphone.php_metaphone( lastname.encode('utf-8') )[:4]

    ref = GenerateUniqueRef( conn, prettyname )

    #print("CreateNewJourno: ",rawname," = ",prettyname," = ",ref);

    # TODO: maybe need to filter out some chars from ref?
    q = conn.cursor()
    q.execute( "select nextval('journo_id_seq')" )
    (journo_id,) = q.fetchone()
    q.execute( "INSERT INTO journo (id,ref,prettyname,firstname,lastname,firstname_metaphone,lastname_metaphone,"
            "created) VALUES (%s,%s,%s,%s,%s,%s,%s,now())",
            ( journo_id,
            ref.encode('utf-8'),
            prettyname.encode('utf-8'),
            firstname.encode('utf-8'),
            lastname.encode('utf-8'),
            firstname_metaphone,
            lastname_metaphone ) )
#gtb    q.execute( "INSERT INTO journo_alias (journo_id,alias) VALUES (%s,%s)",
#           journo_id,
#           alias.encode('utf-8') )
    q.close()

    journo = {
        'id':journo_id,
        'ref':ref,
        'prettyname':prettyname,
        'firstname':firstname,
        'lastname': lastname,
        'firstname_metaphone': firstname_metaphone,
        'lastname_metaphone': lastname_metaphone,
    }

    return journo



def update_activation(journo_id):
    """ activate the journos status if they've got more than one active article and they've not been hidden """

    q = DB.conn().cursor()
    # count number of articles
    q.execute( "SELECT COUNT(*) FROM journo_attr ja INNER JOIN article a ON (a.id=ja.article_id AND a.status='a') WHERE ja.journo_id=%s", (journo_id,))
    r = q.fetchone()
    if r[0] > 1:
        q.execute( "UPDATE journo SET status='a' WHERE id=%s AND status='i'",(journo_id,))
    q.close()



def SeenJobTitle( conn, journo_id, jobtitle, whenseen, srcorg ):
    """ add a link to assign a jobtitle to a journo """

    assert isinstance( jobtitle, unicode )

    jobtitle = jobtitle.strip()

    q = conn.cursor()

    q.execute( "SELECT jobtitle, firstseen, lastseen "
        "FROM journo_jobtitle "
        "WHERE journo_id=%s AND LOWER(jobtitle)=LOWER(%s) AND org_id=%s",
        (journo_id,
        jobtitle.encode('utf-8'),
        srcorg))

    row = q.fetchone()
    if not row:
        # it's new
        q.execute( "INSERT INTO journo_jobtitle (journo_id,jobtitle,firstseen,lastseen,org_id) VALUES (%s,%s,%s,%s,%s)",
            (journo_id,
            jobtitle.encode('utf-8'),
            str(whenseen),
            str(whenseen),
            srcorg))
    else:
        # already got it - extend out the time period
        q.execute( "UPDATE journo_jobtitle "
            "SET lastseen=%s "
            "WHERE journo_id=%s AND LOWER(jobtitle)=LOWER(%s) AND org_id=%s",
            (str(whenseen),
            journo_id,
            jobtitle.encode('utf-8'),
            srcorg))

    q.close()



def EvilPerJournoSpecialCasesLookup( conn, rawname, hints ):
    """Special case evilness handling for journos with particular names

    Returns a journo_id if there is a special case.
    Otherwise returns None, and normal disambiguation rules should be
    applied.

    Mainly for when two or more journos with the same name write for the
    organisation - we need to figure out some other way to distinguish them.
    This inevitably requires special case evilness.
    """

    rawname = rawname.lower()
    if rawname == "kelly rose bradford":
        # normal journo lookup strips "bradford" off as a placename.
        return GetJournoIdFromRef( conn, 'kelly-rose-bradford' )

    if rawname == "paul evans":
        # guardian has two Paul Evans'
        #
        # Most articles by the Country Diarist Paul Evans, but
        # 'Paulville: the town where rightwingers will be free'
        # 'Railing back the years in Iran' are by Paul (David) Evans
        # (pauldavidevans@gmail.com).
        #
        # Paul (David) Evans has also now written for the Times.
        # 
        # (one way to tell them apart: Paul Evans (Country Diarist) byline
        # will be hyperlinked on the Guardian site, while Paul (David)
        # Evans won't be)

        if hints['srcorgname'] != u'guardian':
            # for non-guardian articles, let normal processing differentiate them
            return None
    
        paul_country_diarist_evans = GetJournoIdFromRef( conn, 'paul-evans-1' )
        paul_david_evans = GetJournoIdFromRef( conn, 'paul-evans-2' )

        title = hints['title'].lower()

        # for now, title and/or url should be good enough to guess:
        if 'country diary' in hints['title'].lower():
            return paul_country_diarist_evans

        for marker in ['environment','conservation','wildlife','rural' ]:
            if marker in hints['permalink']:
                return paul_country_diarist_evans

        return paul_david_evans


    if rawname == "andrew walker":
        # The BBC has two Andrew Walkers
        # one is based in and writes about Nigeria (Andrew.Walker-Abuja@bbc.co.uk)
        # other is london-based and writes mostly about economics and finance.
        if hints['srcorgname'] != u'bbcnews':
            # let normal processing differentiate them
            return None

        andrew_walker_nigeria = GetJournoIdFromRef( conn, 'andrew-walker-1' )
        andrew_walker_london = GetJournoIdFromRef( conn, 'andrew-walker-2' )

        byline = hints['byline'].lower()
        for marker in ['abuja', 'bauchi', 'nigeria' ]:
            if marker in byline:
                return andrew_walker_nigeria

        for marker in ['economics', 'business' ]:
            if marker in byline:
                return andrew_walker_london

        title = hints['title'].lower()
        for marker in ['niger', 'nigeria' ]:
            if marker in title:
                return andrew_walker_nigeria

        return andrew_walker_london

    if rawname == "jane hamilton" and hints['srcorgname'] == u'sun':
        # two Jane Hamilton's at the sun. One appears to write about scotland,
        # the other about shoes.
        content = u''
        if hints['content'] is not None:
            content = hints['content'].lower()
        lookups = { 'jane-hamilton-1': (u'scotland', u'glasgow',u'whisky',u'strathclyde' ),
            'jane-hamilton-2': ( u'shoe', u'designer', u'fashion', u'j.hamilton@the-sun.co.uk' ),
            }
        best = ''
        bestscore = 0
        for ref,wordlist in lookups.iteritems():
            score=0
            for w in wordlist:
#                print "%s: %s => %d" % (ref,w,content.count(w))
                score = score + content.count(w)
            if score >= bestscore:
                best = ref
                bestscore = score
        if best:
#            print( "EvilPerJournoSpecialCasesLookup(): picked %s with score %d\n" % (best,bestscore) )
            return GetJournoIdFromRef( conn, best )

    # this _should_ be handled automatically, but for now...
    # (she is bylined both with and without the hyphen)
    if rawname == "alison smith-squire" or rawname=="alison smith squire":
        return GetJournoIdFromRef( conn, 'alison-smithsquire' )


    # Two Michael Fitzpatrick's have written for the guardian. One is a journo, the other a doctor.
    # we'll just assume it's the journo.
    if rawname == 'michael fitzpatrick' and hints['srcorgname'] == u'guardian':
        return GetJournoIdFromRef( conn, 'michael-fitzpatrick-1' )

    # no special cases - just let normal handling proceed!
    return None


# this fn is for scraped bios, where each journo is assumed to only have one of each
# kind (ie only one wikipedia bio).
# manually-maintained bios probably all just handled through web admin pages.
def load_or_update_bio( conn, journo_id, bio, default_approval=False ):
    """ load/update a journo bio entry of a specific kind """

    assert( 'kind' in bio and bio['kind']!='' )
    c = conn.cursor()
    c.execute( "SELECT * FROM journo_bio WHERE journo_id=%s AND kind=%s", (journo_id, bio['kind']) )
    old_bios = c.fetchall()

    # each journo can only have one bio of a kind
    assert( len( old_bios ) in (0,1) )

    if len(old_bios) > 0:
        # update existing bio, keeping the approval status
        c.execute( "UPDATE journo_bio SET bio=%s,srcurl=%s WHERE journo_id=%s AND kind=%s",(bio['bio'].encode('utf-8'), bio['srcurl'], journo_id, bio['kind'] ))

    else:
        # create new bio entry
        c.execute( "INSERT INTO journo_bio (journo_id, bio, kind,srcurl, approved ) VALUES (%s,%s,%s,%s,%s)",( journo_id, bio['bio'].encode('utf-8'), bio['kind'], bio['srcurl'], default_approval))



def find_or_create(journo, hint_art=None, expected_journo=None):
    """ returns journo id """
    j_id = FindJourno(DB.conn(),journo['name'],hint_art,expected_journo)
    if j_id is None:
        j = create(journo['name'])
        ukmedia.DBUG2(" NEW journo: %s\n" % (j['ref'],))
        j_id = j['id']
    return j_id




