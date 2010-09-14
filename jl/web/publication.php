<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/article.php';
require_once '../phplib/journo.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


$pub_id = get_http_var( 'id' );

if( !$pub_id ) {
    page_header( "Publications" );
    list_publications();
    page_footer();
    return;
}


$publication = publication_collect( $pub_id );

if( is_null( $publication ) ) {
    // TODO: 404
    return;
}


$fmt = get_http_var( 'fmt' );
if( $fmt=="rdfxml" ) {
    header( "Content-Type: application/rdf+xml" );
    return;
} else {
    $pagetitle = $publication['prettyname'];
    #    $params = array( 'canonical_url'=>publication_url( $publication ) );
    $params = array();
    page_header( $pagetitle, $params );
    {
        extract( $publication );
        include "../templates/publication.tpl.php";
    }
    page_footer();
}



function publication_collect( $pub_id ) {
    $p = db_getRow( "SELECT * FROM organisation WHERE id=?", $pub_id );

    /* recent articles */
    $arts = db_getAll( "SELECT id,title,pubdate,permalink FROM article WHERE srcorg=? ORDER BY pubdate DESC LIMIT 10", $pub_id );
    foreach( $arts as &$a ) {
        article_augment( $a );
    }
    unset( $a );
    $p['recent_articles'] = $arts;


    /* recent journos */

    $sql = <<<EOT
SELECT DISTINCT j.ref, j.prettyname FROM
    ( ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
        INNER JOIN article a ON a.id=attr.article_id)
    WHERE a.srcorg=?
        AND a.status='a'
        AND a.pubdate > NOW() - INTERVAL '1 week';
EOT;
    $journos = db_getAll( $sql, $pub_id );
    $p['recent_journos'] = $journos;
    return $p;
}


function publication_link( &$pub ) {
    return sprintf( "<a href=\"/publication?id=%d\">%s</a>",
       intval($pub['id']), $pub['prettyname'] );
}



function list_publications() {

    $pubs = db_getAll( "SELECT id,shortname,prettyname FROM organisation ORDER BY prettyname" );

?>
<div class="main">
<div class="head"><h2>Publications</h2></div>
<div class="body">
  <ul>
<?php foreach( $pubs as $pub ) { ?>
    <li><?= publication_link( $pub ) ?></li>
<?php } ?>
  </ul>
</div>
<div class="foot"></div>
</div>  <!-- end main -->
<?php
}

?>
