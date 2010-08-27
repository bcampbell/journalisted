<?php
// helper fns for accessing the scraper functionality from php.
// (usually means running external tools and capturing/parsing output)

require_once '../conf/general';

$JLBIN = OPTION_JL_FSROOT . '/bin';


// try and obtain a srcid from an article url (srcid is a unique id derived
// from url which we use to decide if we've already got an article in the db
// or not).
// returns srcid, or NULL if none could be worked out.
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


// returns text output, or NULL if error
function scrape_ScrapeURL( $url )
{
    global $JLBIN;

	putenv("JL_DEBUG=2");

	$cmd = $JLBIN . "/scrape-tool";
	$cmd .= ' -u ' . escapeshellarg( $url );
	$cmd = $cmd . ' 2>&1';

	ob_start();
    $ret = -1;
    passthru($cmd, $ret );
	$out = ob_get_contents();
	ob_end_clean();

    if($ret == 0 )
        return $out;
    else
        return NULL;
}



//
// returns array with results:
// status - string, one of:
//    'fail'  Failed to scrape
//    'new' a new article was added to DB
//    'already_had' article was already in db
//
// article - array with keys:
//    id - article id
//    journos - array of attributed journos
//
function scrape_ScrapeArticle( $url )
{
    global $JLBIN;

	putenv("JL_DEBUG=2");

	$cmd = $JLBIN . "/scrape-tool";
	$cmd .= ' -u ' . escapeshellarg( $url );
	$cmd = $cmd . ' 2>/dev/null';

	ob_start();
    $ret = -1;
    passthru($cmd, $ret );
	$out = ob_get_contents();
	ob_end_clean();

    $result = array();
    $result['status'] = 'fail';

    if($ret != 0 ) {
        return $result;
    }


    preg_match('/: (?P<newcnt>\d+) new, (?P<failcnt>\d+) failed\s*$/', $out, $matches );
    $newcnt = $matches['newcnt'];
    $failcnt = $matches['failcnt'];

    if( $failcnt > 0 ) {
        return $result;
    }

    preg_match('/\[a(?P<artid>\d+).*?\]/', $out, $matches );

    $art = array();

    $art['id'] = $matches['artid'];
    $art['journos'] = db_getAll( "SELECT j.* FROM journo j INNER JOIN journo_attr attr ON attr.journo_id=j.id WHERE attr.article_id=?", $art['id'] );

    $result['article'] = $art;
    if( $newcnt > 0 ) {
        $result['status'] = 'new';
    } else {
        $result['status'] = 'already_had';
    }

    return $result;

}


?>
