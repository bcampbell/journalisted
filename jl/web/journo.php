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
	$pagetitle = $journo['prettyname'] . " - " . OPTION_WEB_DOMAIN;
else
	$pagetitle = "Unknown journalist - " . OPTION_WEB_DOMAIN;

page_header( array( 'title'=>$pagetitle ));

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
	$titles = db_getAll( "SELECT jobtitle FROM journo_jobtitle WHERE journo_id=?", $journo_id );
	print "<h2>{$journo['prettyname']}</h2>";

?>
<ul>
<?php
	
	foreach( $titles as $t )
	{
		print "  <li>{$t['jobtitle']}</li>\n";
	}

?>
</ul>

<div class="block">
<h3>Most recent article</h3>
<?php

	$sql = "SELECT a.id,a.title,a.description,a.pubdate,a.permalink,a.srcorg FROM article a,journo_attr j WHERE a.id = j.article_id AND j.journo_id=? ORDER BY a.pubdate DESC";
	$sqlparams = array( $journo_id );

	$maxprev = 10;	/* max number of previous articles to list by default*/
	$allarticles = get_http_var( 'allarticles', 'no' );
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


emit_block_stats( $journo_id );
emit_block_tags( $journo_id );
emit_block_links( $journo_id );

}



function emit_block_stats( $journo_id )
{

?>
<div class="block">
<h3>Journa-list by numbers:</h3>
<?php

	$sql = "SELECT SUM(s.wordcount) AS wc_sum, ".
			"AVG(s.wordcount) AS wc_avg, ".
			"MIN(s.wordcount) AS wc_min, ".
			"MAX(s.wordcount) AS wc_max ".
		"FROM (journo_attr a INNER JOIN article s ON (a.article_id=s.id) ) ".
		"WHERE a.journo_id=?";
	$row = db_getRow( $sql, $journo_id );

	print( "<ul>\n" );
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
<h3>Tags</h3>
<?php

	$sql = "SELECT t.tag AS tagname, SUM(t.freq) ".
		"FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
		"WHERE a.journo_id=? ".
		"GROUP BY t.tag ".
		"ORDER BY sum DESC";
	$q = db_query( $sql, $journo_id );

	$cnt =0;
	while( ($row = db_fetch_array( $q )) && $cnt <5)
	{
		$tag = $row['tagname'];
		printf( "<a href=\"/list?tag=%s\">%s</a>  ", urlencode($tag), $tag );
		++$cnt;
	}

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

?>
