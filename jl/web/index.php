<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


// setup for placeholder text in input forms
$head_extra = <<<EOT
  <script type="text/javascript" language="JavaScript">
    window.onload=function() {
      activatePlaceholders();
    }
  </script>
EOT;

page_header( "", array( 'menupage'=>'cover', 'head_extra'=>$head_extra ) );

$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );
$contactemail = OPTION_TEAM_EMAIL;

?>
<div id="contenthead">
<img src="/images/paper.png" alt="" />

<p>Journalisted is an independent, non-commercial website built to help the public navigate the news</p>

<form action="/list" method="get" class="frontform">
 <label for="name">Find out more about a journalist</label>
<input type="text" value="" title="type journalist name here" id="name" name="name" placeholder="type journalist name here" class="text" />
<input type="submit" value="Find" />
</form>

<form action="/list" method="get" class="frontform">
 <label for="outlet">Track down a journalist by news outlet</label>

  <select name="outlet" class="select" >
<?php
	foreach( $orgs as $o )
		print "   <option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
?>
  </select>
 <input type="submit" value="Find" />
</form>


<form action="/list" method="get" class="frontform">
 <label for="find">Find articles about different subjects</label>

 <input type="text" value="" title="type subject here" id="search" name="search" class="text" placeholder="type subject here"/>
 <input type="submit" value="Find" />
</form>


<p>This website is in beta - all information is generated automatically so there are bound to be mistakes. Please
<?=SafeMailto( $contactemail, 'let us know' );?>
 when you find one so we can correct it</p>

</div>


<div id="maincolumn">
<?php

// the stats and tags boxes are generated offline and never
// updated in response to web access because they are so slow.
// (The generation code is in frontpage_emit() in "../phplib/frontpage.php")
cache_emit( 'frontpage', null, null );

?>
</div>
<div id="smallcolumn">
<?php

$P = person_if_signed_on(true);
if( $P )
{
	emit_my_journos_box($P);
}
else
{
	/* recent journos only refreshed once every 10 mins */
	cache_emit( 'f_recent', 'emit_recent_journos_box', 10*60 );
}
?>
</div>
<?php

page_footer();





function emit_my_journos_box( &$P )
{
?>
 <div class="boxnarrow myjournos">
  <h2>My Journalisted</h2>
  <div class="boxnarrow-content">
<?php

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */
	if( $P )	
	$q = db_query( "SELECT a.id,a.journo_id, j.prettyname, j.ref " .
		"FROM (alert a INNER JOIN journo j ON j.id=a.journo_id) " .
		"WHERE a.person_id=? ORDER BY j.lastname", $P->id );
	print "  <ul>\n";
	while( $row=db_fetch_array($q) )
	{
		$journourl = "/{$row['ref']}";
		printf( "   <li><a href=\"%s\">%s</a></li>\n", $journourl, $row['prettyname'] );
	}
	print( "  </ul>\n" );

?>
  </div>
 </div>
<?php


}

// returns a human-readable string representing an interval
// (very coarse grained - rounds to appropriate whole units
// eg "1 day")
function prettyinterval( $seconds )
{
	$inmins = (int)($seconds/60);
	$inhours = (int)($seconds/(60*60));
	$indays = (int)($seconds/(60*60*24));

	// no less than 1min.
	if( $inmins < 60 )
		return $inmins<=1 ? '1 minute' : "$inmins minutes";
	else if( $inhours < 24 )
		return $inhours==1 ? '1 hour' : "$inhours hours";
	else
		return $indays==1 ? '1 day' : "$indays days";
}



// a list of the journos who were most recently published.
// Well, OK, most recently _scraped_ :-)
// It'd be nicer to use time of publication (pubdate field),
// but a lot of outlets only provide a date, not a time.
// ("firstseen" datetime would be more appropriate, but it's usually
// the same for all articles in each scraper run, which makes for a
// boring list).
function emit_recent_journos_box()
{
?>
 <div class="boxnarrow recentjournos">
 <h2>Recently Published</h2>
 <div class="boxnarrow-content">

<?php

	// want 10, but get 20 of them, just in case some have written multiple articles...
	$sql = <<<EOT
SELECT j.prettyname,j.ref
	FROM ( (article a INNER JOIN journo_attr attr ON attr.article_id=a.id)
		INNER JOIN journo j ON attr.journo_id=j.id )
	WHERE a.status='a'
		AND j.status='a'
--		AND a.pubdate > NOW()-interval '1 day'
		AND a.pubdate < NOW()
	ORDER BY a.pubdate desc
	LIMIT 20;
EOT;

	$q = db_query( $sql );
	/* don't want dupes */
	$uniq = array();
	$maxage = 0;
	while( $row=db_fetch_array($q) )
	{
		$journourl = "/{$row['ref']}";
		$uniq[ $row['prettyname'] ] = array( 'url'=>$journourl );
	}

?>
  <p>The most recently published Journalists are:</p>
  <ul>
<?php

	/* output the list */
	$cnt = 0;
	foreach( $uniq as $name=>$info )
	{
		++$cnt;
		if( $cnt >= 10 )
			break;
/*		printf( "   <li><a href=\"%s\">%s</a> <small>(%s ago)</small></li>\n", $info['url'], $name, $info['age'] ); */
		printf( "   <li><a href=\"%s\">%s</a></li>\n", $info['url'], $name );
	}

?>
  </ul>
 </div>
 </div>

<?php
}

?>
