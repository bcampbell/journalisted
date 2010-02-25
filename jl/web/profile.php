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
    $journo = db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );
    if( !$journo ) {
        header("HTTP/1.0 404 Not Found");
        exit(1);
    }
}


$P = person_if_signed_on();

if( is_null($journo) ) {
    // no journo given - if person is logged on, see if they are associated with a journo (or journos)
    if( $P ) {
        $editables = db_getAll( "SELECT j.* FROM ( journo j INNER JOIN person_permission p ON p.journo_id=j.id) WHERE p.person_id=? AND p.permission='edit'", $P->id() );

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


/* only be specific about _active_ journos. for inactive ones, be vague. */
if( $journo['status'] != 'a' )
    $journo = null;


$action = strtolower( get_http_var( 'action' ) );


if( $action == 'lookup' ) {
    showLookupPage();
} else if( $action == 'claim' ) {
    showClaimPage( $journo );
} else if( $action == 'create' ) {
    showCreatePage();
} else {
    showInfoPage( $journo );
}



/* page to lookup and see if journo already has an entry */
function showLookupPage()
{
    page_header( "lookup" );

    $fullname = get_http_var( 'fullname','' );

?>
<div class="main">
<p>You might already have a page on journa<i>listed</i>. Let's see...</p>
<form method="get" action="/profile">
 <label for="fullname">My name is:</label>
 <input type="text" name="fullname" id="fullname" value="<?= h($fullname) ?>" />
 <input type="hidden" name="action" value="lookup" />
 <input type="submit" value="<?= ($fullname=='')?"Look me up":"Look up again" ?>" />
</form>
<?php

    if( $fullname ) {
        $matching_journos = journo_FuzzyFind( $fullname );
        $uniq=0;

        if( $matching_journos ) {

?>
<form method="get" action="/profile">
<p>Are you one of these people already on journa<i>listed</i>?</p>


<?php foreach( $matching_journos as $j ) { ?>
<input type="radio" id="ref_<?= $uniq ?>" name="ref" value="<?= $j['ref'] ?>" />
<label for="ref_<?= $uniq ?>"><?= $j['prettyname']; ?> (<?= $j['oneliner'] ?>)</label>
<br/>
<?php ++$uniq; } ?>
<br/>
<br/>
<input type="hidden" name="action" value="claim" />
<input type="submit" value="Yes - that's me!" />
</form>
<a href="/profile?action=create&fullname=<?= h( $fullname ) ?>">No... Create a new profile for me</a>
</div>
<?php

        } else {
            /* searched, found no matches */
?>
            <p>Couldn't find you.</p>
            <a href="/profile?action=create&fullname=<?= h( $fullname ) ?>">Create a new profile for me</a>
<?php
        }
    }

    page_footer();
}



/*
    $login_reasons = array(
        'reason_web' => "Edit your page on Journalisted",
        'reason_email' => "Edit your page on Journalisted",
        'reason_email_subject' => "Edit your page on Journalisted"
    );
*/






/* show a page with info on claiming or creating a journo profile */
function showInfoPage( $journo=null )
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
<?php if( $journo ) { ?>
<a href="/profile?action=claim&ref=<?= $journo['ref'] ?>">Claim your profile</a>
<?php } else { ?>
<a href="/profile?action=lookup">Create your profile</a>
<?php } ?>
</p>
</div>


</div>
<?php

page_footer();
}



function showCreatePage()
{
    $person = person_signon(array(
        'reason_web' => "Log in to create a profile",
        'reason_email' => "Log in to Journalisted to create a profile",
        'reason_email_subject' => 'Log in to Journalisted'
    ));

    page_header("");
?>
<div class="main">
<p>TODO: Create a new blank profile here, and associate it with <?= $person->email ?></p>
</div>
<?php
    page_footer();
}


function showClaimPage( $journo )
{
    $person = person_signon(array(
        'reason_web' => "Log in to claim your profile",
        'reason_email' => "Log in to Journalisted to claim your profile",
        'reason_email_subject' => 'Log in to Journalisted'
    ));

    page_header("");
?>
<div class="main">
<p>Claim Page TODO: associate <?= $journo['ref'] ?> with <?= $person->email ?> (unless already taken!)</p>
</div>
<?php
    page_footer();
}



?>
