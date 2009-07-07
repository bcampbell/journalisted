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


$P = NULL;

/* display different messages depending on why we're here */
if( get_http_var( 'Add' ) )
{
    $journo_ref = get_http_var( 'j' );
    $jname = db_getOne( "SELECT prettyname FROM journo WHERE ref=? AND status='a'", $journo_ref );

    // adding an alert...
    $r = array(
        'reason_web' => "Set up an email alert for {$jname}",
        'reason_email' => "Set up an email alert for {$jname}",
        'reason_email_subject' => "Set up a Journalisted email alert"
        );
    $P = person_signon($r);
    /* will redirect to login.php if person not logged in, then come back here afterward */
}
else if( get_http_var( 'Remove' ) )
{
    $journo_ref = get_http_var( 'j' );
    $jname = db_getOne( "SELECT prettyname FROM journo WHERE ref=? AND status='a'", $journo_ref );

    // remove an alert...
    $r = array(
        'reason_web' => "Remove email alert for {$jname}",
        'reason_email' => "Remove email alert for {$jname}",
        'reason_email_subject' => "Remove a Journalisted email alert"
        );
    $P = person_signon($r);
    /* will redirect to login.php if person not logged in, then come back here afterward */
}
else
{
    // default - just viewing existing alerts (or updating password)
    $r = array(
        'reason_web' => "Manage your email alerts",
        'reason_email' => "Manage your email alerts",
        'reason_email_subject' => 'Journalisted log in: email confirmation'
        );
    $P = person_if_signed_on();
}


/* OK, if we get here, we've got a logged-in user and can start our output! */ 
page_header( "Alerts", array( 'menupage'=>'my') );

?>
<div id="maincolumn">
<div class="box">
<h2>Alerts</h2>
<div class="box-content">
<p>
Follow your favourite journalist(s).<br />
Just enter your email address and you’ll be able to pick
any bylined journalists from the national press or the BBC. Every time s/he writes a new article
an alert will be emailed to you automatically each morning, along with those of other journalists you’ve picked.
</p>
<?php


if( $P ) {
    // the logged-in version:

    if( get_http_var( 'Add' ) ) {
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
    EmitLookupForm();
} else {
    // the non logged-in version:
    loginform_emit();
}

?>
</div>
</div>
</div>  <!-- end maincolumn -->
<div id="smallcolumn">
<?php
if( $P ) {
    EmitChangePasswordBox();
}
emit_popularalertsbox();
?>
</div>  <!-- end smallcolumn -->
<?php

page_footer();



function DoAddAlert( $P, $journo_ref )
{
    $journo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=? AND status='a'", $journo_ref );
    if( !$journo )
        err( "bad journalist ref" );


    $url = "/{$journo_ref}";

    $journo_id = $journo['id'];
    if( !db_getOne( "SELECT id FROM alert WHERE journo_id=? AND person_id=?", $journo_id, $P->id ) )
    {

        db_query( "INSERT INTO alert (person_id,journo_id) VALUES (?,?)", $P->id, $journo_id );
        db_commit();

        print( "<p class=\"infomessage\"><a href=\"{$url}\">{$journo['prettyname']}</a> was added to your list.</p>\n" );
    }
    else
    {
        print( "<p class=\"infomessage\"><a href=\"{$url}\">{$journo['prettyname']}</a> is already on your list.</p>\n" );
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
    print( "<p class=\"infomessage\"><a href=\"{$url}\">{$journo['prettyname']}</a> was removed from your list.</p>\n" );
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
<div class="box">
  <h3><?=$has_password ? _('Change your password') : _('Set a password') ?></h3>
  <div class="box-content">
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
            print '<p class="infomessage">' . ($has_password ? _('Password successfully updated') 
                : _('Password successfully set'))
            . '</p>';
            $has_password = true;

        }
    }
    if (!is_null($error))
        print "<p class=\"errhint\">$error</p>";
    ?>
      <p>
        <label for="pw1">New password:</label>
        <input type="password" name="pw1" id="pw1" size="15" /><br/>
        <label for="pw2">New password, again:</label>
        <input type="password" name="pw2" id="pw2" size="15" />
      </p>
      <input name="submit" type="submit" value="<?=_('Submit') ?>">
    </form>
  </div>
</div>

    <?
}

/* output a list of alerts for a user */
function alert_emit_list( $person_id )
{
//  print "<h2>Your Email Alerts</h2>\n";

    $alerts = db_getAll( "SELECT a.id,a.journo_id, j.prettyname, j.ref, j.oneliner " .
        "FROM (alert a INNER JOIN journo j ON j.id=a.journo_id) " .
        "WHERE a.person_id=? ORDER BY j.lastname" , $person_id );

    if( $alerts ) {

?>
<p>Your alerts:</p>
    <ul>
<?php foreach( $alerts as $j ) { ?>
      <li>
        <a href="<?php echo '/'.$j['ref']; ?>"><?php echo $j['prettyname']; ?></a> (<?php echo $j['oneliner']; ?>)
          <small>[<a href="/alert?Remove=1&j=<?php echo $j['ref']; ?>">remove</a>]</small>
      </li>
<?php } ?>
    </ul>
<?php

    } else {

?>
<p> You have no alerts set up.  </p>
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
        $journos = db_getAll( "SELECT ref,prettyname,oneliner FROM journo WHERE status='a' AND LOWER(prettyname) LIKE( ? )", $pat );
?>
    <p><?php echo sizeof($journos); ?> matches:</p>
    <ul>
<?php foreach( $journos as $j ) { ?>
      <li>
        <a href="<?php echo '/'.$j['ref']; ?>"><?php echo $j['prettyname']; ?></a> (<?php echo $j['oneliner']; ?>)
          <small>[<a href="/alert?Add=1&j=<?php echo $j['ref']; ?>">add</a>]</small>
      </li>
<?php } ?>
    </ul>
<?php
    }
}


function emit_popularalertsbox()
{
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.oneliner, count(j.ref) AS cnt
    FROM (journo j INNER JOIN alert a ON a.journo_id=j.id)
    GROUP BY j.ref,j.prettyname,j.oneliner
    ORDER BY cnt DESC
    LIMIT 10
EOT;

    $popular = db_getAll( $sql );

?>
<div class="box">
  <h3>Popular alerts</h3>
  <div class="box-content">
    <ul>
<?php foreach( $popular as $j ) { ?>
      <li>
        <a href="<?php echo '/'.$j['ref']; ?>"><?php echo $j['prettyname']; ?></a> (<?php echo $j['oneliner']; ?>)
          <small>[<a href="/alert?Add=1&j=<?php echo $j['ref']; ?>">add</a>]</small>
      </li>
<?php } ?>
    </ul>
  </div>
</div>
<?php

}

?>

