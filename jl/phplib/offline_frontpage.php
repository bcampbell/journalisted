<?php
/* functions for building up the front page (/web/index.php)
 *
 * Because there are so many slow queries on the front page
 * we will build the bulk of the it offline (via a cronjob)
 * and store the resulting HTML snippet in the htmlcache
 * table, where index.php can easily pick it up.
 *
 * see also "../bin/build-front-page"
 */

require_once '../conf/general';
require_once 'misc.php';
require_once '../phplib/journo.php';
require_once '../../phplib/db.php';



/*
 * - has had a story published within last 24 hours
 * - has some bio information
 * - most recent story has a picture
 */

function frontpage_findInterestingJournos()
{
}



function offline_frontpage_emit()
{

	$contactemail = OPTION_TEAM_EMAIL;
	emit_stats();
	emit_whoswritingaboutwhat();
}


// helper fn
// return number of journos who've had articles published
// in the past $interval (eg '1 day', '5 hours' etc...)
// NOTE: inactive/hidden journos are included in the count
function count_published_journos( $interval )
{
	$sql = <<<EOT
SELECT count( DISTINCT journo_id )
	FROM journo_attr
	WHERE article_id IN	(
		SELECT id
			FROM article
			WHERE status='a'
				AND pubdate > NOW()-interval '$interval'
				AND pubdate <= NOW()
		);
EOT;
	return db_getOne( $sql );
}

/* find the most proflic journo over the last $interval
 * only returns active journos and only counts active articles.
 *
 * returns an array with journo details and a 'num_articles' field.
 */
function most_prolific_journo( $interval )
{
    /* NOTE: evil special case exclusion of Debbie Frank. Seems unfair,
       but she writes daily horoscopes and would otherwise be constantly top
       of the lists.
    */
    $exclude_list = "'debbie-frank'";

	$sql = <<<EOT
SELECT j.prettyname, j.ref, count(*) as num_articles
	FROM ((article a INNER JOIN journo_attr attr ON attr.article_id=a.id)
		INNER JOIN journo j ON attr.journo_id=j.id)
	WHERE a.status='a'
		AND j.status='a'
		AND a.pubdate > NOW()-interval '$interval'
		AND a.pubdate <= NOW()
        AND j.ref not in ( {$exclude_list} )
	GROUP BY j.ref,j.prettyname
	ORDER BY num_articles DESC
	LIMIT 1;
EOT;

	$r = db_getRow( $sql );
	if( !$r )
		return null;

	return array(
		'prettyname' => $r['prettyname'],
		'ref' => $r['ref'],
		'num_articles' => $r['num_articles']
	);
}


function emit_stats()
{

?>
<div class="boxwide latest">
<h2>Latest</h2>
<div class="boxwide-content">
<ul>
<?php

	// how many journos have had articles published today?
	printf( "<li>%d journalists have had articles published today</li>\n", count_published_journos( '1 day' ) );



	// which journo has written the most articles In the last day? week? month?
	$intervals = array(
		'1 day' => '%s has written the most articles today',
		'7 days' => '%s has written the most articles in the last seven days',
		'30 days' => '%s has written the most articles in the last month (30 days)'
	);

	foreach( $intervals as $interval=>$fmt )
	{
		$j = most_prolific_journo( $interval );
		if( $j )
		{
			$journo_link = sprintf( "<a href=\"/%s\">%s</a>", $j['ref'], $j['prettyname'] );
			printf( "<li>$fmt</li>\n", $journo_link );
		}
	}


	// "<N> journos have written about <X> today"
	// how many journos have mentioned each topic over the last day?
	// Cheesy hack - discard the top entries as they're likely to be
	// not so interesting (brown, london, government,labour etc...)
	// so we'll just arbitarily pick the 10th.



	$sql = <<<EOT
SELECT t.tag, count(DISTINCT attr.journo_id)
	FROM ((article_tag t INNER JOIN journo_attr attr ON t.article_id=attr.article_id) INNER JOIN article a ON a.id=t.article_id )
	WHERE t.kind<>'c' AND a.status='a' AND a.pubdate>=NOW() - interval '1 day' AND a.pubdate<NOW()
	GROUP BY t.tag
	ORDER BY count DESC
	LIMIT 3
	OFFSET 10
EOT;
	$q = db_query( $sql );

    if( db_num_rows( $q ) > 0 )
    {
    	printf( "<li>Some of today's most popular subjects:\n" );
        print( "  <ul>\n" );
    	while( $r = db_fetch_array( $q ) )
    	{
    		$taglink = tag_gen_link( $r['tag'], null, 'today' );
    		printf( "    <li>%d journalists have written about '<a href=\"%s\">%s</a>'</li>",
    			$r['count'], $taglink, $r['tag'] );
    	}
	    print( "  </ul>\n</li>\n" );
    }


?>
</ul>
</div>
</div>
<?php
}


