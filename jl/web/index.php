<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


page_header( "", array( 'menupage'=>'cover') );

// recalculate front page once every 4 hours
cache_emit( 'frontpage', 'emit_front_page', 4*60*60 );

// smallcolumn not part of cached section - depends on user if logged in
?>
<div id="smallcolumn">
<?php

$P = person_if_signed_on(true);
if( $P )
	emit_my_journos_box($P);
else
	emit_recent_journos_box();
?>
</div>
<?php

page_footer();


function emit_front_page()
{
	$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );

?>
<div id="contenthead">
<img src="/images/paper.png" alt="" />

<form action="/list" method="get">
 <label for="name">Find out more about a journalist</label>
<input type="text" value="" title="type journalist name here" id="name" name="name" class="text" /><input type="submit" value="Find" />
</form>

<form action="/list" method="get">
 <label for="outlet">Track down a journalist by news outlet</label>

  <select name="outlet" class="select" >
<?php
	foreach( $orgs as $o )
		print "   <option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
?>
  </select>
 <input type="submit" value="Find" />
</form>


<form action="/list" method="get">
 <label for="find">Journalist vs journalist</label>

 <input type="text" value="" title="type subject here" id="search" name="search" class="text" />
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
<div class="boxwide latest">
<h2>Latest</h2>
<div class="boxwide-content">
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
</div>
<?php
}


function emit_whoswritingaboutwhat()
{
?>

<div class="boxwide tags">
<h2>The most written about subjects today are...</h2>
<div class="boxwide-content">

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
<p>...click one to see which journalists are writing about it</p>
</div>
</div>
<?php

}


function emit_my_journos_box( &$P )
{
?>
 <div class="boxnarrow myjournos">
  <h2>My Journa-lists</h2>
  <div class="boxnarrow-content">
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
 </div>
<?php


}


function emit_recent_journos_box()
{
?>
 <div class="boxnarrow recentjournos">
 <div class="boxnarrow-content">

  <h2>Recently Published</h2>
  <p>Some journalists who have written articles today:</p>
  <ul>
<?php

	// get 20 of them, just in case some have written multiple articles...
	$sql = <<<EOT
SELECT j.prettyname,j.ref
	FROM ( (article a INNER JOIN journo_attr attr ON attr.article_id=a.id)
		INNER JOIN journo j ON attr.journo_id=j.id )
	WHERE a.pubdate > NOW()-interval '1 day'
	ORDER BY a.pubdate desc
	LIMIT 20;
EOT;

	$q = db_query( $sql );
	$uniq = array();
	while( $row=db_fetch_array($q) )
	{
		$journourl = "/{$row['ref']}";
		$uniq[ $row['prettyname'] ] = $journourl;
	}

	$cnt = 0;
	foreach( $uniq as $name=>$url )
	{
		++$cnt;
		if( $cnt >= 10 )
			break;
		printf( "   <li><a href=\"%s\">%s</a></li>\n", $url, $name );
	}

?>
  </ul>
 </div>
 </div>

<?php
}

?>
