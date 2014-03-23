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
    $kind = get_http_var('type',"");
    $q = get_http_var('q');

    if(!$kind) {
        $j = get_http_var('j',"");
        $a = get_http_var('a',"");
        if($j) {
            $q = $j;
            $kind = 'journo';
        }
        if($a) {
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

    $sort_order = get_http_var('o');
    $article_results = null;
    if($q!="" && $kind!='journo') {
        $as = new ArticleSearch($q,$sort_order,$art_page,'p');
        $article_results = $as->perform();
    }

    $journo_results = null;
    if($q!="" && $kind!='article') {
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

    tmpl($q,$kind,$sort_order,$journo_results,$article_results);
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




function tmpl($q,$kind,$sort_order,$journos, $arts)  {
    page_header( "Search Results" );
    include "../templates/search.tpl.php";
    page_footer();
}


view();

?>
