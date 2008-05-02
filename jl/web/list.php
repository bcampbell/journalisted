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
$searchtext = get_http_var( 'search', null );
if( $name )
{
	page_header("");
	FindByName( $name );
	page_footer();
}
else if( $outlet )
{
	page_header("");
	FindByOutlet( $outlet );
	page_footer();
}
else if( $searchtext )
{
	$pagetitle = "Articles mentioning \"$searchtext\"";
	page_header( $pagetitle );
	FindByText( $searchtext );
	page_footer();
}
else
{
	// default - alphabetical list of all journos in system
	page_header("All journalists", array( 'menupage'=>'all' ) );
	AlphabeticalList();
	page_footer();
}




function EmptyPhonebook()
{
	$b = array();
	for( $l='A'; ord($l)<=ord('Z'); $l=chr(ord($l)+1) )
	{
		$b[$l] = array();
	}

	return $b;
}

function AlphabeticalList()
{
	$order = get_http_var( 'order', 'lastname' );
	$letter = strtolower( get_http_var( 'letter', 'a' ) );

	if( $order == 'firstname' )
		$orderfield = 'firstname';
	else
	{	
		$order = 'lastname';
		$orderfield = 'lastname';
	}

	print "<h2>All Journalists</h2>\n";
	print "<p>Ordered by ";
	if( $orderfield=='firstname' )
		print "first name (<a href=\"list?order=lastname&letter={$letter}\">order by last name</a>)";
	else
		print "last name (<a href=\"list?order=firstname&letter={$letter}\">order by first name</a>)";
	print "</p>\n";

    $sql = <<<EOT
SELECT ref,prettyname,oneliner,{$orderfield}
    FROM journo
    WHERE status='a' AND substring( {$orderfield} from 1 for 1 )=?
    ORDER BY {$orderfield}
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
            printf("<a href=\"%s\">%s</a>\n", $link, strtoupper($c) );
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
}

/* test version - N per page */
function TEST_AlphabeticalList()
{
	$order = get_http_var( 'o', 'l' );
	$page = get_http_var( 'page', '1' );
	$perpage = get_http_var( 'page', '100' );

	if( $order == 'f' )
		$orderfield = 'firstname';
	else
	{	
		$order = 'l';
		$orderfield = 'lastname';
	}

	print "<h2>All Journalists</h2>\n";
	print "<p>Ordered by ";
	if( $orderfield=='firstname' )
		print "first name (<a href=\"list\">order by last name</a>)";
	else
		print "last name (<a href=\"list?o=f\">order by first name</a>)";
	print "</p>\n";

    /* need to figure out how many pages we need... */
    $total = db_getOne( "SELECT COUNT(*) FROM journo WHERE status='a'" );

    $sql = <<<EOT
SELECT ref,prettyname,oneliner,{$orderfield}
    FROM journo
    WHERE status='a'
    ORDER BY {$orderfield}
    LIMIT ?
    OFFSET ?
EOT;
	$q = db_query( $sql, $perpage, ($page-1)*$perpage );

    printf( "<p>tot %d</p>\n", $total );
    printf( "<p>Page %d of %d</p>\n", $page, ($total+($perpage-1))/$perpage );

    print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		print "<li>";
		print FancyJournoLink( $j );
		if( array_key_exists( 'extra', $j ) )
			print( ' ' . $j['extra'] );
		print "</li>\n";
        
	}
    print "</ul>\n";
}

/* All on one page, with letter titles at start of each alphabetical section */
function OLD_AlphabeticalList()
{
	$order = get_http_var( 'o', 'l' );
	if( $order == 'f' )
		$orderfield = 'firstname';
	else
	{	
		$order = 'l';
		$orderfield = 'lastname';
	}

	print "<h2>All Journalists</h2>\n";
	print "<p>Ordered by ";
	if( $orderfield=='firstname' )
		print "first name (<a href=\"list\">order by last name</a>)";
	else
		print "last name (<a href=\"list?o=f\">order by first name</a>)";
	print "</p>\n";

	$q = db_query( "SELECT ref,prettyname,oneliner,{$orderfield} FROM journo WHERE status='a' ORDER BY {$orderfield}" );

	/* slurp in all the returned journos, and index them by letter */
	$phonebook = EmptyPhonebook();
	while( $row = db_fetch_array($q) )
	{
		$idx = substr( $row[ $orderfield ],0,1 );
		if( $idx )
			$idx = strtoupper( $idx );
		else
			$idx = 'other';	// we shouldn't have any non-alpha names!
		$phonebook[ $idx ][] = $row;
	}
	OutputPhonebook( $phonebook );
}



