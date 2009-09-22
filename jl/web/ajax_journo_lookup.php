<?php

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

header("Cache-Control: no-cache");

$q = get_http_var('q');
$q = strtolower( $q );

$pat = '%' . $q . '%';
$rows = db_getAll( "SELECT prettyname,ref,oneliner FROM journo WHERE prettyname ILIKE ? ORDER BY firstname, lastname LIMIT 20", $pat );

foreach( $rows as $row ) {
    print $row['prettyname'] . "|" . $row['oneliner'] ."\n";
}

?>
