<?php

//phpinfo();

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';




/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );

$journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE status='a' AND ref=?", $ref );
// stats for this journo
$stats = FetchJournoStats( $journo );



// Disclaimers:
$publishLink = "<a href=\"about#whichoutlets\">";
$publishNum  = sprintf("%d", 18);   // hack!
$publishInfo = $publishLink.$publishNum." news websites</a>";

$publishDisclaimer = "<p class=\"disclaimer\">Published in one of ".$publishLink.$publishNum." news websites</a>.</p>";
$basedDisclaimer = sprintf( "<p class=\"disclaimer\">Based on %d article%s published in %s since %s.</p>",
    $stats['num_articles'], 
    $stats['num_articles']==1 ? "" : "s", // plural
    $publishInfo,
    $stats['first_pubdate'] );




if(!$journo)
{
    header("HTTP/1.0 404 Not Found");
    exit(1);
}

$rssurl = sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );

$pageparams = array(
    'rss'=>array( 'Recent Articles'=>$rssurl )
);

$title = $journo['prettyname'];
page_header( $title, $pageparams );


// emit the main part of the page (cached for up to 2 hours)

if( get_http_var( 'allarticles', 'no' ) != 'yes' )
{
    // use caching
    $cacheid = sprintf( "j%d", $journo['id'] );
    cache_emit( $cacheid, "emit_journo", 60*60*2 );
}
else
{
    // if we want to list all articles, don't cache!
    emit_journo();
}

page_footer();



/*
 * HELPERS
 */

// emit the cacheable part of the page
function emit_journo()
{
    global $journo,$publishNum,$publishLink;
//  printf( "<h2>%s</h2>\n", $journo['prettyname'] );

    /* main pane */

    print "<div id=\"maincolumn\">\n";

?>
    <div class="caution">
        Caution: this list is not comprehensive but based on articles published on
        <?=$publishLink?><?=$publishNum?> UK national news websites</a>.
        The information is collected automatically so there are bound to be mistakes.
        Please 
        <a href="/missing?j=<?=$journo['ref'];?>">let us know</a>
        when you find one so we can correct it.
    </div>
<?

    emit_block_overview( $journo );
    emit_blocks_articles( $journo, get_http_var( 'allarticles', 'no' ) );
    emit_block_friendlystats( $journo );
    emit_block_tags( $journo );
    emit_block_bynumbers( $journo );
?>
<p>
This is <b>not a comprehensive list of articles</b> for this journalist.
It is based on articles published for <?=$publishLink?><?=$publishNum?> UK news websites</a>.
Click <a href="about#howcollected">here</a> to see how this information is gathered.
</p>
<?php
    print "</div> <!-- end maincolumn -->\n";



    /* small column */

    print "<div id=\"smallcolumn\">\n\n";

    emit_block_links( $journo );
    $rssurl = sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );
    emit_block_alerts( $journo );
    emit_block_rss( $journo, $rssurl );
    //emit_block_tags( $journo );
    emit_block_searchbox( $journo );

?>

<div class="boxnarrow letusknow">
 <h3>Something wrong/missing?</h3>
 <div class="boxnarrow-content">
  <p>Have we got the wrong information about this journalist?
   <a href="/missing?j=<?=$journo['ref'];?>">Let us know</a></p>
 </div>
</div>

<?php

    print "</div> <!-- end smallcolumn -->\n";
}


/* helper - return a fragment of html to show when/when article was
 * published, including a link to it
 */
function PostedFragment( &$r )
{
    $orgs = get_org_names();
    $org = $orgs[ $r['srcorg'] ];
    $pubdate = pretty_date(strtotime($r['pubdate']));

    return "<cite class=\"posted\"><a href=\"{$r['permalink']}\">{$pubdate}, <em>{$org}</em></a></cite>";
}


