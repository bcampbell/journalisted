<?php
//
// handle non-information resources, redirecting appropriately
// see also .htaccess
//
// eg: for /id/journo/fred-bloggs
// content negotiation will redirect to either:
//  /data/journo/fred-bloggs (for rdf data)
//  /fred-bloggs  (the human-friendly html version of the page)
//

require_once '../conf/general';
require_once '../../phplib/utility.php';
require_once '../phplib/conNeg.inc.php';


$type = get_http_var( 'type' );
if( $type=='journo' ) {
    $ref = get_http_var( 'ref' );

    $best = conNeg::mimeBest( "text/html,application/rdf+xml" );

    if( $best == 'text/html' ) {
        header( "HTTP/1.1 303 See Other" );
        header( "Location: /{$ref}" );
        die();
    }
    if( $best == 'text/plain' ) {
        header( "HTTP/1.1 303 See Other" );
        header( "Location: /{$ref}?fmt=text" );
        die();
    }

    if( $best == 'application/rdf+xml' ) {
        header( "HTTP/1.1 303 See Other" );
        header( "Location: /data/journo/{$ref}" );
        die();
    }

    //if( $best === FALSE ) {
    header('HTTP/1.1 406 Not Acceptable');
    die();
    //}
}


if( $type=='article' ) {
    $id36 = get_http_var( 'id36' );

    /*$best = conNeg::mimeBest( "text/html,application/rdf+xml" );*/
    $best = conNeg::mimeBest( "text/html" );

    if( $best == 'text/html' ) {
        header( "HTTP/1.1 303 See Other" );
        header( "Location: /article/{$id36}" );
        die();
    }
    if( $best == 'application/rdf+xml' ) {
        header( "HTTP/1.1 303 See Other" );
        header( "Location: /data/article/{$id36}" );
        die();
    }

    //if( $best === FALSE ) {
    header('HTTP/1.1 406 Not Acceptable');
    die();
    //}
}


?>
