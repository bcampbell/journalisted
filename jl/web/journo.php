<?php

/* 
 * TODO: per week stats for journo (articles written this week)
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );

$journo = db_getRow( 'SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?', $ref );

if(!$journo)
{
	header("HTTP/1.0 404 Not Found");
	exit(1);
}

$rssurl = sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );

$pageparams = array(
	'rss'=>array( 'Recent Articles'=>$rssurl )
);

$title = $journo['prettyname'] . " - " . OPTION_WEB_DOMAIN;
page_header( $title, $pageparams );

// emit the main part of the page (cached for up to 2 hours)
$cacheid = sprintf( "j%d", $journo['id'] );
cache_emit( $cacheid, "emit_journo", 60*60*2 );

page_footer();



/*
 * HELPERS
 */

// emit the cacheable part of the page
function emit_journo()
{
	global $journo;
	printf( "<h2>%s</h2>\n", $journo['prettyname'] );

	/* main pane */

	print "<div id=\"mainpane\">\n";
	emit_block_overview( $journo );
	emit_blocks_articles( $journo, get_http_var( 'allarticles', 'no' ) );
	emit_block_friendlystats( $journo );
	emit_block_tags( $journo );
	emit_block_bynumbers( $journo );
	print "</div> <!-- end mainpane -->\n";

	/* side pane */

	print "<div id=\"sidepane\">\n\n";

	emit_block_links( $journo );
	$rssurl = sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );
	emit_block_rss( $journo, $rssurl );
	emit_block_alerts( $journo );
	//emit_block_tags( $journo );
	emit_block_searchbox( $journo );

	?>
	<div class="block">
	<h3>Something wrong/missing?</h3>
	<p>
	Have we got the wrong information about this journalist?
	<a href="mailto:team@journa-list.dyndns.org?subject=Problem with <?php echo $journo['prettyname']; ?>'s page">Let us know</a>
	</p>
	</div>

	<?php

	print "</div> <!-- end sidepane -->\n";	// end sidepane
}




function emit_blocks_articles( $journo, $allarticles )
{

?>
<div class="block">
<h3>Most recent article</h3>
<?php

	$journo_id = $journo['id'];
	$orgs = get_org_names();

	$sql = "SELECT a.id,a.title,a.description,a.pubdate,a.permalink,a.srcorg " .
		"FROM article a,journo_attr j " .
		"WHERE a.status='a' AND a.id=j.article_id AND j.journo_id=? " .
		"ORDER BY a.pubdate DESC";
	$sqlparams = array( $journo_id );

	$maxprev = 10;	/* max number of previous articles to list by default*/
	if( $allarticles != 'yes' )
	{
		$sql .= ' LIMIT ?';
		/* note: we try to fetch 2 extra articles - one for 'most recent', the
   		other so we know to display the "show all articles" prompt. */
		$sqlparams[] = 1 + $maxprev + 1;
	}

	$q = db_query( $sql, $sqlparams );

	if( $r=db_fetch_array($q) )
	{
		$title = $r['title'];
		$org = $orgs[ $r['srcorg'] ];
		$pubdate = pretty_date(strtotime($r['pubdate']));
		$desc = $r['description'];
		print "<p>\n";
		print "\"<a href=\"/article?id={$r['id']}\">{$r['title']}</a>\", {$pubdate}, <em>{$org}</em>\n";
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small>\n";
		print "<blockquote>{$desc}</blockquote>\n";
		print "</p>\n";

	}

//	printf( "<a href=\"http://%s/%s/rss\"><img src=\"/img/rss.gif\"></a>\n",
//		OPTION_WEB_DOMAIN,
//		$journo['ref'] );

?>
</div>

<div class="block">
<h3>Previous articles</h3>
<ul>
<?php
	$count=0;
	while( 1 )
	{
		if( $allarticles!='yes' && $count >= $maxprev )
			break;
		$r=db_fetch_array($q);
		if( !$r	)
			break;
		++$count;

		$title = $r['title'];
		$org = $orgs[ $r['srcorg'] ];
		$pubdate = pretty_date(strtotime($r['pubdate']));
		$desc = $r['description'];
		print "<li>\n";
		print "<a href=\"/article?id={$r['id']}\">{$r['title']}</a>, {$pubdate}, <em>{$org}</em>\n";
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small>\n";
		print "</li>\n";

	}

?>
</ul>
<?php

	/* if there are any more we're not showing, say so */
	if( db_fetch_array($q) )
	{
		print "<a href=\"/{$journo['ref']}?allarticles=yes\">[Show all previous articles...]</a>\n";
	}

?>
</div>

<?php

}


