<?php
/* Common journo-related functions */


require_once '../conf/general';
require_once 'misc.php';
require_once 'image.php';
require_once '../../phplib/db.php';
require_once '../phplib/gatso.php';
require_once '../phplib/eventlog.php';
require_once '../phplib/article.php';

define( 'OPTION_JL_NUM_SITES_COVERED', '14' );


/* returns a link to journos page, with oneliner (if present)...
 * journo must have 'prettyname' field, at very least. All others optional.
 */
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






function journo_emitCaution( $journo )
{
?>
    <div class="caution">
        Caution: this list is not comprehensive but based on articles published in
        <a href="/faq/what-news-outlets-does-journalisted-cover">21 UK news outlets across <?php echo OPTION_JL_NUM_SITES_COVERED; ?> different websites</a>.
        The information is collected automatically so there are bound to be mistakes.
        Please 
        <a href="/forjournos?j=<?=$journo['ref'];?>">let us know</a>
        when you find one so we can correct it.
    </div>
<?php
}



function journo_emitIsThisYouActionBox( &$journo )
{
?>
<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">
<?php
/*
   <h3>Something wrong/missing?</h3>
   <p>Have we got the wrong information about this journalist?
   <a href="/forjournos?j=<?=$journo['ref'];?>">Let us know</a></p>
*/
?>
   <h3>Are you <?=$journo['prettyname'];?>?</h3>
   <p>Then this is <em>your</em> page!</p>
   <p>You can edit it <a href="/profile?ref=<?=$journo['ref'];?>">here</a>.</p>
  </div>
 <div class="action-box_bottom"><div></div></div>
</div>
<?php
}








// not sure if this is cheesy or not... should bio links also be added into the weblinks table?
function journo_getBioLinks( &$journo )
{
    $links = array();

    $rows = db_getAll( "SELECT srcurl,kind FROM journo_bio WHERE approved=true AND journo_id=?", $journo['id'] );

    foreach( $rows as $r )
    {
        $desc = '';
        $kind = 'profile';
        if( $r['kind'] == 'guardian-profile' ) {
            $desc = "Biography (from The Guardian)";
        } elseif( $r['kind'] == 'wikipedia-profile' ) {
            $desc = "Biography (from Wikipedia)";
        } else {
            continue;
        }
        $links[] = array( 'url'=>$r['srcurl'], 'description'=>$desc, 'kind'=>$kind );
    }

    return $links;
}





/* use the journo data to expand a format string describing generic
 * email format of an organisation.
 * Returns an array of guessed email addresses for the journo.
 */
function expandEmailFormat( $fmt, $journo )
{
//    if( $fmt == '' )
//        return '';

    $fmt = preg_replace( '/\{FIRST\}/', $journo['firstname'], $fmt );
    $fmt = preg_replace( '/\{LAST\}/', $journo['lastname'], $fmt );
    $fmt = preg_replace( '/\{INITIAL\}/', $journo['firstname'][0], $fmt );

    $forms = preg_split( '/\s*,\s*/', $fmt );

    return $forms;
//    $forms = preg_replace( '/(.+)/', '<a href="mailto:\1">\1</a>', $forms );

//    return implode( " or ", $forms );
}



function fetchJournoEmail( $journo )
{
    $row = db_getRow("SELECT email, srcurl FROM journo_email WHERE journo_id=? AND approved",
                     $journo['id']);
    if( !$row )
        return null;
    /* we have an email address on file - show it */
    $email = $row['email'];
    /* if we got it from a webpage (or article), say which one */
    $srcurlname = null;
    if( $row['srcurl'] )
    {
        $matches = '';
        preg_match('/(?:[a-zA-Z0-9\-\_\.]+)(?=\/)/', $row['srcurl'], $matches);
        $srcurlname = $matches[0];
    }

    return array(
        'email'=>$row['email'],
        'srcurl'=>$row['srcurl'],
        'srcurlname'=>$srcurlname );
}


