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



function emit_block_articleinfo( $art )
{
	$article_id = $art['id'];
	$title = $art['title'];
	$byline = $art['byline'];
	$orgs = get_org_names();
	$org = $orgs[ $art['srcorg'] ];
	
	$pubdate = strftime('%a %e %B %Y', strtotime($art['pubdate']) );
	$desc = $art['description'];

	print "<h2>$title</h2>\n";
	print "$byline<br>\n";
	print "$org, $pubdate<br>\n";
	print "<blockquote>$desc</blockquote>\n";

	print "<a href=\"{$art['permalink']}\">Read the original article at $org</a>\n";
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

	/* profile for various sites we source from - they all use their own terminology */
	$profiles = array(
		'digg' =>     array( 'scoreterm'=>'diggs' ),
		'reddit' =>   array( 'scoreterm'=>'points' ),
		'newsvine' => array( 'scoreterm'=>'votes' ),
		'DEFAULT' =>  array( 'scoreterm'=>'points' ),
	);

?>
<div class="boxwide">
<h2>What comments are people making about this article?</h2>
<div class="boxwide-content">
<?php

	$q = db_query( "SELECT * FROM article_commentlink WHERE article_id=?", $article_id );
	$n = db_num_rows( $q );
	if( $n > 0 )
	{
		print "<p>Bookmarked at:</p>\n";
		print "<ul>\n";
		while( $row = db_fetch_array( $q ) )
		{
			$source = $row['source'];
			if( array_key_exists( $source, $profiles ) ) {
				$profile = $profiles[$source];
			} else {
				$profile = $profiles['DEFAULT'];
			}

			$comments = sprintf( "<a href=\"%s\">%d comments</a>", $row['comment_url'], $row['num_comments'] );

			$score = '';
			if( $row['score'] )
				$score = sprintf( ", %d %s", $row['score'], $profile['scoreterm'] );

			printf( "<li>%s (%s%s)</li>\n", $source, $comments, $score );
		}
		print "</ul>\n";
	}
	else
	{
		print "<p>None known</p>\n";
	}

?>
<p class="disclaimer">Based on data from
<a href="http://digg.com">digg</a>,
<a href="http://newsvine.com">newsvine</a> and
<a href="http://reddit.com">reddit</a>
</p>
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

