<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../phplib/passwordbox.php';
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
    if( $journo ) {
        showClaimPage( $journo );
    } else {
        showLookupPage();
    }
} else if( $action == 'create' ) {
    showCreatePage();
} else {
    showInfoPage( $journo );
}



/* page to lookup and see if journo already has an entry */
function showLookupPage()
{

    $fullname = get_http_var( 'fullname','' );
    $matching_journos = array();
    if( $fullname ) {
        $matching_journos = journo_FuzzyFind( $fullname );
    }

    if( sizeof($matching_journos)>0) {
        page_header( "lookup" );
        {
            include "../templates/profile_lookup.tpl.php";
        }
        page_footer();
    } else {
        // no matches - just go ahead and create it
        showCreatePage();
    }
}





/* show a page with info on claiming or creating a journo profile */
function showInfoPage( $journo=null )
{
    $P =  person_if_signed_on();

    $title = "Edit profile";
    if( $journo )
        $title = "Edit profile for " . $journo['prettyname'];

    page_header( $title );

    $contactemail = OPTION_TEAM_EMAIL;

?>
<div class="main">
<div class="head"></div>
<div class="body">

<?php if( $journo ) { ?>
<h2>Are you <?=$journo['prettyname'];?>?</h2>
<?php } else { ?>
<h2>Are you a journalist?</h2>
<?php } ?>

<p>Would you like to edit your journa<i>listed</i> profile?</p>
<p>It's easy and <em>free</em>.</p>
<p>You'll be able to:</p>
<ul>
  <li>Add articles (published anywhere on the web)</li>
  <li>Add biographical information</li>
  <li>Add contact information</li>
  <li>Add weblinks (e.g. blog, twitter, facebook)</li>
</ul>

<div class="register-now">
<p class="get-in-touch">
<?php if( $journo ) { ?>
<a href="/profile?action=claim&ref=<?= $journo['ref'] ?>">Edit your profile</a>
<?php } else { ?>
<a href="/profile?action=lookup">Edit your profile</a>
<?php } ?>
</p>
</div>


<?php
    if( !is_null($P) ) {
        if( db_getOne( "SELECT person_id FROM person_permission WHERE person_id=? AND permission='claimed'", $P->id() ) ) {
?>
  <strong>NOTE: Your registration request is pending</strong>
<?php
        }
    }
?>


</div>
<div class="foot"></div>
</div>
<?php

page_footer();
}



function showCreatePage()
{
    // we need them logged on first
    $P = person_register(array(
        'reason_web' => "Register to create a profile",
        'reason_email' => "Register on Journalisted to create a profile",
        'reason_email_subject' => 'Register on Journalisted'
    ));

    $fullname = get_http_var( 'fullname' );
    if( !$fullname )
    {
        showLookupPage();
        return;
    }
    $journo = journo_create( $fullname );

    // link user to journo
    db_do( "INSERT INTO person_permission ( person_id,journo_id,permission) VALUES(?,?,?)",
        $P->id,
        $journo['id'],
        'edit' );
    db_commit();

    // set persons name if blank
    if( $P->name_or_blank() == '' ) {
        $P->name( $journo['prettyname'] );  // (does a commit)
    }

    // just redirect to /account page.
    header("Location: /account?welcome=1");
    exit();
}


