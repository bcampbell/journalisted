<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';

class ADMIN_PAGE_JL_SUMMARY {
    function ADMIN_PAGE_JL_SUMMARY() {
        $this->id = 'summary';
        $this->navname = 'Summary';
    }

    function display() {
        $articles = db_getOne ('SELECT COUNT(*) FROM article' );
        print "$articles Articles in database";
    }
}


class ADMIN_PAGE_JL_ARTICLELIST {
    function ADMIN_PAGE_JL_ARTICLELIST() {
        $this->id = 'articlelist';
        $this->navname = 'List of Articles';
    }

    function display() {
        $articles = db_getOne ('SELECT COUNT(*) FROM article' );
        print "<p>$articles Articles in database</p>";

        $orgs = array();
        $foo = db_getAll('SELECT id,shortname FROM organisation' );
        foreach( $foo as $f ) {
            $orgs[ $f['id'] ] = $f['shortname'];
        }
 
        $q = db_query( 'SELECT id,title,byline,description,pubdate,firstseen,lastseen,permalink,srcurl,srcorg,srcid FROM article ORDER BY firstseen DESC' );
 
        print "<table border=1>\n";
        while( $r=db_fetch_array($q) ) {

            $artlink = "?page=article&article_id={$r['id']}";

            $out = '';
            $out .= "<td>{$r['firstseen']}</td>";
            $out .= "<td>{$orgs[$r['srcorg']] }</td>";
            $out .= "<td><a href=\"{$artlink}\">{$r['id']}</a></td>";
            $out .= "<td>{$r['title']}</td>";
            $out .= "<td>{$r['byline']}</td>";
            print "<tr>$out</tr>\n";
        }
        print "</table>";
    }
}


class ADMIN_PAGE_JL_ARTICLE {
    function ADMIN_PAGE_JL_ARTICLE() {
        $this->id = 'article';
        $this->navname = 'Article';
    }

    function display() {

        $article_id = get_http_var( 'article_id' );

        $q = db_query( 'SELECT id,title,byline,description,pubdate,firstseen,lastseen,content,permalink,srcurl,srcorg,srcid FROM article WHERE id=?', $article_id );

        $art = db_fetch_array($q);

        print "<table border=1>\n";
        print "<tr><th>title</th><td><h2>{$art['title']}</h2></td></tr>\n";
        print "<tr><th>ID</th><td>{$art['id']}</td></tr>\n";
        print "<tr><th>pubdate</th><td>{$art['pubdate']}</td></tr>\n";
        print "<tr><th>byline</th><td>{$art['byline']}</td></tr>\n";
        print "<tr><th>description</th><td>{$art['description']}</td></tr>\n";
        print "<tr><th>permalink</th><td><a href=\"{$art['permalink']}\">{$art['permalink']}</a></td></tr>\n";

        print "<tr><th>content</th><td>\n";
        
        print "<table>\n";
        print "<tr><th>displayed</th><th>source HTML</th></tr>\n";
        print "<tr><td width=\"50%\">\n{$art['content']}\n</td>\n";
        print "<td width=\"50%\">\n";
        $srchtml = htmlentities( $art['content'], ENT_COMPAT, 'UTF-8' );
        $srchtml = str_replace( "\n", "<br>\n", $srchtml );
        print $srchtml;
        print "\n</td></tr>\n";
        print "</table>\n";

        print "</td></tr>\n";
        $orgname = db_getOne( 'SELECT shortname FROM organisation WHERE id=?', $art['srcorg'] );
        print "<tr><th>srcorg</th><td>{$art['srcorg']} ({$orgname})</td></tr>\n";
        print "<tr><th>srcid</th><td>{$art['srcid']}</td></tr>\n";
        print "<tr><th>srcurl</th><td>{$art['srcurl']}</td></tr>\n";
        print "</table>\n";

        
        print "<table border=1>\n";
        while( $r=db_fetch_array($q) ) {
            $out = '';
            $out .= "<td>{$r['id']}</td>";
            $out .= "<td>{$r['title']}</td>";
            $out .= "<td>{$r['byline']}</td>";
            $out .= "<td>{$r['permalink']}</td>";
            print "<tr>$out</tr>\n";
        }
        print "</table>";
    }
}

?>