/* try guessing contact details for a journo. */
function journo_guessContactDetails( &$journo, $guessed_main_org )
{
    $org = $guessed_main_org;
//    $org = guessMainOrg( $journo['id'] );
    if( $org === null )
        return null;

    $fmt = db_getOne( "SELECT fmt FROM pub_email_format WHERE pub_id=?", $org );
    if( !$fmt ) {
        return null;
    }

    $phone = db_getOne( "SELECT phone FROM pub_phone WHERE pub_id=?", $org );
    $prettyname = db_getRow( "SELECT prettyname FROM organisation WHERE id=?", $org );

    return array(
        'orgname' => $prettyname,
        'orgphone' => $phone,
        'emails' => expandEmailFormat( $fmt, $journo )
    );
}




/* Try and guess which organisation a journo might be employed by.
 * - which org have they written for most in the last 3 months?
 * - have they written at least 5 articles for them during that time?
 * returns srcorg, or null if we can't decide.
 */
function journo_guessMainOrg( $journo_id )
{
    /* cache results for any number of journos, although we'd
     * probably never need more than one... */
	static $cached = array();
	if( array_key_exists( $journo_id, $cached ) )
		return $cached[ $journo_id ];

    gatso_start( "guessMainOrg" );
    $sql = <<<EOT
        SELECT count(*) as artcnt, foo.srcorg
            FROM (
                SELECT a.srcorg
                    FROM article a INNER JOIN journo_attr attr
                        ON (a.status='a' AND a.id=attr.article_id)
                    WHERE attr.journo_id=? AND a.pubdate>NOW()-interval '3 months'
                    ORDER BY a.pubdate DESC
                ) AS foo
                GROUP BY foo.srcorg
                ORDER BY artcnt DESC LIMIT 1;
EOT;

    $row = db_getRow( $sql, $journo_id );

    gatso_stop( "guessMainOrg" );

    if( !$row )
        return null;

    /* require at least 5 articles before we're happy */
    if( $row['artcnt'] < 5 )
        return null;

    return (int)$row['srcorg'];
}





/* return the url of the RSS feed for this journo */
function journoRSS( $journo ) {
    return sprintf( "http://%s/%s/rss", OPTION_WEB_DOMAIN, $journo['ref'] );
}



/* returns a single line of the organisations the journo has written for
 *  eg "The Daily Mail, The Guardian and The Observer"
 * includes any known jobtitles.
 */
function journo_calcWrittenFor( $journo_id )
{

    $orgs = get_org_names();

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

    $writtenfor = pretty_implode( $orglist);

    return $writtenfor;
}

    



/* fetch any bios we've got stored for this journo.
 * returns an array of bio arrays, with these fields:
 *  'bio': the bio text
 *  'srcurl': url is came from, if any
 *  'srcname': name of place the bio came from (eg "Wikipedia")
 */
function journo_fetchBios( $journo_id )
{
    $bios = array();
    $q = db_query("SELECT bio, srcurl, kind FROM journo_bio " .
                     "WHERE journo_id=? AND approved=true",
                     $journo_id);

    while( $row = db_fetch_array($q) )
    {
        switch( $row['kind'] ) {
            case 'wikipedia-profile':
                $srcname='Wikipedia';
                break;
            case 'guardian-profile':
                $srcname='The Guardian';
                break;
            default:
                $srcname=$row['srcurl'];
                break;
        }

        $bios[] = array(
            'bio'=> $row['bio'],
            'srcurl' => $row['srcurl'],
            'srcname' => $srcname
        );
    }

    return $bios;
}


// get various stats for this journo
function journo_calcStats( $journo )
{
    $journo_id = $journo['id'];
    $stats = array();

    // wordcount stats, number of articles...
    $sql = "SELECT SUM(s.wordcount) AS wc_total, ".
            "AVG(s.wordcount) AS wc_avg, ".
            "MIN(s.wordcount) AS wc_min, ".
            "MAX(s.wordcount) AS wc_max, ".
            "to_char( MIN(s.pubdate), 'Month YYYY') AS first_pubdate, ".
            "COUNT(*) AS num_articles ".
        "FROM (journo_attr a INNER JOIN article s ON (s.status='a' AND a.article_id=s.id) ) ".
        "WHERE a.journo_id=?";
    $stats = db_getRow( $sql, $journo_id );

    /* TODO: num_articles doesn't cover the other_articles table */

    // most frequent tag over last month
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' AND a.pubdate>NOW()-interval '1 month' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    $stats['toptag_month'] = $row ? $row['tag'] : null;

    // TODO: remove toptag_alltime and replace with top tag from the last 100 articles
    // (which we get anyway via working out the tag cloud)
    $stats['toptag_alltime'] = null;

    $stats['monthly_stats'] = journo_calcMonthlyStats( $journo );

    return $stats;
}