// some friendly (sentence-based) stats
function emit_block_friendlystats( $journo )
{
	$journo_id = $journo['id'];

	// stats for this journo
	$stats = FetchJournoStats( $journo );

	// get average stats for all journos
	$avg = FetchAverages();

?>
<div class="block">
<h3><?php echo $journo['prettyname']; ?> has written...</h3>
<ul>
<?php
	// more about <X> than anything else
	if( array_key_exists( 'toptag_alltime', $stats ) )
	{
		$link = tag_gen_link( $stats['toptag_alltime'], $journo['ref'] );
		printf( "<li>More about '<a href =\"%s\">%s</a>' than anything else</li>", $link, $stats['toptag_alltime'] );
	}
	// a lot about <Y> in the last month
	if( array_key_exists( 'toptag_month', $stats ) )
	{
		$link = tag_gen_link( $stats['toptag_month'], $journo['ref'] );
		printf( "<li>A lot about '<a href =\"%s\">%s</a>' in the last month</li>", $link, $stats['toptag_month'] );
	}

	// more or less articles than average journo?
	print( "<li>\n" );
	$diff = (float)$stats['num_articles'] / (float)$avg['num_articles'];
	if( $diff < 0.8 )
		print( "Fewer articles than the average journalist" );
	elseif( $diff > 1.2 )
		print("More articles than the average journalist");
	else
		print("About the same number of articles as the average journalist");

	if( $stats['num_articles'] == 1 )
		printf( " (%d article since %s)", $stats['num_articles'], $stats['first_pubdate'] );
	else
		printf( " (%d articles since %s)", $stats['num_articles'], $stats['first_pubdate'] );
	print( "</li>\n" );

?>
</ul>
</div>
<?php

}





