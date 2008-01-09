<?php
// scrape.php
// admin page for running scrapers on individual articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


$scraperdir = OPTION_JL_FSROOT . '/scraper';

$url = get_http_var( 'url', '' );
$action = get_http_var( 'action' );
$preview = $action ? get_http_var( 'preview' ) : 'on';
$scraper = get_http_var( 'scraper', 'guardian' );


$scrapers = array( 'bbcnews' => 'bbcnews.py',
	'guardian' => 'guardian.py',
	'independent' => 'independent.py',
	'mirror' => 'mirror.py',
	'sun' => 'sun.py',
	'telegraph' => 'telegraph.py',
	'times' => 'times.py' );

/*
print "<pre>";
print_r( $_POST );
print "</pre>";
*/

?>
<html>
<head></head>
<body>
<h1>scrape single url</h1>

<form method="post">
<label for="url">URL of story to scrape:</label><br/>
<input id="url" type="text" name="url" value="<?=$url;?>" size="120" />
<br />

<input id="preview" type="checkbox" name="preview"<?=$preview?' checked':''?> />
<label for="preview">Preview?
<small>(just displays the scraped data - doesn't affect database)</small>
</label>
<br />


<label for="scraper">Which scraper?</label><br />
<select id="scraper" name="scraper">
<?php
foreach( $scrapers as $k=>$v )
{
	printf( "	<option value=\"%s\" %s>%s</option>\n", $k, $scraper==$k?'selected ' : '', $k );
}
?>
</select>
<br />

<input type="submit" name="action" value="Go" />
</form>
<?php


if( $action == 'Go' && $url != '')
{
	$cmd = $scraperdir . "/" . $scrapers[$scraper];
	if( $preview )
		$cmd = $cmd . ' -d';
	$cmd = $cmd . ' -u "' . $url . '"';
	$cmd = $cmd . ' 2>&1';

	print "<h2>Output</h2>\n";
	print "<small><tt>$cmd</tt></small>\n";
	print "<hr>\n";

	putenv("JL_DEBUG=2");
	print( run( $cmd ) );
	print "<hr>\n";
}


?>
</body>
</html>

<?php

function run($command) {
	ob_start();
	passthru($command);
	$ret = ob_get_contents();
	ob_end_clean();

	$ret = str_replace( "<", "&lt;", $ret );
	$ret = str_replace( ">", "&gt;", $ret );
	return "<p><pre>$ret</pre></p>";
}

