<?php
// canned.php
//
// catchall page for assorted canned queries
//
// TODO: wrap up "scrapers" and "verysimilararticles" into classes

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/journo.php';    // for journo_link()
require_once '../phplib/xap.php';

/* two sets of buttons/action selectors */
$action = get_http_var( 'action' );

admPageHeader( "Canned Queries" );

$canned = array(
    new KnownEmailAddresses(),
    new OrgList(),
    new ArticleCount(),
    new AlertUsers(),
    new ProlificJournos(),
    new MostIndepthJournos(),
    new TopTags(),
    new QueryFight(),
    new WhosWritingAbout(),
    new NewsletterSubscribers(),
    new EventLog(),
    new RegisteredJournos(),
    new Pingbacks(),
    new FakeJournos(),
    new MightBeStudents(),
    new UserCreatedJournoProfiles(),
    new RecentlyEditedJournos(),
);




function ShowMenu()
{
    global $canned;

?>
<h2>Assorted Canned Queries</h2>
<dl>
<dt><a href="/adm/canned?action=scrapers">Scrapers (very slow)</a></dt>
<dd>summary of number of articles scraped by each scraper over the last week</dd>
<dt><a href="/adm/canned?action=verysimilararticles">VerySimilarArticles</a></dt>
<dd>groups of very-similar articles</dd>
<?php foreach( $canned as $c ) { ?>
<dt><a href="/adm/canned?action=<?php echo $c->ident; ?>"><?php echo $c->name; ?></a></dt>
<dd><?= $c->desc; ?></dd>
<?php } ?>
</dl>
<?php

}

if( $action=='scrapers' )
    EmitScraperReport();
elseif( $action=='verysimilararticles' )
    Do_verysimilararticles();
else
{
    $picked = null;
    foreach( $canned as $c ) {
        if( $c->ident == $action ) {
            $picked = $c;
            break;
        }
    }

    if( $picked ) {
        $picked->go();
    } else {
        ShowMenu();
    }
}

admPageFooter();


/********************************/

function FetchScraperStats( $day )
{
    $results = array();
    $r = db_getAll( "SELECT srcorg, COUNT(*) as cnt FROM article WHERE lastscraped::date=?", $day );
}


function EmitScraperReport( $latest_day='today', $days_back=7 )
{
    $date = new DateTime( $latest_day );
    $orgs = get_org_names();

?>
<h3>Articles scraped over the last <?php echo $days_back; ?> days</h3>

<table>
<thead>
<tr><th>date</th><?php

    foreach( $orgs as $orgid=>$orgname )
        print "<th>{$orgname}</th>";

?></tr>
<tbody>
<?php

    for( $i=0; $i<$days_back; ++$i )
    {
        $d = $date->format( 'Y-m-d' );
        $q = db_query( "SELECT srcorg, COUNT(*) AS cnt FROM article WHERE lastscraped::date=? GROUP BY srcorg", $d );
        $cnts = array();
        while( $row= db_fetch_array( $q ) )
        {
            $cnts[ $row['srcorg'] ] = $row['cnt'];
        }

        printf( "<tr><th>%s</th>", $d );
        foreach( $orgs as $orgid=>$orgname )
        {
            $cnt = 0;
            if( array_key_exists( $orgid, $cnts ) )
                $cnt = $cnts[$orgid];
            printf("<td>%d</td>", $cnt );
        }
        print "</tr>\n";
        $date->modify( '-1 days' );
    }

?>
</tbody>
</table>
<?php

}


