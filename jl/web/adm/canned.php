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

/* two sets of buttons/action selectors */
$action = get_http_var( 'action' );

admPageHeader( "Canned Queries" );

$canned = array(  new KnownEmailAddresses(), new OrgList(), new ArticleCount(), new AlertUsers(), new ProlificJournos(), new MostIndepthJournos(), new TopTags() );

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
<dd><?php echo $c->desc; ?></dd>
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

    if( is_array( $row[$col] ) ) {
        if( $col=='journo' ) {
            $j = $row[$col];
            $out = "<a href=\"/{$j['ref']}\" >{$j['prettyname']}</a>";
            if( array_key_exists( 'oneliner', $j ) )
                $out .= " <small><em>({$j['oneliner']})</em></small>";
            $out .= " <small>[<a href=\"/adm/{$j['ref']}\">admin</a>]</small>";
            return $out;
        }

        if( $col=='article' ) {
            $a = $row[$col];

            // assume we've got title and id at least
            $out = "<a href=\"/article?id={$a['id']}\">{$a['title']}</a>";
            $out .= " <small>[<a href=\"/adm/article?id={$a['id']}\">admin</a>]</small>";
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
            $ref = $row[$col];
            return sprintf( '<a href="/%s">%s</a> [<a href="/adm/%s">admin</a>]', $ref, $ref, $ref );
        }
        return admMarkupPlainText( $row[$col] );
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
    <?php foreach( $columns as $col ) { ?><td><?php echo call_user_func( $colfunc, $row, $col, $prevrow ); ?></td><?php } ?>
  </tr>
<?php $prevrow=$row; } ?>

</tbody>
</table>

<?php

}


//
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
        foreach( $this->param_spec as $p ) {
            $params[$p['name']] = get_http_var( $p['name'], $p['default'] );
        }
        return $params;
    }

    function emit_params_form( $params ) {

?>
<form method="get">
<input type="hidden" name="action" value="<?php echo $this->ident; ?>" />
<?php foreach( $this->param_spec as $p ) { ?>
<label for="<?= $p['name'] ?>"><?= $p['label'] ?></label>
<?php if( array_key_exists( 'options', $p ) ) { /* SELECT element */ ?>
 <select name="<?= $p['name'] ?>" id="<?= $p['name'] ?>">
 <?php foreach( $p['options'] as $value=>$desc ) { ?>
  <option <?= ($params[$p['name']]==$value)?'selected':'' ?> value="<?= $value ?>"><?= $desc ?></option>
 <?php } ?>
 </select>
<?php } else { /* just use a generic text input element */ ?>
 <input type="text" name="<?php echo $p['name']; ?>" id="<?php echo $p['name']; ?>" value="<?php echo $params[$p['name']]; ?>"/>
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
        $this->desc = "Which journos have written the longest articles (top 100)";


        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') )
        );
    }

    function perform($params) {

        $sql = <<<EOT
SELECT a.wordcount,a.id,a.title,a.srcorg,a.pubdate,a.permalink,j.prettyname, j.ref
    FROM article a LEFT JOIN ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id) ON a.id=attr.article_id
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours')
    ORDER BY a.wordcount DESC
    LIMIT 100
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
SELECT t.tag, sum(t.freq) as tag_total
    FROM article a INNER JOIN article_tag t ON a.id=t.article_id
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours')
    GROUP BY tag
    ORDER BY tag_total DESC
    LIMIT 100
EOT;

        $rows = db_getAll( $sql, $params['from_date'], $params['to_date'] );
        collectColumns( $rows );
        Tabulate( $rows );
    }
}


?>
