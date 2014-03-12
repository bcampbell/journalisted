<?php
require_once(__DIR__."/paginator.php");
require_once(__DIR__."/xap.php");
require_once(__DIR__."/article.php");

class ArticleSearch
{
    public $q;      // the query string
    public $page;   // current page

    public $per_page;

    public $sort_order;
    public $page_var;

    function __construct($query,$page,$page_var='p') {
        $this->q = $query;
        $this->page = $page;
        $this->per_page = 20;
        $this->page_var = $page_var;

    }

    function perform() {
        $start = $this->page * $this->per_page;

        $results = array();
        try {
    #        $journo_id = $journo ? $journo['id'] : null;
            $journo_id = null;

            $search = new XapSearch();
            $search->set_query( $this->q, $journo_id );
            $results = $search->run( $start, $this->per_page, $this->sort_order );
        } catch (Exception $e) {
            print $e->getMessage() . "\n";
        }

        $total = $search->total_results;

        foreach( $results as &$art ) {
            article_augment( $art );
        }
        unset( $art );

        return new ArticleSearchResults($results, $total, $this->page_var, $this->per_page);
    }

//    function description() {
//    }

}

class ArticleSearchResults {
    public $total;  // total number of results
    public $data;   // subset of results
    public $per_page; // num of results per page
    public $page_var;

    private $pager;

    function __construct($data,$total,$page_var, $per_page) {
        $this->per_page = $per_page;
        $this->page_var = $page_var;
        $this->data = $data;
        $this->total = $total;
        $this->pager = null;
    }

    function paginator() {
        if($this->pager === null) {
            $this->pager = new Paginator($this->total, $this->per_page, $this->page_var);
        }
        return $this->pager;
    }

    function multi_page() {
        return($this->paginator()->num_pages > 1 );
    }
}

?>

