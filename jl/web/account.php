<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';

require_once '../phplib/passwordbox.php';




account_page();




function account_page()
{

    $r = array( 'reason_web' => "Log in",
        'reason_email' => "Log in to Journalisted",
        'reason_email_subject' => 'Log in to Journalisted' );
    $P = person_signon($r);

    $passwordbox = new PasswordBox();

    // linked to a journo for editing (or claim pending)?
    $sql = <<<EOT
SELECT j.*, perm.permission
    FROM journo j INNER JOIN person_permission perm
        ON perm.journo_id=j.id
    WHERE perm.permission in ('edit','claimed') AND perm.person_id=?
    LIMIT 1
EOT;


    $newsletter = db_getOne( "SELECT person_id FROM person_receives_newsletter WHERE person_id=?", $P->id() ) ? TRUE:FALSE;
    $alert_cnt = db_getOne( "SELECT count(*) FROM alert WHERE person_id=?", $P->id() );

    $journo = db_getRow( $sql, $P->id() );

    $name_or_email = $P->name_or_blank() ? $P->name : $P->email;

    if( get_http_var('welcome') ) {
        $title = "Welcome to journa<i>listed</i>, " . ucfirst($journo['firstname']);
    } else {
        $title = "Your account";
    }
    page_header( $title );
?>
<div class="main">
<h2><?= $title ?></h2>

<p>Hello, <?= $name_or_email ?></p>

<ul>
<?php
    if( !is_null( $journo ) ) {
        if( $journo['permission'] == 'edit' ) {
?>
<li>Your profile page can be edited <a href="/<?= $journo['ref'] ?>">here</a></li>
<?php
        } else {
?>
<li>You have applied to edit <?= $journo['prettyname'] ?>'s <a href="/<?= $journo['ref'] ?>">profile page</a><br/>
(To avoid mix ups - deliberate or otherwise - registrations are manually examined before being activated. We will email you when your profile is available for editing)
</li>
<?php
        }
    }
?>
<li>You have <?= $alert_cnt ?> <a href="/alert">email alerts</a> set up</li>
<?php if( $newsletter ) { ?>
<li>You are subscribed to the weekly digest (<a href="/weeklydigest">unsubscribe here</a>)</li>
<?php } else { ?>
<li>You are not subscribed to the weekly digest (<a href="/weeklydigest">subscribe here</a>)</li>
<?php } ?>
</ul>

<p>If you need to change your email address, please <?= SafeMailto( OPTION_TEAM_EMAIL, "let us know") ?></p>
</div>  <!-- end main -->

<div class="sidebar">
<div class="box">
  <div class="head">
    <h3><?= $passwordbox->title() ?></h3>
  </div>
  <div class="body">
  <?php $passwordbox->emit(); ?>
  </div>
  <div class="foot"></div>
</div>
</div> <!-- end sidebar -->
<?php

    page_footer();
}

?>

