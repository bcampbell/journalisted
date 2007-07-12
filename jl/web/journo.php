<?php

/* 
 * TODO: per week stats for journo (articles written this week)
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
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


printf( "<h2>%s</h2>\n", $journo['prettyname'] );

/* main pane */

print "<div id=\"mainpane\">\n";
emit_block_overview( $journo );
emit_blocks_articles( $journo, get_http_var( 'allarticles', 'no' ) );
//emit_block_tags( $journo );
emit_block_stats( $journo );
print "</div>\n";

/* side pane */

?>
<div id="sidepane">

<div class="block">
<h3>Newsfeed</h3>

<a href="<?php echo $rssurl; ?>"><img src="/img/rss.gif"></a><br>
Recent articles by <?php print $journo['prettyname']; ?>
</div>

<div class="block">
<h3>Email alerts</h3>
<a href="/alert?Add=1&j=<?=$journo['ref'] ?>">Email me when <?=$journo['prettyname'] ?> writes anything!</a>
</div>

<?php

emit_block_tags( $journo );

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

<div class="block">
<h3>Something wrong/missing?</h3>
<p>
Have we got the wrong information about this journalist?
<a href="mailto:team@journa-list.dyndns.org?subject=Problem with <?php echo $journo['prettyname']; ?>'s page!">Let us know!</a>
</p>
</div>
<?php
print "</div>\n";

page_footer();



/*
 * HELPERS
 */

function emit_journo( $journo )
{

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
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small\n";
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
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small\n";
		print "</li>\n";

	}

	/* if there are any more we're not showing, say so */
	if( db_fetch_array($q) )
	{
		print "<a href=\"/{$journo['ref']}?allarticles=yes\">[Show all previous articles...]</a>\n";
	}
?>
</ul>
</div>
<?php

}

function emit_block_stats( $journo )
{

?>
<div class="block">
<h3>Journa-list by numbers:</h3>
<?php

	$journo_id = $journo['id'];

	$avg = FetchAverages();

	$sql = "SELECT SUM(s.wordcount) AS wc_total, ".
			"AVG(s.wordcount) AS wc_avg, ".
			"MIN(s.wordcount) AS wc_min, ".
			"MAX(s.wordcount) AS wc_max, ".
			"to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
			"COUNT(*) AS num_articles ".
		"FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
		"WHERE a.journo_id=?";
	$row = db_getRow( $sql, $journo_id );


	print "<table>\n";
	printf( "<tr><th></th><th>%s&nbsp;&nbsp;</th><th>Average for all journalists</th></tr>",
		$journo['prettyname'] );

	printf( "<tr><th>Articles</th><td>%d (since %s)</td><td>%.1f</td></tr>\n",
		$row['num_articles'], $row['first_pubdate'], $avg['num_articles'] );
	printf( "<tr><th>Total words written</th><td>%d</td><td>%.0f</td></tr>\n",
		$row['wc_total'], $avg['wc_total'] );
	printf( "<tr><th>Average article length</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$row['wc_avg'], $avg['wc_avg'] );
	printf( "<tr><th>Shortest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$row['wc_min'], $avg['wc_min'] );
	printf( "<tr><th>Longest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
		$row['wc_max'], $avg['wc_max'] );
	print "</table>\n";

?>
</div>
<?php
}



function emit_block_tags( $journo )
{

	$journo_id = $journo['id'];
	$ref = $journo['ref'];
?>
<div class="block">
<h3>Most cited [Tag cloud]</h3>
<?php
	$maxtags = 20;

	# TODO: should only include active articles (ie where article.status='a')
	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
		"WHERE a.journo_id=? ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT ?";
	$q = db_query( $sql, $journo_id, $maxtags );

	tag_cloud_from_query( $q, $ref );


?>
</div>
<?php

}



function emit_links( $journo_id )
{
	$q = db_query( "SELECT url, description " .
		"FROM journo_weblink " .
		"WHERE journo_id=?",
		$journo_id );

	$row = db_fetch_array($q);
	if( !$row )
		return;		/* no links to show */
	print "<p>Elsewhere on the web:\n<ul>";
	while( $row )
	{
		printf( "<li><a href=\"%s\">%s</a></li>\n",
			$row['url'],
			$row['description'] );
		$row = db_fetch_array($q);
	}
	print "</ul></p>\n";
}



function emit_block_overview( $journo )
{
	$journo_id = $journo['id'];


	print "<div class=\"block\">\n";
	print "<h3>Overview</h3>\n";

	emit_writtenfor( $journo );

	emit_links( $journo_id );

	print "</div>\n";


}



function emit_writtenfor( $journo )
{
	$orgs = get_org_names();
	$journo_id = $journo['id'];
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

		print "<li>\n";
		print "$orgname\n";

		if( $titles )
		{
			print "<ul>\n";
			foreach( $titles as $t )
				printf( "<li>%s</li>\n", $t['jobtitle']);
			print "</ul>\n";
		}
		print "</li>\n";
	}
	print "</ul>\n</p>\n";
}



function FetchAverages()
{
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

?>