/* helper - return a fragment of html to show how many comments and blog links. */
function BuzzFragment( &$r )
{
    $parts = array();

    $cnt = $r['total_bloglinks'];
    if( $cnt>0 )
        $parts[] = ($cnt==1) ? "1 blog post" : "{$cnt} blog posts";

    $cnt = $r['total_comments'];
    if( $cnt>0 )
        $parts[] = ($cnt==1) ? "1 comment" : "{$cnt} comments";

    if( $parts )
        return ' <small>(' . implode( ',', $parts ) . ')</small>';
    else
        return '';
}



// list the articles we've got for this journo
function emit_blocks_articles( $journo, $allarticles )
{
    global $publishDisclaimer, $stats;

?>
<div class="boxwide recent">
<h3>Most recent article</h3>
<div class="boxwide-content">
<?php

    $journo_id = $journo['id'];
    $orgs = get_org_names();

    $sql = "SELECT a.id,a.title,a.description,a.pubdate,a.permalink,a.srcorg,a.total_bloglinks,a.total_comments " .
        "FROM article a,journo_attr j " .
        "WHERE a.status='a' AND a.id=j.article_id AND j.journo_id=? " .
        "ORDER BY a.pubdate DESC";
    $sqlparams = array( $journo_id );

    $maxprev = 10;  /* max number of previous articles to list by default*/
    if( $allarticles != 'yes' )
    {
        $sql .= ' LIMIT ?';
        /* note: we try to fetch 2 extra articles - one for 'most recent', the
        other so we know to display the "show all articles" prompt. */
        $sqlparams[] = 1 + $maxprev + 1;
    }

    $q = db_query( $sql, $sqlparams );

    if( $r=db_fetch_array($q) )
    {
        /* show most recent article with a bit more detail... */
        $title = $r['title'];
        $desc = $r['description'];

        print "<p>\n";
        print "\"<a href=\"/article?id={$r['id']}\">{$r['title']}</a>\"";
        print BuzzFragment($r);
        print "<br />\n";
        print PostedFragment($r);
        print "\n";
        print "<blockquote>{$desc}</blockquote>\n";
        print "</p>\n";

    }

?>
</div>
</div>

<div class="boxwide previous">
<h3>Previous articles</h3>
<div class="boxwide-content">
<ul class="previous-articles">
<?php
    $count=0;
    while( 1 )
    {
        if( $allarticles!='yes' && $count >= $maxprev )
            break;
        $r=db_fetch_array($q);
        if( !$r )
            break;
        ++$count;

        $title = $r['title'];
        $desc = $r['description'];
        print "<li>\n";
        print "<a href=\"/article?id={$r['id']}\">{$r['title']}</a>";
        print BuzzFragment($r);
        print "<br />\n";
        print PostedFragment($r);
        print "\n";
        print "</li>\n";

    }

?>
</ul>
<?php

    echo $publishDisclaimer;

    /* if there are any more we're not showing, say so */
    if( db_fetch_array($q) )
    {
        print "<a href=\"/{$journo['ref']}?allarticles=yes\">[Show all previous articles...]</a>\n";
    }

?>
<p>Article(s) missing? If you notice an article is missing,
<a href="/missing?j=<?=$journo['ref'];?>">click here</a></p>
</div>
</div>

<?php

}


// some friendly (sentence-based) stats
function emit_block_friendlystats( $journo )
{
    global $basedDisclaimer, $stats;

    $journo_id = $journo['id'];

    // get average stats for all journos
    $avg = FetchAverages();

?>
<div class="boxwide friendlystats">
<h3><?php echo $journo['prettyname']; ?> has written...</h3>
<div class="boxwide-content">
<ul>
<?php
    
    // more about <X> than anything else
    if( array_key_exists( 'toptag_alltime', $stats ) )
    {
        $link = tag_gen_link( $stats['toptag_alltime'], $journo['ref'] );
        printf( "<li>More about '<a href =\"%s\">%s</a>' than anything else</li>", $link, $stats['toptag_alltime'] );
    }
    // a lot about <Y> in the last month
    if( array_key_exists( 'toptag_month', $stats ) )
    {
        $link = tag_gen_link( $stats['toptag_month'], $journo['ref'] );
        printf( "<li>A lot about '<a href =\"%s\">%s</a>' in the last month</li>", $link, $stats['toptag_month'] );
    }

    // more or less articles than average journo?
    $diff = (float)$stats['num_articles'] / (float)$avg['num_articles'];
    if( $diff >= 0.8 )
    {
        print( "<li>\n" );
        if( $diff > 1.2 )
            print("More bylined articles than the average journalist");
        else
            print("About the same number of bylined articles as the average journalist");
        print( "</li>\n" );
    }

    print( "</ul>\n" );
    echo $basedDisclaimer;
    
    print( "\n" );

?>
</div>
</div>
<?php

}





