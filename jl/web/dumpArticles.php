<?php
/* internal API fn to dump out latest articles */

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
    if( 1 != preg_match( '/127.\d{1,3}.\d{1,3}.\d{1,3}/', $_SERVER['REMOTE_ADDR'] )
        && $_SERVER['REMOTE_ADDR']!= '93.93.131.123' )
    {
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
            WHERE lastscraped>=?
            ORDER BY lastscraped
            LIMIT ?;
EOT;
    $r = db_query( $sql, $after_dt->format(DateTime::ISO8601), $limit );
    while( $row = db_fetch_array( $r ) ) {
        $out = array_cherrypick( $row, $fields );
        // sanitize the timestamps
        foreach( $time_fields as $tf ) {
            $dt = new DateTime( $out[$tf] );
            $out[$tf] = $dt->format(DateTime::ISO8601);
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
