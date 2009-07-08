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

/* two sets of buttons/action selectors */
$action = get_http_var( 'action' );

admPageHeader( "Canned Queries" );

$canned = array( new ProlificJournos(), new KnownEmailAddresses(), new OrgList(), new ArticleCount(), new AlertUsers() );

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

<p>Shows similar articles, with score >= 98</p>

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

    Tabluate( $rows, array( 'article','score','similar'), 'Do_verysimilararticles_format' );
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
    if( $col=='ref' ) {
        $ref = $row[$col];
        return sprintf( '<a href="/%s">%s</a> [<a href="/adm/%s">admin</a>]', $ref, $ref, $ref );
    }

    return admMarkupPlainText( $row[$col] );
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


function Tabluate( $rows, $columns=null, $colfunc='Tabulate_defaultformat' ) {

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
<label for="<?php echo $p['name']; ?>"><?php echo $p['label']; ?></label>
<input type="text" name="<?php echo $p['name']; ?>" id="<?php echo $p['name']; ?>" value="<?php echo $params[$p['name']]; ?>"/><br />
<?php } ?>
<input type="submit" name="go" value="Go!" />

</form>
<?

    }

};


class ProlificJournos extends CannedQuery {
    public $name = "ProlificJournos";
    public $ident = "prolificjournos";

    function __construct() {
        $this->name = "ProlificJournos";
        $this->ident = "prolificjournos";
        $this->desc = "list how many articles a journo has written over a period of time";
        $this->param_spec = array(
            array( 'name'=>'from_date', 'label'=>'From date (yyyy-mm-dd):', 'default'=>date_create('1 week ago')->format('Y-m-d') ),
            array( 'name'=>'to_date', 'label'=>'To date (yyyy-mm-dd):', 'default'=>date_create('today')->format('Y-m-d') )
        );
    }

    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";

        $params = $this->get_params();
        $this->emit_params_form( $params );

        if( !get_http_var( 'go' ) )
            return;

        $sql = <<<EOT
SELECT count(a.id) as num_articles, j.id,j.status,j.ref,j.oneliner
    FROM (( article a INNER JOIN journo_attr attr ON attr.article_id=a.id ) INNER JOIN journo j ON j.id=attr.journo_id)
    WHERE a.pubdate >= date ? AND a.pubdate < (date ? + interval '24 hours')
    GROUP BY j.id,j.ref, j.oneliner, j.status
    ORDER BY num_articles DESC;
EOT;

        $rows = db_getAll( $sql, $params['from_date'], $params['to_date'] );
        Tabluate( $rows, array( 'num_articles','journo'), array($this,'fmt') );
    }

    function fmt( &$row, $col, $prevrow ) {
        if( $col == 'journo' ) {
            return sprintf( '<a href="/adm/%s">%s</a> <small>(%s)</small>', $row['ref'], $row['ref'], $row['oneliner'] );
        } else {
            return $row[$col];
        }
    }
}


class KnownEmailAddresses extends CannedQuery {
    function __construct() {
        $this->name = "KnownEmailAddresses";
        $this->ident = "knownemailaddresses";
        $this->desc = "list email addresses for journos";
    }

    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";

        $sql = <<<EOT
SELECT j.ref,e.email FROM (journo_email e INNER JOIN journo j ON e.journo_id=j.id) ORDER BY j.ref;
EOT;

        $rows = db_getAll( $sql );
        Tabluate( $rows );
    }
}


class OrgList extends CannedQuery {
    function __construct() {
        $this->name = "OrgList";
        $this->ident = "orglist";
        $this->desc = "List of news organisations we cover, with details (phone number etc)";
    }

    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";

        $sql = <<<EOT
SELECT * FROM organisation ORDER BY shortname;
EOT;

        $rows = db_getAll( $sql );
        Tabluate( $rows );
    }
}

class ArticleCount extends CannedQuery {
    function __construct() {
        $this->name = "ArticleCount";
        $this->ident = "articlecount";
        $this->desc = "The number of active articles in the database";
    }

    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";

        $sql = <<<EOT
SELECT count(*) as num_articles FROM article WHERE status='a';
EOT;

        $rows = db_getAll( $sql );
        Tabluate( $rows );
    }
}

class AlertUsers extends CannedQuery {
    function __construct() {
        $this->name = "AlertList";
        $this->ident = "alertlist";
        $this->desc = "List of alerts (ordered by email address)";
    }

    function go() {
        echo "<h2>{$this->name}</h2>\n";
        echo "<p>{$this->desc}</p>\n";

        $sql = <<<EOT
SELECT p.email, j.ref FROM ((alert a INNER JOIN person p ON a.person_id=p.id) INNER JOIN journo j ON a.journo_id=j.id) ORDER BY p.email;
EOT;

        $rows = db_getAll( $sql );
        Tabluate( $rows );
    }
}