function Do_verysimilararticles()
{
//    $limit = get_http_var('limit',1000 );
//    $offset = get_http_var( 'offset', 0 );


    $from_date = get_http_var('from_date', date_create('1 week ago')->format('Y-m-d') );
    $to_date = get_http_var('to_date', date_create('today')->format('Y-m-d') );

?>
<h2>VerySimilarArticles</h2>

<p>Shows similar articles, with score &gt;= 98</p>

<form method="get">
<input type="hidden" name="action" value="verysimilararticles" />
<label for="from_date">From (yyyy-mm-dd):</label>
<input type="text" name="from_date" id="from_date" value="<?php echo $from_date; ?>"/>
<label for="to_date">To (yyyy-mm-dd):</label>
<input type="text" name="to_date" id="from_date" value="<?php echo $to_date; ?>"/>

<input type="submit" name="go" value="Go!" />

</form>
<?php

    if( !get_http_var( 'go' ) )
        return;

    $sql = <<<EOT
SELECT s.score,s.article_id, a.title as article_title, a.srcorg as article_srcorg, a.byline as article_byline, s.other_id, a2.title as other_title, a2.srcorg as other_srcorg, a2.byline as other_byline
    FROM ( ( article_similar s INNER JOIN article a ON s.article_id=a.id )
         INNER JOIN article a2 ON s.other_id=a2.id )
    WHERE s.score>98
    AND a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours')
    AND a2.pubdate >= date ? AND a2.pubdate < (date ? + interval '24 hours')
    ORDER BY s.article_id, s.score DESC
EOT;

    $rows = db_getAll( $sql, $from_date, $to_date, $from_date, $to_date );

    Tabulate( $rows, array( 'article','score','similar'), 'Do_verysimilararticles_format' );
}

function Do_verysimilararticles_format( &$row, $col, $prevrow=null ) {
    $orgs = get_org_names();

    if( $col=='score' )
        return $row['score'];
    if( $col=='article' )
    {
        if( $prevrow && $row['article_id'] == $prevrow['article_id'] )
            return "-";
        else
            return sprintf( '<a href="/adm/article?id=%s">%s</a> <small>%s</small><br/><small>byline: "%s"</small>', $row['article_id'], $row['article_title'], $orgs[$row['article_srcorg']], $row['article_byline'] );
    }
    if( $col=='similar' )
        return sprintf( '<a href="/adm/article?id=%s">%s</a> <small>%s</small><br/><small>byline: "%s"</small>', $row['other_id'], $row['other_title'], $orgs[$row['other_srcorg']], $row['other_byline'] );
    return null;
}


function Tabulate_defaultformat( &$row, $col, $prevrow=null ) {
    $cell = $row[$col];
    if( $cell instanceof DateTime ) {
        return $cell->format( 'Y-m-d' );
    } else if( is_array( $cell ) ) {
        if( $col=='journo' ) {
            $j = $cell;
            $out = "<a href=\"/{$j['ref']}\" >{$j['prettyname']}</a>";
            if( array_key_exists( 'oneliner', $j ) )
                $out .= " <small><em>({$j['oneliner']})</em></small>";
            $out .= " <small>[<a href=\"/adm/{$j['ref']}\">admin page</a>]</small>";
            /* can provide an array of extra links */
            if( array_key_exists( 'extralinks', $j ) ) {
                foreach( $j['extralinks'] as $l ) {
                    $out .= " <small>[<a href=\"{$l['href']}\">{$l['text']}</a>]</small>";
                }
            }
            return $out;
        }

        if( $col=='article' ) {
            $a = $cell;

            // assume we've got title and id at least
            $out = "<a href=\"/article?id={$a['id']}\">{$a['title']}</a>";
            $out .= " <small>[<a href=\"/adm/article?id={$a['id']}\">admin page</a>]</small>";
            if( array_key_exists( 'permalink', $a ) ) {
                $out .= " <small>[<a href=\"{$a['permalink']}\">original article</a>]</small>";
            }
            if( array_key_exists( 'srcorgname', $a ) ) {
                $out .= " <small>{$a['srcorgname']}</small>";
            }
            if( array_key_exists( 'pubdate', $a ) ) {
                $prettydate = date_create( $a['pubdate'] )->format('Y-m-d H:i');

                $out .= " <small>{$prettydate}</small>";
            }
            return $out;
        }

        return "[array]";

    } else {
        if( $col=='ref' ) {
            $ref = $cell;
            return sprintf( '<a href="/%s">%s</a> [<a href="/adm/%s">admin page</a>]', $ref, $ref, $ref );
        }
        return admMarkupPlainText( $cell );
    }
}

