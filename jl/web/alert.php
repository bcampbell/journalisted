<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../../phplib/importparams.php';

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
	print"<div id=\"mainpane\">\n";
	print "<p>Hi {$name}, Here are your alerts:</p>\n";

	print"</div>\n";

	print"<div id=\"sidepane\">\n";
	EmitChangePasswordBox();
	print"</div>\n";
	page_footer();
}




function EmitChangePasswordBox()
{
    global $q_UpdateDetails, $q_pw1, $q_pw2;

	$P = person_if_signed_on();
	if( !$P )
		return;

    $has_password = $P->has_password();

    ?>
    <div class="block">
    <h2><?=$P->has_password() ? _('Change password') : _('Set password') ?></h2>

    <form name="setpassword" action="/alert" method="post">
		<input type="hidden" name="UpdateDetails" value="1">
    <?php

    importparams(
    #        array('email',          '/./',          '', null),
            array('pw1',            '/[^\s]+/',     '', null),
            array('pw2',            '/[^\s]+/',     '', null),
            array('UpdateDetails',  '/^.+$/',       '', false)
    );

    $error = null;
    if ($q_UpdateDetails) {
        if (is_null($q_pw1) || is_null($q_pw2))
            $error = _("Please type your new password twice");
        elseif (strlen($q_pw1)<5 || strlen($q_pw2)<5)
            $error = _('Your password must be at least 5 characters long');
        elseif ($q_pw1 != $q_pw2)
            $error = _("Please type the same password twice");
        else {
            $P->password($q_pw1);
            db_commit();
            print '<p class="success">' . ($has_password ? _('Password successfully updated') 
                : _('Password successfully set'))
            . '</p>';
            $has_password = true;

        }
    }
    if (!is_null($error))
        print "<p id=\"error\">$error</p>";
    ?>
    <p>
    <?=_('New password:') ?> <input type="password" name="pw1" id="pw1" size="15">
    <br><?=_('New password, again:') ?> <input type="password" name="pw2" id="pw2" size="10">
    <input name="submit" type="submit" value="<?=_('Submit') ?>"></p>
    </form>
    </div>

    <?
}