function journo_calcMonthlyStats( $journo )
{
    $num_months = 12;


    // fetch data in range [$start,$end)
    $start = date_create( "-" . ($num_months-1) . " months" )->format( 'Y-m-01' );
    $end = date_create( "+1 month" )->format( 'Y-m-01' );

    // TODO: include other_articles!
    $sql = <<<EOT
SELECT DATE_TRUNC( 'month', a.pubdate)::date as month,
        COUNT(*) AS num_articles,
        AVG(a.wordcount) AS avg_words
    FROM (journo_attr attr INNER JOIN article a ON a.id=attr.article_id)
    WHERE attr.journo_id=? AND a.pubdate>=?::timestamp AND a.pubdate<?::timestamp
    GROUP BY month ORDER BY month ASC
EOT;

    $rows = db_getAll( $sql,
        $journo['id'],
        $start,
        $end );

    // prefill to handles months missing from query results
    $stats = array();
    for( $i=$num_months-1; $i>=0; --$i )
    {
        $dt = new DateTime( "-$i months" );
        $stats[ $dt->format('Y-m') ] = array( 'num_articles'=>0, 'avg_words'=>0 );
    }

    foreach( $rows as $row ) {
        $year = substr( $row['month'], 0,4 ); // "yyyy-mm-dd" => "yyyy"
        $month = substr( $row['month'], 5,2 ); // "yyyy-mm-dd" => "mm"

        $stats[ "$year-$month" ] = array( 'num_articles'=>$row['num_articles'], 'avg_words'=>(int)$row['avg_words'] );
    }

    return $stats;
}





function journo_calculateSlowData( &$journo ) {

    /* SELECT t.tag, sum(t.freq) as mentions FROM article_tag t WHERE article_id IN (SELECT attr.article_id FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) WHERE attr.journo_id=? ORDER BY a.pubdate DESC LIMIT 50) GROUP BY t.tag ORDER BY mentions DESC LIMIT 10; */


    $slowdata = journo_calcStats( $journo );

    /* TOP TAGS (for the most recent N articles */
    $num_arts = 50;
    $maxtags = 10;
    $sql = <<<EOT
SELECT tag, SUM(freq) as mentions
    FROM article_tag
    WHERE kind<>'c' AND
        article_id IN (
          SELECT attr.article_id
            FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
            WHERE attr.journo_id=? AND a.status='a'
            ORDER BY a.pubdate DESC LIMIT ?
          )
        GROUP BY tag
        ORDER BY mentions DESC
        LIMIT ?
EOT;
    $foo = db_getAll( $sql, $journo['id'], $num_arts, $maxtags );

    if($foo) {
        // most frequent tag (TODO: get rid of misleading "alltime" naming)
        $slowdata['toptag_alltime'] = $foo[0]['tag'];
    }

    $tags = array();
    foreach( $foo as $f ) {
        $tags[$f['tag']] = $f['mentions'];
    }
    ksort( $tags );
    $slowdata['tags'] = $tags;

    $slowdata['guessed_main_org'] = journo_guessMainOrg( $journo['id'] );
    $slowdata['writtenfor'] = journo_calcWrittenFor( $journo['id'] );


    /* find the most commented-upon article in the last 6 months */
    $sql = <<<EOT
SELECT a.id, a.title, a.pubdate, a.srcorg, o.prettyname AS srcorgname, a.permalink, a.total_bloglinks, a.total_comments
    FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE attr.journo_id=?
        AND a.status='a'
        AND a.pubdate > NOW()-interval '6 months'
        AND a.total_comments > 0
    ORDER BY a.total_comments DESC,a.pubdate DESC LIMIT 1;
EOT;

    $a = db_getRow( $sql, $journo['id'] );
    if( $a )
        article_augment( $a );
    $slowdata[ 'most_commented' ] = $a;

    /* find the most blogged article in the last 6 months */
    $sql = <<<EOT
SELECT a.id, a.title, a.pubdate, a.srcorg, o.prettyname AS srcorgname, a.permalink, a.total_bloglinks, a.total_comments
    FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE attr.journo_id=?
        AND a.status='a'
        AND a.pubdate > NOW()-interval '6 months'
        AND a.total_bloglinks > 0
    ORDER BY a.total_bloglinks DESC,a.pubdate DESC LIMIT 1;
EOT;

    $a = db_getRow( $sql, $journo['id'] );
    if( $a )
        article_augment( $a );
    $slowdata[ 'most_blogged' ] = $a;

    return $slowdata;
}



