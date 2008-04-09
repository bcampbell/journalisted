<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

/*
 * Misc helper functions for journa-list
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

/* return a human-readable date given a time stamp */
function pretty_date( $t )
{
	$now = time();
	if( strftime('%Y%m%d',$t) == strftime('%Y%m%d',$now) )
		return 'today';
	elseif( strftime('%U %Y',$t) == strftime('%U %Y',$now) )
		return strftime('%A',$t);
	elseif( strftime('%Y',$t) == strftime('%Y',$now) )
		return strftime('%A %e %B',$t);
	else
		return strftime('%a %e %B %Y',$t);
}

/***************************************************************************
*	function:		SafeMailto
*	description:	return a spam-safe MailTo link using javascript
*					Uses gen_mailto() in scripts/base.js
*					$addr - email address to output
*					NOTE: doesn't work if name part contains a dot (.)
*					eg "fred.smith@megacorp.com"
***************************************************************************/
function SafeMailto( $addr, $text='' )
{
	$text = addslashes( $text );
	$addr = addslashes( $addr );
	$parts = array_reverse( preg_split( '/[.@]/', $addr ) );
	
	$safetext = str_replace('@', '&#x0040;', $text);
	$out = "<script>gen_mailto( '$text', '" . implode( "','", $parts ). "');</script><noscript>$safetext</noscript>";

	return $out;
}



// generate a link for a tag
function tag_gen_link( $tag, $journo_ref=null, $period=null )
{
	$l = '/' . ($journo_ref ? $journo_ref:'tags');
	if( $period )
		$l = $l . '/' . $period;
	$l = $l . '/' . urlencode($tag);

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




// emit a list of links to journos who use this tag
function tag_emit_journo_list( $tag, $excludejourno_id=null, $period=null )
{
	$periodcond = '';
	if( $period == 'today' )
		$periodcond = "  AND a.pubdate > NOW() - INTERVAL '1 day' ";
	else	// HACKY HACK HACK! TODO: FIXME!
		$periodcond = "  AND a.pubdate > NOW() - INTERVAL '7 days' ";

	$sql = "SELECT SUM(freq), j.id, j.ref, j.prettyname ".
		"FROM ((journo j INNER JOIN journo_attr attr ON (j.id=attr.journo_id) ) ".
		"  INNER JOIN article_tag t ON (t.article_id=attr.article_id)) ".
		"    INNER JOIN article a ON (t.article_id=a.id) ".
		"WHERE t.tag=? ".
		"  AND a.status='a' ".
		$periodcond .
		"GROUP BY j.id,j.ref,j.prettyname ".
		"ORDER BY SUM DESC";
	$q = db_query( $sql, $tag );

	$cnt = 0;
	print "<ul>\n";
	while( $j = db_fetch_array($q) )
	{
        if( $excludejourno_id == $j['id'] )
            continue;
		$cnt++;
		$journo_url = '/' . $j['ref'];
		$tagurl = $journo_url . '/' . urlencode( $tag );
		printf( "<li><a href=\"%s\">%s</a> (<a href=\"%s\">%d %s</a>)</li>\n",
           $journo_url,
           $j['prettyname'],
           $tagurl,
           $j['sum'],
           $j['sum']==1 ? 'mention':'mentions' );
	}
	print "</ul>\n";
    printf( "<p>%d %s</p>\n",
        $cnt, $cnt==1 ? 'journalist':'journalists' );
}


// emit list of articles by a particular journo matching a tag.
function tag_emit_matching_articles( $tag, $journo_id )
{
	$sql = "SELECT a.id,a.title,a.description,a.pubdate,a.permalink,a.srcorg " .
		"FROM (article a INNER JOIN journo_attr j ON (a.id=j.article_id)) " .
			"INNER JOIN article_tag t ON (a.id=t.article_id) " .
		"WHERE a.status='a' AND j.journo_id=? AND t.tag=? " .
		"ORDER BY a.pubdate DESC";

	$r = db_query( $sql, $journo_id, $tag );

    $cnt = 0;
	print "<ul>\n";
	while( $row = db_fetch_array( $r ) )
	{
		printf( "<li>%s</li>\n", article_link( $row ) );
        ++$cnt;
	}
	print "</ul>\n";

    printf( "<p>%d %s</p>\n",
        $cnt, $cnt==1 ? 'article':'articles' );
}


function article_link( $art )
{
	$orgs = get_org_names();
	$htmlfrag = sprintf( "<a href=\"/article?id=%s\">%s</a>, %s, <em>%s</em> ".
		"<small>(<a href=\"%s\">original article</a>)</small>",
		$art['id'],
		$art['title'],
		pretty_date( strtotime($art['pubdate']) ),
		$org = $orgs[ $art['srcorg'] ],
		$art['permalink'] );

	return $htmlfrag;
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



/*
 * Function for cached output of data.
 * If the cache contains a valid copy of the data then it'll just
 * be fetched and output.
 * If the cache entry is missing or out of date, genfunc will
 * be called to generate it, it will be output and a copy will
 * be stored in the cache.
 *
 * cacheid  - name of the entry in the cache (<= 10 chars)
 * genfunc - function which generates and outputs the data
 * maxage   - maximum age (in seconds) before regenerating the data
 */
function cache_emit( $cacheid, $genfunc, $maxage )
{
    $cache_disabled = 0;
    if( defined('OPTION_JL_DISABLE_CACHE') && OPTION_JL_DISABLE_CACHE ==1 )
        $cache_disabled = 1;


    if( !$cache_disabled )
    {
        $row = db_getRow( "SELECT EXTRACT(EPOCH FROM NOW()-gentime) as elapsed, content FROM htmlcache WHERE name=?", $cacheid );
        if( $row )
        {
            if( $row['elapsed'] < $maxage )
            {
                printf( "<!-- cache: '%s' fetched from cache -->\n", $cacheid );
                // cache is valid.
                print $row['content'];
                printf( "<!-- cache: end '%s' -->\n", $cacheid );
                return;
            }

        }
    }

	// if we got this far the cache entry is invalid (missing or expired)
	printf( "<!-- cache: '%s' regenerated -->\n", $cacheid );
	ob_start();
	call_user_func( $genfunc );
	$content = ob_get_contents();
	ob_flush();
	printf( "<!-- cache: end '%s' -->\n", $cacheid );

    if( !$cache_disabled )
    {
        if( $row )
        {
            db_do( "UPDATE htmlcache SET content=?, gentime=NOW() WHERE name=?",
                $content, $cacheid );
        }
        else
        {
            db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)",
                $cacheid, $content );
        }
        db_commit();
    }
}


function cache_clear( $cacheid )
{
	db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_commit();
}

?>
