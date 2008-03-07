<?php

/* 
 * Test news feed for Julian
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


$from = get_http_var( 'from', 0 );
$num = get_http_var( 'num', 'all' );

header('Content-type: text/xml;');

emit_articles( $from, $num );



function emit_articles( $offset, $limit )
{
	print "<?xml version=\"1.0\"?>\n";
	print "<rss version=\"2.0\" xmlns:jl=\"http://www.journalisted.com/jlns#\">\n";
	print "\t<channel>\n";
	printf( "\t\t<title>Recent articles</title>\n" );
	printf( "\t\t<link>http://%s</link>\n", OPTION_WEB_DOMAIN );

	printf( "\t\t<description></description>\n" );
	print( "\t\t<language>en-gb</language>\n" );

	$params = array();
	$sql = "SELECT id,title,description,pubdate,permalink,srcorg,wordcount " .
		"FROM article " .
		"WHERE status='a' AND " .
			"pubdate>(NOW()-interval '24 hours') AND pubdate<=NOW() " .
		"ORDER BY pubdate DESC";

	if( $limit != 'all' ) {
		$sql .= ' LIMIT ?';
		$params[] = $limit;

		$sql .= ' OFFSET ?';
		$params[] = $offset;
	}

	$q = db_query( $sql, $params );

	while( $art = db_fetch_array($q) )
	{
		print( "\t\t<item>\n" );
		printf( "\t\t\t<title>%s</title>\n", $art['title'] );
		printf( "\t\t\t<link>%s</link>\n", $art['permalink'] );
		printf( "\t\t\t<description>%s</description>\n", $art['description'] );
		printf( "\t\t\t<pubDate>%s</pubDate>\n", $art['pubdate'] );
		printf( "\t\t\t<guid>%s</guid>\n", $art['permalink'] );
		printf( "\t\t\t<jl:wordcount>%s</jl:wordcount>\n", $art['wordcount'] );
		print( "\t\t</item>\n" );
	}

	print "\t</channel>\n";
	print "</rss>\n";

}
?>

