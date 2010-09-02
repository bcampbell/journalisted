<?php
/* Common article-related functions */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/article_rdf.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


function article_id_to_id36( $id ) {
    return base_convert( $id, 10,36 );
}

function article_id36_to_id( $id ) {
    return base_convert( $id, 36,10 );
}



// prepare an article for display by adding a few derived fields
function article_augment( &$a )
{
    $d = new datetime( $a['pubdate'] );
    $a['pretty_pubdate'] = pretty_date(strtotime($a['pubdate']));
    $a['iso_pubdate'] = $d->format('c');
    // fill in prettyname of publisher, if possible
    if( !array_key_exists('srcorgname', $a ) && array_key_exists('srcorg',$a) ) {
        $orgs = get_org_names();
        $a['srcorgname'] = $orgs[ $a['srcorg'] ];
    }
}


// build an article url from an id
function article_url( $article_id, $sim_orderby='score', $sim_showall='no' )
{
    $id36 = article_id_to_id36( $article_id );
    $url = "/article/{$id36}";
    $extra = array();
    if( strtolower($sim_orderby) == 'date' )
        $extra[] = 'sim_orderby=date';
    if( strtolower($sim_showall) == 'yes' )
        $extra[] = 'sim_showall=yes';

    if( $extra ) {
        $url = $url . "?" . implode( '&',$extra );
    }
    return $url;
}

// collect up all the data we've got about an article, ready for displaying
function article_collect( $article_id, $sim_orderby='score', $sim_showall='no' ) {
    $art = db_getRow( 'SELECT * FROM article WHERE id=?', $article_id );
    $art['article_id'] = $art['id'];
    $art['id36'] = article_id_to_id36( $art['id'] );
    $art['blog_links'] = db_getAll( "SELECT * FROM article_bloglink WHERE article_id=? ORDER BY linkcreated DESC", $article_id );

    // journos
    $sql = <<<EOT
SELECT j.prettyname, j.ref
    FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
    WHERE attr.article_id=? AND j.status='a';
EOT;
    $art['journos'] = db_getAll( $sql, $article_id );
    $art['byline'] = article_markup_byline( $art['byline'], $art['journos'] );

    $orginfo = db_getRow( "SELECT * FROM organisation WHERE id=?", $art['srcorg'] );
    $art['srcorgname'] = $orginfo[ 'prettyname' ];
    $art['sop_name'] = $orginfo['sop_name'];
    $art['sop_url'] = $orginfo['sop_url'];
    $art['srcorg_url'] = $orginfo['home_url'];

    $permalink = $art['permalink'];
    $d = new datetime( $art['pubdate'] );
    $art['pretty_pubdate'] = pretty_date(strtotime($art['pubdate']));
    $art['iso_pubdate'] = $d->format('c');
    $art['buzz'] = BuzzFragment( $art );

    /* similar articles */
    if($sim_orderby=='date')
        $ord = 'a.pubdate DESC, s.score DESC';
    else    // 'score'
        $ord = 's.score DESC, a.pubdate DESC';

    $sql = <<<EOT
SELECT a.id,a.title, a.srcorg,a.byline,a.permalink,a.pubdate
    FROM article a INNER JOIN article_similar s ON s.other_id=a.id
    WHERE s.article_id=? and a.status='a'
    ORDER BY {$ord}
EOT;
    /* only the first 10 by default */
    if( $sim_showall != 'yes' ) {
        $sql  .= "   LIMIT 10";
    }

    $sim_arts = db_getAll( $sql, $article_id );
    foreach( $sim_arts as &$s ) {
        article_augment( $s );
    }
    unset( $s );

    $art['sim_orderby'] = $sim_orderby;
    $art['sim_showall'] = $sim_showall;
    $art['sim_arts'] = $sim_arts;

    $tags = db_getAll( 'SELECT tag, freq FROM article_tag WHERE article_id=? ORDER BY freq DESC', $article_id );
    $sorted_tags = array();
	foreach( $tags as $t )
	{
		$sorted_tags[ $t['tag'] ] = intval( $t['freq'] );
	}
	ksort( $sorted_tags );
    $art['tags'] = $sorted_tags;


    $art['comment_links'] = article_collect_commentlinks( $article_id );

    return $art;
}



function article_collect_commentlinks( $article_id )
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
    $orgs = db_getAll( "SELECT shortname, prettyname FROM organisation" );
    foreach( $orgs as $o )
        $profiles[$o['shortname']] = array( 'prettyname'=>$o['prettyname'], 'scoreterm'=>'points' );


    $comment_links = db_getAll( "SELECT * FROM article_commentlink WHERE article_id=?", $article_id );

    foreach( $comment_links as &$c ) {
        $source = $c['source'];
        $profile = $profiles['DEFAULT'];
        if( array_key_exists( $source, $profiles ) )
            $profile = $profiles[$source];

        $bits = array();
        if( !is_null( $c['num_comments'] ) ) {
            if( $c['num_comments'] > 0 )
                $bits[] = sprintf( "%d comments", $c['num_comments'] );
            else
                $bits[] = "no comments yet";
        }
        if( $c['score'] )
            $bits[] = sprintf( "%d %s", $c['score'], $profile['scoreterm'] );
        $c['buzz'] = implode( ', ', $bits);

        $c[ 'source_prettyname' ] = $profile['prettyname'];
        $c[ 'source_scoreterm' ] = $profile['scoreterm'];
    }
    return $comment_links;
}







/* Mark up the byline of an article with links to the journo pages.
 * TODO: should use journo_alias table instead of journo.prettyname
 */ 
function article_markup_byline( $byline, $journos )
{
    foreach( $journos as $j )
    {
        $pat = sprintf("/%s/i", $j['prettyname'] );

        $replacement = '<span class="author vcard"><a class="url fn" href="/'. $j['ref'] . '">\0</a></span>';
        $byline = preg_replace( $pat, $replacement, $byline );
    }

    return $byline;
}








/* return a prettified blog link */
function article_gen_bloglink( $l )
{
    $blog_link = sprintf( "<a class=\"extlink\" href=\"%s\">%s</a>", $l['blogurl'], $l['blogname'] );

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
    $entry_link = sprintf( "<a class=\"extlink\" href=\"%s\">%s</a>", $url, $title );

    $linkdate = pretty_date(strtotime($l['linkcreated']));

    $s = sprintf( "%s<br />\n<cite class=\"posted\">posted at %s on %s</cite>\n", $entry_link, $blog_link, $linkdate );

    return $s;
}



?>
