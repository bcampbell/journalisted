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


function tags_display_cloud( &$tags )
{
	$minsize = 10;
	$maxsize = 30;

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
		if( $high != $low )
			$size = $minsize + (( $freq * ($maxsize-$minsize) ) / ($high-$low)  );
		else
			$size = $minsize + ($maxsize-$minsize)/4;	// quarter-size seems about right

		printf( "&nbsp;<a href=\"/list?tag=%s\" style=\"font-size: %dpx\">%s</a>&nbsp;\n", urlencode($tag), $size, $tag);
	}

}



function tags_cloud_from_query( &$q )
{
	$tags = array();
	while( ($row = db_fetch_array( $q )) )
	{
		$tag = $row['tag'];
		$freq = $row['freq'];
		$tags[ $tag ] = intval( $freq );
	}
	ksort( $tags );
	tags_display_cloud( $tags );
}

?>
