<?php
/* internal API fn to dump out latest articles */



// NOTE: there is a little bordercase where you might miss articles
// under the following circumstances:
// - multiple articles having an _identical_ lastscraped value
// - the LIMIT clause of the SQL clipping results off in the middle of such a group
// Not likely to be a big deal in practice, but if we ever do a bulk import
// we should be a little clever about generating timestamps.
require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/article.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

$status = 0;
$results = array();
$details = '';
try {
    /* apache config should handle this... but just in case... */
    /* (TODO: any way to remove the hardcoded IP?) */


    $ip_whitelist = array( '127.0.1.1','93.93.131.123','82.133.93.217','72.14.194.33','64.233.172.18','79.77.49.90','93.93.131.253' );

    if( !in_array( $_SERVER['REMOTE_ADDR'], $ip_whitelist ) ) {
        throw new Exception( "local access only" );
    }

    $after = get_http_var('after' );
    $before = get_http_var('before' );


    $MAX_LIMIT=1000;
    $limit = intval( get_http_var('limit',1));

    if( $limit<0 || $limit > $MAX_LIMIT ) {
        throw new Exception( "limit out of range (max $MAX_LIMIT)" );
    }

    $r=null;
    if( !$after && !$before ) {
        throw new Exception( "required parameter: after or before" );
    } elseif( $after && $before ) {
        throw new Exception( "bad parameter: after and before are mutually exclusive" );
    } elseif( $after ) {
        $after_dt = date_create( $after );
        if( !$after_dt ) {
            throw new Exception( "bad date: after" );
        }

        $sql = <<<EOT
            SELECT a.id, a.srcid, a.title, c.content, a.pubdate, a.lastscraped, a.permalink,
                    a.srcorg, o.shortname, o.prettyname, o.home_url
                FROM ((article a INNER JOIN article_content c ON c.article_id=a.id) INNER JOIN organisation o ON o.id=a.srcorg)
                WHERE a.lastscraped>?
                    AND a.srcorg IN (SELECT pub_id FROM (pub_set_map m INNER JOIN pub_set s ON s.id=m.pub_set_id) WHERE name='national_uk')
                ORDER BY a.lastscraped
                LIMIT ?;
EOT;
        $r = db_query( $sql, $after_dt->format('Y-m-d\TH:i:s.uO'), $limit );
    } elseif( $before ) {
        $before_dt = date_create( $before );
        if( !$before_dt ) {
            throw new Exception( "bad date: before" );
        }
        $sql = <<<EOT
            SELECT a.id, a.srcid, a.title, c.content, a.pubdate, a.lastscraped, a.permalink,
                    a.srcorg, o.shortname, o.prettyname, o.home_url
                FROM ((article a INNER JOIN article_content c ON c.article_id=a.id) INNER JOIN organisation o ON o.id=a.srcorg)
                WHERE a.lastscraped<?
                    AND a.srcorg IN (SELECT pub_id FROM (pub_set_map m INNER JOIN pub_set s ON s.id=m.pub_set_id) WHERE name='national_uk')
                ORDER BY a.lastscraped DESC
                LIMIT ?;
EOT;
        $r = db_query( $sql, $before_dt->format('Y-m-d\TH:i:s.uO'), $limit );
    }

    $fields = array( 'id','srcid','title','content','pubdate','lastscraped','permalink' );
    $time_fields = array( 'pubdate','lastscraped' );
    while( $row = db_fetch_array( $r ) ) {
        $out = array_cherrypick( $row, $fields );

        // add source publication info
        $source = array( 'id'=>$row['srcorg'],
            'shortname'=>$row['shortname'],
            'prettyname'=>$row['prettyname'],
            'home_url'=>$row['home_url'] );
        $out['source'] = $source;

        // sanitize the timestamps
        foreach( $time_fields as $tf ) {
            $dt = new DateTime( $out[$tf] );
            $out[$tf] = $dt->format('Y-m-d\TH:i:s.uO');
        }
        $results[] = $out;
    }

    // go through and add in id36 and journo data to articles
    foreach( $results as &$art ) {
        $art['id36'] = article_id_to_id36( $art['id'] );

        $sql = <<<EOT
SELECT j.prettyname, j.ref
    FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
    WHERE attr.article_id=? AND j.status='a';
EOT;
        $journos = array();
        foreach( db_getAll( $sql, $art['id'] ) as $j ) {
            $journos[] = array( 'ref'=>$j['ref'], 'prettyname'=>$j['prettyname'] );
        }
        $art['journos'] = $journos;
    }

} catch (Exception $e) {
    $details = $e->getMessage();
    $status = -1;
}


header('Content-type: application/json');
print json_encode( array( 'status'=>$status, 'details'=>$details,'results'=>$results ) );
?>
