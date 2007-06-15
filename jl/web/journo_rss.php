<?php

/* 
 * RSS feed for recent articles by Journo
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

header('Content-type: text/xml;');

emit_recent_articles( $journo );



function emit_recent_articles( $journo )
{
	print "<?xml version=\"1.0\"?>\n";
	print "<rss version=\"2.0\">\n";
	print "\t<channel>\n";
	printf( "\t\t<title>Recent articles by %s</title>\n", $journo['prettyname'] );
	printf( "\t\t<link>http://%s/%s</link>\n", OPTION_WEB_DOMAIN, $journo['ref'] );

	printf( "\t\t<description></description>\n" );
	print( "\t\t<language>en-gb</language>\n" );

	$journo_id = $journo[ 'id' ];
	$sql = "SELECT a.id,a.title,a.description,a.pubdate,a.permalink,a.srcorg " .
		"FROM article a,journo_attr j " .
		"WHERE a.status='a' AND a.id=j.article_id AND " .
			"j.journo_id=? AND a.pubdate>(NOW()-interval '10 months') " .
		"ORDER BY a.pubdate DESC";

	$q = db_query( $sql, $journo_id );
	
	while( $art = db_fetch_array($q) )
	{
		print( "\t\t<item>\n" );
		printf( "\t\t\t<title>%s</title>\n", $art['title'] );
		printf( "\t\t\t<link>%s</link>\n", $art['permalink'] );
		printf( "\t\t\t<description>%s</description>\n", $art['description'] );
		printf( "\t\t\t<pubDate>%s</pubDate>\n", $art['pubdate'] );
		printf( "\t\t\t<guid>%s</guid>\n", $art['permalink'] );
		print( "\t\t</item>\n" );
	}

	print "\t</channel>\n";
	print "</rss>\n";

}
?>