function emit_block_bynumbers( $journo )
{
	$journo_id = $journo['id'];

	// stats for this journo
	$stats = FetchJournoStats( $journo );

	// get average stats for all journos
	$avg = FetchAverages();

?>
<div class="block">
<h3>Journa-list by numbers</h3>
<?php

	print "<table>\n";
	printf( "<tr><th></th><th>%s&nbsp;&nbsp;</th><th>Average for all journalists</th></tr>",
		$journo['prettyname'] );

	printf( "<tr><th>Articles</th><td>%d (since %s)</td><td>%.0f</td></tr>\n",
		$stats['num_articles'], $stats['first_pubdate'], $avg['num_articles'] );
	printf( "<tr><th>Total words written</th><td>%d</td><td>%.0f</td></tr>\n",
		$stats['wc_total'], $avg['wc_total'] );
	printf( "<tr><th>Average article length</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$stats['wc_avg'], $avg['wc_avg'] );
	printf( "<tr><th>Shortest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$stats['wc_min'], $avg['wc_min'] );
	printf( "<tr><th>Longest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$stats['wc_max'], $avg['wc_max'] );
	print "</table>\n";

?>
<p>(no reflection of quality, just stats)</p>

</div>

<?php

}



function emit_block_tags( $journo )
{

	$journo_id = $journo['id'];
	$ref = $journo['ref'];
	$prettyname = $journo['prettyname'];

?>
<div class="block">
<h3>The topics <?=$prettyname; ?> mentions most:</h3>
<?php

	$maxtags = 20;

	gatso_start('tags');
	# TODO: should only include active articles (ie where article.status='a')
	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
		"WHERE a.journo_id=? ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT ?";
	$q = db_query( $sql, $journo_id, $maxtags );
	gatso_stop('tags');

	tag_cloud_from_query( $q, $ref );


?>
</div>

<?php

}




function emit_block_links( $journo )
{
	$journo_id = $journo['id'];
	$q = db_query( "SELECT url, description " .
		"FROM journo_weblink " .
		"WHERE journo_id=?",
		$journo_id );

	$row = db_fetch_array($q);
	if( !$row )
		return;		/* no links to show */

	print "<div class=\"block\">\n";
	print "<h3>Elsewhere on the web</h3>\n";
	print "<ul>\n";
	while( $row )
	{
		printf( "<li><a href=\"%s\">%s</a></li>\n",
			$row['url'],
			$row['description'] );
		$row = db_fetch_array($q);
	}
	print "</ul></p>\n";
	print "</div>\n";
}


// block with rss feed for journo
function emit_block_rss( $journo, $rssurl )
{

?>
<div class="block">
<h3>Newsfeed</h3>
<a href="<?php echo $rssurl; ?>"><img src="/img/rss.gif"></a><br>
Articles by <?php print $journo['prettyname']; ?>

</div>

<?php
}

// email alerts
function emit_block_alerts( $journo )
{

?>
<div class="block">
<h3>My Journa-list</h3>
<a href="/alert?Add=1&j=<?=$journo['ref'] ?>">Email me</a> when <?=$journo['prettyname'] ?> writes an article
</div>

<?php

}


// search box for this journo
// ("find articles by this journo containing ....")
function emit_block_searchbox( $journo )
{

?>
<div class="block">
<h3>Find</h3>
<p>
<form action="article" method="get">
<input type="hidden" name="ref" value="<?php echo $journo['ref'];?>"/>
Find articles by <?php echo $journo['prettyname'];?> containing:
<input type="text" name="find" value=""/>
<input type="submit" value="Find" />
</form>
</p>
</div>

<?php

}




function emit_block_overview( $journo )
{
	$journo_id = $journo['id'];

	print "<div class=\"block\">\n";
	print "<h3>Overview</h3>\n";

	emit_writtenfor( $journo );

	print "</div>\n\n";


}



function emit_writtenfor( $journo )
{
	$orgs = get_org_names();
	$journo_id = $journo['id'];

	gatso_start( 'writtenfor' );
	$writtenfor = db_getAll( "SELECT DISTINCT a.srcorg " .
		"FROM article a INNER JOIN journo_attr j ON (a.status='a' AND a.id=j.article_id) ".
		"WHERE j.journo_id=?",
		$journo_id );
	printf( "<p>\n%s writes for:\n", $journo['prettyname'] );
	print "<ul>\n";
	foreach( $writtenfor as $row )
	{
		$srcorg = $row['srcorg'];

		// get jobtitles seen for this org:	
		$titles = db_getAll( "SELECT jobtitle FROM journo_jobtitle WHERE journo_id=? AND org_id=?",
			$journo_id, $srcorg );
		$orgname = $orgs[ $srcorg ];

		print "<li>";
		print "$orgname";

		if( $titles )
		{
			print "\n<ul>\n";
			foreach( $titles as $t )
				printf( "<li>%s</li>\n", $t['jobtitle']);
			print "</ul>\n";
		}
		print "</li>\n";
	}
	print "</ul>\n</p>\n";
	gatso_stop( 'writtenfor' );
}



function FetchAverages()
{
	static $avgs = NULL;
	if( $avgs !== NULL )
		return $avgs;

	$refreshinterval = 60*60*2;		// 2 hours

	$avgs = db_getRow( "SELECT date_part('epoch', now()-last_updated) AS elapsed, ".
			"wc_total, wc_avg, wc_min, wc_max, num_articles ".
		"FROM journo_average_cache" );

	if( !$avgs || $avgs['elapsed'] > $refreshinterval )
	{
		$avgs = CalcAverages();
		StoreAverages( $avgs );
	}
	return $avgs;
}


function CalcAverages()
{
	$q = db_query( "SELECT ".
				"SUM(art.wordcount) AS wc_total, ".
				"AVG(art.wordcount) AS wc_avg, ".
				"MIN(art.wordcount) AS wc_min, ".
				"MAX(art.wordcount) AS wc_max, ".
				"COUNT(*) as num_articles ".
			"FROM article art INNER JOIN journo_attr attr ".
				"ON (attr.article_id=art.id) GROUP BY journo_id" );

	$fields = array('wc_total','wc_avg','wc_min','wc_max','num_articles');
	$runningtotal = array();
	foreach( $fields as $f )
		$runningtotal[$f] = 0.0;
	$rowcnt = 0;
	while( $row = db_fetch_array( $q ) )
	{
		++$rowcnt;
		foreach( $fields as $f )
			$runningtotal[$f] += $row[$f];
	}

	$averages = array();
	foreach( $fields as $f )
		$averages[$f] = $runningtotal[$f] / $rowcnt;

	return $averages;
}

function StoreAverages( $avgs )
{
	db_do( "DELETE FROM journo_average_cache" );
	db_do( "INSERT INTO journo_average_cache ".
			"(last_updated,wc_total,wc_avg,wc_min,wc_max,num_articles) ".
		"VALUES (now(),?,?,?,?,?)",
		$avgs['wc_total'],
		$avgs['wc_avg'],
		$avgs['wc_min'],
		$avgs['wc_max'],
		$avgs['num_articles'] );
	db_commit();
}


// get various stats for this journo
function FetchJournoStats( $journo )
{
	static $ret = NULL;
	if( $ret !== NULL )
		return $ret;

	$journo_id = $journo['id'];
	$ret = array();

	// wordcount stats, number of articles...
	$sql = "SELECT SUM(s.wordcount) AS wc_total, ".
			"AVG(s.wordcount) AS wc_avg, ".
			"MIN(s.wordcount) AS wc_min, ".
			"MAX(s.wordcount) AS wc_max, ".
			"to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
			"COUNT(*) AS num_articles ".
		"FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
		"WHERE a.journo_id=?";
	$row = db_getRow( $sql, $journo_id );
	$ret = $row;

	// most frequent tag over last month
	$sql = "SELECT t.tag, sum(t.freq) as mentions ".
		"FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
			"INNER JOIN article a ON a.id=t.article_id) ".
		"WHERE attr.journo_id = ? AND a.status='a' AND a.pubdate>NOW()-interval '1 month' ".
		"GROUP BY t.tag ".
		"ORDER BY mentions DESC ".
		"LIMIT 1";
	$row = db_getRow( $sql, $journo_id );
	if( $row )
		$ret['toptag_month'] = $row['tag'];

	// most frequent tag  of all time
	$sql = "SELECT t.tag, sum(t.freq) as mentions ".
		"FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
			"INNER JOIN article a ON a.id=t.article_id) ".
		"WHERE attr.journo_id = ? AND a.status='a' ".
		"GROUP BY t.tag ".
		"ORDER BY mentions DESC ".
		"LIMIT 1";
	$row = db_getRow( $sql, $journo_id );
	if( $row )
		$ret['toptag_alltime'] = $row['tag'];

	return $ret;
}


?>
