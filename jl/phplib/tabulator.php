<?php

// tabulator class for outputting tables of data, with sortable columns
// etc etc...



// helper class to describe a column for the tabulator
class Column {
    function __construct($name,$opts=array()) {
        $default_opts = array(
            'label'=>null,
            'sortable'=>FALSE,
        );
        $this->name = $name;
        $this->opts = array_merge($default_opts,$opts);
        $this->orderfield = 'o';
        $this->orderdir = 'ot';
    }

    // override this to customise data presentation
    // (has access to whole row of data, so you can combine multiple fields
    // into a single cell)
    function fmt($row) {
        return $row[$this->name];
    }


    function gen_url($ot) {
        assert($ot=='asc' || $ot='desc');
        assert($this->opts['sortable']);
        $params = $_GET;
        $params[$this->orderfield] = $this->name;
        $params[$this->orderdir] = $ot;

        // scrub the page number
        unset($params['p']);

        list($path) = explode("?", $_SERVER["REQUEST_URI"], 2);
        $url = $path . "?" . http_build_query($params);

        return $url;
    }


    function heading() {
        if($this->opts['sortable']) {

            $params = $_GET;
            if(arr_get($this->orderfield,$params)==$this->name) {
                // this is the active column!
                $ot = arr_get($this->orderdir,$_GET,'asc');
                $ot = $ot=='asc' ? 'desc':'asc';  // toggle
                $url = $this->gen_url($ot);
                // &#8595; downwards arrow
                // &#8593; upwards arrow
                $heading = sprintf('<em><a href="%s">%s %s</a></em>',
                    $url,
                    $this->name,
                    $ot=='asc'?'&uarr;':'&darr;');
            } else {
                $url = $this->gen_url('asc');
                $heading = sprintf('<a href="%s">%s</a>', $url, $this->name);
            }
        }
        else {
            $heading = $this->name;
        }

        return $heading;
    }
}



class Tabulator {
    function __construct($columns) {
        $this->columns = $columns;
    }

    function as_table($data) {
        $out = "<tr>";
        foreach($this->columns as $col) {
            $out .= '<th>' . $col->heading() . '</th>';
        }
        $out .= "</tr>\n";

        foreach($data as $row) {
            $out .= "<tr>";
            foreach($this->columns as $col) {
                $out .= '<td>' . $col->fmt($row) . '</td>';
            }
            $out .= "</tr>\n";
        }
        return $out;
    }

    function as_tr($row) {
        $out = "<tr>";
        foreach($this->columns as $col) {
            $out .= $col->as_td($row);
        }
        $out .= "</tr>\n";
        return $out;
    }
};

?>
