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


$action = strtolower( get_http_var( 'action' ) );

if( $action == 'lookup' ) {
    showLookupPage();
} else if( $action == 'claim' ) {
    $ref = strtolower( get_http_var( 'ref' ) );
    showClaimPage($ref);
} else if( $action == 'create' ) {
    showCreatePage();
} else {
    header("HTTP/1.0 404 Not Found");
    return;
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


function showClaimPage($ref)
{
    /* claiming a specific journo? */
    $ref = strtolower( get_http_var( 'ref' ) );

    $iamwhoisay = get_http_var( "iamwhoisay" );
    if( !$ref || !$iamwhoisay ) {
        // go back to claim selection
    }


    $journo = db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );
    if( !$journo ) {
        header("HTTP/1.0 404 Not Found");
        return;
    }


    // we need them logged on first
    $P = person_register(array(
        'reason_web' => "Register to claim your profile",
        'reason_email' => "Register on Journalisted to claim your profile",
        'reason_email_subject' => 'Register on Journalisted'
    ));

    // TODO: show claim pending message if already claimed

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
