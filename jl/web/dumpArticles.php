<?php
/* internal API fn to dump out latest articles */



// NOTE: there is a little bordercase where you might miss articles
// under the following circumstances:
// - multiple articles having an _identical_ lastscraped value
// - the LIMIT clause of the SQL clipping results off in the middle of such a group
// Not likely to be a big deal in practice, but if we ever do a bulk import
// we should be a little clever about generating timestamps.
require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

function array_cherrypick( &$srcarray, &$keys )
{
    $out = array();
    foreach( $keys as $k ) {
        $out[$k] = $srcarray[$k];
    }
    return $out;
}

$status = 0;
$results = array();
$details = '';
try {
    /* apache config should handle this... but just in case... */
    /* (TODO: any way to remove the hardcoded IP?) */


    $ip_whitelist = array( '93.93.131.123','82.133.93.217' );

    if( !in_array( $_SERVER['REMOTE_ADDR'], $ip_whitelist ) ) {
        throw new Exception( "local access only" );
    }

    $after = get_http_var('after' );

    if( !$after ) {
        throw new Exception( "required parameter: after" );
    }

    $MAX_LIMIT=1000;
    $limit = intval( get_http_var('limit',1));

    if( $limit<0 || $limit > $MAX_LIMIT ) {
        throw new Exception( "limit out of range (max $MAX_LIMIT)" );
    }

    $after_dt = date_create( $after );
    if( !$after_dt ) {
        throw new Exception( "bad date: after" );
    }

    $fields = array( 'id','srcid','title','content','pubdate','lastscraped' );
    $time_fields = array( 'pubdate','lastscraped' );
    $fieldlist = implode(',',$fields);
    $sql = <<<EOT
        SELECT {$fieldlist}
            FROM article
            WHERE lastscraped>?
            ORDER BY lastscraped
            LIMIT ?;
EOT;
    //$r = db_query( $sql, $after_dt->format(DateTime::ISO8601), $limit );
    $r = db_query( $sql, $after_dt->format('Y-m-d\TH:i:s.uO'), $limit );
    while( $row = db_fetch_array( $r ) ) {
        $out = array_cherrypick( $row, $fields );
        // sanitize the timestamps
        foreach( $time_fields as $tf ) {
            $dt = new DateTime( $out[$tf] );
            $out[$tf] = $dt->format('Y-m-d\TH:i:s.uO');
        }
        $results[] = $out;
    }
} catch (Exception $e) {
    $details = $e->getMessage();
    $status = -1;
}


header('Content-type: application/json');
print json_encode( array( 'status'=>$status, 'details'=>$details,'results'=>$results ) );
?>
