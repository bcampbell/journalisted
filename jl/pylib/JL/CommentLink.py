#
# CommentLink.py
#
# For adding links to comments elsewhere on the web about
# articles in our db.
# The "article_commentlink" table.
#


import DB


def upsert(article_id, commentlink):
    """insert/replace commentlink"""
    c = DB.conn().cursor()

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

