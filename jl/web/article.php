<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



$article_id = get_http_var( 'id' );
$findtext = get_http_var( 'find' );
$ref = get_http_var( 'ref' );

if( $findtext )
	emit_page_findarticles( $findtext, $ref );
else
	emit_page_article( $article_id );


function emit_page_article( $article_id )
{
	$art = db_getRow( 'SELECT * FROM article WHERE id=?', $article_id );

	if( $art['status'] != 'a' )
		return; /* TODO: a message or something... */

	$pagetitle = $art['title'];
	page_header( $pagetitle );

	print "<div id=\"maincolumn\">\n";

	emit_block_articleinfo( $art );
	emit_block_commentlinks( $article_id );
	emit_block_bloglinks( $article_id );
	print "</div> <!-- end maincolumn -->\n";

	print "<div id=\"smallcolumn\">\n\n";
	emit_block_articletags( $article_id );
	print "</div> <!-- end smallcolumn -->\n";
	page_footer();
}

function emit_page_findarticles( $findtext,$ref=null )
{
	if( $ref )
	{
		$journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE status='a' AND ref=?", $ref );
		$pagetitle = sprintf( "Articles by %s containing \"%s\"", $journo['prettyname'], $findtext );
	}
	else
		$pagetitle = "Articles containing \"$findtext\"";
	page_header( $pagetitle );

	if( $ref )
	{
		$q = db_query( "SELECT id,title,description,pubdate,permalink,srcorg " .
		"FROM article,journo_attr j " .
		"WHERE status='a' AND id=j.article_id AND j.journo_id=? " .
			"AND content ILIKE ? " .
		"ORDER BY pubdate DESC", $journo['id'], '%'.$findtext.'%' );
		printf( "<h2>Articles by %s containing \"%s\"</h2>", $journo['prettyname'], $findtext );
	}
	else
	{
		printf( "<h2>Articles within the last week containing \"%s\"</h2>", $findtext );
		$q= db_query( "SELECT id,title,description,pubdate,permalink,byline,srcorg FROM article WHERE status='a' AND AGE(pubdate) < interval '7 days' AND content ILIKE ? ORDER BY pubdate DESC", '%' . $findtext . '%' );
	}

	print "<ul>\n";

	$cnt = 0;
	$orgs = get_org_names();
	while( $r=db_fetch_array($q) )
	{
		++$cnt;
		$org = $orgs[ $r['srcorg'] ];
		$pubdate = pretty_date(strtotime($r['pubdate']));
		print "<li>\n";
		print "<a href=\"/article?id={$r['id']}\">{$r['title']}</a>, {$pubdate}, <em>{$org}</em>\n";
		print "<small>(<a href=\"{$r['permalink']}\">original article</a>)</small\n";
		print "</li>\n";
	}
	print "</ul>\n";

	printf( "<p>Found %d matching articles</p>", $cnt );
	page_footer();
}


/* Mark up the byline of an article with links to the journo pages.
 * TODO: should use journo_alias table instead of journo.prettyname
 */ 
function markup_byline( $byline, $article_id )
{
	$sql = <<<EOT
SELECT j.prettyname, j.ref
	FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
	WHERE attr.article_id=?	AND j.status='a';
EOT;

	$journos = db_getAll( $sql, $article_id );

	foreach( $journos as $j )
	{
		$pat = sprintf("/%s/i", $j['prettyname'] );

        $replacement = '<span class="author vcard"><a class="url fn" href="'. $j['ref'] . '">\0</a></span>';
		$byline = preg_replace( $pat, $replacement, $byline );
	}

	return $byline;
}


function emit_block_articleinfo( $art )
{
	$article_id = $art['id'];
	$orgs = get_org_names();

	$title = $art['title'];
	$byline = markup_byline( $art['byline'], $article_id );

	$org = $orgs[ $art['srcorg'] ];
    $permalink = $art['permalink'];
    $pubdate_timestamp = strtotime($art['pubdate']);
	$pubdate_human = strftime('%a %e %B %Y', $pubdate_timestamp );
	$pubdate_iso = date('c', $pubdate_timestamp );
	$desc = $art['description'];


?>

<div class="hentry">
  <h2 class="entry-title"><?php echo $title; ?></h2>
  <?php echo $byline; ?><br/>
  <?php echo $org; ?>, <abbr class="published" title="<?php echo $pubdate_iso; ?>"><?php echo $pubdate_human; ?></abbr><br/>
  <blockquote><?php echo $desc; ?></blockquote>
  <a rel="bookmark" href="<?php echo $art['permalink']; ?>">Read the original article at <?php echo $org;?></a>
</div>

<?php

}



