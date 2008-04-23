<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


// setup for placeholder text in input forms
$head_extra = <<<EOT
  <script type="text/javascript" language="JavaScript">
    window.onload=function() {
      activatePlaceholders();
    }
  </script>
EOT;

page_header( "", array( 'menupage'=>'cover', 'head_extra'=>$head_extra ) );

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

	$contactemail = OPTION_TEAM_EMAIL;

?>
<div id="contenthead">
<img src="/images/paper.png" alt="" />

<p>Journalisted is an independent, non-commercial website built to help the public navigate the news</p>

<form action="/list" method="get" class="frontform">
 <label for="name">Find out more about a journalist</label>
<input type="text" value="" title="type journalist name here" id="name" name="name" placeholder="type journalist name here" class="text" />
<input type="submit" value="Find" />
</form>

<form action="/list" method="get" class="frontform">
 <label for="outlet">Track down a journalist by news outlet</label>

  <select name="outlet" class="select" >
<?php
	foreach( $orgs as $o )
		print "   <option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
?>
  </select>
 <input type="submit" value="Find" />
</form>


<form action="/list" method="get" class="frontform">
 <label for="find">Find articles about different subjects</label>

 <input type="text" value="" title="type subject here" id="search" name="search" class="text" placeholder="type subject here"/>
 <input type="submit" value="Find" />
</form>


<p>This website is in beta - all information is generated automatically so there are bound to be mistakes. Please
<?=SafeMailto( $contactemail, 'let us know' );?>
 when you find one so we can correct it</p>

</div>


<div id="maincolumn">
<?php

	emit_stats();
	emit_whoswritingaboutwhat();
?>
</div>


<?php

}


// helper fn
// return number of journos who've had articles published
// in the past $interval (eg '1 day', '5 hours' etc...)
// NOTE: inactive/hidden journos are included in the count
function count_published_journos( $interval )
{
	$sql = <<<EOT
SELECT count( DISTINCT journo_id )
	FROM journo_attr
	WHERE article_id IN	(
		SELECT id
			FROM article
			WHERE status='a'
				AND pubdate > NOW()-interval '$interval'
				AND pubdate <= NOW()
		);
EOT;
	return db_getOne( $sql );
}

/* find the most proflic journo over the last $interval
 * only returns active journos and only counts active articles.
 *
 * returns an array with journo details and a 'num_articles' field.
 */
function most_prolific_journo( $interval )
{
	$sql = <<<EOT
SELECT j.prettyname, j.ref, count(*) as num_articles
	FROM ((article a INNER JOIN journo_attr attr ON attr.article_id=a.id)
		INNER JOIN journo j ON attr.journo_id=j.id)
	WHERE a.status='a'
		AND j.status='a'
		AND a.pubdate > NOW()-interval '$interval'
		AND a.pubdate <= NOW()
	GROUP BY j.ref,j.prettyname
	ORDER BY num_articles DESC
	LIMIT 1;
EOT;

	$r = db_getRow( $sql );
	if( !$r )
		return null;

	return array(
		'prettyname' => $r['prettyname'],
		'ref' => $r['ref'],
		'num_articles' => $r['num_articles']
	);
}


