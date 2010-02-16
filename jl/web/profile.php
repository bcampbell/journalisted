<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );
$journo = NULL;

if( $ref ) {
    $journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
    if( !$journo ) {
        header("HTTP/1.0 404 Not Found");
        exit(1);
    }
}

$login_reasons = array(
    'reason_web' => "Edit your page on Journalisted",
    'reason_email' => "Edit your page on Journalisted",
    'reason_email_subject' => "Edit your page on Journalisted"
    );

$P = person_if_signed_on();

if( is_null($journo) ) {
    // no journo given - if person is logged on, see if they are associated with a journo (or journos)
    if( $P ) {
        $editables = db_getAll( "SELECT j.* FROM ( journo j INNER JOIN person_permission p ON p.journo_id=j.id) WHERE p.person_id=? AND p.permission='edit' AND j.status='a'", $P->id() );

        if( sizeof( $editables) > 1 ) {
            /* let user pick which one... */
            page_header("");

?>
<p>Which one do you want to edit?</p>
<ul>
<?php foreach( $editables as $j ) { ?>
<li><a href="/profile?ref=<?= $j['ref'] ?>"><?= $j['prettyname'] ?></a></li>
<?php } ?>
</ul>
<?php

            page_footer();
            exit;
        }

        if( sizeof( $editables ) == 1 )
            $journo = $editables[0];        // just one journo.
    }
}



// if logged in and have access to the journo, redirect.
if( $journo && $P ) {
    // is this person allowed to edit this journo?
    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo['id'] ) ) {
        header( "Location: /{$journo['ref']}" );

//        showPage( $journo );
        // yes - just redirect to the first profile page
//        header( "Location: /profile_admired?ref={$journo['ref']}" );
            exit();
    }
}

showRegistration( $journo );



function showRegistration( $journo )
{

$title = "Edit profile";
if( $journo )
    $title = "Edit profile for " . $journo['prettyname'];
page_header( $title );

    $contactemail = OPTION_TEAM_EMAIL;

?>
<div class="main">

<?php if( $journo ) { ?>
<h2>Are you <?=$journo['prettyname'];?>? Register to edit your profile!</h2>
<?php } else { ?>
<h2>Are you a journalist?</h2>

<p>Would you like a profile on journa<i>listed</i>?</p>

<?php } ?>

<p>Once registered, you can:</p>
<ul>
  <li>Add articles (published anywhere on the web)</li>
  <li>Add biographical information</li>
  <li>Add contact information</li>
  <li>Add weblinks (e.g. blog, twitter, facebook)</li>
</ul>

<div class="register-now">
<p class="get-in-touch">
To register, just <?= SafeMailto( $contactemail, 'get in touch' );?> and let us know who you are.
</p>
<p>or if you already have an account, <a href="/login">log in now</a></p>
</div>


</div>
<?php

page_footer();
}

?>
