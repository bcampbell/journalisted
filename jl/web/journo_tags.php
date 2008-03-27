<?php

/* 
 * Page to show articles by a journo containing particular tags
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */
$ref = strtolower( get_http_var( 'ref' ) );
$journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=? AND status='a'", $ref );

if(!$journo)
{
	header("HTTP/1.0 404 Not Found");
	exit(1);
}

page_header( $journo['prettyname'] . " - " . OPTION_WEB_DOMAIN );


$tag = get_http_var( 'tag', '' );

printf( "<h2>Articles by <a href=\"%s\">%s</a> mentioning \"%s\"</h2>",
	'/' . $journo['ref'],
	$journo['prettyname'],
	$tag );
tag_emit_matching_articles( $tag, $journo['id'] );

/*
Disabled by Francis - too slow, and Googlebot was crawling them all! 
Needs a table with the frequency counts precomputed.
print "<h2>Other journalists who have mentioned \"{$tag}\"</h2>";
tag_emit_journo_list( $tag, $journo['id'] );
*/

page_footer();

?>
