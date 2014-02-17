<?php
require_once(__DIR__."/paginator.php");
require_once(__DIR__."/xap.php");
require_once(__DIR__."/article.php");

class ArticleSearch
{
    public $q;
    public $per_page;
    public $page;

    public $sort_order;

    function __construct($reqvars=array()) {
        $this->q = '';
        $this->per_page = 20;
        $this->page = 0;

        if(array_key_exists('j',$reqvars)) {
            $this->q = $reqvars['j'];
        } elseif(array_key_exists('q',$reqvars)) {
            $this->q = $reqvars['q'];
        }
        if(array_key_exists('p',$reqvars)) {
            $this->page = $reqvars['p'];
        }
        if(array_key_exists('by',$reqvars)) {
            $this->q .= " author:" . $reqvars['by'];
        }
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

        return new ArticleSearchResults($this, $results, $total);
    }

//    function description() {
//    }

}

class ArticleSearchResults {
    public $total;  // total number of results
    public $data;   // subset of results
    public $search; // the original Search

    private $pager;

    function __construct($search, $data,$total) {
        $this->search = $search;
        $this->data = $data;
        $this->total = $total;
        $this->pager = null;
    }

    function paginator() {
        if($this->pager === null) {
            $this->pager = new Paginator($this->total, $this->search->per_page, 'p');
        }
        return $this->pager;
    }

    function multi_page() {
        return($this->paginator()->num_pages > 1 );
    }
}

?>

