<?php
// article.php
// admin page for searching for and editing articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';


// handle either base-10 or base-36 articles
$article_id = get_http_var( 'id36' );
if( $article_id ) {
    $article_id = article_id36_to_id( $article_id );
} else {
    $article_id = get_http_var( 'id' );
    if( !$article_id )
        $article_id = get_http_var( 'article_id' );
}
$action = get_http_var( 'action' );

admPageHeader();

if( !$article_id )
{
    print "<p>missing article id</p>";
}
else
{
	$art = FetchArticle( $article_id );
	if( $action == 'remove_journo' )
	{
		$journo_id = get_http_var( 'journo_id' );
		ConfirmRemoveJourno( $art, $journo_id );
	}
	else if ( $action == 'remove_journo_confirmed' )
	{
		$journo_id = get_http_var( 'journo_id' );
		RemoveJourno( $art, $journo_id );
		EmitArticle( $art );
	}
	else if( $action == 'lookup_journo' )
	{
		LookupJourno( $art, get_http_var('journo_name') );
	}
	else if( $action == 'add_journo' )
	{
		AddJourno( $art, get_http_var('journo_id') );
		EmitArticle( $art );
	}
	else if( $action == 'update_similar' )
    {
        DoUpdateSimilars( $article_id );
    }
	else
	{
		/* default action is to just display the article */
		EmitArticle( $art );
	}
}

admPageFooter();

/********************************/



/* pull an article out of the DB and return it as an array */
function FetchArticle( $article_id )
{
	$q = db_query( 'SELECT * FROM article WHERE id=?', $article_id );
	$art = db_fetch_array($q);
    $art['images'] = db_getAll( "SELECT * FROM article_image WHERE article_id=?", $article_id );
    $art['content'] = db_getOne( "SELECT content FROM article_content WHERE article_id=?", $article_id );
	return $art;
}


