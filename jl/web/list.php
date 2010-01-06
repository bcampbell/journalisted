<?php
/*
 * list.php
 * Page for finding and listing journos
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



db_connect();

$name = get_http_var( 'name', null );
$outlet = get_http_var( 'outlet', null );
if( $name )
{
	FindByName( $name );
}
else if( $outlet )
{
	FindByOutlet( $outlet );
}
else
{
	// default - alphabetical list of all journos in system
	AlphabeticalList();
}



function FancyJournoLink( $j )
{
    $name = $j['prettyname'];
    $url = '/' . $j['ref'];
    $desc = '';
    if( $j['oneliner'] )
        $desc = " (<em>" . $j['oneliner'] . "</em>)";

    return "<a href=\"$url\">$name</a><span class=\"journodesc\">$desc</span>";
}



function AlphabeticalList()
{
	page_header("Journalists A-Z", array( 'menupage'=>'all' ) );

	$letter = strtolower( get_http_var( 'letter', 'a' ) );
	$order = get_http_var( 'order', 'lastname' );

?>
<div class="main">
<h2>Journalists A-Z</h2>

<p>Ordered by <?php	if( $order=='firstname' ) { ?>
first name (<a href="list?letter=<?= $letter ?>">order by last name</a>)
<?php }	else { ?>
last name (<a href="list?order=firstname&amp;letter=<?=$letter?>">order by first name</a>)
<?php } ?>
</p>
<?php

	$orderfield = ($order=='firstname') ? 'firstname':'lastname';
	$orderfields = ($order=='firstname') ?
         'firstname,lastname' : 'lastname,firstname';
    $sql = <<<EOT
SELECT ref,prettyname,oneliner,{$orderfield}
    FROM journo
    WHERE status='a' AND substring( {$orderfield} from 1 for 1 )=?
    ORDER BY {$orderfields}
EOT;
	$q = db_query( $sql, $letter );


    print "<p>\n";

    for( $i=ord('a'); $i<=ord('z'); ++$i )
    {
        $c = chr($i);
        if( $c == $letter )
        {
            printf("<strong>%s</strong>\n", strtoupper($c) );
        }
        else
        {
            $link = sprintf( "/list?order=%s&letter=%s", $order, $c );
            printf("<a href=\"%s\">%s</a>\n", htmlentities($link), strtoupper($c) );
        }
    }
    print "</p>\n";

    print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		print "<li>";
		print FancyJournoLink( $j );
		print "</li>\n";
        
	}
    print "</ul>\n";
    print "</div>\n";
	page_footer();
}



function FindByName( $name )
{
	$order = get_http_var( 'order', 'lastname' );
	$orderfields = ($order=='firstname') ?
         'firstname,lastname' : 'lastname,firstname';

	$pat = strtolower( "%{$name}%" );
	$q = db_query( "SELECT ref,prettyname,oneliner FROM journo WHERE LOWER(prettyname) LIKE( ? ) AND status='a' ORDER BY {$orderfields}", $pat );
    $numfound = db_num_rows($q);
    if( $numfound == 1 )
    {
        /* special case - iff we found one single journo, redirect to their page */
	    $j = db_fetch_array($q);
        $newurl = '/' . $j['ref'];
        header("location: {$newurl}");
        return;
    }

	page_header("");
	print "<h2>Journalists matching \"{$name}\"</h2>";

	print "<p>Ordered by ";
	if( $order=='firstname' )
		printf( "first name (<a href=\"list?name=%s\">order by last name</a>)", urlencode( $name ) );
	else
		printf( "last name (<a href=\"list?name=%s&amp;order=firstname\">order by first name</a>)", urlencode($name) );
	print "</p>\n";
?>
<form action="list" method="get">
Find a journalist by name:
<input type="text" name="name" value="<?=$name; ?>"/>
<input type="submit" value="Find" />
</form>
<?php
	

	printf( "<p>Found %d matches</p>", $numfound );
	print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		printf( "<li>%s</li>\n", FancyJournoLink( $j ) );
	}
	print "</ul>\n";

    page_footer();
}






function FindByOutlet( $outlet )
{
	$order = get_http_var( 'order', 'lastname' );

	page_header("");
	$org = db_getRow( "SELECT id,prettyname FROM organisation WHERE shortname=?", $outlet );

	printf( "<h2>Journalists who have written for %s</h2>",
   		$org['prettyname'] );

	print "<p>Ordered by ";
	if( $order=='firstname' )
		print "first name (<a href=\"list?outlet={$outlet}\">order by last name</a>)";
	else
		print "last name (<a href=\"list?outlet={$outlet}&amp;order=firstname\">order by first name</a>)";
	print "</p>\n";

/*
	$sql = "SELECT j.ref, j.prettyname, j.oneliner, j.lastname, count(a.id) " .
		"FROM (( article a INNER JOIN journo_attr ja ON (a.status='a' AND a.id=ja.article_id) ) " .
			"INNER JOIN journo j ON (j.status='a' AND j.id=ja.journo_id) ) " .
		"WHERE a.srcorg=?  " .
		"GROUP BY j.ref,j.prettyname,j.oneliner,j.lastname " .
		"ORDER BY count DESC";
*/
	$orderfields = ($order=='firstname') ?
         'j.firstname,j.lastname' : 'j.lastname,j.firstname';
	$sql = "SELECT DISTINCT j.ref, j.prettyname, j.oneliner, j.firstname, j.lastname " .
		"FROM (( article a INNER JOIN journo_attr ja ON (a.status='a' AND a.id=ja.article_id) ) " .
			"INNER JOIN journo j ON (j.status='a' AND j.id=ja.journo_id) ) " .
		"WHERE a.srcorg=?  " .
		"ORDER BY {$orderfields}";

	$q = db_query( $sql, $org['id'] );

	printf( "<p>Found %d matches</p>", db_num_rows($q) );
	print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		printf( "<li>%s</li>\n", FancyJournoLink( $j ) );
	}
	print "</ul>\n";
    page_footer();
}


?>