function emit_block_bynumbers( $journo )
{
    global $basedDisclaimer;
    $journo_id = $journo['id'];

    // stats for this journo
    $stats = FetchJournoStats( $journo );

    // get average stats for all journos
    $avg = FetchAverages();

?>
<div class="boxwide bynumbers">
<h3>Journalisted by numbers (since <?php echo $stats['first_pubdate'];?>)</h3>
<div class="boxwide-content">
<?php

    print "<table>\n";
    printf( "<tr><th></th><th>%s&nbsp;&nbsp;</th><th>Average</th></tr>",
        $journo['prettyname'] );

    printf( "<tr><th>Articles</th><td>%d</td><td>%.0f</td></tr>\n",
        $stats['num_articles'], $avg['num_articles'] );
    printf( "<tr><th>Total words written</th><td>%d</td><td>%.0f</td></tr>\n",
        $stats['wc_total'], $avg['wc_total'] );
    printf( "<tr><th>Average article length</th><td>%d words</td><td>%.0f words</td></tr>\n",
        $stats['wc_avg'], $avg['wc_avg'] );
    printf( "<tr><th>Shortest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
        $stats['wc_min'], $avg['wc_min'] );
    printf( "<tr><th>Longest article</th><td>%d words</td><td>%.0f words</td></tr>\n",
        $stats['wc_max'], $avg['wc_max'] );
    print "</table>\n";

?>
<? // <p>(no reflection of quality, just stats)</p>
?>
<?
    echo $basedDisclaimer;
?>

</div>
</div>

<?php

}



function emit_block_tags( $journo )
{
    global $basedDisclaimer;
    
    $journo_id = $journo['id'];
    $ref = $journo['ref'];
    $prettyname = $journo['prettyname'];

?>
<div class="boxwide tags">
<h3>The topics <?=$prettyname; ?> mentions most:</h3>
<div class="boxwide-content">
<?php
    
    $stats = FetchJournoStats( $journo );
//  printf( "(based on %d articles)<br />\n", $stats['num_articles'] );

    $maxtags = 20;

    gatso_start('tags');
    # TODO: should only include active articles (ie where article.status='a')
    $sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
        "FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
        "WHERE a.journo_id=? ".
        "GROUP BY t.tag ".
        "ORDER BY freq DESC " .
        "LIMIT ?";
    $q = db_query( $sql, $journo_id, $maxtags );
    gatso_stop('tags');

    tag_cloud_from_query( $q, $ref );

    echo $basedDisclaimer;
?>
</div>
</div>

<?php

}



// list any links to other places on the web for this journo
function emit_block_links( $journo )
{
    $journo_id = $journo['id'];
    $q = db_query( "SELECT url, description " .
        "FROM journo_weblink " .
        "WHERE journo_id=? " .
        "AND journo_weblink.type!='cif:blog:feed' " .
        "AND approved",
        $journo_id );

    $row = db_fetch_array($q);
    if( !$row )
        return;     /* no links to show */

?>
<div class="boxnarrow links">
<h3>On the web</h3>
<div class="boxnarrow-content">
<ul>
<?php

    while( $row )
    {
        printf( "<li><a href=\"%s\">%s</a></li>\n",
            $row['url'],
            $row['description'] );
        $row = db_fetch_array($q);
    }

?>
</ul>
</div>
</div>
<?php

}