/*
function ExtraHead() {

?>
<script type="text/JavaScript">
$(document).ready(function() 
    { 
        $("#canned-output").tablesorter(); 
    } 
);
</script>
<?php
}
*/


function Tabulate( $rows, $columns=null, $colfunc='Tabulate_defaultformat' ) {

?>
<p><?php echo sizeof($rows); ?> rows:</p>
<?php
    if( !$rows )
        return;

    if( is_null( $columns ) ) {
        $columns = array_keys( $rows[0] );
    }

    $prevrow = null;

?>
<table border=1 id="canned-output">
<thead>
  <tr>
    <?php foreach( $columns as $col ) { ?><th><?php echo $col; ?></th><?php } ?>
  </tr>
</thead>
<tbody>
<?php foreach( $rows as $row ) { ?>
  <tr>
    <?php foreach( $columns as $col ) { ?><td><?php echo call_user_func( $colfunc, &$row, $col, $prevrow ); ?></td><?php } ?>
  </tr>
<?php $prevrow=$row; } ?>

</tbody>
</table>

<?php

}


// compress certain groups of columns into a single one for better formatting
function collectColumns( &$rows )
{
    $orgs = get_org_names();

    foreach( $rows as &$row ) {

        // journo?
        if( array_key_exists( 'ref', $row ) ) {
            $j = array();
            foreach( array( 'ref','prettyname','oneliner' ) as $col ) {
                if( array_key_exists( $col, $row ) ) {
                    if( !is_null( $row[$col] ) ) {
                        $j[$col] = $row[$col];
                        unset( $row[$col] );
                    }
                }
            }

            if( !$j )
                $j=NULL;

            $row['journo'] = $j;
        }

        // article
        if( array_key_exists( 'title', $row ) ) {
            $a = array();
            foreach( array( 'title','id','permalink','pubdate','srcorg' ) as $col ) {
                if( array_key_exists( $col, $row ) ) {
                    $a[$col] = $row[$col];
                    unset( $row[$col] );
                }

                if( array_key_exists( 'srcorg', $a ) ) {
                    $a['srcorgname'] = $orgs[$a['srcorg']];
                }
            }
            $row['article'] = $a;
        }
    }
}




class CannedQuery {

    public $param_spec = array();

    function get_params() {
        $params = array();
        foreach( $this->param_spec as $spec ) {
            $value = get_http_var( $spec['name'], $spec['default'] );
            if( arr_get('type',$spec)=='date' ) {
                if( $value )
                    $value = new DateTime( $value );
            }
            $params[$spec['name']] = $value;
        }
        return $params;
    }

    function emit_params_form( $params ) {

?>
<form method="get">
<input type="hidden" name="action" value="<?php echo $this->ident; ?>" />
<?php foreach( $this->param_spec as $spec ) { ?>
<label for="<?= $spec['name'] ?>"><?= $spec['label'] ?></label>
<?php if( array_key_exists( 'options', $spec ) ) { /* SELECT element */ ?>
 <select name="<?= $spec['name'] ?>" id="<?= $spec['name'] ?>">
 <?php foreach( $spec['options'] as $value=>$desc ) { ?>
  <option <?= ($params[$spec['name']]==$value)?'selected':'' ?> value="<?= h($value) ?>"><?= $desc ?></option>
 <?php } ?>
 </select>
<?php } else { /* just use a generic text input element */ ?>
 <?php if( arr_get('type',$spec) == 'date' ) { ?>
 <input type="text" name="<?= $spec['name']; ?>" id="<?= $spec['name'] ?>" value="<?= $params[$spec['name']]->format('Y-m-d') ?>"/>
 <?php } else { ?>
 <input type="text" name="<?= $spec['name']; ?>" id="<?= $spec['name'] ?>" value="<?= h($params[$spec['name']]); ?>"/>
 <?php } ?>
<?php } ?>
<br />
<?php } ?>
<input type="submit" name="go" value="Go!" />

</form>
<?

    }




    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";
        if( isset($this->longdesc) ) {
            echo "<p>{$this->longdesc}</p>\n";
        }

