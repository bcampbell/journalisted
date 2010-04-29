#!/usr/bin/env php
<?php

/* go through all the journos in the database and set their metaphone values */

chdir(dirname($_SERVER['SCRIPT_FILENAME']));
require_once "../conf/general";
require_once '../../phplib/db.php';

$q=null;
if( sizeof( $argv) > 1 )
{
    $ref = $argv[1];
    if( $ref=='--all' ) {
        print "do ALL journos...\n";
        $q = db_query( "SELECT id,ref,firstname,lastname FROM journo" );
    } else {
        print "do single journo...\n";
        $q = db_query( "SELECT id,ref,firstname,lastname FROM journo WHERE ref=?", $ref );
    }
} else {
    print "look for journos with missing metaphones...\n";
    $q = db_query( "SELECT id,ref,firstname,lastname FROM journo WHERE firstname_metaphone='' OR lastname_metaphone=''" );
}

$cnt = 0;
while( $j = db_fetch_array($q) ) {

    $f = substr( metaphone($j['firstname']), 0, 4);
    $l = substr( metaphone($j['lastname']), 0, 4);

    if(!$f)
        $f='';
    if(!$l)
        $l='';

    print "'{$j['firstname']}', '{$j['lastname']}' ({$j['ref']}): $f,$l\n";

    db_do( "UPDATE journo SET firstname_metaphone=?, lastname_metaphone=? WHERE id=?",
        $f,
        $l,
        $j['id'] );
    $cnt++;
}

db_commit();

print "done. set metaphones on $cnt journos\n";


?>

