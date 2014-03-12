<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
//require_once '../phplib/misc.php';
//require_once '../phplib/journo.php';
//require_once '../phplib/article.php';
//require_once '../phplib/xap.php';
//require_once '../phplib/search.php';
require_once '../phplib/journo_search.php';
require_once '../phplib/article_search.php';
require_once '../../phplib/db.php';








function view() {
    // parse the search params
    $kind = get_http_var('type',null);
    $q = get_http_var('q');

    if(is_null($kind)) {
        $j = get_http_var('j',null);
        $a = get_http_var('a',null);
        if(!is_null($j)) {
            $q = $j;
            $kind = 'journo';
        }
        if(!is_null($a)) {
            $q = $a;
            $kind = 'article';
        }
    }

    $art_page = get_http_var('p',0);
    $journo_page = get_http_var('jp',0);

    // special 'by' param for article searches
    $by = get_http_var('by',"");
    if($by && $kind !='journo' ) {
        $q .= " author:" . $by;
    }

    $article_results = null;
    if($kind!='journo') {
        $as = new ArticleSearch($q,$art_page,'p');
        $article_results = $as->perform();
    }

    $journo_results = null;
    if($kind!='article') {
        $js = new JournoSearch($q,$journo_page,'jp');
        $journo_results = $js->perform();
    }

    // hackhackhack
/*
      if( $s['fmt'] == 'csv' ) {
        search_articles_output_csv($article_results->data);
        return;
      }
*/

    tmpl($q,$journo_results,$article_results);
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




function tmpl($q, $journo_results, $article_results )  {
    page_header( "Search Results" );

?>
<div class="main">


  <div class="search">
    <form action="/search" method="get">
      <input type="text" value="<?= h($q) ?>" id="q2" name="q" />
      <input type="submit" alt="search" value="Search" />
    </form>
  </div>


<?php
if( $journo_results !== null ) {
    /**** show journo results ****/
?>

<div class="search-results">
  <div class="head">
    <h4><?= $journo_results->total ?> matching journalists</h4>
  </div>

  <div class="body">
    <ul>
<?php   foreach( $journo_results->data as $j ) { ?>
      <li><?= journo_link($j); ?></li>
<?php   } ?>
    </ul>

    <? if ($journo_results->multi_page()) { ?>
    <div class="paginator">page <?= $journo_results->paginator()->render(); ?></div>
    <? } ?>
  </div>
  <div class="foot">
  </div>
</div> <!-- end .search-results -->

<?php } /**** end of journo results ****/ ?>


<?php
if( $article_results !== null ) {
    /**** show article results ****/
?>
<div class="search-results">
  <div class="head">
    <h4>around <?= $article_results->total ?> matching articles</h4>
  </div>

  <div class="body">
    <ul class="art-list hfeed">
<?php
    foreach( $article_results->data as $art ) {
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
    <? if ($article_results->multi_page()) { ?>
    <div class="paginator">page <?= $article_results->paginator()->render(); ?></div>
    <? } ?>
  </div>
  <div class="foot">
  </div>

</div> <!-- end .search-results -->

<?php } /**** end of article results ****/ ?>


</div>  <!-- end .main -->
<?php
    page_footer();
}


view();

?>
