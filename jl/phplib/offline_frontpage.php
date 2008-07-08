<?php
/* functions for building up the front page (/web/index.php)
 *
 * Because there are so many slow queries on the front page
 * we will build the bulk of the it offline (via a cronjob)
 * and store the resulting HTML snippet in the htmlcache
 * table, where index.php can easily pick it up.
 *
 * see also "../bin/build-front-page"
 */

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


function offline_frontpage_emit()
{

	$contactemail = OPTION_TEAM_EMAIL;
	emit_stats();
	emit_whoswritingaboutwhat();
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


?>