/* display article */
function EmitArticle( $art )
{
	$orgs = get_org_names();
	$orgname = $orgs[$art['srcorg']];


    $sql = <<<EOT
SELECT a.id, a.title, a.pubdate, a.srcorg, s.score
    FROM (article_similar s INNER JOIN article a ON a.id=s.other_id )
    WHERE s.article_id=?
    ORDER BY s.score DESC
EOT;

    $similar_articles = db_getAll( $sql, $art['id'] );

    foreach( $similar_articles as &$sim )
        $sim['srcorgname'] = $orgs[$sim['srcorg'] ];
    unset($sim);

    $urls = db_getAll("SELECT url FROM article_url WHERE article_id=?",$art['id']);

?>
<table border="1">
<tr><th>title</th><td><h2><?php echo $art['title']; ?></h2><a class="button edit" href="/adm/editarticle?id36=<?= article_id_to_id36($art['id']); ?>">edit article</a></td></tr>
<tr><th>status</th><td><?php echo $art['status']; ?></td></tr>
<tr><th>id</th><td><?php echo $art['id']; ?> [<a href="<?= article_url( $art['id'] ); ?>">go to article page</a>]
<tr><th>srcorg</th><td><?php echo $orgname;?> (id <?php echo $art['srcorg'];?>)</td></tr>
<tr><th>urls</th><td>
    permalink: <a href="<?php echo $art['permalink'];?>"><?php echo $art['permalink']; ?></a><br/>
    srcurl: <a href="<?php echo $art['srcurl'];?>"><?php echo $art['srcurl']; ?></a><br/>
    all urls <?= sizeof($urls); ?>:
    <ul><?php foreach($urls as $foo) { $url=$foo['url']; ?>
        <li><a href="<?= $url ?>"><?= $url ?></a></li>
    <?php } ?></ul>
</td></tr>
<tr><th>pubdate</th><td><?php echo $art['pubdate']; ?></td></tr>
<tr><th>lastscraped</th><td><?php echo $art['lastscraped']; ?></td></tr>
<tr><th>byline</th>
  <td>
  raw byline: "<?php echo $art['byline']; ?>"<br/>
  attributed to:<br/>
  <?php EmitAttribution( $art ); ?>
  </td>
</tr>
<tr><th>description</th><td><?php echo $art['description']; ?></td></tr>
<tr><th>srcid</th><td><?php echo $art['srcid']; ?></td></tr>
<tr><th>total_comments</th><td><?php echo $art['total_comments']; ?></td></tr>
<tr><th>total_bloglinks</th><td><?php echo $art['total_bloglinks']; ?></td></tr>
<tr><th>needs_indexing</th><td><?php echo $art['needs_indexing']; ?></td></tr>
<tr><th>last_similar</th><td><?php echo $art['last_similar']; ?></td></tr>
<tr><th>last_comment_check</th><td><?php echo $art['last_comment_check']; ?></td></tr>

<tr><th>images</th><td>
 <ul>
<?php foreach( $art['images'] as $im ) { ?>
 <li>
   <a href="<?= $im['url'] ?>"><?= $im['url'] ?></a><br/>
   caption: <?= h($im['caption']) ?><br/>
   credit: <?= h($im['credit']) ?><br/>
<?php } ?>
 </ul>
</td>

</table>

<h2>content</h2>
<?php if( is_null($art['content']) ) { ?>
<p> -- content not scraped -- </p>
<?php } else { ?>
<table border=1>
  <tr><th>displayed</th><th>source HTML</th></tr>
  <tr>
    <td width="50%">
<?php echo $art['content']; ?>
    </td>
    <td width="50%">
	
<?php	
	$srchtml = htmlentities( $art['content'], ENT_COMPAT, 'UTF-8' );
	$srchtml = str_replace( "\n", "<br>\n", $srchtml );
	print $srchtml;
?>
    </td>
  </tr>
</table>
<?php } ?>

<h2>similar articles</h2>
<a href="/adm/article?id=<?php echo $art['id']; ?>&action=update_similar">Run similar-articles tool now</a> (to update the list)<br/>
<table>
  <tr><th>score</th><th>other article</th></tr>
<?php foreach( $similar_articles as $sim) { ?>
  <tr>
    <td><?php echo $sim['score']; ?></td>
    <td>
      <a href="/adm/article?id=<?php echo $sim['id']; ?>"><?php echo $sim['title']; ?></a>,
      <?php echo $sim['srcorgname']; ?>, <?php echo $sim['pubdate']; ?>
    </td>
<?php } ?>
</table>
<?php
}






/* output a list of the journos attributed with this article,
 * with links for adding/removing them */
function EmitAttribution( $art )
{
	$article_id = $art['id'];

	/* get the journos it's attributed to */
	$journos = db_getAll( "SELECT j.id, j.ref, j.prettyname ".
		"FROM journo j INNER JOIN journo_attr attr ".
			"ON attr.journo_id=j.id ".
		"WHERE attr.article_id=?",
		$article_id );

	print( "<ul>\n" );
	foreach( $journos as $j )
	{
		$journo_url = "/adm/journo?journo_id=" . $j['id'];
		$removal_url = sprintf( "/adm/article?id=%s&action=remove_journo&journo_id=%s",
			$article_id, $j['id'] );

		printf( "<li><a href=\"%s\">%s</a> <small>[<a href=\"%s\">remove</a>]</small></li>\n",
			$journo_url,
			$j['prettyname'],
			$removal_url );
	}	
	print( "</ul>\n" );
	print("Add a Journo:");
	EmitLookupForm( $art );
}

/* remove this journo from the article*/
function RemoveJourno( $art, $journo_id )
{
	$article_id = $art['id'];

	db_query( "DELETE FROM journo_attr WHERE article_id=? AND journo_id=?",
		$article_id, $journo_id );
	db_commit();
	print( "<strong>REMOVED JOURNO</strong>" );
	/* TODO: LOG IT! */

	/* make sure the journos page gets updated on next view */
	$cacheid = sprintf( "j%s", $journo_id );
	cache_clear( $cacheid );

}


/* Form to confirm that the journo _should_ in fact be removed from this
 * article
 */