        $params = array();
        if( $this->param_spec ) {
            $params = $this->get_params();
            $this->emit_params_form( $params );
            if( !get_http_var( 'go' ) )
                return;
        }

        $this->perform( $params );
    }


    // to be overidden
    function perform( $params = array () )
    {
    }

};



class ProlificJournos extends CannedQuery {
    function __construct() {
        $this->name = "ProlificJournos";
        $this->ident = "prolificjournos";
        $this->desc = "Rank journos according to total words/articles written over time interval (top 100)";
        $orgs = get_org_names();
        $orgs[ 'all' ] = 'All';

        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') ),
            array( 'name'=>'orderby',
                'label'=>'Who has written the:',
                'options'=>array( 'words'=>'Most Words', 'articles'=>'Most Articles'),
                'default'=>'articles' ),
            array( 'name'=>'publication',
                'label'=>'Publication',
                'options'=>$orgs,
                'default'=>'all' ),
         //o Who have written the most words and/or articles in each of the national papers',
        );
    }


    function perform($params) {
        $orderby = NULL;
        $column_order = array();

        $sqlparams = array( $params['from_date'], $params['to_date'] );

        switch( $params['orderby'] ) {
            case 'words':
                $orderby='total_words';
                $column_order = array( 'total_words','total_articles','journo');
                break;
            case 'articles' :
                $orderby='total_articles';
                $column_order = array( 'total_articles','total_words','journo');
                break;
        }

        // restrict by publication (maybe)
        $extraclause = '';
        if( $params['publication']!='all' ) {
            $extraclause = 'AND srcorg=?';
            $sqlparams[] = $params['publication'];
        }

        $sql = <<<EOT
SELECT count(a.id) as total_articles, sum(a.wordcount) as total_words, j.id,j.status,j.ref,j.prettyname,j.oneliner
    FROM (( article a INNER JOIN journo_attr attr ON attr.article_id=a.id ) INNER JOIN journo j ON j.id=attr.journo_id)
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours') {$extraclause}
    GROUP BY j.id,j.ref, j.oneliner, j.status,j.prettyname
    ORDER BY {$orderby} DESC
    LIMIT 100;
EOT;

        $rows = db_getAll( $sql, $sqlparams );
        collectColumns( $rows );

        Tabulate( $rows, $column_order );
    }
}


class KnownEmailAddresses extends CannedQuery {
    function __construct() {
        $this->name = "KnownEmailAddresses";
        $this->ident = "knownemailaddresses";
        $this->desc = "list email addresses for journos";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT j.ref,e.email FROM (journo_email e INNER JOIN journo j ON e.journo_id=j.id) ORDER BY j.ref;
EOT;

        $rows = db_getAll( $sql );
        Tabulate( $rows );
    }
}


class OrgList extends CannedQuery {
    function __construct() {
        $this->name = "OrgList";
        $this->ident = "orglist";
        $this->desc = "List of news organisations we cover, with details (phone number etc)";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT * FROM organisation ORDER BY shortname;
EOT;

        $rows = db_getAll( $sql );
        Tabulate( $rows );
    }
}

class ArticleCount extends CannedQuery {
    function __construct() {
        $this->name = "ArticleCount";
        $this->ident = "articlecount";
        $this->desc = "The number of active articles in the database";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT count(*) as num_articles FROM article WHERE status='a';
EOT;

        $rows = db_getAll( $sql );
        Tabulate( $rows );
    }
}

