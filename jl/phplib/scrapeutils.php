<?php
// helper fns for accessing the scraper functionality from php.
// (usually means running external tools and capturing/parsing output)

require_once '../conf/general';

$JLBIN = OPTION_JL_FSROOT . '/bin';


// try and obtain a srcid from an article url (srcid is a unique id derived
// from url which we use to decide if we've already got an article in the db
// or not).
// returns srcid, or NULL if none could be worked out.
//
//
// TODO: KILL KILL KILL!!!!! TODO
//
//
function scrape_CalcSrcID( $url )
{
    global $JLBIN;

	$cmd = $JLBIN . "/scrape-tool";
    $cmd .= ' -s';
	$cmd .= ' -u ' . escapeshellarg( $url );

	ob_start();
    $ret = -1;
    passthru($cmd, $ret );
	$out = ob_get_contents();
	ob_end_clean();

    if($ret == 0 )
        return trim($out);  // got one!
    else
        return NULL;
}


// returns array(return code, text output)
function scrape_ScrapeURL( $url )
{
    global $JLBIN;

	putenv("JL_DEBUG=2");

	$cmd = $JLBIN . "/articlescraper ";
	$cmd .= ' ' . escapeshellarg( $url );
	$cmd = $cmd . ' 2>&1';

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
