<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../../phplib/importparams.php';


$r = array(
    'reason_web' => "Subscribe to the journalisted weekly digest",
    'reason_email' => "Subscribe to the journalisted weekly digest",
    'reason_email_subject' => "Subscribe to the journalisted weekly digest"
    );
$P = person_signon($r);


$action = get_http_var('action');

page_header( "Weekly digest" );

$info_msg = null;

if( $action == 'subscribe' ) {
    db_do( "DELETE FROM person_receives_newsletter WHERE person_id=?", $P->id );
    db_do( "INSERT INTO person_receives_newsletter (person_id) VALUES (?)", $P->id );
    db_commit();
    $info_msg = "You have been subscribed to the weekly digest.";
}

if( $action == 'unsubscribe' ) {
    db_do( "DELETE FROM person_receives_newsletter WHERE person_id=?", $P->id );
    db_commit();
    $info_msg = "You have been unsubscribed from the weekly digest.";
}



$subscribed = FALSE;
if( !is_null( db_getOne( "SELECT person_id FROM person_receives_newsletter WHERE person_id=?", $P->id ) ) ) {
    $subscribed = TRUE;
}

?>

<div class="main">

<?php if( $info_msg ) { ?>
<div class="infomessage">
    <?= $info_msg ?>
</div>
<?php }?>

<?php if( $subscribed ) { ?>
<h3>Unsubscribe from the weekly digest</h3>
<p>The journa<i>listed</i> digest is sent out each Tuesday via email.</p>
<form action="/weeklydigest" method="GET">
  <p><strong>You are currently subscribed.</strong></p>
  <input type="hidden" name="action" value="unsubscribe" />
  <button type="submit">Unsubscribe me</button>
</form>
<?php } else { ?>
<h3>Subscribe to the weekly digest</h3>
<p>The journa<i>listed</i> digest is sent out each Tuesday via email.</p>
<form action="/weeklydigest" method="GET">
  <p><em>Would you like to receive it?</em></p>
  <input type="hidden" name="action" value="subscribe" />
  <button type="submit">Yes, I'd like to subscribe</button>
</form>
<?php } ?>

</div> <!-- end main -->

<?php
page_footer();
?>

