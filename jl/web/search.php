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
<?php if( $query ) { ?>
  <div class="head">
    <b>Search Results:</b> <span class="count"><?= sizeof($journos) ?> journalists</span> like <span class="query"><?= h($query) ?></span>
  </div>

  <div class="body">
<?php if( $journos ) { ?>
<ul>
<?php   foreach( $journos as $j ) { ?>
  <li><?= journo_link($j); ?></li>
<?php   } ?>
</ul>
<?php } ?>
  </div>

<?php } else { /* blank query */?>
<div class="head"></div>
<div class="body"></div>
<?php } ?>

<div class="foot">
<?php search_emit_onpage_form(); ?>
</div>
</div>  <!-- end main -->
<?php
    page_footer();
}


function search_articles()
{
    $s = search_getParams();
    $query = $s['q'];
    if( !$query ) {
        page_header( "Search Articles", array('search_params'=>$s) );
?>
<div class="main search-results">
 <div class="head"></div>
 <div class="body"></div>
 <div class="foot">
<?php search_emit_onpage_form(); ?>
 </div>
</div> <!-- end main -->
<?php
        page_footer();
        return;
    }


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


    page_header( "Search Articles", array('search_params'=>$s) );



?>
<div class="main search-results">

  <div class="head">
<?php if( $results || $start>0 ) { ?>
    <b>Search Results:</b> <?= $first ?>-<?= $last ?> of around <span class="count"><?= $total ?></span> articles matching <span class="query"><?= h($query) ?></span>
<?php } else { ?>
    <b>Search Results:</b> no articles matching <span class="query"><?= h($query) ?></span>

<?php } ?>
  </div>
  <div class="body">
<?php if( $results || $start>0 ) { ?>
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
  <div class="pager">
<?php
        search_emitPageControl( $s, $total );
?>
<?php } ?>
  </div>

  </div>
  <div class="foot">
<?php search_emit_onpage_form(); ?>
  </div>
</div> <!-- end main -->
<?php

    page_footer();
}



?>