class AlertUsers extends CannedQuery {
    function __construct() {
        $this->name = "AlertList";
        $this->ident = "alertlist";
        $this->desc = "List of alerts (ordered by email address)";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT p.email, j.ref FROM ((alert a INNER JOIN person p ON a.person_id=p.id) INNER JOIN journo j ON a.journo_id=j.id) ORDER BY p.email;
EOT;

        $rows = db_getAll( $sql );
        Tabulate( $rows );
    }
}


class MostIndepthJournos extends CannedQuery {
    function __construct() {
        $this->name = "MostIndepthJournos";
        $this->ident = strtolower( $this->name );
        $this->desc = "Which journos have written the longest articles (top 1000)";


        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') )
        );
    }

    function perform($params) {

        $sql = <<<EOT
SELECT a.wordcount,a.id,a.title,a.srcorg,a.pubdate,a.permalink,j.prettyname, j.ref
    FROM article a INNER JOIN ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id) ON a.id=attr.article_id
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours')
    ORDER BY a.wordcount DESC NULLS LAST
    LIMIT 1000
EOT;

        $rows = db_getAll( $sql, $params['from_date'], $params['to_date'] );
        collectColumns( $rows );
        Tabulate( $rows, array( 'journo','wordcount','article') );
    }
}



class TopTags extends CannedQuery {
    function __construct() {
        $this->name = "TopTags";
        $this->ident = strtolower( $this->name );
        $this->desc = "The top 100 tags used over the given interval";

        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') )
        );
    }


    function perform($params) {

        $sql = <<<EOT
SELECT t.tag, sum(t.freq) as tag_total, count(*) as num_articles
    FROM article a INNER JOIN article_tag t ON a.id=t.article_id
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours') AND t.kind=' '
    GROUP BY tag
    ORDER BY tag_total DESC
    LIMIT 100
EOT;

        $rows = db_getAll( $sql, $params['from_date'], $params['to_date'] );
        collectColumns( $rows );
        Tabulate( $rows );
    }
}



class QueryFight extends CannedQuery {
    function __construct() {
        $this->name = "QueryFight";
        $this->ident = strtolower( $this->name );
        $this->desc = "Compare fulltext queries side-by-side over time";

        $this->param_spec = array(
            array( 'name'=>'q1', 'label'=>'Query one', 'default'=>'' ),
            array( 'name'=>'q2', 'label'=>'Query two', 'default'=>'' ),
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') )
        );
    }


    function q_count( $q ) {
        $n = 0;
        $batchsize = 1000;

        $xap = new XapSearch();
        $xap->set_query( $q );
        while(1) {
            $rows = $xap->run($n,$batchsize,'relevance');
            $n += sizeof( $rows );
            if( sizeof( $rows ) < $batchsize )
                break;
        }

        return $n;
    }


    function perform($params) {
        $q1 = $params['q1'];
        $q2 = $params['q2'];

        if( $q1 && $q2 ) {
            $q1 = $params['q1'];
            $q2 = $params['q2'];
            if( $params['from_date'] || $params['to_date'] ) {
                $from = date_create( $params['from_date'])->format('Ymd');
                $to = date_create( $params['to_date'])->format('Ymd');
                $range = " $from..$to";
                $q1 .= $range;
                $q2 .= $range;
            }
?>
<pre>
q1: [<?= $q1 ?>]
q2: [<?= $q2 ?>]
</pre>
<?php

            $result = array();
            $n1 = $this->q_count( $q1 );
            $result[ "q1: {$params['q1']}" ] = $n1;
            $n2 = $this->q_count( $q2 );
            $result[ "q2: {$params['q2']}" ] = $n2;

            Tabulate( array( $result ) );
        }
    }
}


