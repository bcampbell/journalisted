<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

header("Cache-Control: no-cache");

$q = get_http_var('q');
$q = strtolower( $q );
$q = str_replace( ' ', '-', $q );

$pat = '%' . $q . '%';
$rows = db_getAll( "SELECT ref FROM journo WHERE ref like ? LIMIT 20", $pat );


foreach( $rows as $row ) {
    print $row['ref'] . "\n";
}

?>