function showClaimPage( $journo )
{
    // we need them logged on first
    $P = person_register(array(
        'reason_web' => "Register to claim your profile",
        'reason_email' => "Register on Journalisted to claim your profile",
        'reason_email_subject' => 'Register on Journalisted'
    ));

    // make them click a meaningless tickbox to make sure they _are_ who they say they are...
    $flag = get_http_var( "iamwhoisay" );
    if( !$flag ) {
        page_header("");
?>
<div class="main">
<form method="get" action="/profile">
 <input type="checkbox" name="iamwhoisay" id="iamwhoisay" value="yes" />
 <label for="iamwhoisay">I confirm that I <strong>am</strong> the <?= $journo['prettyname'] ?> listed <a href="/<?= $journo['ref'] ?>">here</a> on journa<i>listed</i></label><br/>
 <input type="hidden" name="action" value="claim" />
 <input type="hidden" name="ref" value="<?= $journo['ref'] ?>" />
<p>To complete your registration, please agree to the above statement by ticking the box</p>
 <input type="submit" value="Submit" />
</form>
</div>  <!-- end main -->
<?php
        page_footer();
        return;
    }

    // has anyone already claimed this journo?
    $foo = db_getAll( "SELECT journo_id FROM person_permission WHERE journo_id=? AND permission='edit'", $journo['id'] );
    if( $foo ) {
        // uhoh...
        page_header("");
?>
<div class="main">
<p>Sorry - someone has already claimed to be <?= $journo['prettyname'] ?>...</p>
<p>If you are the <em>real</em> <?= $journo['prettyname'] ?>, please <?= SafeMailto( OPTION_TEAM_EMAIL, 'let us know' );?></p>
</div>
<?php
        page_footer();
        return;
    }

    // OK - _claim_ the profile!
    db_do( "DELETE FROM person_permission WHERE person_id=? AND journo_id=? AND permission='claimed'",
        $P->id,
        $journo['id'] );

    db_do( "INSERT INTO person_permission ( person_id,journo_id,permission) VALUES(?,?,?)",
        $P->id,
        $journo['id'],
        'claimed' );

    db_commit();

    // set persons name if blank
//    if( $P->name_or_blank() == '' ) {
//        $P->name( $journo['prettyname'] );  // (does a commit)
//    }

    if( !$P->has_password() ) {
        // we'll use an password form which submits to /account instead of here.
        $passwordbox = new PasswordBox( '/account' );
    }

    page_header("");
?>
<div class="main">
<h3>Welcome to journa<i>listed</i>, <?= $journo['prettyname'] ?></h3>

<p>To avoid mix ups - deliberate or otherwise - registrations are manually examined before being activated.</p>
<p>We will email you when your profile is available for editing.</p>
<p>Thanks,<br/>
- the journa<i>listed</i> team</p>

<?php if( !$P->has_password() ) { ?>
<h3><?= $passwordbox->title() ?></h3>
<?php $passwordbox->emit(); ?>
<?php } ?>
</div>
<?php
    page_footer();
}


function toSlug( $s )
{
    $s = trim( $s );
    $s = preg_replace( '/\s+/', ' ', $s );  // collapse spaces
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s );  
    $s = preg_replace("/[^a-zA-Z0-9 -]/", "", $s );  
    $s = strtolower($s);
    $s = str_replace(" ", '-', $s );
    return $s;
}




// create a new, blank journo entry
function journo_create( $fullname )
{

    $fullname = trim( $fullname );
    $fullname = preg_replace( '/\s+/', ' ', $fullname );  // collapse spaces

    // TODO: should deal with name titles/suffixes ("Dr." etc) but not a big deal
    $ref = toSlug( $fullname );
    // make sure ref is unique
    $i=1;
    while( db_getOne( "SELECT id FROM journo WHERE ref=?", $ref ) ) {
        $ref = toSlug( $fullname ) . "-" . $i++;
    }


    // work out firstname and lastname
    $parts = explode( ' ', $fullname );
    $firstname = array_shift( $parts );
    if( is_null( $firstname ) )
        $firstname = '';
    $lastname = array_pop( $parts );
    if( is_null( $lastname ) )
        $lastname = '';

    $sql = <<<EOT
INSERT INTO journo (ref,prettyname,firstname,lastname,status,firstname_metaphone,lastname_metaphone,created)
    VALUES (?,?,?,?,?,?,?,NOW())
EOT;
    db_do( $sql,
        $ref,
        $fullname,
        $firstname,
        $lastname,
        'i',
        substr( metaphone($firstname), 0, 4),
        substr( metaphone($lastname), 0, 4)
    );
    db_commit();

    return db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );
}


/* CHEESYHACK! */
function person_register($template_data, $email = null, $name = null, $person_if_signed_on_function = null) {
    $P = person_already_signed_on($email, $name, $person_if_signed_on_function);
    if ($P)
        return $P;

    /* Get rid of any previous cookie -- if user is logging in again under a
     * different email, we don't want to remember the old one. */
    person_signoff();

    if (headers_sent())
        err("Headers have already been sent in person_signon without cookie being present");

    if (array_key_exists('instantly_send_email', $template_data)) {
        $send_email_part = "&SendEmail=1";
        unset($template_data['instantly_send_email']);
    } else
        $send_email_part = '';
    /* No or invalid cookie. We will need to redirect the user via another
     * page, either to log in or to prove their email address. */
    $st = stash_request(rabx_serialise($template_data), $email);
    db_commit();
    if ($email)
        $email_part = "&email=" . urlencode($email);
    else
        $email_part = "";
    if ($name) 
        $name_part = "&name=" . urlencode($name);
    else
        $name_part = "";
    header("Location: /login?action=register&stash=$st$send_email_part$email_part$name_part");
    exit();
}

?>
