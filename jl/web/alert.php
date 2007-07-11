<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';

$who = get_http_var( 'who' );
if( $who )
{
	DoCreateAlertPage();
	// Adding an alert
	$errs = AddAlert();
	if( is_array($errs) )
	{
		page_header( "all fucked up" );
		print "<pre>\n";
		print_r( $errs );
		print "</pre>\n";
		page_footer();
	}
}
else
{
	// default - show users alerts
	DoMyAlertsPage();
}



function DoCreateAlertPage()
{
    /* ensure user is logged in (creates a new user if required) */
	$r = array(
		'reason_web' => 'Before setting up the email alert, we need to confirm your email address.',
		'reason_email' => "You'll then be emailed when the journalist writes",
		'reason_email_subject' => "Subscribe to an email alert at journa-list"
		);
	$P = person_signon($r);

	page_header( 'Email Alerts' );

?>
<p>CREATE AN ALERT! <?=get_http_var('who') ?></p>
<?php

	page_footer();
}




function DoMyAlertsPage()
{
    /* ensure user is logged in (creates a new user if required) */
	$r = array(
		'reason_web' => "To view your alerts, we need to check your email address.",
		'reason_email' => "Then you will be able to view your alerts.",
		'reason_email_subject' => 'View your alerts at Journa-list'
		);
	$P = person_signon($r);

	$name = $P->name_or_blank() ? $P->name() : $P->email();

	page_header("My Alerts");
	print "<p>Hi {$name}, Here are your alerts:</p>\n";
	page_footer();
}