class WhosWritingAbout extends CannedQuery {
    function __construct() {
        $this->name = "WhosWritingAbout";
        $this->ident = strtolower( $this->name );
        $this->desc = "List journos writing about a certain topic";

        $this->param_spec = array(
            array( 'name'=>'q', 'label'=>'Topic', 'default'=>'' ),
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') ),
            array( 'name'=>'orderby',
                'label'=>'Order by',
                'options'=>array( 'articles'=>'articles', 'wordcount'=>'wordcount'),
                'default'=>'articles' ),
        );
    }


    function perform($params) {
        $from = date_create( $params['from_date'])->format('Ymd');
        $to = date_create( $params['to_date'])->format('Ymd');
        $range = " $from..$to";

        if( $params['q'] ) {
            $q = $params['q'] . $range;


            $xap = new XapSearch();
            $xap->set_query( $q );
            $rows = $xap->run(0,999999,'date');

            /* collect journos */
            $journos = array();
            foreach( $rows as $r ) {
                foreach( $r['journos'] as $j ) {
                    $journo_id = $j['id'];
                    if( array_key_exists( $journo_id, $journos ) ) {
                        $journos[$journo_id]['articles'] += 1;
                        $wc = db_getOne( "SELECT wordcount FROM article WHERE id=?", $r['id'] );
                        $journos[$journo_id]['wordcount'] += $wc;
                    } else {
                        $j['articles'] = 1;
                        $wc = db_getOne( "SELECT wordcount FROM article WHERE id=?", $r['id'] );
                        $j['wordcount'] = $wc;
                        $journos[ $journo_id ] = $j;
                    }
                }
            }

            /* format */
            $results = array();
            foreach( $journos as $j ) {

                $search_url = "/search?type=article&by=" . $j['ref'] . "&q=" . urlencode( $q );

                $j['extralinks'] = array(
                    array('href'=>$search_url, 'text'=>'show articles')
                );
                $results[] = array( 'journo'=>$j,
                    'articles'=>$j['articles'],
                    'wordcount'=>$j['wordcount'] );
            }

            if( $params['orderby'] == 'wordcount' ) {
                uasort($results, 'cmp_word_count');
            } else {
                uasort($results, 'cmp_article_count');
            }

            Tabulate( $results );
        }
    }
}


function cmp_article_count( $a, $b ) {
    if( $a['articles'] == $b['articles'] )
        return 0;
    if( $a['articles'] < $b['articles'] )
        return 1;
    else
        return -1;
}

function cmp_word_count( $a, $b ) {
    if( $a['wordcount'] == $b['wordcount'] )
        return 0;
    if( $a['wordcount'] < $b['wordcount'] )
        return 1;
    else
        return -1;
}


class NewsletterSubscribers extends CannedQuery {
    function __construct() {
        $this->name = "NewsletterSubscribers";
        $this->ident = strtolower( $this->name );
        $this->desc = "List of users subscribed to the newsletter";

    }

    function perform($params) {

        $sql = <<<EOT
SELECT p.name,p.email
    FROM person_receives_newsletter n INNER JOIN person p ON p.id=n.person_id
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }
}

# select j.ref,e.* from event_log e INNER join journo j on j.id=e.journo_id where event_time > now() - interval '1 day';

class EventLog extends CannedQuery {
    function __construct() {
        $this->name = "EventLog";
        $this->ident = strtolower( $this->name );
        $this->desc = "Recent events (journo profile modifications, etc)";

        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'type'=>'date', 'default'=>'1 week ago' ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'type'=>'date', 'default'=>'today' )
        );
    }


    function perform($params) {

        $sql = <<<EOT
SELECT e.event_type,e.event_time,e.context_json,j.ref,j.prettyname,j.oneliner
    FROM event_log e INNER join journo j on j.id=e.journo_id
    WHERE e.event_time >= date ? AND e.event_time < (date ? + interval '24 hours')
    ORDER BY e.event_time DESC
EOT;
        $rows = db_getAll( $sql, $params['from_date']->format('Y-m-d'), $params['to_date']->format('Y-m-d') );
        foreach( $rows as &$r ) {
            $r['description'] = $r['event_type'] . ' ("' . eventlog_Describe( $r ) . '")';
            unset( $r['event_type'] );
            unset( $r['context'] );
            unset( $r['context_json'] );

            $r['event_time'] = date_create( $r['event_time'] )->format( 'Y-m-d h:i:s' );
        }

        collectColumns( $rows );
        Tabulate( $rows );
    }
}