function journo_emitAllArticles( &$journo )
{

    $artificial_limit = 5000;
    // TODO: use paging to remove artificial 5000 limit
    $arts = journo_collectArticles( $journo, $artificial_limit, 0 );

?>
 <h2>Articles by <a href="/<?php echo $journo['ref']; ?>"><?php echo $journo['prettyname']; ?></a></h2>
  <p><?php echo sizeof($arts); ?> articles:</p>
  <ul class="art-list">


<?php unset($a); foreach( $arts as $art ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<?php } ?><br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
    </li>
<?php } ?>

  </ul>

  <p>Article(s) missing? If you notice an article is missing,
  <a href="/missing?j=<?php echo $journo['ref'];?>">click here</a></p>
<?php
}


// return a friendly, standardised description for weblink $l
function journo_describeWeblink( $journo, $l )
{
    $prettyname = $journo['prettyname'];

    $desc = '';
    // 'kind' corresponds to the dropdown on the profile editing page
    switch( $l['kind'] ) {
        case 'blog':
            $desc = "{$prettyname}'s blog";
            break;
        case 'homepage':
            $desc = "{$prettyname}'s website";
            break;
        case 'twitter':
            $desc = "Follow {$prettyname} on Twitter";
            break;
        case 'profile':
            $parts = crack_url( $l['url'] );
            if( $parts ) {
                // get site name without www. prefix
                $sitename = preg_replace( '/^www[.]/', '', $parts['host'] );
                $desc = "Biography/Profile (at {$sitename})";
            }
            else
                $desc = "{$prettyname}'s profile";
            break;
        case 'pingback':
        case '':    // (other)
        default:
            // could be anything - use the free text description the user entered
            $desc = $l['description'];
            break;
    }
    return $desc;
}




/* collect all the info needed for the journo page */
function journo_collectData( $journo, $quick_n_nasty=false )
{
    $data = $journo;

    if( $data['fake'] == 't' ) {
        $data['fake'] = True;
    }

    $data['quick_n_nasty'] = $quick_n_nasty;
    if( !$quick_n_nasty ) {
        $slowdata = journo_calculateSlowData( $journo );
        $data = $data + $slowdata;
    }
    $data['rssurl'] = journoRSS( $journo );

    $data['bios'] = journo_fetchBios( $journo['id'] );

    $data['picture'] = journo_getThumbnail( $journo['id'] );

    /* contact details */
    $guessed = null;
    $known = fetchJournoEmail( $journo );
    if( !is_null($known) ) {
        /* if there is an email address, but it is blank, don't display _anything_ */
        if( $known['email'] == '' )
            $known = null;
    } else {
        if( !$quick_n_nasty ) {
            $guessed = journo_guessContactDetails( $journo, $data['guessed_main_org'] );
        }
    }

    $data['address'] = db_getOne( "SELECT address FROM journo_address WHERE journo_id=?", $journo['id'] );
    $data['phone_number'] = db_getOne( "SELECT phone_number FROM journo_phone WHERE journo_id=?", $journo['id'] );

    $data['known_email'] = $known;
    $data['guessed'] = $guessed;
    $data['twitter_id'] = journo_fetchTwitterID( $journo['id'] );
    if( $data['twitter_id'] ) {
        $data['twitter_url'] = 'http://twitter.com/' . urlencode($data['twitter_id']);
    } else {
        $data['twitter_url'] = NULL;
    }


    /* assorted bio things */
    $data['employers'] = journo_collectEmployment( $journo['id'] );
    $data['education'] = journo_collectEducation( $journo['id'] );
    $data['awards'] = journo_collectAwards( $journo['id'] );

    $data['books'] = db_getAll( "SELECT * FROM journo_books WHERE journo_id=? ORDER BY year_published DESC", $journo['id'] );

    /* admired journos */
    $sql = <<<EOT
SELECT a.admired_name AS prettyname, j.ref, j.oneliner
    FROM (journo_admired a LEFT JOIN journo j ON j.id=a.admired_id)
    WHERE a.journo_id=?
EOT;
    $data['admired'] = db_getAll( $sql, $journo['id'] );

    $data['articles'] = journo_collectArticles( $journo );
    $data['more_articles'] = true;

    /*links*/
    $sql = "SELECT id, url, description, kind " .
        "FROM journo_weblink " .
        "WHERE journo_id=? " .
        "AND approved ORDER BY rank DESC";

    $links = db_getAll( $sql, $journo['id'] );
    foreach( $links as &$l ) {
        $l['description'] = journo_describeWeblink( $journo, $l );
    }
    unset( $l );


    $links = array_merge( $links, journo_getBioLinks( $journo ) );
    $data['links'] = $links;


    /* similar journos */
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_similar s INNER JOIN journo j ON j.id=s.other_id)
    WHERE s.journo_id=?
    ORDER BY s.score DESC
    LIMIT 10
