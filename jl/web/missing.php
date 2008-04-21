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
$url = get_http_var( 'url', '' );

$journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=? AND status='a'", $ref );


page_header( 'Missing information' );

$submitting = get_http_var( 'submit' );

if( !$submitting )
{
	/* default */
	EmitBlurb( $journo );
	EmitMissingArticleForm( $journo, $url );
}
else
{
	$errs = Validate( $journo, $url );
	if( $errs )
	{
		EmitMissingArticleForm( $journo, $url, $errs );
	}
	else
	{
		Process( $journo, $url );
		EmitBlurb( $journo );
		$url = '';
		EmitMissingArticleForm( $journo, $url );
	}

#	$errors = Process( $journo, $url );
#	print "<pre>\n";
#	print_r( $_POST );
#	print "</pre>\n";
}



page_footer();




function EmitBlurb( $journo )
{

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
/*
		$contactemail = OPTION_TEAM_EMAIL;
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
*/
}



function EmitMissingArticleForm( $journo, $url='', $errs=array() )
{
	$heading = "Submit a missing article";
	$exampleurl = null;
	if( $journo )
	{
		$heading = "Submit a missing article for {$journo['prettyname']}";
		$exampleurl = db_getOne( "SELECT a.permalink " .
			"FROM ( article a INNER JOIN journo_attr attr ON a.id=attr.article_id ) " .
			"WHERE attr.journo_id=?",
			$journo['id'] );
	}

	if( !$exampleurl )
		$exampleurl = "http://randomnewspaper.com/news/article12345";

	$errhtml='';
	if( $errs )
	{
		$errhtml = "<div id=\"errors\">\n<ul>\n";
		foreach( $errs as $e )
		{
			$errhtml .= "<li>$e</li>\n";
		}
		$errhtml .= "</ul>\n</div>\n";
	}


?>
<form method="post" action="/missing">
<h3><?php echo $heading; ?></h3>
<?php echo $errhtml; ?>
<input type="hidden" name="j" value="<?php echo $journo['ref']; ?>" />

<label for="url">If you know of an article we've missed, please enter it's URL here:<br />
<small>(eg "<?php echo $exampleurl; ?>")</small></label>
<br />
<input type="text" name="url" id="url" size="80" value="<?php echo $url; ?>" />
<br />
<input type="submit" name="submit" value="Submit" />
</form>
<?php

}

function Validate( $journo, $url )
{
	$errs = array();
	if( !$url )
		$errs[] = "You need to supply a URL!";

	/* TODO: if no journo, and article isn't covered by our scrapers... that's an error */

	return $errs;
}


function Process( $journo, $url )
{
	$msg = '';
	if( $journo )
		$subject = sprintf( "[missing article for %s]", $journo['prettyname'] );
	else
		$subject = '[missing article]';

	$to_email = OPTION_TEAM_EMAIL;
	$from_name = "Journalisted";
	$from_email = OPTION_TEAM_EMAIL;


	if( $journo )
	{
		/* provide a nice easy link to journos page */

		$journo_url = OPTION_BASE_URL . "/" . $journo['ref'];
		$journo_admin_url = OPTION_BASE_URL . "/adm/journo?journo_id=" . $journo['id'];

		$msg .= sprintf( "Missing article URL submitted for %s:\n", $journo['prettyname'] );
		$msg .= "$url\n\n";

		$msg .= $journo['prettyname'] . "'s pages:\n";
		$msg .= "$journo_url\n";
		$msg .= "$journo_admin_url\n";
		$msg .= "\n";
	}
	else
	{
		$msg .= "Missing article URL submitted:\n$url\n";
	}


	/* SEND IT! */
	if( jl_send_text_email( $to_email, $from_name, $from_email, $subject, $msg ) )
	{

?>
<div id="forminfo">
<p>The Journalisted team has been notified of the missing article.</p>
<p>Thanks for letting us know!</p>
</div>
<?php

	}
	else
	{

?>
<div id="errors">
<p>Uh-oh... there was an unknown problem when notifying
The Journalisted team...</p>
</div>
<?php

	}
}

?>

