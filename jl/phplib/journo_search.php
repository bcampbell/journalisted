<?php
require_once(__DIR__."/paginator.php");
require_once(__DIR__."/journo.php");

class JournoSearch
{

    public $q;
    public $page_var;
    public $per_page;
    public $page;

    function __construct($query, $page, $page_var='jp') {
        $this->q = $query;
        $this->page = 0;
        $this->per_page = 20;
        $this->page_var = $page_var;

    }

    function perform() {
        $journos = array();
        if( $this->q ) {
            $journos = journo_FuzzyFind($this->q);
        }
        $total = sizeof($journos);
        $offset = $this->page * $this->per_page;
        $journos = array_slice( $journos, $offset, $this->per_page);

        return new JournoSearchResults($journos, $total, $this->page_var, $this->per_page);
    }

//    function description() {
//    }

}

class JournoSearchResults {
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
            $this->pager = new Paginator($this->total, $this->per_page,  $this->page_var);
        }
        return $this->pager;
    }

    function multi_page() {
        return($this->paginator()->num_pages > 1 );
    }

}

?>
