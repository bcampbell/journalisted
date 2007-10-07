<?php

/* 
 * TODO: per week stats for journo (articles written this week)
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';




$tag = get_http_var( 'tag', null );
if( $tag )
{
	// list journos who've used that tag.
	page_header( "Journalists who have mentioned \"{$tag}\"", array( 'menupage'=>'') );
	print "<h2>Journalists who have mentioned \"{$tag}\"</h2>";
	tag_emit_journo_list( $tag );
	page_footer();
}
else
{
	// no tag specified - display summary (cache for 12 hours)
	page_header( 'Subject Index', array( 'menupage'=>'subject') );
	cache_emit( "tags", "emit_tag_summaries", 60*60*12 );
	page_footer();
}



function emit_tag_summaries()
{

?>
<h2>Most written about topics</h2>

<div class="block">
<h3>Last 24 hours</h3>
<?php

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE a.pubdate > NOW() - interval '24 hours' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 64";
	$q = db_query( $sql );
	tag_cloud_from_query( $q );

?>
</div>

<div class="block">
<h3>Over the last week</h3>
<?php

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE a.pubdate > NOW() - interval '1 week' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 64";
	$q = db_query( $sql );
	tag_cloud_from_query( $q );

?>
</div>

<div class="block">
<h3>All Time</h3>
<?php

	$sql = "SELECT tag, SUM(freq) AS freq ".
		"FROM article_tag ".
		"GROUP BY tag ".
		"ORDER BY freq DESC ".
		"LIMIT 128";
	$q = db_query( $sql );

	tag_cloud_from_query( $q );

?>
</div>
<?php 

}