EOT;
    $data['similar_journos'] = db_getAll( $sql, $journo['id'] );


    /* collect journo score data */

    $data['scoring'] = journo_collectScoring($journo);

    return $data;
}



function journo_collectScoring(&$journo) {
    $out = array();

    $out['num_admirers'] = (int)db_getOne("SELECT count(*) FROM journo_admired WHERE admired_id=?", $journo['id']);
    $out['num_alerts'] = (int)db_getOne("SELECT count(*) FROM alert WHERE journo_id=?", $journo['id']);
    return $out;


    // TODO: stash scoring data in separate journo_score table, with daily update

    // TODO: need to maintain max alerts and max admirers for weighting.
    // select max(cnt) from (select admired_id,count(*) as cnt from journo_admired where admired_id is not null group by admired_id order by count(*)) as foo;
    //
    //select max(cnt) from (select count(*) as cnt from alert group by journo_id) as foo;
}



function journo_collectArticles( &$journo, $limit=10, $offset=0 ) {

    // union to merge results from "articles" and "journo_other_articles"
    // into one query... NOTE: union doesn't use column names, so
    // the order is important.
    $sql = <<<EOT
(
    SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
        FROM article a
            INNER JOIN journo_attr attr ON a.id=attr.article_id
            INNER JOIN organisation o ON o.id=a.srcorg
        WHERE a.status='a' AND attr.journo_id=?
UNION ALL
    SELECT NULL as id, title, NULL as description, pubdate, url as permalink, publication as srcorgname, NULL as srcorg, 0 as total_bloglinks, 0 as total_comments
        FROM journo_other_articles
        WHERE status='a' AND journo_id=?
)
ORDER BY pubdate DESC
LIMIT ?
OFFSET ?
EOT;

    $arts = db_getAll( $sql,$journo['id'], $journo['id'], $limit, $offset );

    // now do a pass over to pretty up the results
    foreach( $arts as &$a ) {
        // add pretty pubdate etc...
        article_augment($a);
        if( !is_null( $a['id'] ) )
            $a['buzz'] = BuzzFragment( $a );
        else
            $a['buzz'] = '';

        // no publication given? use the hostname from the url
        if( !$a['srcorgname'] ) {
            $bits = crack_url( $a['permalink'] );
            $a['srcorgname'] = $bits['host'];
        }
    }
    unset($a);

    return $arts;

}

