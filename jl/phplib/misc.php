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


function tag_gen_link( $tag, $journo_id=null )
{
    if( $journo_id )
        return sprintf( "/list?tag=%s&journo_id=%d", urlencode($tag), $journo_id );
    else
        return sprintf( "/list?tag=%s", urlencode($tag));
}


function tag_display_cloud( &$tags, $journo_id=null )
{
	$minsize = 10;
	$maxsize = 30;
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
		$size = $minsize + ( (($freq-$low)*($maxsize-$minsize)) / ($high-$low)  );

		printf( "&nbsp;<a href=\"%s\" style=\"font-size: %dpx\">%s</a>&nbsp;\n", tag_gen_link( $tag, $journo_id ), $size, $tag);
	}

}



function tag_cloud_from_query( &$q, $journo_id=null )
{
	$tags = array();
	while( ($row = db_fetch_array( $q )) )
	{
		$tag = $row['tag'];
		$freq = $row['freq'];
		$tags[ $tag ] = intval( $freq );
	}
	ksort( $tags );
	tag_display_cloud( $tags, $journo_id );
}

?>
