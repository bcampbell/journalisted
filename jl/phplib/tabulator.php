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
    }

    // override this to customise data presentation
    // (has access to whole row of data, so you can combine multiple fields
    // into a single cell)
    function fmt($row) {
        return $row[$this->name];
    }


    function heading() {
        if($this->opts['sortable']) {
            $heading = sprintf('<a href="%s">%s</a>',
                "#", $this->name );
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
