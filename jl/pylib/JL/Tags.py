#
# Tags - cheesy term-extraction until we get something better.
#

import re

import DB
import ukmedia
import csv
import os

countrylist_cached = None
blacklist_cached = None



def GetCountryList():
    """load list of nations and nationalities"""

    global countrylist_cached

    if countrylist_cached != None:
        return countrylist_cached

    countrylist_cached = []


    countrydatafile = os.path.join(os.path.dirname(__file__),'countries.csv')
    f = open( countrydatafile, "rb" )

    reader = csv.reader( f )
    for row in reader:
        c = row[0].decode( 'utf-8' ).lower()
        countrylist_cached.append( c )
    return countrylist_cached


def GetBlacklist():
    """get the list of banned tags from the db"""

    global blacklist_cached

    # don't hit db more often that necessary
    if blacklist_cached != None:
        return blacklist_cached

    c = DB.conn().cursor()
    c.execute( "SELECT bannedtag FROM tag_blacklist" )

    blacklist_cached = []
    while 1:
        r = c.fetchone()
        if not r:
            break
        tag = r[0].decode('utf-8')
        blacklist_cached.append( tag )

    c.close()
    return blacklist_cached


def ExtractFromText( txt ):
    """extract a list of terms (tags) from text"""

    blacklist = GetBlacklist()
    countries = GetCountryList()
    synonyms = {}

    # extract phrases with the first letter of each word capitalised,
    # but not at the beginning of a sentence.
    tagpat = re.compile( u'[^.\\s]\\s*(([A-Z]\\w+)(\\s+([A-Z]\\w+))*)', re.UNICODE|re.DOTALL )

    # prefixes we'll trim off
    prefixpat = re.compile( u'^(mr|dr|ms|mrs)\\s+',re.UNICODE|re.IGNORECASE )

    # calculate tags using noddy Crapitisation algorithm
    tags = {}
    for m in tagpat.findall(txt):
        # compress whitespace
        words = m[0].split()
        if len( words ) > 4:
            continue    # discard tags with more than 4 words
        t = ' '.join( words )

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


        # which kind of term is it?
        kind = ' '  # unknown
        if t in countries:
            kind = 'c'


        # TODO: kind field in DB should be part of primary key too!

        # key is tag name _and_ kind of tag!
        k = (t,kind)
        tags[k] = tags.get(k,0) + 1     # ++freq

    return tags


def generate(article_id, article_content):
    """ Generate tags for an article """


    txt = ukmedia.StripHTML( article_content )

    tags = ExtractFromText( txt )

    # write the tags into the DB
    c2 = DB.conn().cursor()
    c2.execute("DELETE FROM article_tag WHERE article_id=%s", (article_id,))
    for tagkey,tagfreq in tags.items():

        tagname = tagkey[0].encode('utf-8')
        tagkind = tagkey[1]
        c2.execute( "INSERT INTO article_tag (article_id, tag, kind, freq) VALUES (%s,%s,%s,%s)",
            (article_id, tagname, tagkind, tagfreq))
    c2.close()

