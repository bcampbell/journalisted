<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/article.php';
require_once '../phplib/article_rdf.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



// handle either base-10 or base-36 article ids
$article_id = get_http_var( 'id36' );
if( $article_id ) {
    $article_id = article_id36_to_id( $article_id );
} else {
    $article_id = get_http_var( 'id' );
}

$sim_orderby = strtolower( get_http_var( 'sim_orderby', 'score' ) );
$sim_showall = strtolower( get_http_var( 'sim_showall', 'no' ) );

$art = article_collect( $article_id, $sim_orderby, $sim_showall );
if( $art['status'] != 'a' ) {
    return; /* TODO: 404? */
}


$fmt = get_http_var( 'fmt' );
if( $fmt=="rdfxml" ) {
    header( "Content-Type: application/rdf+xml" );
    article_emitRDFXML( $art );
    return;
} else {
    emit_page_article( $art );
}

function emit_page_article( $art )
{
    $pagetitle = $art['title'];
    $params = array( 'canonical_url'=>article_url( $art['id'] ) );
    page_header( $pagetitle, $params );

    {
        extract( $art );
        include "../templates/article.tpl.php";
    }
    page_footer();
}

?>
