<?php
// article.php
// admin page for searching for and editing articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

require_once "HTML/QuickForm.php";
//require_once "HTML/QuickForm/Rule.php";
//require_once "HTML/QuickForm/Renderer/Default.php";

$article_id = get_http_var( 'article_id' );
$action = get_http_var( 'action' );

admPageHeader();

if( !$article_id )
{
	/* default - show the search form */
	EmitFindArticleForm();
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
	$q = db_query( 'SELECT id,title,byline,description,pubdate,lastscraped,firstseen,lastseen,content,permalink,srcurl,srcorg,srcid FROM article WHERE id=?', $article_id );

	$art = db_fetch_array($q);
	return $art;
}


/* display article */
function EmitArticle( $art )
{
	
	print "<table border=1>\n";
	print "<tr><th>title</th><td><h2>{$art['title']}</h2></td></tr>\n";
	print "<tr><th>id</th><td>{$art['id']}</td></tr>\n";
	$orgs = get_org_names();
	$orgname = $orgs[$art['srcorg']];
	print "<tr><th>srcorg</th><td>{$orgname} (id {$art['srcorg']})</td></tr>\n";
	print "<tr><th>pubdate</th><td>{$art['pubdate']}</td></tr>\n";
	print "<tr><th>lastscraped</th><td>{$art['lastscraped']}</td></tr>\n";
	print "<tr><th>byline</th>\n";

	print "<td>\n";
	print "raw byline: \"{$art['byline']}\"<br/>\n";
	print "attributed to:<br/>\n";
	EmitAttribution( $art );
	print "</td></tr>\n";


	print "<tr><th>description</th><td>{$art['description']}</td></tr>\n";
	print "<tr><th>permalink</th><td><a href=\"{$art['permalink']}\">{$art['permalink']}</a></td></tr>\n";
	print "<tr><th>srcid</th><td>{$art['srcid']}</td></tr>\n";
	print "<tr><th>srcurl</th><td>{$art['srcurl']}</td></tr>\n";

	print "<tr><th>content</th><td>\n";
	
	print "<table>\n";
	print "<tr><th>displayed</th><th>source HTML</th></tr>\n";
	print "<tr><td width=\"50%\">\n{$art['content']}\n</td>\n";
	print "<td width=\"50%\">\n";
	
//        print "<iframe src=\"{$art['srcurl']}\" width=\"100%\" height=\"100%\"></iframe>\n";
	
	$srchtml = htmlentities( $art['content'], ENT_COMPAT, 'UTF-8' );
	$srchtml = str_replace( "\n", "<br>\n", $srchtml );
	print $srchtml;
	print "\n</td></tr>\n";
	print "</table>\n";

	print "</td></tr>\n";
	print "</table>\n";

	print "</table>";
}




/* Display a form for searching for articles */
function EmitFindArticleForm()
{
	print( "<h2>Find Article(s)</h2>\n" );
	$orgs = get_org_names();

	$orgs = array('any'=>'Any') + get_org_names();

	$periods = array( 'all'=>'All', '24hrs'=>'Last 24 hrs', '7days'=>'Last 7 days' );

    $form = new HTML_QuickForm('article_query','get' );
	$form->addElement( 'select', 'srcorg', 'Organisation', $orgs );
	$form->addElement( 'select', 'pubdate', 'Published within', $periods );
	$form->addElement( 'select', 'lastscraped', 'Last scraped within', $periods);
	$form->addElement( 'text', 'headline', 'Headline containing:',
		array('size'=>80 ) );
	$form->addElement( 'text', 'byline', 'Byline containing:',
		array('size'=>80 ) );
	$form->addElement( 'text', 'url', 'URL containing:',
		array('size'=>80 ) );
	$form->addElement( 'submit', 'submit', 'Search');

	$form->setDefaults( array('pubdate'=>'24hrs' ) );

//	$form->addElement( 'hidden', 'page', get_http_var( 'page' ) );

	$form->display();

	if( $form->validate() )
		$form->process( 'process_article_query' );
}


/* translate our period identifiers into postgres interval */
function period_to_pginterval( $period ) {
	if( $period == '24hrs' ) {
		return '24 hours';
	} else if( $period == '7days' ) {
		return '7 days';
	}
	return null;
}



/* Callback to process the article search form.
 * builds and executes the query, and displays a list of matches.
 */
function process_article_query( $values )
{

    $params = array();
    $conds = array();

	$period = period_to_pginterval( $values['lastscraped'] );
	if( $period ) {
        $conds[] = "lastscraped >= (now() - interval ?)";
        $params[] = $period;
    }

	$period = period_to_pginterval( $values['pubdate'] );
	if( $period ) {
        $conds[] = "pubdate >= (now() - interval ?)";
        $params[] = $period;
    }

    if( $values['srcorg'] != 'any' ) {
        $conds[] = "srcorg = ?";
        $params[] = $values['srcorg'];
    }

	if( $values['headline'] ) {
		/* search for any headline containing this snippet */
        $conds[] = "title ilike ?";
        $params[] = '%' . $values['headline'] . '%';
	}

	if( $values['byline'] ) {
		/* search for any byline containing this snippet */
        $conds[] = "byline ilike ?";
        $params[] = '%' . $values['byline'] . '%';
	}

	if( $values['url'] ) {
		/* search for any byline containing this snippet */
        $conds[] = "permalink ilike ?";
        $params[] = '%' . $values['url'] . '%';
	}

    $sql = "SELECT id,title,byline,description,permalink,srcorg,to_char(pubdate, 'YYYY-MM-DD') as when " .
       'FROM article';

    if( $conds ) {
        $sql = $sql . ' WHERE ' . implode( ' AND ', $conds );
    }
    $sql = $sql . ' ORDER BY pubdate DESC';
 
//    print "<pre>\n";
//    print db_subst( $sql, $params );
//    print "</pre>\n";
    $orgs = get_org_names();

    $q = db_query( $sql, $params );

    printf( "<p>%d matches</p>\n", db_num_rows( $q ) );
    
    print "<table border=1>\n";
	print "<tr>\n";
	print " <th>pubdate</th>\n";
	print " <th>headline</th>\n";
	print " <th>byline</th>\n";
	print "</tr>\n";
    while( $r=db_fetch_array($q) ) {

        $arturl = "?article_id={$r['id']}";
        $checkurl = "?page=checkscrapers&article_id={$r['id']}";

        $out = '';
		$out .= "<td>{$r['when']}</td>\n";
        $out .= "<td>\"<a href=\"{$arturl}\">{$r['title']}\"</a><br />\n<small>(<a href=\"{$r['permalink']}\">original at {$orgs[$r['srcorg']] }</a>)(<a href=\"{$checkurl}\">check</a>)</small></td>";
        $out .= "<td>{$r['byline']}</td>";
        print "<tr>$out</tr>\n";
    }
    print "</table>";
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
		$removal_url = sprintf( "?article_id=%s&action=remove_journo&journo_id=%s",
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

	/* make sure the journos page gets updated on next view */
	$cachename = sprintf( "j%s", $journo_id );
	db_query( "DELETE FROM htmlcache WHERE name=?", $cachename );

	/* TODO: LOG IT! */
	db_commit();
	print( "<strong>REMOVED JOURNO</strong>" );
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
<a href="?article_id=<?=$art['id'];?>">No, I've changed my mind</a>
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
?>
<form method="post" action="/adm/article">
<a href="<?=$url;?>"><?=$prettyname;?></a>
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

?>
