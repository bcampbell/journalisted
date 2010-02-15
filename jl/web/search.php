<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/misc.php';
require_once '../phplib/journo.php';
require_once '../phplib/xap.php';
require_once '../../phplib/db.php';





$s = search_getParams();
if( $s['type']=='article' ) {
    search_articles();
} else {
    search_journos();
}




function search_journos() {

    $s = search_getParams();
    $query = $s['q'];

    page_header( "Search Journalists", array('search_params'=>$s) );

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

<?php search_emit_onpage_form(); ?>

</div>  <!-- end main -->
<?php
    page_footer();
}


function search_articles()
{
    $s = search_getParams();
    $query = $s['q'];
    $num_per_page = $s['num'];
    $start = $s['start'];
    $sort_order = $s['sort_order'];

/* temp hack */
//$query = str_replace( '"', '', $query );


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

    foreach( $results as &$art ) {
        article_Augment( $art );
    }
    unset( $art );


    page_header( "Search Articles", array('search_params'=>$s) );



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
            $journolinks[] = sprintf( "<a href=\"%s\">%s</a>", '/'.$j['ref'], h( $j['prettyname'] ) );
        }
?>
      <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <?php if( $journolinks ) { ?><small><?= implode( ', ', $journolinks ); ?></small><br/><?php } ?>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
      </li>
<?php } ?>
    </ul>
  </div>
  <div class="pager">
<?php
        EmitPageControl( $query, $sort_order, $start, $num_per_page, $total );
?>
  </div>

<?php search_emit_onpage_form(); ?>
</div> <!-- end main -->
<?php

    page_footer();
}





function search_emit_onpage_form() {
    $s = search_getParams();
?>
  <div class="search">
    <form action="/search" method="get">
<!--        <label for="q">Search articles</label> -->
      <select name="type">
        <option value="journo"<?= ($s['type']=='journo')?' selected':'' ?>>Search journalists</option>
        <option value="article"<?= ($s['type']=='article')?' selected':'' ?>>Search articles</option>
      </select>
      <input type="text" value="<?= h($s['q']) ?>" id="q2" name="q" />
      <input type="submit" alt="search" value="Search" />
    </form>
  </div>
<?php
}






function PageURL( $query, $sort_order, $start, $num_per_page )
{
    $type = strtolower( get_http_var( 'type', 'journo' ) );
    $url = sprintf( "/search?q=%s&start=%d&num=%d&type=%s",
        urlencode($query), $start, $num_per_page, h($type) );
    if( $sort_order!='date' )
        $url .= "&o={h($sort_order)}";
    return $url;
}



function EmitPageControl( $query, $sort_order, $start, $num_per_page, $total )
{
    $total_pages = (int)(($total+($num_per_page-1))/$num_per_page);
    $current_page = (int)($start/$num_per_page);

    $max_pages = 10;
    $firstpage = max( 0, $current_page-$max_pages/2 );
    $lastpage = min( $total_pages-1, $firstpage+($max_pages-1) );

?>
<?php if( $total == 0 ) { ?>
<span>Page 0 of 0</span>
<?php } else { ?>
<span>Page <?= $current_page+1 ?> of <?= $total_pages ?></span>

<span class="page-links">
<?php
    if( $start>0 && $total>0 )
    {
?>
  <a rel="prev" href="<?= PageURL($query, $sort_order, max(0,$start-$num_per_page), $num_per_page) ?>" >&laquo; Previous</a> |
<?php
    }

    if( $firstpage > 0 )
        print " &hellip; ";

    for( $page=$firstpage; $page<=$lastpage; ++$page )
    {
        if( $page == $current_page ) {
            printf("%s ", $page+1 );
        } else {
            printf( "<a href=\"%s\">%s</a> ",
                htmlentities( PageURL($query, $sort_order, $page*$num_per_page, $num_per_page) ),
                $page+1 );
        }
    }

    if( $lastpage+1 < $total_pages )
        print " &hellip; ";

    if( $start+$num_per_page < $total )
    {
?>
  | <a rel="next" href="<?= PageURL($query, $sort_order, $start+$num_per_page, $num_per_page) ?>">Next &raquo;</a>
<?php
    }
?>
</span>
<?php } ?>

<?php
}


?>