// block with rss feed for journo
function emit_block_rss( $journo, $rssurl )
{

?>
<div class="boxnarrow rss">
<h3>Newsfeed</h3>
<div class="boxnarrow-content">
<a href="<?php echo $rssurl; ?>"><img src="/images/rss.gif" /></a><br />
Articles by <?php print $journo['prettyname']; ?>

</div>
</div>

<?php
}

// email alerts
function emit_block_alerts( $journo )
{

?>
<div class="boxnarrow alert">
<h3>My Journalisted</h3>
<div class="boxnarrow-content">
<a href="/alert?Add=1&amp;j=<?=$journo['ref'] ?>">Email me</a> when <?=$journo['prettyname'] ?> writes an article
</div>
</div>

<?php

}


// search box for this journo
// ("find articles by this journo containing ....")
function emit_block_searchbox( $journo )
{

?>
<div class="boxnarrow find">
<h3>Find</h3>
<div class="boxnarrow-content">
<p>
<form action="article" method="get">
<input type="hidden" name="ref" value="<?php echo $journo['ref'];?>"/>
Find articles by <?php echo $journo['prettyname'];?> containing:
<input id="findarticles" type="text" name="find" value=""/>
<input type="submit" value="Find" />
</form>
</p>
</div>
</div>

<?php

}



// show whatever general info we know about this journo
// - biodata from wikipedia/whereever
// - which organisations they've written for (that we know about)
// - their email address (if we have it)
function emit_block_overview( $journo )
{
    $journo_id = $journo['id'];

    print "<div class=\"boxwide overview\">\n";
    printf( "<h2>%s</h2>\n", $journo['prettyname'] );
    print "<div class=\"boxwide-content\">\n";

    emit_journo_bios( $journo );
    emit_writtenfor( $journo );
    emit_journo_mailto( $journo );

    print "</div>\n";
    print "</div>\n\n";
}



function emit_writtenfor( $journo )
{
    global $basedDisclaimer;

    $orgs = get_org_names();
    $journo_id = $journo['id'];

    gatso_start( 'writtenfor' );
    $writtenfor = db_getAll( "SELECT DISTINCT a.srcorg " .
        "FROM article a INNER JOIN journo_attr j ON (a.status='a' AND a.id=j.article_id) ".
        "WHERE j.journo_id=?",
        $journo_id );

    $orglist = array();
    foreach( $writtenfor as $row )
    {
        $srcorg = $row['srcorg'];
        // get jobtitles seen for this org: 
        $titles = db_getAll( "SELECT jobtitle FROM journo_jobtitle WHERE journo_id=? AND org_id=?",
            $journo_id, $srcorg );
        $titlelist = array();
        foreach( $titles as $t )
            $titlelist[] = $t['jobtitle'];

        $s = "<span class=\"publication\">" . $orgs[ $srcorg ] . "</span>";
        if( !empty( $titlelist ) )
            $s .= ' (' . implode( ', ', $titlelist) . ')';
        $orglist[] = $s;
    }

    printf( "<p>" . $journo['prettyname'] . " has written articles published in %s.</p>", PrettyImplode( $orglist) );

    gatso_stop( 'writtenfor' );
    
    echo $basedDisclaimer;
}


function emit_journo_bios( $journo )
{
    $row = db_getRow("SELECT bio, srcurl, type FROM journo_bio " .
                     "WHERE journo_id=? AND approved",
                     $journo['id']);
    if ($row)
    {
        $biotype = $row['type'];
        $srcurl = $row['srcurl'];
        
        if ($biotype=='wikipedia:journo')
            $biourltext = 'Wikipedia';
        else if ($biotype=='cif:contributors-az')
            $biourltext = 'Comment is free';
        else
            $biourltext = $row['srcurl'];
        
        print "<div class=\"bio-para\">\n";
        print $row['bio'];
        print " <div class=\"disclaimer\">(source: <a href=\"$srcurl\">$biourltext</a>)</div></div>\n";
    }
}


