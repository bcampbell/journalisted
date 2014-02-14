<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/misc.php';
require_once '../phplib/journo.php';
require_once '../phplib/article.php';
require_once '../phplib/xap.php';
require_once '../phplib/search.php';
require_once '../../phplib/db.php';








function view() {
    $s = search_getParams();

    $foo = array();
    $journos = array();

    if( $s['type']!='journo' ) {
        $foo = search_articles($s);
    } else {
        $foo = array('articles'=>array(), 'total'=>0);
    }

    if( $s['type']!='article' ) {
        $journos = search_journos($s);
    }

    // hackhackhack
    if( $s['fmt'] == 'csv' ) {
        search_articles_output_csv($articles);
        return;
    }

    tmpl($s, $journos,$foo['articles'], $foo['total']);
}



function search_journos($s) {

    $query = $s['q'];

    $journos = array();
    if( $query )
        $journos = journo_FuzzyFind( $query );

    return $journos;
}


function search_articles($s)
{
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
        article_augment( $art );
    }
    unset( $art );

    return array('articles'=>$results, 'total'=>$total);
}


// cheesy hackery to output an article search as csv
// TODO: better filename handling
function search_articles_output_csv($results)
{
    $filename="jl_search.csv";

    $fields = array("title","permalink","srcorgname","pretty_pubdate","iso_pubdate");

    $fp = fopen('php://output', 'w');
    if($fp) {
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename={$filename}");
        header("Pragma: no-cache");
        header("Expires: 0");

        fputcsv($fp,$fields);
        foreach($results as $art) {
            $row = array();
            foreach($fields as $f) {
                $row[] = $art[$f];
            }
            fputcsv($fp,$row);
        }
        fclose($fp);
    }
}




function tmpl($s, $journos, $articles, $total=69 )  {
    page_header( "Search Results", array('search_params'=>$s) );

?>
<div class="main">

<?php search_emit_onpage_form(); ?>


<?php
if( $s['type'] != 'article' ) {
    /**** show journo results ****/
?>

<div class="search-results">
  <div class="head">
    <h4><?= sizeof($journos) ?> matching journalists</h4>
  </div>

  <div class="body">
<?php if( $journos ) { ?>
    <ul>
<?php   foreach( $journos as $j ) { ?>
      <li><?= journo_link($j); ?></li>
<?php   } ?>
    </ul>
<?php } else { ?>
    <br/>
<?php } ?>
  </div>
  <div class="foot"></div>
</div> <!-- end .search-results -->

<?php } /**** end of journo results ****/ ?>


<?php
if( $s['type'] != 'article' ) {
    /**** show article results ****/
?>
<div class="search-results">
  <div class="head">
    <h4>around <?= $total ?> matching articles</h4>
  </div>

  <div class="body">
<?php if( $articles ) { ?>
    <ul class="art-list hfeed">
<?php
    foreach( $articles as $art ) {
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
<!--
    <div class="pager">
<?php search_emitPageControl( $s, $total ); ?>
    </div>
-->

<?php } else { ?>
    <br/>
<? } ?>

  </div>
  <div class="foot"> </div>

</div> <!-- end .search-results -->

<?php } /**** end of article results ****/ ?>


</div>  <!-- end .main -->
<?php
    page_footer();
}


view();

?>
