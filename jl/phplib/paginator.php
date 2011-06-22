<?php

// Paginator - class to implement nice paging widget.
// Generates page links based on the current GET request,
// modified with a page parameter (specified via $page_var).

class Paginator
{
    /* page indexes begin at 0 (but are displayed to user as pagenum+1)
     *
     * per_page: number of items to display on each page
     * total: total number of items
     * page_var: the http GET variable which holds page number
     */

    function __construct($total, $per_page, $page_var="p") {
        $this->page_var = $page_var;
        $this->total = $total;
        $this->per_page = $per_page;
        $this->num_pages = intval(($total+$per_page-1)/$per_page);

        /* remember current page might not be valid :-) */
        $page = intval(arr_get($this->page_var, $_GET));
/*        if($page < 0)
            $page = 0;
        if($page > $this->num_pages-1)
            $page = $this->num_pages-1;*/
        $this->page = $page;
    }

    // generate appropriate html for a given page link
    function link($pagenum) {
        if($pagenum == $this->page) {
            return sprintf('<span class="this-page">%d</span>', $pagenum+1);
        }
        $params = array_merge($_GET, array($this->page_var=>$pagenum));
        list($path) = explode("?", $_SERVER["REQUEST_URI"], 2);
        $url = $path . "?" . http_build_query($params);
        // TODO: rel="next/prev" etc...
        return sprintf( '<a href="%s">%d</a>', $url, $pagenum+1);
    }

    // return html for the whole widget
    // (doesn't include a container - that's left up to the caller)
    function render() {
        $endmargin = 2;
        $midmargin = 3;

        if( $this->num_pages<2 )
            return '';

        $current = $this->page;
        $sections = array();

        // each section is range: [startpage,endpage)
        $sections[] = array(0,$endmargin);
        $sections[] = array($current - $midmargin, $current + $midmargin + 1);
        $sections[] = array($this->num_pages-$endmargin, $this->num_pages);
        // clip sections
        foreach($sections as &$s) {
            if($s[0] < 0)
                $s[0] = 0;
            if($s[1] > $this->num_pages)
                $s[1] = $this->num_pages;
        }
        unset($s);

        // coallese adjoining/overlapping sections
        if($sections[1][1] >= $sections[2][0]) {
            $sections[1][1] = $sections[2][1];
            unset($sections[2]);
        }
        if($sections[0][1] >= $sections[1][0]) {
            $sections[0][1] = $sections[1][1];
            unset($sections[1]);
        }

        $parts = array();
        foreach($sections as $s) {
            $pagelinks=array();
            for($n=$s[0]; $n<$s[1]; ++$n) {
                $pagelinks[] = $this->link($n);
            }
            $parts[] = implode(' ', $pagelinks);
        }

        return implode(' ... ',$parts);
    }
};


?>
