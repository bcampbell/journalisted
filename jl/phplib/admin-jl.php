<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';

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


class ADMIN_PAGE_JL_ARTICLES {
    function ADMIN_PAGE_JL_ARTICLES() {
        $this->id = 'articles';
        $this->navname = 'Search Articles';
    }

    function display() {

        $orgs = array('any'=>'Any');
        $r = db_query( 'SELECT id, prettyname FROM organisation' );
        while( $row = db_fetch_array($r) )
            $orgs[ $row['id'] ] = $row['prettyname'];



        $form = new HTML_QuickForm('article_query','get','','',null, TRUE );

        $form->addElement( 'select', 'srcorg', 'Organisation', $orgs );
        $form->addElement( 'select', 'period', 'Time Period',
            array( 'all'=>'All', '24hrs'=>'Last 24 hrs', '7days'=>'Last 7 days' ) );
        $form->addElement( 'submit', 'submit', 'Search');

        $form->addElement( 'hidden', 'page', get_http_var( 'page' ) );

        $form->display();

        if( $form->validate() )
            $form->process( 'process_article_query' );
    }
}


function process_article_query( $values )
{
    $period = $values['period'];
    $srcorg = $values['srcorg'];

    $params = array();
    $conds = array();

    if( $period == '24hrs' ) {
        $conds[] = "lastscraped >= (now() - interval ?)";
        $params[] = '24 hours';
    } else if( $period == '7days' ) {
        $conds[] = "lastscraped >= (now() - interval ?)";
        $params[] = '7 days';
    }

    if( $srcorg != 'any' ) {
        $conds[] = "srcorg = ?";
        $params[] = $srcorg;
    }

    $sql = 'SELECT id,title,byline,description,permalink,srcorg,lastscraped ' .
       'FROM article';

    if( $conds ) {
        $sql = $sql . ' WHERE ' . implode( ' AND ', $conds );
    }
    $sql = $sql . ' ORDER BY lastscraped DESC';
 
//    print "<pre>\n";
//    print db_subst( $sql, $params );
//    print "</pre>\n";
    $orgs = get_org_names();

    $q = db_query( $sql, $params );

    printf( "<p>%d matches</p>\n", db_num_rows( $q ) );
    
    print "<table border=1>\n";
    while( $r=db_fetch_array($q) ) {

        $artlink = "?page=checkarticle&article_id={$r['id']}";

        $out = '';
        $out .= "<td>" . prettify($r['lastscraped']) . "</td>";
        $out .= "<td>{$orgs[$r['srcorg']] }</td>";
        $out .= "<td><a href=\"{$artlink}\">{$r['id']}</a></td>";
        $out .= "<td>{$r['title']}</td>";
        $out .= "<td>{$r['byline']}</td>";
        print "<tr>$out</tr>\n";
    }
    print "</table>";
}







class ADMIN_PAGE_JL_ARTICLE {
    function ADMIN_PAGE_JL_ARTICLE() {
        $this->id = 'article';
        $this->navname = 'Article';
        $this->notnavbar = TRUE;
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
        
//        print "<iframe src=\"{$art['srcurl']}\" width=\"100%\" height=\"100%\"></iframe>\n";
        
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




class ADMIN_PAGE_JL_CHECKARTICLE {
    function ADMIN_PAGE_JL_CHECKARTICLE() {
        $this->id = 'checkarticle';
        $this->navname = 'Check Article';
        $this->notnavbar = TRUE;
    }

    function display() {

        $article_id = get_http_var( 'article_id' );

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
}
?>