class RegisteredJournos extends CannedQuery {
    function __construct() {
        $this->name = "RegisteredJournos";
        $this->ident = strtolower( $this->name );
        $this->desc = "List journo pages which have been claimed";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT j.ref, j.prettyname, j.oneliner, p.email, p.name FROM (person p INNER JOIN person_permission perm ON perm.person_id=p.id) INNER JOIN journo j ON j.id=perm.journo_id WHERE perm.permission='edit';
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }
}


class Pingbacks extends CannedQuery {
    function __construct() {
        $this->name = "Pingbacks";
        $this->ident = strtolower( $this->name );
        $this->desc = "Show pingbacks, by journo";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT j.ref, j.prettyname, j.oneliner, l.url, l.description FROM journo j INNER join journo_weblink l ON j.id=l.journo_id WHERE l.kind='pingback' AND l.approved=true ORDER BY l.journo_id;
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }
}

class FakeJournos extends CannedQuery {
    function __construct() {
        $this->name = get_class($this);
        $this->ident = strtolower( $this->name );
        $this->desc = "Show journos marked as fake";
    }

    function perform($params) {
        $sql = <<<EOT
SELECT ref, prettyname, oneliner FROM journo WHERE fake=true;
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }
}


class MightBeStudents extends CannedQuery {
    function __construct() {
        $this->name = get_class($this);
        $this->ident = strtolower( $this->name );
        $this->desc = "Show journos who look like they might still be students (because they have an education entry with an open end date)";
    }

    function perform($params) {
        $sql = <<<EOT
SELECT j.ref, j.prettyname, j.oneliner,j.created as journo_created, e.school,e.field,e.qualification,e.year_from
    FROM journo j INNER JOIN journo_education e ON e.journo_id=j.id
    WHERE e.year_to IS NULL
    ORDER BY journo_created DESC, j.ref
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }
}


class UserCreatedJournoProfiles extends CannedQuery {
    function __construct() {
        $this->name = get_class($this);
        $this->ident = strtolower( $this->name );
        $this->desc = "Show journo profiles which were created by users";
        $this->longdesc = <<<EOT
NOTE: we don't actually have any way to tell if a journo was created by
a user claim or automatically via article scraping.
So this list just shows journo profiles which were created on the same
day as being claimed.
EOT;
    }

    function perform($params) {
        $sql = <<<EOT
SELECT j.status, j.ref, j.prettyname, j.created
    FROM journo j INNER JOIN person_permission perm ON perm.journo_id=j.id
    WHERE date(j.created) = date(perm.created)
        AND perm.permission='edit'
        AND j.status='a'
    ORDER BY j.created DESC;
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }

}


class RecentlyEditedJournos extends CannedQuery {
    function __construct() {
        $this->name = get_class($this);
        $this->ident = strtolower( $this->name );
        $this->desc = "Show journo profiles in order of most recent edit";
    }

    function perform($params) {

        $sql = <<<EOT
SELECT distinct j.id,j.ref,j.prettyname, (SELECT MAX(event_time) FROM event_log WHERE journo_id=j.id) last_edited
    FROM journo j
    WHERE j.id in (select distinct journo_id from event_log) ORDER BY last_edited DESC;
EOT;
        $rows = db_getAll( $sql ); 
        collectColumns( $rows );
        Tabulate( $rows );
    }

}


?>
