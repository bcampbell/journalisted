<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/misc.php';
require_once '../phplib/journo.php';
require_once '../phplib/xap.php';
require_once '../../phplib/db.php';


define( 'DEFAULT_NUM_PER_PAGE', 25 );


$type = strtolower( get_http_var( 'type', 'journo' ) );

if( $type=='article' ) {
    search_articles();
#    page_header( "Search Articles" );
#    page_footer();
} else {
    search_journos();
}
return;

function search_journos() {
    $query = get_http_var( 'q', '' );

    page_header( "Search Journalists" );

    $journos = array();
    if( $query )
        $journos = journo_FuzzyFind( $query );

?>
<div class="main search-results">
  <div class="head">
    <b>Search Results:</b> <span class="count"><?= sizeof($journos) ?> journalists</span> like <span class="query"><?= h($query) ?></span>
  </div>
  <div class="body">
<?php

    if( $journos ) {
?>
<ul>
<?php   foreach( $journos as $j ) { ?>
  <li><?= journo_link($j); ?></li>
<?php   } ?>
</ul>
<?php } ?>
  </div>
</div>  <!-- end main -->
<?php
    page_footer();
}



function search_articles()
{
    $query = get_http_var( 'q', '' );
    $num_per_page = (int)get_http_var('num', DEFAULT_NUM_PER_PAGE );
    $start = (int)get_http_var('start', '0' );
    $sort_order = get_http_var( 'o', 'date' );

/* temp hack */
$query = str_replace( '"', '', $query );


    $results = array();
    try {
#        $journo_id = $journo ? $journo['id'] : null;
        $journo_id = null;

        $search = new XapSearch();
        $search->set_query( $query, $journo_id );
        $results = $search->run( $start, $num_per_page, $sort_order );
    } catch (Exception $e) {
        print $e->getMessage() . "\n";
    }

    $total = $search->total_results;
    $first = $start+1;
    $last = min( $start+$num_per_page, $total );

    page_header( "Search Articles" );
?>
<div class="main search-results">

  <div class="head">
<?php if( $results ) { ?>
    <b>Search Results:</b> <?= $first ?>-<?= $last ?> of around <span class="count"><?= $total ?></span> articles matching <span class="query"><?= h($query) ?></span>
<?php } else { ?>
    <b>Search Results:</b> no articles matching <span class="query"><?= h($query) ?></span>

<?php } ?>
  </div>
  <div class="body">
    <ul class="art-list">
<?php
    foreach( $results as $art ) {
        $journolinks = array();
        foreach( $art['journos'] as $j ) {
            $journolinks[] = sprintf( "<a href=\"%s\">%s</a>", '/'.$j['ref'], cook( $j['prettyname'] ) );
        }
?>
      <li>
        <a href="/article?id=<?= $art['id']; ?>"><?= h($art['title']);?></a><br/>
        <?php if( $journolinks ) { ?><small><?= implode( ', ', $journolinks ); ?></small><br/><?php } ?>
        <?= PostedFragment( $art ); ?>
      </li>
<?php } ?>
    </ul>
  </div>
  <div class="foot">
<?php
    if( $total >= $num_per_page || $start>0 ) {
        EmitPageControl( $query, $sort_order, $start, $num_per_page, $total );
    }
?>
  </div>
</div> <!-- end main -->
<?php

    page_footer();
}








/* temp hack */
$query = str_replace( '"', '', $query );

/* was a particular journo specified? (can be an id or ref) */
$journo = null;
$id_or_ref = get_http_var( 'j', null );
if( $id_or_ref )
{
    $sql = sprintf( "SELECT id,ref,prettyname FROM journo WHERE %s=?",
        (int)$id_or_ref ? "id" : "ref" );
    $journo = db_getRow( $sql, $id_or_ref );
}

page_header( "" );

?>
<div id="maincolumn">
<?php



if( $journo ) {
    /* restricting query to a single journo */
    $journo_link = "<a href=\"/{$journo['ref']}\">{$journo['prettyname']}</a>";
    printf( "<h2>Find articles by %s</h2>\n", $journo_link );
} else {
    print "<h2>Find articles</h2>\n";
}


EmitQueryForm( $query, $sort_order, $num_per_page, $journo );
if( $query )
    DoQuery( $query, $sort_order, $start, $num_per_page, $journo );

?>
</div>
<?php

page_footer();




/*------------------------------------*/




