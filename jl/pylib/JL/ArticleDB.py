import sys
import re
from datetime import datetime

import DB
import Journo
import Byline
import ukmedia
import Tags
import Misc
import CommentLink
import Publication
import urlparse


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
    srcid - unique identifier within organisation (eg url) TODO: REMOVE!
    firstseen -
    lastseen -
    text, title, content, description, byline should all be unicode
    """

    def __init__(self):
        pass


    # TODO: KILL THIS
    def Add( self, art ):
        """Store an article in the DB

        returns id of newly-added article
        """

        assert 'id' not in art
        return self.upsert(art)



    def upsert( self, art ):
        """Insert or update an article"""


        # if no separate 'urls' set, create it
        if not 'urls' in art:
            art['urls'] = set((art['permalink'], art['srcurl']))

        # fill in some defaults if missing
        if 'lastscraped' not in art:
            art['lastscraped'] = datetime.now()
        if 'lastseen' not in art:
            art['lastseen'] = datetime.now()
        if 'description' not in art:
            if 'content' in art:
                art['description'] = ukmedia.FirstPara(art['content'])
            else:
                art['description'] = u""

        CheckArticle( art )

        # send text to the DB as utf-8
        title = art['title'].encode( 'utf-8' )
        byline = art[ 'byline' ].encode( 'utf-8' )
        description = art['description'].encode( 'utf-8' )
        pubdate = "%s" %(art['pubdate'])
        lastscraped = "%s" % (art['lastscraped'])
        lastseen = "%s" % (art['lastseen'])
        firstseen = lastseen    # it's a new entry
        srcurl = art['srcurl']
        permalink = art['permalink']
        srcorg = art['srcorg']

        # phasing out srcid...
        srcid = art['permalink']

        wordcount = None
        content = None
        # does article include content?
        if 'content' in art:
            content = art['content'].encode( 'utf-8' )
            # noddy wordcount
            txt = ukmedia.StripHTML( art['content'] )
            wordcount = len( txt.split() );

        # send to db!
        cursor = DB.conn().cursor()



        updating = False
        if 'id' in art:
            updating = True

        if updating:
            # update existing
            article_id = art['id']
            q = 'UPDATE article SET (title, byline, description, lastscraped, pubdate, lastseen, permalink, srcurl, srcorg, srcid, wordcount, last_comment_check) = (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s) WHERE id=%s'
            cursor.execute(q, (title, byline, description, lastscraped, pubdate, lastseen, permalink, srcurl, srcorg, srcid, wordcount, lastscraped, article_id))
        else:
            # insert new
            q = 'INSERT INTO article (title, byline, description, lastscraped, pubdate, firstseen, lastseen, permalink, srcurl, srcorg, srcid, wordcount, last_comment_check) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)'
            cursor.execute( q, ( title, byline, description, lastscraped, pubdate, firstseen, lastseen, permalink, srcurl, srcorg, srcid, wordcount, lastscraped ) )
            # get the newly-allocated id
            cursor.execute( "select currval('article_id_seq')" )
            article_id = cursor.fetchone()[0]


        # add the known urls for the article
        if updating:
            cursor.execute( "DELETE FROM article_url WHERE article_id=%s", (article_id,))
        for url in set(art['urls']):
            cursor.execute( "INSERT INTO article_url (url,article_id) VALUES (%s,%s)", (url,article_id))

        # update content, if included
        if content is None:
            insert_content = False
        else:
            insert_content = True
            if updating:
                # TODO: keep multiple revisions to track changes
                # has the content actually changed?
                cursor.execute("SELECT id FROM article_content WHERE article_id=%s AND content=%s", (article_id,content))
                foo = cursor.fetchall()     # gah... couldn't get cursor.rowcount to work...
                if len(foo)>=1:
                    # no change, so just leave it as is
                    insert_content = False

        if insert_content:
            cursor.execute("DELETE FROM article_content WHERE article_id=%s", (article_id,))
            q = 'INSERT INTO article_content (article_id, content,scraped) VALUES ( %s,%s,%s )'
            cursor.execute(q, (article_id, content, lastscraped))

        # queue it for xapian indexing
        cursor.execute("DELETE FROM article_needs_indexing WHERE article_id=%s", (article_id,))
        cursor.execute("INSERT INTO article_needs_indexing (article_id) VALUES (%s)", (article_id,))

        # if there was a scraper error entry for this article, delete it now
        cursor.execute( "DELETE FROM error_articlescrape WHERE srcid=%s", (srcid,) )

        # if there were images, add them too
        if updating:
            cursor.execute("DELETE FROM article_image WHERE article_id=%s", (article_id,))
        if 'images' in art:
            for im in art['images']:
                cap = im['caption'].encode('utf-8')
                cred = ''
                if 'credit' in im:
                    cred = im['credit'].encode('utf-8')
                cursor.execute("INSERT INTO article_image (article_id,url,caption,credit) VALUES (%s,%s,%s,%s)",
                    (article_id, im['url'], cap, cred))

        # if there were commentlinks, add them too
        if 'commentlinks' in art:
            for c in art['commentlinks']:
                c['source'] = art['srcorgname']
                CommentLink.upsert(article_id, c)

        # add tags
        if content is not None:
            Tags.generate(article_id, art['content'])

        # attribute journos
        assert 'journos' in art
        cursor.execute("DELETE FROM journo_attr WHERE article_id=%s", (article_id,))
        for journo_id in art['journos']:
            cursor.execute("INSERT INTO journo_attr (journo_id,article_id) VALUES (%s,%s)", (journo_id,article_id))

            # make sure journo activates if they meet the criteria
            Journo.update_activation(journo_id)

            # also clear the html cache for that journos page
            cachename = 'j%s' % (journo_id)
            cursor.execute( "DELETE FROM htmlcache WHERE name=%s", (cachename,) )


        op = 'update' if updating else 'new'
        if insert_content:
            op += ' meta+content'
        else:
            op += ' meta'

        ukmedia.DBUG2( u"%s: %s [a%s %s ] ('%s' %s)\n" % (
            art['srcorgname'] if 'srcorgname' in art else srcorg,
            op,
            article_id,
            art['srcurl'],
            art['byline'],
            ','.join( [ '[j%s]'%(j) for j in art['journos'] ] )
            ))
        return article_id



    def find_article(self,known_urls):
        sql = "SELECT DISTINCT article_id FROM article_url WHERE url IN (" + ','.join(['%s' for u in known_urls]) + ")"
        c = DB.conn().cursor()
        c.execute(sql,list(known_urls))
        return [row['article_id'] for row in c]



def CheckArticle(art):
    tagpat = re.compile( "<.*?>", re.UNICODE )
#   entpat = re.compile( "&((\w\w+)|(#[0-9]+)|(#[xX][0-9a-fA-F]+));", re.UNICODE )

    # make sure all the urls are listed
    assert art['permalink'] in art['urls']
    assert art['srcurl'] in art['urls']

    # check for missing/null fields
    for f in ('title','permalink','srcurl','lastscraped','pubdate','srcorg','journos' ):
        assert f in art, "missing '%s' field!" % (f,)
        assert art[f] is not None, "null '%s' field!" % (f,)

    # check for empty strings
    for f in ('title', 'permalink', 'srcurl'):
        s = art[f]
        assert s.strip() != u'', "blank '%s' field!" % (f,)

    # if content present, make sure it's not empty
    if 'content' in art:
        assert art['content'] != ""

#   print "CheckArticle byline: ["+art['byline']+"]"
    # make sure assorted fields are unicode
    for f in ( 'title', 'byline', 'description', 'content' ):   #, 'permalink', 'srcurl','srcid' ):
        if f in art:
            assert isinstance(art[f], unicode), "'%s' is not unicode" % (f,)

    # check title and byline are single-line
    for f in ( 'title','byline' ):
        s = art[f]
        assert s == s.strip(), "%s has leading/trailing whitespace ('%s')" % (f, s.encode('utf-8','replace'))
        assert s.find("\n") == -1, "multi-line %s ('%s')" % (f,s.encode('utf-8','replace'))

    # check for unwanted html tags & entities
    for f in ( 'title','byline','description' ):
        if f in art:
            s = art[f]
            assert s==ukmedia.DescapeHTML( s ), "%s contains html entities ('%s')" % (f,s.encode('utf-8','replace'))
            assert not tagpat.search(s), "%s contains html tags ('%s')" % (f,s.encode('latin-1','replace'))
    
    # fix link URLs, then check for relative links
    for f in ('content', 'bio'):
        if f in art:
            # TODO: fix links elsewhere!
            art[f] = FixLinkURLs(art[f])


def FixLinkURLs(html):
    """ Fix common errors in URLs in hyperlinks. """
    # Using regexps works better than BeautifulSoup for this.
    
    def fixup(match):
        url = match.group(1) or match.group(2) or ''
        url = re.sub('http:/(?=[^/])', 'http://', url)
        url = re.sub('https:/(?=[^/])', 'https://', url)
        url = re.sub(r'e?mail\s*to[:=]\s*', 'mailto:', url)
        url = re.sub(r'^(?=[a-zA-Z\.]+@[a-zA-Z\.]+)', 'mailto:', url)
        url = re.sub(r'^(?:http\.)?www\.', 'http://www.', url)
        return 'href="%s"' % url

    return re.sub(r'''href\s*=\s*(?:['"](.*?)['"]|(\S*?)(?=\>))''', fixup, html)



