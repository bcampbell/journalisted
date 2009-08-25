<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

/*
 * Misc helper functions for journalisted
 */


 /* return an array of organisation names indexed by thier id */
function get_org_names()
{
	static $orgs=null;
	if( $orgs )
		return $orgs;

	$orgs = array();
	$q = db_query( "SELECT id,prettyname FROM organisation" );
	while( $r=db_fetch_array( $q ) )
	{
		$orgs[ $r['id'] ] = $r['prettyname'];
	}
	return $orgs;	
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



/***************************************************************************
*	function:		SafeMailto
*	description:	return a spam-safe MailTo link using javascript
*					Uses gen_mailto() in jl.js
*					$addr - email address to output
*                   $text - text for link ('' to use email address)
***************************************************************************/
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

    $l = '/search?q=' . urlencode( $query );
    if( $journo_ref )
        $l .= '&j=' . $journo_ref;
	return $l;
}



// TODO: merge with tag_cloud_from_getall()
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


// TEMP for journo page - TODO: tidy up all the tag functions
function tag_cloud_from_getall( &$tags, $journo_ref=null, $period=null )
{
    $sorted_tags = array();
	foreach( $tags as $t )
	{
		$sorted_tags[ $t['tag'] ] = intval( $t['freq'] );
	}
	ksort( $sorted_tags );
	tag_display_cloud( $sorted_tags, $journo_ref, $period );
}

// TODO: KILL THIS ONE
function tag_cloud_from_query( &$q, $journo_ref=null, $period=null )
{
	$tags = array();
	while( ($row = db_fetch_array( $q )) )
	{
		$tag = $row['tag'];
		$freq = $row['freq'];
		$tags[ $tag ] = intval( $freq );
	}
	ksort( $tags );
	tag_display_cloud( $tags, $journo_ref, $period );
}




function article_url( $article_id, $sim_orderby='score', $sim_showall='no' )
{
    $url = "/article?id={$article_id}";
    if( strtolower($sim_orderby) == 'date' )
        $url .= '&sim_orderby=date';
    if( strtolower($sim_showall) == 'yes' )
        $url .= '&sim_showall=yes';
    return $url;
}


// Send a text email (swiped from planningalerts.com)
function jl_send_text_email($to, $from_name, $from_email, $subject, $body)
{
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/plain; charset=iso-8859-1' . "\r\n";
    $headers .= 'From: ' . $from_name. ' <' . $from_email . ">\r\n";
    return mail($to, $subject, $body, $headers);
}


// Send an html email
function jl_send_html_email($to, $from_name, $from_email, $subject, $htmltext )
{
    $headers = '';
    $headers .= 'From: ' . $from_name. ' <' . $from_email . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";

    $body = chunk_split(base64_encode($htmltext));

    return mail($to, $subject, $body, $headers);
}



/* helper - return a fragment of html to show when/when article was
 * published, including a link to it
 */
function PostedFragment( &$r )
{
    $orgs = get_org_names();
    $org = $orgs[ $r['srcorg'] ];
    if( $r['pubdate'] instanceof DateTime )
        $pubdate = pretty_date( $r['pubdate'] );
    else
        $pubdate = pretty_date(strtotime($r['pubdate']));   /* depreicated */

    return sprintf( "<cite class=\"posted\"><a class=\"extlink\" href=\"%s\">%s, <em>%s</em></a></cite>",
        htmlentities($r['permalink']), $pubdate, $org );
}


/* helper - return a fragment of text to show how many comments and blog links on an article */
function BuzzFragment( &$r )
{
    $parts = array();


    $cnt = $r['total_comments'];
    if( $cnt>0 )
        $parts[] = ($cnt==1) ? "1 comment" : "{$cnt} comments";

    $cnt = $r['total_bloglinks'];
    if( $cnt>0 )
        $parts[] = ($cnt==1) ? "1 blog link" : "{$cnt} blog links";

    if( $parts )
        return implode( ', ', $parts );
    else
        return '';
}



