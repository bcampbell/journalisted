<?php

/* 
 * TODO: per week stats for journo (articles written this week)
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



$article_id = get_http_var( 'id' );
$art = db_getRow( 'SELECT * FROM article WHERE id=?', $article_id );


$pagetitle = $art['title'];
page_header( array( 'title'=>$pagetitle ));
emit_article_info( $art );

page_footer();


function emit_article_info( $art )
{
	$article_id = $art['id'];
	$title = $art['title'];
	$byline = $art['byline'];
	$orgs = get_org_names();
	$org = $orgs[ $art['srcorg'] ];
	
	$pubdate = strftime('%a %e %B %Y', strtotime($art['pubdate']) );
	$desc = $art['description'];

	print "<h2>$title</h2>\n";
	print "$byline<br>\n";
	print "$org, $pubdate<br>\n";
	print "<p>$desc</p>\n";

	print "<a href=\"{$art['permalink']}\">Read the original article at $org</a>\n";

?>

<br>
<br>
<div class="block">
<h3>Tags</h3>

<?php
	$q = db_query( 'SELECT tag, freq FROM article_tag WHERE article_id=? ORDER BY freq DESC', $article_id );
	tag_cloud_from_query( $q );
?>
</div>
<?php

}

