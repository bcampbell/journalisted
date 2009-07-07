<?php

/*
 * offline_tags.php
 *
 * For generating the tags page, which is generated via a cronjob instead of
 * on-demand becasue it's got some big slow queries.
 *
 */

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



function offline_tags_emit()
{

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE t.kind<>'c' AND a.pubdate<NOW()+interval '24 hours' AND a.pubdate > NOW() - interval '24 hours' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 64";
	$tags_24hrs = db_getAll( $sql );

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE t.kind<>'c' AND a.pubdate<NOW()+interval '24 hours' AND a.pubdate > NOW() - interval '1 week' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 64";
	$tags_week = db_getAll( $sql );

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE t.kind<>'c' AND a.pubdate<NOW()+interval '24 hours' AND a.pubdate > NOW() - interval '1 year' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 128";
	$tags_year = db_getAll( $sql );


?>
<h2>Most written about subjects</h2>


<p>The larger the word, the more it's been written about. Click on any of
the words and you'll see which articles mention it.</p>

<div class="box">
<h3>Last 24 hours</h3>
<div class="box-content">
<div class="tags">
<?php tag_cloud_from_getall( $tags_24hrs ); ?>
</div>
</div>
</div>

<div class="box">
<h3>Over the last week</h3>
<div class="box-content">
<div class="tags">
<?php tag_cloud_from_getall( $tags_week ); ?>
</div>
</div>
</div>

<div class="box">
<h3>Over the last Year</h3>
<div class="box-content">
<div class="tags">
<?php tag_cloud_from_getall( $tags_year ); ?>
</div>
</div>
</div>

<?php 

}


