<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../../phplib/importparams.php';


/* first, make sure we've got a logged-in user.
 * Because we're not currently buffering output, redirects will not work
 * after we start writing stuff.
 * And because the login system relies on redirects, we have to sort out
 * the login _before_ we start writing...
 */


/* display different messages depending on why we're here */
if( get_http_var( 'Add' ) )
{
	// adding an alert...
	$r = array(
		'reason_web' => 'Before setting up the email alert, we need to confirm your email address.',
		'reason_email' => "You'll then be emailed when the journalist writes",
		'reason_email_subject' => "Set up an email alert at journa-list"
		);
}
else if( get_http_var( 'Remove' ) )
{
	// remove an alert...
	$r = array(
		'reason_web' => 'Before removing the email alert, we need to confirm your email address.',
		'reason_email' => "Your email alert will then be removed",
		'reason_email_subject' => "Remove an email alert at journa-list"
		);
}
else
{
	// default - just viewing exiting alerts (or updating password)
	$r = array(
		'reason_web' => "To view your alerts, we need to check your email address.",
		'reason_email' => "Then you will be able to view your alerts.",
		'reason_email_subject' => 'View your alerts at Journa-list'
		);
}

/* if user isn't logged in, person_signon will stash the request in the db,
 * redirect to the login page, and redirect back here with the original
 * request when done.
 */
$P = person_signon($r);


/* OK, if we get here, we've got a logged-in user and can start our output! */ 
page_header( "Your Email Alerts" );

print"<div id=\"mainpane\">\n";

if( get_http_var( 'Add' ) )
{
	// create a new alert
	$journo_ref = get_http_var( 'j' );
	DoAddAlert( $P, $journo_ref );
}
else if( get_http_var( 'Remove' ) )
{
	// remove an alert
	$journo_ref = get_http_var( 'j' );
	DoRemoveAlert( $P, $journo_ref );
}

alert_emit_list( $P->id );
print"</div>\n";

print"<div id=\"sidepane\">\n";
EmitChangePasswordBox();
print"</div>\n";

page_footer();



function DoAddAlert( $P, $journo_ref )
{
	$journo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $journo_ref );
	if( !$journo )
		err( "bad journalist ref" );


	$url = "/{$journo_ref}";

	$journo_id = $journo['id'];
	if( !db_getOne( "SELECT id FROM alert WHERE journo_id=? AND person_id=?", $journo_id, $P->id ) )
	{

		db_query( "INSERT INTO alert (person_id,journo_id) VALUES (?,?)", $P->id, $journo_id );
		db_commit();

		print( "<p>An email alert has been set for <a href=\"{$url}\">{$journo['prettyname']}</a></p>\n" );
	}
	else
	{
		print( "<p>You already have an alert set for <a href=\"{$url}\">{$journo['prettyname']}</a></p>\n" );
	}
}


function DoRemoveAlert( $P, $journo_ref )
{
	$journo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $journo_ref );
	if( !$journo )
		err( "bad journalist ref" );

	$url = "/{$journo_ref}";

	$journo_id = $journo['id'];
	db_query( "DELETE FROM alert WHERE journo_id=? AND person_id=?", $journo_id, $P->id );
	db_commit();
	print( "<p>Removed email alert for <a href=\"{$url}\">{$journo['prettyname']}</a></p>\n" );
}




function EmitChangePasswordBox()
{
    global $q_UpdateDetails, $q_pw1, $q_pw2;

	$P = person_if_signed_on();
	if( !$P )
		return;

    importparams(
    #        array('email',          '/./',          '', null),
            array('pw1',            '/[^\s]+/',     '', null),
            array('pw2',            '/[^\s]+/',     '', null),
            array('UpdateDetails',  '/^.+$/',       '', false)
    );

    $has_password = $P->has_password();

?>
<div class="block">
<h2><?=$has_password ? _('Change password') : _('Set password') ?></h2>
<?php
	if( !$q_UpdateDetails && !$has_password ) {
?>
<p>Setting up a password means you won't have to confirm your
email address every time you want to manage your alerts.</p>
<?php
	}
?>
<form name="setpassword" action="/alert" method="post">
	<input type="hidden" name="UpdateDetails" value="1">
<?php


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

/* output a list of alerts for a user */
function alert_emit_list( $person_id )
{
	print "<h2>Your Email Alerts</h2>\n";

	$q = db_query( "SELECT a.id,a.journo_id, j.prettyname, j.ref " .
		"FROM (alert a INNER JOIN journo j ON j.id=a.journo_id) " .
		"WHERE a.person_id=? ORDER BY j.lastname" , $person_id );

	if( db_num_rows($q) > 0 )
	{
		print "<ul>\n";
		while( $row=db_fetch_array($q) )
		{
			$journopage = "/{$row['ref']}";
			$removeurl = "/alert?Remove=1&j={$row['ref']}";
			printf( "<li><a href=\"%s\">%s</a> <small>[<a href=\"%s\">remove</a>]</small></li>",
				$journopage, $row['prettyname'], $removeurl );
		}
		print "</ul>\n";
	}
	else
	{

?>
<p>
You have no email alerts set up.<br>
Each journalist has a link on their page for setting up an alert.
</p>
<?

	}
}


