<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


page_header( "" );

// recalculate front page once every 4 hours
cache_emit( 'frontpage', 'emit_front_page', 4*60*60 );

page_footer();


function emit_front_page()
{
	$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );

?>

<h2>At Journa-list, you can:</h2>

<p>
<form action="list" method="get">
Find a journalist by name:
<input type="text" name="name" value=""/>
<input type="submit" value="Find" />
</form>
</p>

<p>
<form action="/list" method="get">
Browse Journalists by news outlet:
<select name="outlet">
<?php

	foreach( $orgs as $o )
	{
		print "<option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
	}

?>
</select>
<input type="submit" value="Find">
</form>

<p>
<form action="article" method="get">
Find articles containing:
<input type="text" name="find" value=""/>
<input type="submit" value="Find" />
<small>(within the last 7 days)</small>
</form>
</p>

<br>
<br>
<?php

	emit_stats();

?>
<div class="block">
<h3>Who's writing about what?</h3>
<p>
Here are some topics which have appeared frequently in the last 24 hours:
</p>

<?php

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE a.pubdate > NOW() - interval '24 hours' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 32";
	$q = db_query( $sql );
	tag_cloud_from_query( $q );

?>
<p>Click one to see who writes about it!</p>
</div>
<?php

}

function emit_stats()
{

?>
<div class="block">
<ul>
<?php

	// which journo has written the most articles today?
	$sql = "SELECT j.prettyname, j.ref, count(*) ".
		"FROM ((article a INNER JOIN journo_attr attr ON attr.article_id=a.id) ".
			"INNER JOIN journo j ON attr.journo_id=j.id) ".
		"WHERE a.status='a' ".
			"AND a.pubdate > NOW()-interval '1 day' ".
			"AND a.pubdate <= NOW() ".
		"GROUP BY j.ref,j.prettyname ".
		"ORDER BY count DESC ".
		"LIMIT 1";

	$r = db_getRow( $sql );
	if( $r )
	{
		$journo_url = "/" . $r['ref'];
		printf( "<li><a href=\"%s\">%s</a> has written more today than any other journalist</li>\n",
			$journo_url, $r['prettyname'] );
	}

	// "<N> journos have written about <X> today"
	// how many journos have mentioned each topic over the last day?
	// Cheesy hack - discard the top 15 entries as they're likely to be "britain" 
	// "british" "us" etc etc etc
	// so we'll just arbitarily pick the 15th.
	$sql ="SELECT t.tag, count(DISTINCT attr.journo_id) FROM ((article_tag t INNER JOIN journo_attr attr ON t.article_id=attr.article_id) INNER JOIN article a ON a.id=t.article_id ) WHERE a.status='a' AND a.pubdate>=NOW() - interval '1 day' AND a.pubdate<NOW() GROUP BY t.tag ORDER BY count DESC LIMIT 1 OFFSET 15";
	$r = db_getRow( $sql );
	if( $r )
	{
		$taglink = tag_gen_link( $r['tag'] );
		printf( "<li>%d journalists have written about <a href=\"%s\">%s</a> today</li><br>\n",
			$r['count'], $taglink, $r['tag'] );
	}
?>
</ul>
</div>
<?php
}


?>
