<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



// handle either base-10 or base-36 article ids
$article_id = get_http_var( 'id36' );
if( $article_id ) {
    $article_id = base_convert( $article_id, 36,10 );
} else {
    $article_id = get_http_var( 'id' );
}

emit_page_article( $article_id );

function emit_page_article( $article_id )
{
    $art = article_collect( $article_id );  //db_getRow( 'SELECT * FROM article WHERE id=?', $article_id );
    if( $art['status'] != 'a' )
        return; /* TODO: a message or something... */
    $pagetitle = $art['title'];
    $params = array( 'canonical_url'=>article_url( $article_id ) );
    page_header( $pagetitle, $params );
    {
        extract( $art );
        include "../templates/article.tpl.php";
    }
    page_footer();
}


function article_collect( $article_id ) {
    $art = db_getRow( 'SELECT * FROM article WHERE id=?', $article_id );
    $art['article_id'] = $art['id'];
    $art['blog_links'] = db_getAll( "SELECT * FROM article_bloglink WHERE article_id=? ORDER BY linkcreated DESC", $article_id );
    $art['byline'] = markup_byline( $art['byline'], $article_id );

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
    $sim_orderby = strtolower( get_http_var( 'sim_orderby', 'score' ) );
    $sim_showall = strtolower( get_http_var( 'sim_showall', 'no' ) );

    if($sim_orderby=='date')
        $ord = 'a.pubdate DESC, s.score DESC';
    else    // 'score'
        $ord = 's.score DESC, a.pubdate DESC';

    $sql = <<<EOT
SELECT *
    FROM article a INNER JOIN article_similar s ON s.other_id=a.id
    WHERE s.article_id=? and a.status='a'
    ORDER BY {$ord}
EOT;

    $sim_arts = db_getAll( $sql, $article_id );
    foreach( $sim_arts as &$s ) {
        article_Augment( $s );
    }
    unset( $s );

    $sim_total = sizeof($sim_arts);
    $default_cnt = 10;
    if( $sim_total > $default_cnt && $sim_showall != 'yes' )
        $sim_arts = array_slice( $sim_arts, 0, $default_cnt );

    $art['sim_orderby'] = $sim_orderby;
    $art['sim_showall'] = $sim_showall;
    $art['sim_total'] = $sim_total;
    $art['sim_arts'] = $sim_arts;


    $tags = db_getAll( 'SELECT tag, freq FROM article_tag WHERE article_id=? ORDER BY freq DESC', $article_id );
    $sorted_tags = array();
	foreach( $tags as $t )
	{
		$sorted_tags[ $t['tag'] ] = intval( $t['freq'] );
	}
	ksort( $sorted_tags );
    $art['tags'] = $sorted_tags;


    $art['comment_links'] = collect_commentlinks( $article_id );

    return $art;
}



function collect_commentlinks( $article_id )
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
function markup_byline( $byline, $article_id )
{
    $sql = <<<EOT
SELECT j.prettyname, j.ref
    FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
    WHERE attr.article_id=? AND j.status='a';
EOT;

    $journos = db_getAll( $sql, $article_id );

    foreach( $journos as $j )
    {
        $pat = sprintf("/%s/i", $j['prettyname'] );

        $replacement = '<span class="author vcard"><a class="url fn" href="/'. $j['ref'] . '">\0</a></span>';
        $byline = preg_replace( $pat, $replacement, $byline );
    }

    return $byline;
}








/* return a prettified blog link */
function gen_bloglink( $l )
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
