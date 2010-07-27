<?
require_once '../conf/general';
require_once '../phplib/misc.php';
//require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
//require_once '../../phplib/utility.php';


function CRAPLOG( $msg ) {
/*
    $O = fopen( "/tmp/crap","a" );
    fwrite( $O, $msg );
    fclose( $O );
*/
}

function handle_pingback($method, $params, $extra)
{
    list ($sourceURI, $targetURI) = $params;

    // fetch the source URI to verify that the source does indeed link to the target
    $html = file_get_contents($sourceURI);
    if( $html === FALSE ) {
        CRAPLOG( "0x10\n" );
        return 0x10;      // "The source URI does not exist."
    }

    // cheesy conversion to utf-8
    $html = mb_convert_encoding($html, 'UTF-8',
          mb_detect_encoding($html, 'UTF-8, ISO-8859-1, windows-1252', true));

    $html = html_entity_decode($html, ENT_COMPAT, 'UTF-8' );
    if( strpos( $html, $targetURI ) === FALSE) {
        CRAPLOG( "0x11\n" );
        return 0x011;      // "The source URI does not contain a link to the target URI, and so cannot be used as a source."
    }

    // check URL, try and extract journo ref
    $bits = crack_url( $targetURI );
    $path = $bits['path'];
    $m=array();
    $ref = null;
    if( preg_match( "%([a-zA-Z0-9]+-[-a-zA-Z0-9]+)/?%", $path, $m ) ) {
        $ref = $m[1];
    }

    if( $ref === null ) {
        CRAPLOG( "0x21\n" );
        return 0x0021;  // "The specified target URI cannot be used as a target."
    }

    // valid journo?
    $journo = db_getRow( "SELECT * FROM journo WHERE ref=? AND status='a'", $ref );
    if( $journo === null ) {
        CRAPLOG( "0x21 (invalid journo)\n" );
        return 0x0021;  // "The specified target URI cannot be used as a target."
    }


    // try and extract title to use as description
    $desc = $sourceURI;
    $m=array();
    if( preg_match('!<title>(.*?)</title>!i', $html, $m) ) {
        $desc = $m[1];
        $desc = preg_replace('/\s+/', ' ', $desc );
    }


    // already got this pingback?
    if( db_getOne( "SELECT id FROM journo_weblink WHERE journo_id=? AND url=? AND approved=true", $journo['id'], $sourceURI ) )
    {
        CRAPLOG( "0x30\n" );
        return 0x0030;  // "The pingback has already been registered."
    }

    // OK. time to add it!
    $sql = <<<EOT
INSERT INTO journo_weblink
    (journo_id, url, description, approved, kind, rank)
    VALUES ( ?,?,?,true,'pingback',500)
EOT;

    db_do( $sql, $journo['id'], $sourceURI, $desc );
    db_commit();

    CRAPLOG( "added.\n" );
    return "Ping registered - thanks";
}





$svr = xmlrpc_server_create();
xmlrpc_server_register_method($svr, "pingback.ping", "handle_pingback");

$postdata = file_get_contents("php://input");



//CRAPLOG( sprintf( "%s\n--------\n", $postdata ) );

if( $response = xmlrpc_server_call_method($svr, $postdata, null) ) {
    header("Content-Type: text/xml");
    print( $response );
}


?>