function loginform_emit( $email='', $stash='', $rememberme='', $errs = array())
{
    $h_email = htmlspecialchars($email);
    $h_stash = htmlspecialchars($stash);


    $stashpart = $stash ? "stash={$h_stash}&" : "";
    $register_url = "/login?{$stashpart}action=register";
    $nopass_url = "/login?{$stashpart}action=sendemail";
?>

<form action="/login" name="login" class="login" method="POST" accept-charset="utf-8">


<strong>New users: <a href="<?php echo $register_url; ?>">REGISTER HERE</a></strong><br/>
If you already have a Journa<i>listed</i> account, login below

<input type="hidden" name="stash" value="<?=$h_stash?>" />


<?php if(array_key_exists('badpass',$errs) ) { ?><p class="errhint"><?php echo $errs['badpass'];?></p><?php } ?>

<p>
<?php if(array_key_exists('email',$errs) ) { ?><span class="errhint"><?php echo $errs['email'];?></span><br/><?php } ?>
<label for="email">Email address</label>
<input type="text" size="30" name="email" id="email" value="<?php echo $h_email; ?>" />
</p>

<p>
<label for="password">Password</label>
<input type="password" size="30" name="password" id="password" value="" />
</p>

<p>
<label for="rememberme">Remember me</label>
<input type="checkbox" name="rememberme" id="rememberme" <?php echo $rememberme ? "checked" : ""; ?> />
<small>(don't use this on a public or shared computer)</small>
</p>

<a href="<?php echo $nopass_url; ?>">forgot password?</a>
<p>
<input type="submit" name="loginsubmit" value="Continue" />
</p>


</form>


<?

}


function donatebutton_emit( $txt='Donate', $href='/donate' )
{

?>
<div class="donate-box">
 <div class="donate-box_top"><div></div></div>
  <div class="donate-box_content">
    <a class="donatebutton" href="<?php echo $href; ?>"><?php echo $txt; ?></a>
  </div>
 <div class="donate-box_bottom"><div></div></div>
</div>
<?php

}


// htmlentities()-encode any strings in the array, adding them under
// new values with an "h_"-prefixed key
function h_array( $a )
{
    $out = array();
    foreach( $a as $key=>$value ) {
        if( strpos( $key,'h_')!==0 ) {
            if( is_string($value) )
                $out[ "h_{$key}" ] = htmlentities($value);
            elseif( is_null( $value ) )
                $out[ "h_{$key}" ] = '';
        }
        $out[ $key ] = $value;
    }

    return $out;
}


// differs from built-in parse_url() in these ways:
// - doesn't abort with errors if it gets confused
// - if it looks like a valid url, _all_ fields will be returned (missing ones will be empty)
function crack_url( $url )
{
    $fields = array( 'scheme','user','pass','host','port','path','query','fragment' );
    $m = array();
    if( preg_match( "/^((?P<scheme>\w+):\/\/)?((?P<user>.+?)(:(?P<pass>.+?))?@)?(?P<host>[^\/:?]+)?(:(?P<port>\d+))?(?P<path>.+?)?([?](?P<query>.*?))?([#](?P<fragment>.*?))?$/",$url,&$m ) > 0 ) {
        $ret = array();
        foreach( $fields as $f ) {
            $ret[$f] = arr_get( $f, $m, '' );
        }
        return $ret;
    } else {
        /* actually, I'm not sure the preg_match can ever fail... every part is optional... */
        return FALSE;
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


// modified from glue_url() posted in user comments on the php parse_url() docs page
function glue_url($parsed) {
    assert( is_array($parsed) );

    $uri = arr_get('scheme',$parsed) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '' : '//') : '';
    $uri .= arr_get('user',$parsed) ? $parsed['user'].(arr_get('pass',$parsed) ? ':'.$parsed['pass'] : '').'@' : '';
    $uri .= arr_get('host',$parsed) ? $parsed['host'] : '';
    $uri .= arr_get('port',$parsed) ? ':'.$parsed['port'] : '';

    if (arr_get('path',$parsed)) {
        $uri .= (substr($parsed['path'], 0, 1) == '/') ?
            $parsed['path'] : ((!empty($uri) ? '/' : '' ) . $parsed['path']);
    }

    $uri .= arr_get('query',$parsed) ? '?'.$parsed['query'] : '';
    $uri .= arr_get('fragment',$parsed) ? '#'.$parsed['fragment'] : '';

    return $uri;
}




?>