function emit_block_articletags( $article_id )
{

?>
<div class="boxnarrow tags">
<h2>Topics mentioned</h2>
<div class="boxnarrow-content">
<?php

	$q = db_query( 'SELECT tag, freq FROM article_tag WHERE article_id=? ORDER BY freq DESC', $article_id );
	tag_cloud_from_query( $q );

?>
</div>
</div>
<?php

}




function emit_block_commentlinks( $article_id )
{

	/* profile for various non-newspaper sites we source from - they all use their own terminology */
	$profiles = array(
		'digg' => array( 'scoreterm'=>'diggs', 'prettyname'=>'Digg' ),
		'reddit' => array( 'scoreterm'=>'points', 'prettyname'=>'Reddit' ),
		'newsvine' => array( 'scoreterm'=>'votes', 'prettyname'=>'Newsvine' ),
		'fark' => array( 'scoreterm'=>'votes', 'prettyname'=>'Fark' ),
		'del.icio.us' => array( 'scoreterm'=>'saves', 'prettyname'=>'del.icio.us' ),
		'DEFAULT' => array( 'scoreterm'=>'points', 'prettyname'=>'unknown' ),
	);

    /* add the newspapers to the list of profiles */
    $rows = db_getAll( "SELECT shortname, prettyname FROM organisation" );
    foreach( $rows as $r )
        $profiles[$r['shortname']] = array( 'prettyname'=>$r['prettyname'], 'scoreterm'=>'points' )


?>
<div class="boxwide">
<h2>Where are people talking about this article?</h2>
<div class="boxwide-content">
<?php

	$q = db_query( "SELECT * FROM article_commentlink WHERE article_id=?", $article_id );
	$n = db_num_rows( $q );
	if( $n > 0 )
	{
		print "<ul>\n";
		while( $row = db_fetch_array( $q ) )
		{
			$source = $row['source'];
    		$profile = $profiles['DEFAULT'];
			if( array_key_exists( $source, $profiles ) )
				$profile = $profiles[$source];

			$bits = array();
			if( !is_null( $row['num_comments'] ) )
				$bits[] = sprintf( "%d comments", $row['num_comments'] );
			if( $row['score'] )
				$bits[] = sprintf( "%d %s", $row['score'], $profile['scoreterm'] );

			printf( "<li>%s (<a href=\"%s\">%s</a>)</li>\n",
				$profile['prettyname'],
				$row['comment_url'],
				implode( ', ', $bits) );
		}
		print "</ul>\n";
	}
	else
	{
		print "<p>None known</p>\n";
	}
/*
?>
<p class="disclaimer">Based on data from
<a href="http://del.icio.us">del.icio.us</a>,
<a href="http://digg.com">digg</a>,
<a href="http://fark.com">fark</a>,
<a href="http://newsvine.com">newsvine</a> and
<a href="http://reddit.com">reddit</a>
</p>
*/

?>
</div>
</div>
<?php

}



/* display blogs which reference this article */
function emit_block_bloglinks( $article_id )
{

?>
<div class="boxwide">
<h2>Which blogs are linking to this article?</h2>
<div class="boxwide-content">
<?php

	$q = db_query( "SELECT * FROM article_bloglink WHERE article_id=? ORDER BY linkcreated DESC", $article_id );
	$n = db_num_rows( $q );
	if( $n > 0 )
	{
		print "<p>$n blog posts link to this article:</p>\n";
		print "<ul>\n";
		while( $row=db_fetch_array($q) )
		{

			printf("<li>%s</li>\n", gen_bloglink( $row ) );
		}

		print "</ul>\n";
	}
	else
	{
		print "<p>None known</p>\n";
	}

	// TODO: form to submit blog links?

?>
<p class="disclaimer">Based on blogs recorded by <a href="http://technorati.com">Technorati</a></p>
</div>
</div>
<?php

}



/* return a prettified blog link */
function gen_bloglink( $l )
{
	$blog_link = sprintf( "<a href=\"%s\">%s</a>", $l['blogurl'], $l['blogname'] );

	$url = $l['nearestpermalink'];
	if( !$url )
	{
		/* we don't have a permalink to that posting... */
		$url = $l['blogurl'];
	}

	$title = $l['title'];
	if( !$title )
	{
		$title = $l['blogname'];
	}
	$entry_link = sprintf( "<a href=\"%s\">%s</a>", $url, $title );

	$linkdate = pretty_date(strtotime($l['linkcreated']));

	$s = sprintf( "%s<br />\n<cite class=\"posted\">posted at %s on %s</cite>\n", $entry_link, $blog_link, $linkdate );

	return $s;
}

