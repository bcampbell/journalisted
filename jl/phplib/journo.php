<?php
/* Common journo-related functions */


require_once '../conf/general';
require_once 'misc.php';
require_once 'image.php';
require_once '../../phplib/db.php';
require_once '../phplib/gatso.php';
require_once '../phplib/eventlog.php';

define( 'OPTION_JL_NUM_SITES_COVERED', '14' );


/* returns a link to journos page, with oneliner (if present)... */
function journo_link( $j )
{
    $a = "<a href=\"/{$j['ref']}\" >{$j['prettyname']}</a>";
    if( array_key_exists( 'oneliner', $j ) ) {
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
        if( $r['kind'] == 'guardian-profile' ) {
            $desc = "Biography (from The Guardian)";
        } elseif( $r['kind'] == 'wikipedia-profile' ) {
            $desc = "Biography (from Wikipedia)";
        } else {
            continue;
        }
        $links[] = array( 'url'=>$r['srcurl'], 'description'=>$desc );
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

    $row = db_getRow( "SELECT prettyname,phone,email_format FROM organisation WHERE id=?", $org );

    return array(
        'orgname' => $row['prettyname'],
        'orgphone' => $row['phone'],
        'emails' => expandEmailFormat( $row['email_format'], $journo )
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

    // most frequent tag  of all time
    $sql = "SELECT t.tag, sum(t.freq) as mentions ".
        "FROM ((article_tag t INNER JOIN journo_attr attr ON attr.article_id=t.article_id) ".
            "INNER JOIN article a ON a.id=t.article_id) ".
        "WHERE t.kind<>'c' AND attr.journo_id = ? AND a.status='a' ".
        "GROUP BY t.tag ".
        "ORDER BY mentions DESC ".
        "LIMIT 1";
    $row = db_getRow( $sql, $journo_id );
    $stats['toptag_alltime'] = $row ? $row['tag'] : null;

    return $stats;
}



function journo_calculateSlowData( &$journo ) {

    $slowdata = journo_calcStats( $journo );

    /* TAGS */
    $maxtags = 20;
    # TODO: should only include active articles (ie where article.status='a')
    $sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
        "FROM ( journo_attr a INNER JOIN article_tag t ON a.article_id=t.article_id ) ".
        "WHERE a.journo_id=? AND t.kind<>'c' ".
        "GROUP BY t.tag ".
        "ORDER BY freq DESC " .
        "LIMIT ?";
    $slowdata['tags'] = db_getAll( $sql, $journo['id'], $maxtags );
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
        article_Augment( $a );
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
        article_Augment( $a );
    $slowdata[ 'most_blogged' ] = $a;

    return $slowdata;
}



function journo_emitAllArticles( &$journo )
{

    $sql = <<<EOT
SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE a.status='a' AND attr.journo_id=?
    ORDER BY a.pubdate DESC
EOT;

    $arts = db_getAll( $sql, $journo['id'] );

    /* augment results with pretty formatted date and buzz info */
    foreach( $arts as &$a ) {
        article_Augment( $a );
        $a['buzz'] = BuzzFragment( $a );
    }
    /* sigh... php trainwreck. without this unset, the last element in array gets blatted-over
      if we again use $a in a foreach loop. Which we do.
      Because $a is still referencing last element. Gah.
      see  http://bugs.php.net/bug.php?id=29992 for gory details. */
    unset($a);

?>
<div class="box">
 <h2>Articles by <a href="/<?php echo $journo['ref']; ?>"><?php echo $journo['prettyname']; ?></a></h2>
 <div class="box-content">
  <p><?php echo sizeof($arts); ?> articles:</p>
  <ul class="art-list">


<?php unset($a); foreach( $arts as $a ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a href="<?php echo article_url($a['id']);?>"><?php echo $a['title']; ?></a></h4>
        <span class="publication"><?php echo $a['srcorgname']; ?>,</span>
        <abbr class="published" title="<?php echo $a['iso_pubdate']; ?>"><?php echo $a['pretty_pubdate']; ?></abbr>
        <?php if( $a['buzz'] ) { ?> (<?php echo $a['buzz']; ?>)<?php } ?><br/>
        <div class="art-info">
          <a class="extlink" href="<?php echo $a['permalink'];?>" >Original article at <?php echo $a['srcorgname']?></a><br/>
        </div>
    </li>
<?php } ?>

  </ul>

  <p>Article(s) missing? If you notice an article is missing,
  <a href="/missing?j=<?php echo $journo['ref'];?>">click here</a></p>
 </div>
</div>
<?php
}




/* collect all the info needed for the journo page */
function journo_collectData( $journo, $quick_n_nasty=false )
{
    $data = $journo;

    $data['quick_n_nasty'] = $quick_n_nasty;
    if( !$quick_n_nasty ) {
        $slowdata = journo_calculateSlowData( $journo );
        $data = $data + $slowdata;
    }
    $data['rssurl'] = journoRSS( $journo );

    $data['bios'] = journo_fetchBios( $journo['id'] );

    $data['picture'] = journo_getPicture( $journo['id'] );

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

    $data['known_email'] = $known;
    $data['guessed'] = $guessed;

    $data['employers'] = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=? ORDER BY year_to DESC", $journo['id'] );
    $data['education'] = db_getAll( "SELECT * FROM journo_education WHERE journo_id=? ORDER BY year_to DESC", $journo['id'] );
    $data['awards'] = db_getAll( "SELECT * FROM journo_awards WHERE journo_id=? ORDER BY year DESC", $journo['id'] );
    $data['books'] = db_getAll( "SELECT * FROM journo_books WHERE journo_id=? ORDER BY year_published DESC", $journo['id'] );

    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_admired a INNER JOIN journo j ON j.id=a.admired_id)
    WHERE a.journo_id=?
EOT;
    $data['admired'] = db_getAll( $sql, $journo['id'] );

    $data['articles'] = journo_collectArticles( $journo );
    $data['more_articles'] = true;

    /*links*/
    $sql = "SELECT url, description " .
        "FROM journo_weblink " .
        "WHERE journo_id=? " .
        "AND approved";

    $links = db_getAll( $sql, $journo['id'] );
    $links = array_merge( $links, journo_getBioLinks( $journo ) );
    $data['links'] = $links;

    /* admired journos */
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_admired a INNER JOIN journo j ON j.id=a.admired_id)
    WHERE a.journo_id=?
EOT;
    $data['admired'] = db_getAll( $sql, $journo['id'] );

    /* similar journos */
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner
    FROM (journo_similar s INNER JOIN journo j ON j.id=s.other_id)
    WHERE s.journo_id=?
    ORDER BY s.score DESC
    LIMIT 10
EOT;
    $data['similar_journos'] = db_getAll( $sql, $journo['id'] );

    
    return $data;
}





