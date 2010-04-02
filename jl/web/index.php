<?php

/*
 * index.php - the front page for journalisted.com
 *
 * Most of this page is generated offline and cached.
 * see ../bin/offline-page-build tool for details
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

require_once '../phplib/offline_frontpage.php';


NEW_version();


function extra_head()
{
?>
<script language="javascript" type="text/javascript">
    $(document).ready( function () {
        // equalHeight() by Rob Glazebrook
        function equalHeight(group) {
            var tallest = 0;
            group.each(function() {
                var thisHeight = $(this).height();
                if(thisHeight > tallest) {
                    tallest = thisHeight;
                }
            });
            group.height(tallest);
        }
        equalHeight( $(".recently-viewed .body,.recently-updated .body,.most-blogged .body") );
    });
</script>
<?php
}



function NEW_version()
{


    // setup for placeholder text in input forms
    $head_extra = <<<EOT
      <script type="text/javascript" language="JavaScript">
        window.onload=function() {
          activatePlaceholders();
        }
      </script>
EOT;


    page_header( "", array( 'menupage'=>'cover', 'head_extra_fn'=>"extra_head" ) );

/*
    $sql = <<<EOT
SELECT j.ref AS journo_ref, j.prettyname as journo_prettyname, e.event_time, e.event_type, e.context_json
    FROM event_log e LEFT JOIN journo j ON j.id=e.journo_id
    WHERE event_time>NOW()-interval '12 hours'
    ORDER BY event_time DESC
    LIMIT 10;
EOT;
    $events = db_getAll( $sql );
    foreach( $events as &$ev ) {
//        $ev['context'] = json_decode( $ev['context_json'], TRUE );
        $ev['description'] = eventlog_Describe( $ev );
    }
*/

    $sql = <<<EOT
SELECT j.ref AS journo_ref, j.prettyname as journo_prettyname, j.oneliner as journo_oneliner, min(now()-e.event_time) as when
    FROM event_log e LEFT JOIN journo j ON j.id=e.journo_id
    WHERE event_time>NOW()-interval '7 days'
    GROUP BY journo_ref, journo_prettyname, journo_oneliner
    ORDER BY min( now()-e.event_time) ASC
    LIMIT 10;
EOT;
    $events = db_getAll( $sql );
    foreach( $events as &$ev ) {
//        $ev['context'] = json_decode( $ev['context_json'], TRUE );
        $ev['description'] = $ev['when'];    //eventlog_Describe( $ev );
        $ev['journo'] = array( 'ref'=> $ev['journo_ref'], 'prettyname'=>$ev['journo_prettyname'], 'oneliner'=>$ev['journo_oneliner'] );
        unset( $ev['journo_ref'] );
        unset( $ev['journo_prettyname'] );
        unset( $ev['journo_oneliner'] );
    }

    $news = db_getAll( "SELECT id,slug,title,posted FROM news WHERE status='a' ORDER BY posted DESC LIMIT 5" );

    foreach( $news as &$n ) {
        $n['prettydate'] = pretty_date( strtotime($n['posted']) );
    }
    unset( $n );

    $orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );


    // most blogged-about articles
    $sql = <<<EOT
SELECT a.id,a.srcorg,a.title,a.permalink,a.total_bloglinks,o.prettyname as srcorgname
    FROM article a INNER JOIN organisation o ON a.srcorg=o.id
    WHERE a.pubdate <= NOW() AND a.pubdate > NOW()-interval '48 hours'
        AND a.total_bloglinks > 0
    ORDER BY a.total_bloglinks DESC
    LIMIT 5
EOT;

    $most_blogged_about = db_getAll( $sql );
    foreach( $most_blogged_about as &$a ) {
        article_addJournos( $a );
    }
    unset( $a );


    // recently-viewed journos
    // TODO: really not so happy about this... (see web/journo.php too)
    $sql = <<<EOT
SELECT j.ref, j.prettyname, j.oneliner
    FROM recently_viewed v INNER JOIN journo j ON j.id=v.journo_id
    ORDER BY v.view_time DESC
    LIMIT 10
EOT;
    $recently_viewed = db_getAll( $sql );

    {
        include "../templates/frontpage.tpl.php";
    }

    page_footer();
}




function OLD_version()
{

$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );

// setup for placeholder text in input forms
$head_extra = <<<EOT
  <script type="text/javascript" language="JavaScript">
    window.onload=function() {
      activatePlaceholders();
    }
  </script>
EOT;

page_header( "", array( 'menupage'=>'cover', 'head_extra'=>$head_extra ) );

?>

<div class="greenbox">
Journa<i>listed</i> is an independent, non-profit web service to help the public navigate the news
</div>

<div id="maincolumn">





<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">

   <form action="/list" method="get" class="frontform">
    <label for="name">Find out more about a journalist</label>
    <input type="text" value="" title="type journalist name here" id="name" name="name" placeholder="type journalist name here" class="text" />
<!--    <input type="submit" value="Find" /><br /> -->
    <input type="image" src="images/white_arrow.png" alt="find" />
   </form>

   <form action="/list" method="get" class="frontform">
    <label for="outlet">Track down a journalist by news outlet</label>

     <select id="outlet" name="outlet" class="select" >
<?php
	foreach( $orgs as $o )
		print "   <option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
?>
     </select>
    <input type="image" src="images/white_arrow.png" alt="find" />
<!--    <input type="submit" value="Find" /><br /> -->
   </form>


   <form action="/search" method="get" class="frontform">
    <label for="q">Search articles</label>
    <input type="text" value="" title="type subject here" id="q" name="q" class="text" placeholder="type subject here"/>
<!--    <input type="submit" value="Find" /><br /> -->
    <input type="image" src="images/white_arrow.png" alt="find" />
   </form>

  </div>
 <div class="action-box_bottom"><div></div></div>
</div>




<?php
#$featured = db_getRow( "SELECT * FROM journo WHERE ref='jeff-prestridge'" );
#$featured = db_getRow( "SELECT * FROM journo WHERE ref='ben-goldacre'" );
$featured = db_getRow( "SELECT * FROM journo WHERE ref='jim-alkhalili'" );
frontpage_emitFeaturedJourno( $featured );
cache_emit( "fp_tags","emit_whoswritingaboutwhat",60*60*24 );
?>

</div>  <!-- end maincolumn -->


<div id="smallcolumn">
<div class="greenbox">Build your own newsroom</div>
<div class="greenbox">Donate</div>
<div class="greenbox">journa<i>listed</i> blog</div>
<?php emit_mostbloggedarticles(); ?>
<?php emit_mostcommentedarticles(); ?>
<div class="greenbox">What should Journa<i>listed</i> do next?</div>
</div>

<?php

page_footer();
}



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