function journo_collectEmployment( $journo_id ) {
    $sql = <<<EOT
SELECT e.*,
        l.id as src__id,
        l.url as src__url,
        l.title as src__title,
        l.pubdate as src__pubdate,
        l.publication as src__publication
    FROM (journo_employment e LEFT JOIN link l ON e.src=l.id )
    WHERE e.journo_id=?
    ORDER BY current DESC, year_to DESC, year_from DESC, rank DESC
EOT;
    $rows = db_getAll( $sql, $journo_id );
    $emps = array();
    foreach( $rows as $row ) {
        $src = null;
        if( $row['src__id'] ) {
            $src = array(
                'id'=>$row['src__id'],
                'url'=>$row['src__url'],
                'title'=>$row['src__title'],
                'pubdate'=>$row['src__pubdate'],
                'publication'=>$row['src__publication'] );
        }
        $emp = array( 'kind'=>$row['kind'],
            'id'=>$row['id'],
            'employer'=>$row['employer'],
            'job_title'=>$row['job_title'],
            'year_from'=>$row['year_from'],
            'year_to'=>$row['year_to'],
            'rank'=>$row['rank'],
            'current'=>$row['current']=='t'?TRUE:FALSE,
            'src'=>$src );
        $emps[] = $emp;
    }

    return $emps;
}


function journo_collectEducation( $journo_id ) {
    $sql = <<<EOT
SELECT e.*,
        l.id as src__id,
        l.url as src__url,
        l.title as src__title,
        l.pubdate as src__pubdate,
        l.publication as src__publication
    FROM (journo_education e LEFT JOIN link l ON e.src=l.id )
    WHERE e.journo_id=?
    ORDER BY e.year_to DESC, (e.kind='u') DESC
EOT;
    $rows = db_getAll( $sql, $journo_id );
    $entries = array();
    foreach( $rows as $row ) {
        $src = null;
        if( $row['src__id'] ) {
            $src = array(
                'id'=>$row['src__id'],
                'url'=>$row['src__url'],
                'title'=>$row['src__title'],
                'pubdate'=>$row['src__pubdate'],
                'publication'=>$row['src__publication'] );
        }
        $entry = array();
        foreach( array('id','school','field','qualification','year_from','year_to','kind' ) as $f ) {
            $entry[$f] = $row[$f];
        }
        $entry['src'] = $src;
        $entries[] = $entry;
    }
    return $entries;
}


function journo_collectAwards( $journo_id ) {
    $sql = <<<EOT
SELECT a.*,
        l.id as src__id,
        l.url as src__url,
        l.title as src__title,
        l.pubdate as src__pubdate,
        l.publication as src__publication
    FROM (journo_awards a LEFT JOIN link l ON a.src=l.id )
    WHERE a.journo_id=?
    ORDER BY a.year DESC
EOT;
    $rows = db_getAll( $sql, $journo_id );
    $entries = array();
    foreach( $rows as $row ) {
        $src = null;
        if( $row['src__id'] ) {
            $src = array(
                'id'=>$row['src__id'],
                'url'=>$row['src__url'],
                'title'=>$row['src__title'],
                'pubdate'=>$row['src__pubdate'],
                'publication'=>$row['src__publication'] );
        }
        $entry = array();
        foreach( array( 'id','award','year' ) as $f ) {
            $entry[$f] = $row[$f];
        }
        $entry['src'] = $src;
        $entries[] = $entry;
    }
    return $entries;
}


// return a list of journos which match the query text using metaphone
// algorithm to do approximate matching. result is returned in order
// of levenstien distance (best match first)
//
// Tries to handle these cases:
// - full firstname and full lastname, eg "Andrew Marr"
// - single name eg "Andrew" (tries as firstname, then lastname)
// - full firstname and partial lastname eg "Andrew M"
//
// TODO: some kind of prefix checking/handling?
//
function journo_FuzzyFind( $query )
{
    $parts = preg_split( '/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY );

    $matches = array();
    if( sizeof( $parts ) == 1 ) {
        /* single name - match as firstname, then lastname */
        $mph = metaphone( $parts[0],4 );
        $matches =  array_merge(
            db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=?", $mph ),
            db_getAll( "SELECT * FROM journo WHERE status='a' AND lastname_metaphone=?", $mph ) );
    } else if( sizeof($parts) >= 2 ) {
        /* try matching both first and last names */
        $firstname_mph = metaphone($parts[0],4);
        $lastname_mph = metaphone(end($parts),4);
        $matches = db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=? AND lastname_metaphone=?",
            $firstname_mph, $lastname_mph );
        if( !$matches ) {
            /* if no matches, treat the last name as partially entered and use it as wildcard */
            $lastname = strtolower( end( $parts ) );
            $matches = db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=? AND lastname LIKE ?",
            $firstname_mph,
            $lastname . '%' );
        }
    }

    /* add levenshtein distance and sort */
    foreach( $matches as &$m ) {
        $m['levenshtein'] = levenshtein( $query, $m['prettyname'] );
    }
    unset( $m );

    usort( $matches, "cmp_levenshtein" );

    return $matches;
}

