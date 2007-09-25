<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


page_header( "" );

// recalculate front page once every 4 hours
cache_emit( 'frontpage', 'emit_front_page', 4*60*60 );

// smallcolumn not part of cached section - depends on user if logged in
?>
<div id="smallcolumn">
<?php

$P = person_if_signed_on(true);
if( $P )
	emit_my_journos_box($P);
// else what?
?>
</div>
<?php

page_footer();


function emit_front_page()
{
	$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );

?>
<div id="contenthead">
<img src="img/paper.png" alt="" />

<form action="list" method="get">
 <label for="name">Find out more about a journalist</label>
<input type="text" value="" title="type journalist name here" id="name" /><input type="submit" value="Find" />
</form>

<form action="/list" method="get">
 <label for="outlet">Track down a journalist by news outlet</label>
  <select name="outlet">
<?php
	foreach( $orgs as $o )
		print "   <option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
?>
  </select>
 <input type="submit" value="Find" />
</form>


<form action="list" method="get">
 <label for="find">Find articles containing</label>
 <input type="text" value="" title="type keywords here" id="find" />
 <input type="submit" value="Find" />
</form>

</div>


<div id="maincolumn">
<?php

	emit_stats();
	emit_whoswritingaboutwhat();
?>
</div>


<?php

}




function emit_stats()
{

?>
<div class="boxwide">
<h2>Latest</h2>
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
	// Cheesy hack - discard the top entries as they're likely to be
	// not so interesting (brown, london, government,labour etc...)
	// so we'll just arbitarily pick the 10th.
	$sql = <<<EOT
SELECT t.tag, count(DISTINCT attr.journo_id)
	FROM ((article_tag t INNER JOIN journo_attr attr ON t.article_id=attr.article_id) INNER JOIN article a ON a.id=t.article_id )
	WHERE t.kind<>'c' AND a.status='a' AND a.pubdate>=NOW() - interval '1 day' AND a.pubdate<NOW()
	GROUP BY t.tag
	ORDER BY count DESC
	LIMIT 1
	OFFSET 10
EOT;
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


function emit_whoswritingaboutwhat()
{
?>

<div class="boxwide">
<h2>Who's writing about what?</h2>
<p>
Here are some topics which have appeared frequently in the last 24 hours:
</p>

<?php

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON ( a.id=t.article_id AND t.kind <> 'c') ) ".
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


function emit_my_journos_box( &$P )
{
?>
 <div class="boxnarrow">
  <h2>My Journa-lists</h2>
<?php

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */
	if( $P )	
	$q = db_query( "SELECT a.id,a.journo_id, j.prettyname, j.ref " .
		"FROM (alert a INNER JOIN journo j ON j.id=a.journo_id) " .
		"WHERE a.person_id=? ORDER BY j.lastname", $P->id );
	print "  <ul>\n";
	while( $row=db_fetch_array($q) )
	{
		$journourl = "/{$row['ref']}";
		printf( "   <li><a href=\"%s\">%s</a></li>\n", $journourl, $row['prettyname'] );
	}
	print( "  </ul>\n" );

?>
 </div>
<?php
}

?>