function ConfirmRemoveJourno( $art, $journo_id )
{
	$j = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );
	$journo_url = "/adm/journo?journo_id=" . $j['id'];

?>
<form method="post" action="/adm/article">
<p>Are you sure you want to remove
<a href="<?=$journo_url;?>"><?=$j['prettyname']; ?></a>
from the article?<br />
<input type="hidden" name="article_id" value="<?=$art['id'];?>" />
<input type="hidden" name="journo_id" value="<?=$journo_id;?>" />
<input type="hidden" name="action" value="remove_journo_confirmed" />
<input type="submit" name="submit" value="Yes!" />
<a href="/adm/article?id=<?=$art['id'];?>">No, I've changed my mind</a>
</form>
<?php

}


/* Look up journo by name. Shows a form for entering names to search for,
 * followed by a list of currently matching journos, each with an 'Add'
 * button.
 */
function LookupJourno( $art, $lookup )
{
	EmitLookupForm( $art,$lookup );

	$journos = db_getAll( "SELECT * FROM journo WHERE prettyname ilike ? ORDER BY lastname",
		'%'.$lookup.'%' );

	if( count($journos) > 0 )
	{
		print "<ul>";
		foreach( $journos as $j )
		{
			print "<li>";
			EmitAddJournoForm( $art, $j );
			print "</li>\n";
		}
		print "</ul>\n";
	}
	else
		print "<p>No matches</p>\n";
}


/* Show a single journo with an 'Add' button for adding them to the article */
function EmitAddJournoForm( $art, $journo )
{
	$prettyname = $journo['prettyname'];
	$url = '/adm/journo?journo_id='.$journo['id'];
	$journo_id = $journo['id'];
	$oneliner = $journo['oneliner'];
	$ref = $journo['ref'];

?>
<form method="post" action="/adm/article">
<a href="<?=$url;?>"><?=$prettyname;?> <small>(<code><?=$ref;?></code>)</small></a> (<?=$oneliner;?>) 
<input type="hidden" name="article_id" value="<?=$art['id'];?>" />
<input type="hidden" name="action" value="add_journo" />
<input type="hidden" name="journo_id" value="<?=$journo_id;?>" />
<input type="submit" name="submit" value="Add" />
</form>
<?php
}


/* form for looking up a journo by name */
function EmitLookupForm( $art, $lookup="" )
{
?>
<form method="post" action="/adm/article">
<input type="hidden" name="article_id" value="<?=$art['id'];?>" />
<input type="hidden" name="action" value="lookup_journo" />
<input type="text" name="journo_name" value="<?=$lookup;?>" size="40" />
<input type="submit" name="submit" value="Lookup" />
</form>
<?php
}


/* attribute journo to the article */
function AddJourno( $art, $journo_id )
{
	db_query( "INSERT INTO journo_attr (journo_id, article_id) VALUES (?,?)",
		$journo_id, $art['id'] );

	/* make sure the journos page gets updated on next view */
	$cachename = sprintf( "j%s", $journo_id );
	db_query( "DELETE FROM htmlcache WHERE name=?", $cachename );

	/* TODO: LOG IT! */
	db_commit();
	print( "<strong>ADDED JOURNO</strong>" );

}

function DoUpdateSimilars( $article_id )
{
    $jlbin = OPTION_JL_FSROOT . '/bin';
	$cmd = "{$jlbin}/similar-article -v -a {$article_id} 2>&1";

?>
<h3>Updating similar-article list for article <?php echo $article_id; ?></h3>
(<a href="/adm/article?id=<?php echo $article_id; ?>">back to article admin page</a>)<br/>
<tt><?php echo $cmd; ?></tt>
<hr>
<?php
//	putenv("JL_DEBUG=2");
    ob_start();
    passthru( $cmd );
  	$output = ob_get_contents();
    ob_end_clean();

?>
<p><pre>
<?php echo admMarkupPlainText( $output ); ?>
</pre></p>
<hr>
<?php

}




/**********************************/



function run($command) {
	ob_start();
	passthru($command);
	$ret = ob_get_contents();
	ob_end_clean();
    return $ret;
}

?>