function cmp_isopubdate( $a, $b ) {
    if( $a['iso_pubdate'] == $b['iso_pubdate'] )
        return 0;
    if( $a['iso_pubdate'] < $b['iso_pubdate'] )
        return 1;
    else
        return -1;
}


function journo_collectArticles( &$journo) {

    $limit = 10;

    $sql = <<<EOT
SELECT a.id,a.title,a.description,a.pubdate,a.permalink, o.prettyname as srcorgname, a.srcorg,a.total_bloglinks,a.total_comments
    FROM article a
        INNER JOIN journo_attr attr ON a.id=attr.article_id
        INNER JOIN organisation o ON o.id=a.srcorg
    WHERE a.status='a' AND attr.journo_id=?
    ORDER BY a.pubdate DESC
    LIMIT ?
EOT;
    $arts = db_getAll( $sql, $journo['id'], $limit );


    $sql = <<<EOT
SELECT NULL as id, title, NULL as description, pubdate, url as permalink, publication as srcorgname, NULL as srcorg, 0 as total_bloglinks, 0 as total_comments
    FROM journo_other_articles
    WHERE journo_id=? AND status='a'
    ORDER BY pubdate DESC
    LIMIT ?
EOT;
    $others = db_getAll( $sql, $journo['id'], $limit );

    $arts = array_merge( $arts, $others );

    /* augment results with pretty formatted date and buzz info */
    foreach( $arts as &$a ) {
        article_Augment($a);
        if( !is_null( $a['id'] ) )
            $a['buzz'] = BuzzFragment( $a );
        else
            $a['buzz'] = '';
    }

    usort( $arts, "cmp_isopubdate" );

    return $arts;

}



// return a list of journos which match the query text using metaphone
// algorithm to do approximate matching.
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
    $parts = preg_split( '/\s+/', $query );

    $matches = array();
    if( sizeof( $parts ) == 1 ) {
        /* single name - match as firstname, then lastname */
        $mph = substr( metaphone( $parts[0] ), 0, 4 );
        $matches =  array_merge(
            db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=? ORDER BY lastname,firstname", $mph ),
            db_getAll( "SELECT * FROM journo WHERE status='a' AND lastname_metaphone=? ORDER BY lastname,firstname", $mph ) );
    } else if( sizeof($parts) >= 2 ) {
        /* try matching both first and last names */
        $firstname_mph = substr( metaphone( $parts[0] ),0,4 );
        $lastname_mph = substr( metaphone( end( $parts ) ),0,4 );
        $matches = db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=? AND lastname_metaphone=? ORDER BY lastname,firstname",
            $firstname_mph, $lastname_mph );
        if( !$matches ) {
            /* if no matches, treat the last name as partially entered and use it as wildcard */
            $lastname = strtolower( end( $parts ) );
            $matches = db_getAll( "SELECT * FROM journo WHERE status='a' AND firstname_metaphone=? AND lastname LIKE ? ORDER BY lastname,firstname",
            $firstname_mph,
            $lastname . '%' );
        }


    }

    return $matches;
}


function journo_getPicture( $journo_id )
{
    $sql = <<<EOT
SELECT i.id,i.filename,i.width,i.height
        FROM ( image i INNER JOIN journo_picture jp ON jp.image_id=i.id )
        WHERE jp.journo_id=?
EOT;
    $img = db_getRow( $sql, $journo_id );

    if( $img ) {
        $img['url'] = imageUrl( $img['filename'] );
    }
    return $img;
}



function journo_getContactDetails( $journo_id )
{
    /* contact info is a bit odd. behind the scenes it's all in different tables,
    but the ui puts some restrictions on it for the sake of simplification */

    $email = db_getRow( "SELECT email, approved as show_public FROM journo_email WHERE journo_id=? LIMIT 1", $journo_id );
    $phone = db_getRow( "SELECT * FROM journo_phone WHERE journo_id=? LIMIT 1", $journo_id );
    $address = db_getRow( "SELECT * FROM journo_address WHERE journo_id=? LIMIT 1", $journo_id );

    // convert bool fields into php bools
    if( $email )
        $email['show_public'] = $email['show_public'] =='t' ? TRUE:FALSE;
    if( $phone )
        $phone['show_public'] = $phone['show_public'] =='t' ? TRUE:FALSE;
    if( $address )
        $address['show_public'] = $address['show_public'] =='t' ? TRUE:FALSE;

    return array( 'email' => $email, 'phone'=>$phone, 'address'=>$address );

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



