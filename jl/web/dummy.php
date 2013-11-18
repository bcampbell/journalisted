<?php
// stand-in functions, just enough to let dummyarticle.php and dummyjourno.php work.
// aim is to avoid any other dependencies


require_once '../conf/general';
//require_once '../phplib/misc.php';

function journo_link( $j )
{
    $a = '';
    if( arr_get( 'ref', $j ) ) {
        $a .= "<a href=\"/{$j['ref']}\" >{$j['prettyname']}</a>";
    } else {
        $a .= $j['prettyname'];
    }

    if( arr_get( 'oneliner', $j ) ) {
        $a .= " <em>({$j['oneliner']})</em>";
    }

    return $a; 
}

/* return a human-readable date, given a datetime */
function pretty_date( $t )
{
    if( $t instanceof DateTime ) {
        $now = new DateTime();
        if( $t->format('Y') == $now->format('Y') )
            return $t->format('l j F'); // eg "Monday 24 October"
        else
            return $t->format('D j F Y'); // eg "Mon 24 October 2006"
    } else {
        /* depreicated */
        $now = time();
        if( strftime('%Y',$t) == strftime('%Y',$now) )
            return strftime('%A %e %B',$t);
        else
            return strftime('%a %e %B %Y',$t);
    }
}


// join strings using ", " and " and "
// eg ("foo", "bar", "wibble") => "foo, bar and wibble"
function pretty_implode( $parts)
{
    if( empty( $parts ) )
        return '';
    $last = array_pop( $parts );
    if( empty( $parts ) )
        return $last;
    else
        return implode( ', ', $parts ) . " and " . $last;
}


/* return the url of the RSS feed for this journo */
function journoRSS( $journo ) {
    return sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );
}


function SafeMailto( $addr, $text='' )
{
	$addr = addslashes( $addr );


    $foo = explode( '@', $addr, 2); /* split into name and domain */
	$parts = array_reverse( explode( '.', $foo[1]) );   /* domain parts in revers order */
    $parts[] = $foo[0]; /* last arg is name */

	$noscript_text = $text;
    if( !$noscript_text )
    {
        $noscript_text = "[<em>Sorry spam protected - you'll need to enable Javascript</em>]";
    }

	$text = addslashes( $text );
	$out = "<script type=\"text/javascript\">gen_mailto( '{$text}', '" . implode( "','", $parts ). "');</script><noscript>{$noscript_text}</noscript>";

	return $out;
}


// generate a link for a tag
function tag_gen_link( $tag, $journo_ref=null, $period=null )
{
    /* old version, using tags page */
/*
	$l = '/' . ($journo_ref ? $journo_ref:'tags');
	if( $period )
		$l = $l . '/' . $period;
	$l = $l . '/' . urlencode($tag);
*/

    /* new version, using xapian index */
//    $query = (strpos($tag, ' ') === FALSE ) ? $tag: '"'.$tag.'"';
    $query = $tag;

    $l = '/search?a=' . urlencode( $query );
    if( $journo_ref )
        $l .= '&by=' . $journo_ref;
	return $l;
}


function tag_display_cloud( &$tags, $journo_ref=null, $period=null )
{
	$minsize = 10;
	$maxsize = 24;
    // map most frequent to maxsize and least frequent to minsize

	$total = 0;
	$low = 9999;
	$high = 0;
	foreach( $tags as $freq ) {
		if( $freq > $high )
			$high = $freq;
		if( $freq < $low )
			$low = $freq;
	}
	foreach( $tags as $tag=>$freq )
	{
		if( $high == $low )
			$size = $minsize + ($maxsize-$minsize)/4;	// artibrary. looks about right.
		else
			$size = $minsize + ( (($freq-$low)*($maxsize-$minsize)) / ($high-$low)  );

		printf( "&nbsp;<a href=\"%s\" style=\"font-size: %dpx\">%s</a>&nbsp;\n", tag_gen_link( $tag, $journo_ref, $period ), $size, $tag);
	}

}


// return a value in an array if it exists, otherwise return the default.
function arr_get( $key, &$arr, $default=NULL )
{
    assert( is_array( $arr ) );
    if( array_key_exists( $key, $arr ) )
        return $arr[$key];
    else
        return $default;
}

function h( $s, $enc='UTF-8' )
{
    return htmlentities( $s, ENT_QUOTES, $enc='UTF-8' );
}
function gatso_report_html() {
}

function page_header($title,$params) {
    $mnpage = '';
    $rss_feeds = array();
    $canonical_url = null;
    $js_files = array( "/jl.js" );
    $head_extra = '';
    $datestring = date( 'l d.m.Y' );
    $logged_in_user = null;
    $can_edit_profile = FALSE;
    $search = array( 'q'=>'', 'type'=>'journo' );
    if (array_key_exists('head_extra_fn', $params)) {
        ob_start();
        call_user_func( $params['head_extra_fn'] );
        $head_extra .= ob_get_contents();
        ob_end_clean();
    }
    header( 'Content-Type: text/html; charset=utf-8' );
    include "../templates/header.tpl.php";
}
function page_footer()
{
    include "../templates/footer.tpl.php";
}


/*
article_url
article_gen_bloglink
pretty_implode
pretty_date
 */

function article_id_to_id36( $id ) {
    return base_convert( $id, 10,36 );
}

function article_id36_to_id( $id ) {
    return base_convert( $id, 36,10 );
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
