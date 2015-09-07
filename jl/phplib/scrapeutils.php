<?php
// helper fns for accessing the scraper functionality from php.
// (usually means running external tools and capturing/parsing output)

require_once '../conf/general';

$JLBIN = OPTION_JL_FSROOT . '/bin';



// returns array(return code, text output)
function scrape_ScrapeURL( $url, $expected_ref=null )
{
    global $JLBIN;

	putenv("JL_DEBUG=2");

    // TODO: this is a bit brittle. should check which opts are set
    $db_uri = sprintf("user=%s host=%s port=%s dbname=%s sslmode=disable",
        OPTION_JL_DB_USER,
        OPTION_JL_DB_HOST,
        OPTION_JL_DB_PORT,
        OPTION_JL_DB_NAME);

    putenv("JL_DB_URI={$db_uri}");

	$cmd = $JLBIN . "/jlscrape";
    if(!is_null($expected_ref))
        $cmd .= ' -j ' . $expected_ref;
	$cmd .= ' ' . escapeshellarg( $url );
	$cmd .= ' 2>&1';

	ob_start();
    $ret = -1;
    passthru($cmd, $ret );
	$out = ob_get_contents();
	ob_end_clean();

    return array($ret,$out);
}




// parse articles id markers in scraper output and flesh out the results
function scrape_ParseOutput($out)
{
    preg_match_all('/\[a(?P<artid>\d+).*?\]/', $out, $matches );

    $arts = array();
    foreach($matches[1] as $art_id) {
        $arts[] = array('id'=>$art_id);
    }
    return $arts;
}


?>