function emit_whoswritingaboutwhat()
{
?>
<div class="box tags">
<h2>The most written about subjects today are:</h2>
<div class="box-content">

<?php

	$sql = <<<EOT
SELECT t.tag AS tag, SUM(t.freq) AS freq
	FROM ( article a INNER JOIN article_tag t ON ( a.id=t.article_id AND t.kind <> 'c') )
	WHERE a.status='a'
		AND a.pubdate > NOW() - interval '24 hours'
		AND a.pubdate < NOW()
	GROUP BY t.tag
	ORDER BY freq DESC
	LIMIT 32;
EOT;
	$q = db_query( $sql );
	tag_cloud_from_query( $q, null, 'today' );

?>
<p>...click one to see which journalists are writing about it</p>
</div>
</div>
<?php

}


function article_addJournos( &$a )
{
    $j = db_getAll( "SELECT prettyname,ref FROM journo j INNER JOIN journo_attr attr ON attr.journo_id=j.id WHERE attr.article_id=? LIMIT 1", ($a['id']) );
    if( $j )
        $a['journos'] = $j;
    else
        $a['journos'] = array();
}

/* fn to generate the "most commented on stories today" box */
function emit_mostcommentedarticles()
{
    $sql = <<<EOT
SELECT a.id,a.srcorg,a.title,a.permalink,a.total_comments,o.prettyname as srcorgname
    FROM article a INNER JOIN organisation o ON a.srcorg=o.id
    WHERE a.pubdate <= NOW() AND a.pubdate > NOW()-interval '48 hours'
    ORDER BY a.total_comments DESC
    LIMIT 5
EOT;

    $arts = db_getAll( $sql );
    foreach( $arts as &$a ) {
        article_addJournos( $a );
    }


?>
<div class="box">
  <h3>Most commented on stories today</h3>
  <div class="box-content">
    <ul>
<?php foreach( $arts as $art ) { ?>
      <li><a href="<?php echo article_url( $art['id'] ); ?>"><?php echo $art['title']; ?></a><br/>
        &nbsp;&nbsp;&nbsp;
<?php if($art['journos']) { $j=$art['journos'][0]; ?>
        <a href="/<?php echo $j['ref']; ?>"><?php echo $j['prettyname']; ?></a>,
<?php } ?>
        <span class="publication"><?php echo $art['srcorgname']; ?></span><br/>
        &nbsp;&nbsp;&nbsp; (<?php echo $art['total_comments'];?> comments)
      </li>
<?php } ?>
  </ul>
 </div>
</div>
<?php

}

