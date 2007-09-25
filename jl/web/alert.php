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
		'reason_web' => 'Before adding the journalist to your list, we need to confirm your email address.',
		'reason_email' => "You'll then be emailed when the journalist writes anything",
		'reason_email_subject' => "Set up an email alert at Journa-list"
		);
}
else if( get_http_var( 'Remove' ) )
{
	// remove an alert...
	$r = array(
		'reason_web' => 'Before removing the journalist from your list, we need to confirm your email address.',
		'reason_email' => "Your email alert will then be removed",
		'reason_email_subject' => "Remove an email alert at Journa-list"
		);
}
else
{
	// default - just viewing existing alerts (or updating password)
	$r = array(
		'reason_web' => "To use My Journa-list, we need to check your email address.",
		'reason_email' => "Then you will be able to use My Journa-list.",
		'reason_email_subject' => 'My Journa-list: email confirmation'
		);
}

/* if user isn't logged in, person_signon will stash the request in the db,
 * redirect to the login page, and redirect back here with the original
 * request when done.
 */
$P = person_signon($r);


/* OK, if we get here, we've got a logged-in user and can start our output! */ 
page_header( "My Journa-list" );

print"<div id=\"maincolumn\">\n";

?>
<div class="boxwide">
<h2>My Journa-list</h2>
<p>
Create your own newspaper! Sort of.
</p>
<p>
Tell us who your favourite journalists are and we'll email you whenever they write an article.
</p>
<?php

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
print "<br>\n";
EmitLookupForm();
print"</div>\n";
print"</div>\n";

print"<div id=\"smallcolumn\">\n";
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

		print( "<p><a href=\"{$url}\">{$journo['prettyname']}</a> was added to your list.</p>\n" );
	}
	else
	{
		print( "<p><a href=\"{$url}\">{$journo['prettyname']}</a> is already on your list.</p>\n" );
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
	print( "<p><a href=\"{$url}\">{$journo['prettyname']}</a> was removed from your list.</p>\n" );
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
<div class="boxnarrow">
<h2><?=$has_password ? _('Change password') : _('Set password') ?></h2>
<?php
	if( !$q_UpdateDetails && !$has_password ) {
?>
<p>Setting up a password means you won't have to confirm your
email address every time you want to manage your journalist list.</p>
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
//	print "<h2>Your Email Alerts</h2>\n";

	$q = db_query( "SELECT a.id,a.journo_id, j.prettyname, j.ref " .
		"FROM (alert a INNER JOIN journo j ON j.id=a.journo_id) " .
		"WHERE a.person_id=? ORDER BY j.lastname" , $person_id );

	if( db_num_rows($q) > 0 )
	{
		print "<p>Your list of journalists:</p>\n";
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
You have no journalists on your list.<br>
To add some, use the "My Journa-list" box on a journalists page, or use
the search box below...
</p>
<?

	}
}


// form to quickly lookup journos by name
function EmitLookupForm()
{
	$lookup = get_http_var('lookup')
?>
<form action="/alert" method="get">
Look up journalist by name:
<input type="text" name="lookup" value="<?=$lookup; ?>"/>
<input type="submit" value="Look Up" />
</form>
<?php

	if( $lookup )
	{
		$pat = strtolower( "%{$lookup}%" );
		$q = db_query( "SELECT ref,prettyname FROM journo WHERE LOWER(prettyname) LIKE( ? )", $pat );

		$cnt = 0;
		print "<ul>\n";
		while( $j = db_fetch_array($q) )
		{
			$cnt++;
			$url = '/' . $j['ref'];
			print "<li><a href=\"{$url}\">{$j['prettyname']}</a> ";
			print "<small>[<a href=\"/alert?Add=1&j={$j['ref']}\">add</a>]</small></li>\n";
		}
		print "</ul>\n";
		print "<p>{$cnt} Matches</p>";
	}
	print "<br>\n";
}


