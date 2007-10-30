<?php

/* 
 * Page to show information about why we might be missing articles for
 * a journo.
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */
$ref = strtolower( get_http_var( 'j' ) );
$journo = db_getRow( 'SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?', $ref );


page_header( 'Missing information' );


$heading = "Missing information?";
$earliest = "May 2007";

if( $journo )
{
	$heading = sprintf( "Missing Information for %s?", $journo['prettyname'] );
	$t = db_getOne( "SELECT date_part('epoch', MIN(a.pubdate) ) ".
		"FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) ".
		"WHERE attr.journo_id=?",
		$journo['id'] );
	if( $t )
	{
		$earliest = sprintf( "Our earliest article from %s was on %s", $journo['prettyname'], strftime('%d/%m/%Y',$t) /*pretty_date( $t )*/ );
	}
}


?>
<h2><?=$heading;?></h2>
<p>
An article may not appear on a journalist's page if:
<ul>
 <li>It was not published in one of the <a href="/about#whichoutlets">news outlets</a> we cover</li>
 <li>It was not bylined</li>
 <li>The byline was mis-spelt in the original publication</li>
 <li>The byline was contained within the text of the article so our system could not find it</li>
 <li>It was published before we started collecting articles (<?=$earliest;?>)</li>
 <li>It was published in a 'registration required' area of the news outlet's website</li>
</ul>
</p>
<?php

	$contactemail = "team@" . OPTION_WEB_DOMAIN;
	if( $journo )
		$subject = "Missing information for {$journo['prettyname']}";
	else
		$subject = "Missing information";

?>
<p>
If you know of any missing articles from news outlets we cover please
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'send us the link(s)' );?>!
</p>
<?php


page_footer();

?>
