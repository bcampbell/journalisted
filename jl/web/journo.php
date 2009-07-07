<?php

//phpinfo();

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );
$journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
if(!$journo)
{
    header("HTTP/1.0 404 Not Found");
    exit(1);
}


$pageparams = array(
    'rss'=>array( 'Recent Articles'=>journoRSS( $journo ) )
);



if( get_http_var( 'allarticles' ) == 'yes' ) {
    $title = "All articles by " . $journo['prettyname'];
    page_header( $title, $pageparams );

?>
<div id="maincolumn">
<?php    journo_emitAllArticles( $journo ); ?>
</div>
<div id= "smallcolumn">
</div>
<?php

    page_footer();
    exit;
}

$title = $journo['prettyname'];
page_header( $title, $pageparams );

// just use journo id to index cache... other pages won't clash.
$cacheid = $journo['id'];

if( strtolower( get_http_var('full') == 'yes' ) ) {
    /* force a full page rebuild (slow) */
    ob_start();
    journo_emitPageFull( $journo );
    $content = ob_get_contents();
	ob_end_clean();

    db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)", $cacheid, $content );
    db_do( "UPDATE journo SET modified=false WHERE id=?", $journo['id'] );
	db_commit();
}


$cached_content = db_getOne( "SELECT content FROM htmlcache WHERE name=?", $cacheid );
if( !is_null( $cached_content ) ){
    /* yay! it's there! */

    if( $journo['modified'] == 't' ) {
?>
<!-- NOTE: page is marked for rebuilding -->
<?php
    }

    print $cached_content;
} else {
    /* uh-oh... page is missing from cache... */

    /* mark journo as needing their page sorted out! */
    db_do("UPDATE journo SET modified=true WHERE id=?", $journo['id'] );
    db_commit();

    /* output the quick version of page (no stats or tags etc) */
    ob_start();
    journo_emitPageQuick( $journo );
    $content = ob_get_contents();
	ob_flush();

    /* save it in the cache for next time, but don't clear the modified flag */
//    db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)", $cacheid, $content );
	db_commit();
}

page_footer();



/*
 * HELPERS
 */