/* fn to generate the "most blogged about stories today" box */
function emit_mostbloggedarticles()
{
    $sql = <<<EOT
SELECT a.id,a.srcorg,a.title,a.permalink,a.total_bloglinks,o.prettyname as srcorgname
    FROM article a INNER JOIN organisation o ON a.srcorg=o.id
    WHERE a.pubdate <= NOW() AND a.pubdate > NOW()-interval '48 hours'
    ORDER BY a.total_bloglinks DESC
    LIMIT 5
EOT;

    $arts = db_getAll( $sql );
    foreach( $arts as &$a ) {
        article_addJournos( $a );
    }

?>
<div class="box">
 <h3>Most blogged about stories today</h3>
 <div class="box-content">
  <ul>
<?php foreach( $arts as $art ) { ?>
      <li><a href="<?php echo article_url( $art['id'] ); ?>"><?php echo $art['title']; ?></a><br/>
        &nbsp;&nbsp;&nbsp;
<?php if($art['journos']) { $j=$art['journos'][0]; ?>
        <a href="/<?php echo $j['ref']; ?>"><?php echo $j['prettyname']; ?></a>,
<?php } ?>
        <span class="publication"><?php echo $art['srcorgname']; ?></span><br/>
        &nbsp;&nbsp;&nbsp; (<?php echo $art['total_bloglinks'];?> blogs)
      </li>
<?php } ?>
  </ul>
 </div>
</div>
<?php

}





function frontpage_emitFeaturedJourno( &$journo ) {

    $journo_id = $journo['id'];

    $bios = journo_fetchBios( $journo_id );
    $writtenfor = journo_fetchWrittenFor( $journo_id );

    $sql = <<<EOT
SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE a.status='a' AND attr.journo_id=?
    ORDER BY a.pubdate DESC
    LIMIT 5
EOT;

    $articles = db_getAll( $sql , $journo['id'] );

    $newest_art = array_shift( $articles );
    if( $newest_art ) {
        $img = db_getRow( "SELECT * FROM article_image WHERE article_id=? LIMIT 1", $newest_art['id'] );
        if( $img )
        {
            $img['thumb_w'] = 128;
            $img['thumb_url'] = sprintf( '/imgsize?img=%s&w=%d&unsharp=1',
                urlencode( $img['url'] ),
                $img['thumb_w'] );
        }
        $newest_art['image'] = $img;
    }

?>
<div class="box featured-journo">

  <h2>Featured Journalist: <a href="/<?php echo $journo['ref']; ?>"><?php echo $journo['prettyname']; ?></a></h2>
  <div class="box-content">
    <ul>
<?php
    foreach($bios as $bio ) {
?>
    <li class="bio-para"><?php echo $bio['bio']; ?>
<!--      <div style="clear:both;"></div> -->
      <div class="disclaimer">
        (source: <a class="extlink" href="<?php echo $bio['srcurl'];?>"><?php echo $bio['srcname'];?></a>)
      </div>
    </li>
<?php
    }
?>

    <li>
      <?php echo $journo['prettyname'];?> has written articles published in <?php echo $writtenfor; ?>.
    </li>
    <div style="clear:both;"></div>

    <li>Most Recent Article:<br/>
    <div class="art art-summary">
<!--      <?php $img=$newest_art['image']; if( $img ) { ?>
      <img class="thumb" src="<?php echo $img['thumb_url']; ?>" />
      <?php } ?> -->
      <h3><a href="<?php echo article_url($newest_art['id']);?>" ><?php echo $newest_art['title']; ?></a></h3>
      <span class="publication"><?php echo $newest_art['srcorgname']; ?></span>
      <blockquote>
        <?php echo $newest_art['description']; ?>
      </blockquote>
      <div style="clear:both;"></div>
    </div>
    </li>

    <li>Previous Articles
      <ul class="art-list-brief">


<?php foreach( $articles as $art ) { ?>
        <li class="art">'<a href="<?php echo article_url($art['id']);?>" ><?php echo $art['title']; ?></a>', <span class="publication"><?php echo $art['srcorgname']; ?></span></li>
<?php } ?>
      </ul>
    </li>

    </ul>

  </div>
</div>
<?php
}

?>
