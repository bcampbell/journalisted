<?php
// scrape.php
// admin page for running scrapers on individual articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
//require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';


$scraperdir = OPTION_JL_FSROOT . '/scraper';


admPageHeader();

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
<h2>scrape single url</h2>

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
	/* Do it! */
	$cmd = $scraperdir . "/" . $scrapers[$scraper];
	if( $preview )
		$cmd = $cmd . ' -d';
	$cmd = $cmd . ' -u "' . $url . '"';
	$cmd = $cmd . ' 2>&1';

	print "<h3>Output</h3>\n";
	print "<small><tt>$cmd</tt></small>\n";
	print "<hr>\n";

	putenv("JL_DEBUG=2");
	print( run( $cmd ) );
	print "<hr>\n";
}

admPageFooter();



/**********************************/



function run($command) {
	ob_start();
	passthru($command);
	$ret = ob_get_contents();
	ob_end_clean();

	$ret = str_replace( "<", "&lt;", $ret );
	$ret = str_replace( ">", "&gt;", $ret );
	$ret = MarkupLog( $ret );
	return "<p><pre>$ret</pre></p>";
}


function MarkupLog( $txt )
{
#	artpat = re.compile( "\\[a([0-9]+)(\\s*'(.*?)')?\\s*\\]" )
#	journopat = re.compile( "\\[j([0-9]+)(\\s*'(.*?)')?\\s*\\]" )

	$html = preg_replace( "/\\[a([0-9]+)(\\s*'(.*?)')?\\s*\\]/", "<a href=\"/adm/article?article_id=\\1\">\\0</a>", $txt );

	return $html;
}