function cmp_levenshtein( $a, $b ) {
    if( $a['levenshtein'] == $b['levenshtein'] )
        return 0;
    if( $a['levenshtein'] < $b['levenshtein'] )
        return -1;  // want lowest first
    else
        return 1;
}


/* returns the journo's thumbnail photo if set, else null */
function journo_getThumbnail( $journo_id )
{
    $sql = <<<EOT
SELECT i.id,i.filename,i.width,i.height
        FROM ( image i INNER JOIN journo_photo jp ON jp.image_id=i.id AND jp.is_thumbnail=true )
        WHERE jp.journo_id=?
EOT;
    $img = db_getRow( $sql, $journo_id );

    if( $img ) {
        $img['url'] = image_url( $img['filename'] );
    }
    return $img;
}




// fetch a list of recent profile-editing events for the journo
function journo_fetchRecentEvents( $journo_id ) {

    $sql = <<<EOT
SELECT event_time, event_type, context_json FROM event_log
    WHERE journo_id=? AND event_time>NOW()-interval '12 hours'
    ORDER BY event_time DESC;
EOT;
    $events = db_getAll( $sql, $journo_id );
    foreach( $events as &$ev ) {
        $ev['context'] = json_decode( $ev['context_json'], TRUE );
        $ev['description'] = eventlog_Describe( $ev );
    }

    return $events;
}


function journo_fetchTwitterID( $journo_id ) {
    $twitter_id = NULL;
    $l = db_getRow( "SELECT * FROM journo_weblink WHERE journo_id=? AND kind='twitter' LIMIT 1", $journo_id );
    if( !is_null( $l ) )
    {
        $matches = array();
        if( preg_match( '%.*twitter.com/([^/?]+)$%i', $l['url'], $matches ) ) {
            $twitter_id = $matches[1];
        }
    }
    return $twitter_id;
}




function journo_countArticles( $journo_id ) {
    $sql = <<<EOT
SELECT COUNT(*)
    FROM journo_other_articles
    WHERE status='a' AND journo_id=?
EOT;

    $cnt = db_getOne( $sql, $journo_id );

    $sql = <<<EOT
SELECT COUNT(*)
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
    WHERE a.status='a' AND attr.journo_id=?
EOT;
    $cnt += db_getOne( $sql, $journo_id );

    return $cnt;
}


// returns TRUE if journo status was changed to active
function journo_checkActivation( $journo_id )
{
    if( journo_countArticles($journo_id) >= OPTION_JL_JOURNO_ACTIVATION_THRESHOLD ) {
        $n = db_do( "UPDATE journo SET status='a', modified=true WHERE status='i' AND id=?", $journo_id );
        db_commit();
        if( $n > 0 )
            return TRUE;
    }
    return FALSE;
}


function journo_buildOneliner($employers,$articles)
{
    $names = array();

    foreach($employers as $emp) {
        if(!$emp['current'])
            continue;
        $name = $emp['kind']=='f' ? 'Freelance' : $emp['employer'];
        if(array_search(strtolower($name), array_map('strtolower', $names)) === FALSE) {
            $names[] = $name;
        }
    }

    // clip to 3 employers, max
    $names = array_slice($names,0,3);

    if(!$names && sizeof($articles) >= 2) {
        // nothing from employment data - guess using article history instead

        $counts = array();
        foreach($articles as $art) {
            $pubname = $art['srcorgname'];
            if(isset($counts[$pubname])) {
                $counts[$pubname] += 1.0;
            } else {
                $counts[$pubname] = 1.0;
            }
        }
        arsort($counts);

        // give up if too many publications (TODO: only use recent articles)
        if(sizeof($counts)<5) {

            $total = (float)sizeof($articles);

            $names = array();
            foreach($counts as $pubname=>$cnt) {
                if(($cnt/$total) > 0.33) {
                    $names[] = $pubname;
                }
            }

            // clip to two publications, max
            $names = array_slice($names,0,2);
        }
    }

    return implode(', ',$names);
}