function emit_stats()
{

?>
<div class="boxwide latest">
<h2>Latest</h2>
<div class="boxwide-content">
<ul>
<?php

	// how many journos have had articles published today?
	printf( "<li>%d journalists have had articles published today</li>\n", count_published_journos( '1 day' ) );



	// which journo has written the most articles In the last day? week? month?
	$intervals = array(
		'1 day' => '%s has written the most articles today',
		'7 days' => '%s has written the most articles in the last seven days',
		'30 days' => '%s has written the most articles in the last month (30 days)'
	);

	foreach( $intervals as $interval=>$fmt )
	{
		$j = most_prolific_journo( $interval );
		if( $j )
		{
			$journo_link = sprintf( "<a href=\"/%s\">%s</a>", $j['ref'], $j['prettyname'] );
			printf( "<li>$fmt</li>\n", $journo_link );
		}
	}


	// "<N> journos have written about <X> today"
	// how many journos have mentioned each topic over the last day?
	// Cheesy hack - discard the top entries as they're likely to be
	// not so interesting (brown, london, government,labour etc...)
	// so we'll just arbitarily pick the 10th.


	printf( "<li>Some of today's most popular subjects:<ul>" );

	$sql = <<<EOT
SELECT t.tag, count(DISTINCT attr.journo_id)
	FROM ((article_tag t INNER JOIN journo_attr attr ON t.article_id=attr.article_id) INNER JOIN article a ON a.id=t.article_id )
	WHERE t.kind<>'c' AND a.status='a' AND a.pubdate>=NOW() - interval '1 day' AND a.pubdate<NOW()
	GROUP BY t.tag
	ORDER BY count DESC
	LIMIT 3
	OFFSET 10
EOT;
	$q = db_query( $sql );
	while( $r = db_fetch_array( $q ) )
	{
		$taglink = tag_gen_link( $r['tag'], null, 'today' );
		printf( "<li>%d journalists have written about '<a href=\"%s\">%s</a>'</li>",
			$r['count'], $taglink, $r['tag'] );
	}

	print( "</ul></li>\n" );

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

	$sql = <<<EOT
SELECT t.tag AS tag, SUM(t.freq) AS freq
	FROM ( article a INNER JOIN article_tag t ON ( a.id=t.article_id AND t.kind <> 'c') )
	WHERE a.status='a'
		AND a.pubdate > NOW() - interval '24 hours'
		AND a.pubdate < NOW()
	GROUP BY t.tag
	ORDER BY freq DESC
	LIMIT 32;
EOT;
	$q = db_query( $sql );
	tag_cloud_from_query( $q, null, 'today' );

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

// returns a human-readable string representing an interval
// (very coarse grained - rounds to appropriate whole units
// eg "1 day")
function prettyinterval( $seconds )
{
	$inmins = (int)($seconds/60);
	$inhours = (int)($seconds/(60*60));
	$indays = (int)($seconds/(60*60*24));

	// no less than 1min.
	if( $inmins < 60 )
		return $inmins<=1 ? '1 minute' : "$inmins minutes";
	else if( $inhours < 24 )
		return $inhours==1 ? '1 hour' : "$inhours hours";
	else
		return $indays==1 ? '1 day' : "$indays days";
}



// a list of the journos who were most recently published.
// Well, OK, most recently _scraped_ :-)
// It'd be nicer to use time of publication (pubdate field),
// but a lot of outlets only provide a date, not a time.
// ("firstseen" datetime would be more appropriate, but it's usually
// the same for all articles in each scraper run, which makes for a
// boring list).
function emit_recent_journos_box()
{
?>
 <div class="boxnarrow recentjournos">
 <h2>Recently Published</h2>
 <div class="boxnarrow-content">

<?php

	// want 10, but get 20 of them, just in case some have written multiple articles...
	$sql = <<<EOT
SELECT j.prettyname,j.ref, EXTRACT(EPOCH FROM NOW()-a.lastscraped) as age
	FROM ( (article a INNER JOIN journo_attr attr ON attr.article_id=a.id)
		INNER JOIN journo j ON attr.journo_id=j.id )
	WHERE a.status='a'
		AND a.pubdate > NOW()-interval '1 day'
		AND a.pubdate < NOW()
	ORDER BY a.lastscraped desc
	LIMIT 20;
EOT;

	$q = db_query( $sql );
	/* don't want dupes */
	$uniq = array();
	$maxage = 0;
	while( $row=db_fetch_array($q) )
	{
		$journourl = "/{$row['ref']}";
		$a = (int)$row['age'];
		if( $a > $maxage )
			$maxage = $a;
		$uniq[ $row['prettyname'] ] = array( 'url'=>$journourl, 'age'=>prettyinterval($a) );
	}

?>
  <p>The most recently published Journalists are:</p>
  <ul>
<?php

	/* output the list */
	$cnt = 0;
	foreach( $uniq as $name=>$info )
	{
		++$cnt;
		if( $cnt >= 10 )
			break;
/*		printf( "   <li><a href=\"%s\">%s</a> <small>(%s ago)</small></li>\n", $info['url'], $name, $info['age'] ); */
		printf( "   <li><a href=\"%s\">%s</a></li>\n", $info['url'], $name );
	}

?>
  </ul>
 </div>
 </div>

<?php
}

?>
