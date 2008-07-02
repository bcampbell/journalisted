<?php

/* 
 * TODO: per week stats for journo (articles written this week)
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



//$period = get_http_var( 'period', null );
//$tag = get_http_var( 'tag', null );

// display summary (cache for 12 hours)
page_header( 'Subject Index', array( 'menupage'=>'subject') );
cache_emit( "tags", "emit_tag_summaries", 60*60*12 );
page_footer();



function emit_tag_summaries()
{

?>
<h2>Most written about topics</h2>


<p>The larger the word, the more it's been written about. Click on any of
the words and you'll see which journalists have written about it.</p>


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
<h3>All Time (since May 2007)</h3>
<?php
	/* TODO: (since May 2007) should really be derived from a DB query,
    but it's not a big deal unless someelse reuses the code for another
    dataset :-) */
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