function OutputPhonebook( &$phonebook )
{
	/* output quick-jump link for each letter */
	print "<p>\n";
	foreach( $phonebook as $letter=>$v )
	{
		print "<a href=\"#{$letter}\">{$letter}</a>\n";
	}
	print "</p>\n";

	/* now output each letter group */
	foreach( $phonebook as $letter=>$group )
	{
		print "<a name=\"{$letter}\"><h3>{$letter}</h3></a>\n";
		print "<ul>\n";
		foreach( $group as $j )
		{
			print "<li>";
			print FancyJournoLink( $j );
			if( array_key_exists( 'extra', $j ) )
				print( ' ' . $j['extra'] );
			print "</li>\n";
		}
		print "</ul>\n";
	}
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


function FindByName( $name )
{
	print "<h2>Journalists matching \"{$name}\"</h2>";
?>
<form action="list" method="get">
Find a journalist by name:
<input type="text" name="name" value="<?=$name; ?>"/>
<input type="submit" value="Find" />
</form>
<?php
	
	$pat = strtolower( "%{$name}%" );
	$q = db_query( "SELECT ref,prettyname,oneliner FROM journo WHERE LOWER(prettyname) LIKE( ? ) AND status='a'", $pat );

	printf( "<p>Found %d matches</p>", db_num_rows($q) );
	print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		printf( "<li>%s</li>\n", FancyJournoLink( $j ) );
	}
	print "</ul>\n";
}






function FindByOutlet( $outlet )
{
	$org = db_getRow( "SELECT id,prettyname FROM organisation WHERE shortname=?", $outlet );

	printf( "<h2>Journalists who have written for %s</h2>",
   		$org['prettyname'] );

	$sql = "SELECT j.ref, j.prettyname, j.oneliner, j.lastname, count(a.id) " .
		"FROM (( article a INNER JOIN journo_attr ja ON (a.status='a' AND a.id=ja.article_id) ) " .
			"INNER JOIN journo j ON (j.status='a' AND j.id=ja.journo_id) ) " .
		"WHERE a.srcorg=?  " .
		"GROUP BY j.ref,j.prettyname,j.oneliner,j.lastname " .
		"ORDER BY count DESC";

	$q = db_query( $sql, $org['id'] );

	printf( "<p>Found %d matches</p>", db_num_rows($q) );
	print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
		printf( "<li>%s (%d articles)</li>\n", FancyJournoLink( $j ), $j['count'] );
	}
	print "</ul>\n";
}


// list journos who've written articles containing $searchtext
function FindByText( $searchtext )
{

	$orgs = get_org_names();

	$sql = <<<EOT
SELECT j.id AS journo_id, j.ref, j.prettyname, j.oneliner, a.id AS article_id,a.title,a.permalink,a.pubdate,a.srcorg
	FROM ((article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
		INNER JOIN journo j ON j.id=attr.journo_id)
	WHERE a.status='a'
		AND j.status='a'
		AND a.content ilike ?
		AND a.pubdate>now()-interval '5 days'
	ORDER BY j.id
EOT;
	$q = db_query( $sql, '%'.$searchtext.'%' );

	printf( "<h2>Articles within the last five days mentioning \"%s\"</h2>", $searchtext );

	// want to group articles by journo
	$journo_id = null;
	printf( "<p>Found %d matching articles</p>", db_num_rows($q) );
	print "<ul>\n";

	$articlecnt = 0;
	$journocnt = 0;
	while( $row=db_fetch_array($q) )
	{
		++$articlecnt;
		$org = $orgs[ $row['srcorg'] ];
		$pubdate = pretty_date(strtotime($row['pubdate']));

		if( $journo_id != $row['journo_id'] )
		{
			// it's a new journo
			++$journocnt;
			if( $journo_id )
			{
				// close old journo
				print("  </ul>");	// article sublist
				print(" </li>");
			}

			//start new journo
			$journo_id = $row['journo_id'];

			printf( " <li>%s\n", FancyJournoLink( $row ) );
			print( "  <ul>\n" );	// start article sublist
		}
		$art_html = sprintf( "%s (%s, %s)", $row['title'], $pubdate, $org );

		// article
		print "   <li>\n";
		print "    <a href=\"/article?id={$row['article_id']}\">{$row['title']}</a><br />\n";
		print "    <cite class=\"posted\"<a href=\"{$row['permalink']}\">{$pubdate}, <em>{$org}</em></a></cite>\n";
		print "   </li>";
	}

	// close any open journo
	if( $journocnt > 0 )
	{
		print "  </ul>\n";	// close article sublist
		print " </li>\n";
	}

	print "</ul>\n";

//	printf( "<p>Found %d matching articles by %d journalists</p>", $articlecnt, $journocnt );
}
?>
