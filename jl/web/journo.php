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

if($journo)
{
	$rssurl = sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );

	$pageparams = array(
		'title'=>$journo['prettyname'] . " - " . OPTION_WEB_DOMAIN,
		'rss'=>array( 'Recent Articles'=>$rssurl )
	);
}
else
{
	$pageparams = array( 'title'=>"Unknown journalist - " . OPTION_WEB_DOMAIN );
}

page_header( $pageparams );

if( $journo )
	emit_journo( $journo );
else
	print "Not found";

page_footer();



/*
 * HELPERS
 */

function emit_journo( $journo )
{
	$orgs = get_org_names();
	$journo_id = $journo['id'];

	emit_block_general( $journo );

	emit_blocks_articles( $journo, get_http_var( 'allarticles', 'no' ) );
	emit_block_tags( $journo_id );
	emit_block_stats( $journo_id );
	emit_block_links( $journo_id );

}


function emit_blocks_articles( $journo, $allarticles )
{


?>

<div class="block">
<h3>Most recent article<?php


?></h3>
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

		print "\"<a href=\"/article?id={$r['id']}\">{$r['title']}</a>\", {$pubdate}, <em>{$org}</em>\n";
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small\n";
		print "<blockquote>{$desc}</blockquote>\n";

	}

	printf( "<a href=\"http://%s/%s/rss\"><img src=\"/img/rss.gif\"></a>\n",
		OPTION_WEB_DOMAIN,
		$journo['ref'] );
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
		print "\"<a href=\"/article?id={$r['id']}\">{$r['title']}</a>\", {$pubdate}, <em>{$org}</em>\n";
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

function emit_block_stats( $journo_id )
{

?>
<div class="block">
<h3>Journa-list by numbers:</h3>
<?php

	print( "<ul>\n" );

	$sql = "SELECT SUM(s.wordcount) AS wc_sum, ".
			"AVG(s.wordcount) AS wc_avg, ".
			"MIN(s.wordcount) AS wc_min, ".
			"MAX(s.wordcount) AS wc_max, ".
			"to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
			"COUNT(*) AS num_articles ".
		"FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
		"WHERE a.journo_id=?";
	$row = db_getRow( $sql, $journo_id );

	printf( "<li>%d articles (since %s)</li>\n", $row['num_articles'], $row['first_pubdate'] );
	printf( "<li>%d average words per article</li>\n", $row['wc_avg'] );
	printf( "<li>%d words maximum</li>\n", $row['wc_max'] );
	printf( "<li>%d words minimum</li>\n", $row['wc_min'] );
	print( "</ul>\n" );

?>
</div>
<?php
}



function emit_block_tags( $journo_id )
{

?>
<div class="block">
<h3>Most cited [Tag cloud]</h3>
<?php
	$maxtags = 50;

	# TODO: should only include active articles (ie where article.status='a')
	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
		"WHERE a.journo_id=? ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT ?";
	$q = db_query( $sql, $journo_id, $maxtags );

	tag_cloud_from_query( $q, $journo_id );


?>
</div>
<?php

}



function emit_block_links( $journo_id )
{
	$q = db_query( "SELECT url, description " .
		"FROM journo_weblink " .
		"WHERE journo_id=?",
		$journo_id );

	$row = db_fetch_array($q);
	if( !$row )
		return;		/* no links to show */

?>
<div class="block">
<h3>Links</h3>
<ul>
<?php

	while( $row )
	{
		printf( "<li><a href=\"%s\">%s</a></li>\n", $row['url'], $row['description'] );
		$row = db_fetch_array($q);
	}

?>
</ul>
</div>
<?php

}



function emit_block_general( $journo )
{


	printf( "<h2>Journalist: %s</h2>\n", $journo['prettyname'] );
	$journo_id = $journo['id'];
	$orgs = get_org_names();

	$writtenfor = db_getAll( "SELECT DISTINCT a.srcorg " .
		"FROM article a INNER JOIN journo_attr j ON (a.status='a' AND a.id=j.article_id) ".
		"WHERE j.journo_id=?",
		$journo_id );

	print "<div class=\"block\">\n";
	print "<h3>Written for:</h3>\n";
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
	print "</ul>\n";
	print "</div>\n";


}

?>
