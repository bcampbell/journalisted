#
# CommentLink.py
#
# For adding links to comments elsewhere on the web about
# articles in our db.
# The "article_commentlink" table.
#

import sys

import DB
import ukmedia



class Stats:
    """ helper class for tracking many entries we've processed """
    def __init__(self):
        self.matched = 0            # matched to DB
        self.missing = 0        # can't find article for in DB
        self.not_handled = 0    # not handled by our scrapers

    def Accumulate( self, other ):
        """ add one a set of stats to this one """
        self.matched += other.matched
        self.missing += other.missing
        self.not_handled += other.not_handled

    def Total( self ):
        """ total number of links we've accounted for """
        return self.matched + self.missing + self.not_handled

    def Report( self ):
        if self.Total() == 0:
            return "None processed"
        else:
            return "%d entries - %d matched (%d%%), %d missing, %d not handled" % (
                self.Total(),
                self.matched,
                (self.matched * 100) / self.Total(),
                self.missing,
                self.not_handled,
                )

def AddCommentLink( conn, commentlink ):
    """ add/update a comment link

    commentlink is a dictionary with these fields required:

    srcid - unique id of article (derived from url)
    score - number of diggs, points, votes (can be None)
    num_comments - number of comments
    comment_url - url of comment page on digg/newsvine/whatever...
    source - 'digg', 'reddit' etc...

    returns True if link was matched to an article in the DB, else False
    """

    c = conn.cursor()
    e = commentlink

    # do we have this article in the db?
    c.execute( "SELECT id FROM article WHERE srcid=%s", (e['srcid'],) )
    articles = c.fetchall()
    if len(articles) < 1:
        # can't find article in DB
        return False

    if len(articles)>1:
        print >>sys.__stderr__, "WARNING: multiple articles with same srcid (%s)" % (e['srcid'])
    article_id = articles[0]['id']

    # found it - insert/replace commentlink
    c.execute( """DELETE FROM article_commentlink WHERE article_id=%s and source=%s""", (article_id, e['source']) )
    c.execute( """INSERT INTO article_commentlink (article_id,source,comment_url,num_comments,score ) VALUES (%s,%s,%s,%s,%s)""",
        (article_id,
        e['source'],
        e['comment_url'],
        e['num_comments'],
        e['score']) )

    conn.commit()
    return True



def upsert( conn, article_id, commentlink ):
    """insert/replace commentlink"""
    c = conn.cursor()

    l = commentlink

    # fill in optional args
    if 'score' not in l:
        l['score'] = None
    if 'num_comments' not in l:
        l['num_comments'] = None

    c.execute( """DELETE FROM article_commentlink WHERE article_id=%s and source=%s""", (article_id, l['source']) )
    c.execute( """INSERT INTO article_commentlink (article_id,source,comment_url,num_comments,score ) VALUES (%s,%s,%s,%s,%s)""",
        ( article_id, l['source'], l['comment_url'], l['num_comments'], l['score'] ) )
#    print "added link for [%d, %s]: %s (%d comments)" %(article_id,l['source'],l['comment_url'],l['num_comments'] )