function emit_journo_mailto( $journo )
{
    $row = db_getRow("SELECT email, srcurl FROM journo_email WHERE journo_id=? AND approved",
                     $journo['id']);
    if ($row)
    {
        $shorturl = $row['srcurl'];
        $matches = '';
        preg_match('/(?:[a-zA-Z0-9\-\_\.]+)(?=\/)/', $shorturl, $matches);
        $shorturl = $matches[0];
        $email = str_replace('@', '&#x0040;', $row['email']);
        print ("<p><span class=\"journo-email-outer\">Email: <span class=\"journo-email\">" .
               "<a href=\"mailto:$email\">$email</a></span> " .
               "<span class=\"disclaimer\">(from <a href=\"" . $row['srcurl'] .
               "\">" . $shorturl . "</a>)</span></span></p>");
    }
}

// join strings using ", " and " and "
// eg ("foo", "bar", "wibble") => "foo, bar and wibble"
function PrettyImplode( $parts)
{
    if( empty( $parts ) )
        return '';
    $last = array_pop( $parts );
    if( empty( $parts ) )
        return $last;
    else
        return implode( ', ', $parts ) . " and " . $last;
}

function FetchAverages()
{
    static $avgs = NULL;
    if( $avgs !== NULL )
        return $avgs;

    $refreshinterval = 60*60*24*7;      // 7 days

    $avgs = db_getRow( "SELECT date_part('epoch', now()-last_updated) AS elapsed, ".
            "wc_total, wc_avg, wc_min, wc_max, num_articles ".
        "FROM journo_average_cache" );

    if( !$avgs || $avgs['elapsed'] > $refreshinterval )
    {
        $avgs = CalcAverages();
        StoreAverages( $avgs );
    }
    return $avgs;
}


function CalcAverages()
{
    $q = db_query( "SELECT ".
                "SUM(art.wordcount) AS wc_total, ".
                "AVG(art.wordcount) AS wc_avg, ".
                "MIN(art.wordcount) AS wc_min, ".
                "MAX(art.wordcount) AS wc_max, ".
                "COUNT(*) as num_articles ".
            "FROM article art INNER JOIN journo_attr attr ".
                "ON (attr.article_id=art.id) GROUP BY journo_id" );

    $fields = array('wc_total','wc_avg','wc_min','wc_max','num_articles');
    $runningtotal = array();
    foreach( $fields as $f )
        $runningtotal[$f] = 0.0;
    $rowcnt = 0;
    while( $row = db_fetch_array( $q ) )
    {
        ++$rowcnt;
        foreach( $fields as $f )
            $runningtotal[$f] += $row[$f];
    }

    $averages = array();
    foreach( $fields as $f )
        $averages[$f] = $runningtotal[$f] / $rowcnt;

    return $averages;
}

function StoreAverages( $avgs )
{
    db_do( "DELETE FROM journo_average_cache" );
    db_do( "INSERT INTO journo_average_cache ".
            "(last_updated,wc_total,wc_avg,wc_min,wc_max,num_articles) ".
        "VALUES (now(),?,?,?,?,?)",
        $avgs['wc_total'],
        $avgs['wc_avg'],
        $avgs['wc_min'],
        $avgs['wc_max'],
        $avgs['num_articles'] );
    db_commit();
}


// get various stats for this journo
function FetchJournoStats( $journo )
{
    static $ret = NULL;
    if( $ret !== NULL )
        return $ret;

    $journo_id = $journo['id'];
    $ret = array();

    // wordcount stats, number of articles...
    $sql = "SELECT SUM(s.wordcount) AS wc_total, ".
            "AVG(s.wordcount) AS wc_avg, ".
            "MIN(s.wordcount) AS wc_min, ".
            "MAX(s.wordcount) AS wc_max, ".
            "to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
            "COUNT(*) AS num_articles ".
        "FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
        "WHERE a.journo_id=?";
    $row = db_getRow( $sql, $journo_id );
    $ret = $row;

    // most frequent tag over last month
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' AND a.pubdate>NOW()-interval '1 month' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    if( $row )
        $ret['toptag_month'] = $row['tag'];

    // most frequent tag  of all time
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    if( $row )
        $ret['toptag_alltime'] = $row['tag'];

    return $ret;
}


?>
