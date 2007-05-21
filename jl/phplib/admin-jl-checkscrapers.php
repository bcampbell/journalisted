<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */
/*
 * Admin page for checking scrapers.
 * Shows some random articles for manual checking, and displays scraped
 * data side-by-side with the original page.
 */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';




class ADMIN_PAGE_JL_CHECKSCRAPERS {
    function ADMIN_PAGE_JL_CHECKSCRAPERS() {
        $this->id = 'checkscrapers';
        $this->navname = 'Check Scrapers';
    }

    function display() {

        $article_id = get_http_var( 'article_id' );
        if( !$article_id ) {
            $this->pick_random_articles();
            return;
        }

        /* show an article side-by-side with the orignal page (in an iframe) */
        $q = db_query( 'SELECT title,byline,description,content,srcurl FROM article WHERE id=?', $article_id );

        $art = db_fetch_array($q);
?>
<div>

<div style="float:right; width:60%; height:550px;">
<h2>Original Article</h2>
<p>
<?php
        print "<iframe width=\"100%\" height=\"500px\" src=\"{$art['srcurl']}\" scrolling=\"auto\"></iframe>\n";
?>
</p>
</div>
<div style="float:left; width:38%;">


<h2>Scraped Article</h2>
<div style="height:500px; overflow:auto; border: 1px solid black;">
<?php
        print "<p><strong>Title:</strong>{$art['title']}</p>\n";
        print "<p><strong>Byline:</strong><em>{$art['byline']}</em></p>\n";
        print "<div style=\"font:x-small;\">\n{$art['content']}\n</div>\n";
?>
</div>
</div>
</div>
<?php
        
    }

    function pick_random_articles() {

        print "<p>Here are 10 randomly-selected articles scraped within the last 7 days. Click on an article to compare it to the original page...</p>\n";
        print "<ul>\n";

        $orgs = get_org_names();

        $rows = db_getAll( "SELECT id, srcorg, title FROM article " .
            "WHERE lastscraped>(now()-interval '7 days') " .
            "ORDER BY random() LIMIT 10");
        foreach( $rows as $r ) {
            $checkurl = sprintf( "%s&article_id=%d", $this->self_link, $r['id'] );
            printf( "<li><a href=\"%s\">%s</a> - %s</li>\n", $checkurl, $r['title'], $orgs[$r['srcorg']] );
        }
        print "</ul>\n";
    }
    
}

?>
