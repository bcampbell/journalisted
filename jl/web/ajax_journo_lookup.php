<?php

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/journo.php';

header("Cache-Control: no-cache");

$q = strtolower( get_http_var('q','') );
$q = strtolower( $q );
if( $q ) {
    $matches = journo_FuzzyFind( $q );
    foreach( $matches as $j ) {
        print "{$j['prettyname']}|{$j['oneliner']}|{$j['ref']}\n";
    }
}

?>