function EmitQueryForm( $query, $sort_order, $num_per_page, $journo=null )
{
/*
?>
<small>
<p>examples:<br/>
<code>hash browns</code> - (articles with hash OR brown)<br/>
<code>"hash browns"</code> - (use quotes for exact phrase)<br/>
<code>title:grapefruit</code> - (search only titles)<br/>
<code>byline:"ben goldacre"</code><br/>
<code>byline:"ben goldacre" AND gillian</code> - (ben goldacres articles containing "gillian")<br/>
Detailed query syntax <a href="http://www.xapian.org/docs/queryparser.html">here</a>.
</p>

</small>
*/

?>
<form method="get" action="/search">
<input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" />
<?php
    if( $journo ) {
        printf( "<input type=\"hidden\" name=\"j\" value=\"%s\" />\n",
            $journo['ref'] );
    }

?>
<select name="o">
 <option <?php echo $sort_order=='date'?'selected="selected" ':''; ?>value="date">order by date</option>
 <option <?php echo $sort_order=='relevance'?'selected="selected" ':''; ?>value="relevance">order by relevance</option>
</select>
<br/>
<input type="submit" value="Find" />
</form>

<?php

}


function PageURL( $query, $sort_order, $start, $num_per_page )
{
    $type = strtolower( get_http_var( 'type', 'journo' ) );
    $url = sprintf( "/search?q=%s&start=%d&num=%d&type=%s",
        urlencode($query), $start, $num_per_page, h($type) );
    if( $sort_order!='date' )
        $url .= "&o={$sort_order}";
    return $url;
}

function EmitPageControl( $query, $sort_order, $start, $num_per_page, $total )
{
    $pagecnt = $total/$num_per_page;
    $currentpage = $start/$num_per_page;

    $n = 20;
    $firstpage = max( 0, $currentpage-$n/2 );
    $lastpage = min( $pagecnt, $firstpage+($n-1) );

    if( $start>0 && $total>0 )
    {
        printf( "<a rel=\"prev\" href=\"%s\">%s</a> ",
            htmlentities( PageURL($query,
                $sort_order,
                max(0,$start-$num_per_page),
                $num_per_page) ),
            "Previous" );
    }

    for( $page=$firstpage; $page<=$lastpage; ++$page )
    {
        if( $page == $currentpage ) {
            printf("%s ", $page+1 );
        } else {
            printf( "<a href=\"%s\">%s</a> ",
                htmlentities( PageURL($query, $sort_order, $page*$num_per_page, $num_per_page) ),
                $page+1 );
        }
    }

    if( $start+$num_per_page < $total )
    {
        printf( "<a rel=\"next\" href=\"%s\">%s</a> ",
            htmlentities( PageURL($query, $sort_order, $start+$num_per_page, $num_per_page) ),
            "Next" );
    }

    print "<br/>\n";


}




function cook( $raw )
{
    return htmlentities( $raw,ENT_COMPAT,'UTF-8' );
}


/* perform query, display results */
function DoQuery( $query_string, $sort_order, $start, $num_per_page, $journo=null )
{

    $results = array();
    try {
        $journo_id = $journo ? $journo['id'] : null;

        $search = new XapSearch();
        $search->set_query( $query_string, $journo_id );
        $results = $search->run( $start, $num_per_page, $sort_order );
    } catch (Exception $e) {
        print $e->getMessage() . "\n";
    }


    if( sizeof( $results ) == 0 ) {
        print( "<p>None found.</p>\n" );
        return;
    }

    $total = $search->total_results;

    $first = $start+1;
    $last = min( $start+$num_per_page, $total );



?>
<p>Showing <?= $first ?>-<?= $last ?> of around <?= $total ?> articles</p>
<ul>
<?php
    foreach( $results as $art ) {
        $journolinks = array();
        foreach( $art['journos'] as $j ) {
            $journolinks[] = sprintf( "<a href=\"%s\">%s</a>", '/'.$j['ref'], cook( $j['prettyname'] ) );
        }
?>
  <li>
    <a href="/article?id=<?php echo $art['id']; ?>"><?php echo cook($art['title']);?></a><br/>
    <?php if( $journolinks ) { ?><small><?php echo implode( ', ', $journolinks ); ?></small><br/><?php } ?>
    <?php echo PostedFragment( $art ); ?>
  </li>
<?php } ?>
</ul>
<?php
    if( $total >= $num_per_page || $start>0 ) {
        EmitPageControl( $query_string, $sort_order, $start, $num_per_page, $total );
    }
}
?>

