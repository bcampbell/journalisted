<?php
require_once(__DIR__."/paginator.php");
require_once(__DIR__."/journo.php");

class JournoSearch
{
    public $q;
    public $per_page;
    public $page;

    function __construct($reqvars=array()) {
        $this->q = '';
        $this->per_page = 20;
        $this->page = 0;

        if(array_key_exists('j',$reqvars)) {
            $this->q = $reqvars['j'];
        } elseif(array_key_exists('q',$reqvars)) {
            $this->q = $reqvars['q'];
        }
        if(array_key_exists('jp',$reqvars)) {
            $this->page = $reqvars['jp'];
        }
    }

    function perform() {
        $journos = array();
        if( $this->q )
            $journos = journo_FuzzyFind($this->q);
        $offset = $this->page * $this->per_page;
        return new JournoSearchResults($this, array_slice( $journos, $offset, $this->per_page), sizeof($journos));
    }

//    function description() {
//    }

}

class JournoSearchResults {
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
            $this->pager = new Paginator($this->total, $this->search->per_page, 'jp');
        }
        return $this->pager;
    }

    function multi_page() {
        return($this->paginator()->num_pages > 1 );
    }

}

?>
