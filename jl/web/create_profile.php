<?php

//
// page for creating or claiming profiles
//

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../phplib/passwordbox.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


/*
* VIEWS
*/

// entry point
function view() {
    $action = strtolower( get_http_var( 'action' ) );

    if( $action == 'lookup' ) {
        view_lookup();
    } else if( $action == 'claim' ) {
        view_claim();
    } else if( $action == 'create' ) {
        view_create();
    } else {
        header("HTTP/1.0 404 Not Found");
    }
}



/* page to lookup and see if journo already has an entry */
function view_lookup()
{
    $fullname = get_http_var( 'fullname','' );
    $fullname = trim( $fullname );
    $fullname = preg_replace( '/\s+/', ' ', $fullname );  // collapse spaces

    $matching_journos = array();
    if( $fullname ) {
        $matching_journos = journo_FuzzyFind( $fullname );
    }

    if( sizeof($matching_journos)>0) {
        tmpl_lookup($fullname, $matching_journos);
    } else {
        // no matches - just go ahead and create it
        // TODO: show confirmation/fixup page
        view_create();
    }
}




function view_create()
{
    $fullname = get_http_var('fullname');
    $fullname = trim( $fullname );
    $fullname = preg_replace( '/\s+/', ' ', $fullname );  // collapse spaces
    if( !$fullname )
    {
        header("Location: /");
        return;
    }

    $confirm = get_http_var('confirm');
    // is name insane?
    if(!is_name_sensible($fullname) && !$confirm) {
        // show confirmation page

        // suggest capitalisation?
        $s = "";
        if(ucwords(strtolower($fullname))!=$fullname) {
            $s = ucwords($fullname);
        }
        tmpl_create_confirm($fullname,$s);
        return;
    }

    // we need them logged on first
    $P = person_register(array(
        'reason_web' => "Register to create a profile",
        'reason_email' => "Register on Journalisted to create a profile",
        'reason_email_subject' => 'Register on Journalisted'
    ));

    // have they already created a profile?
    // if so, just redirect them there
    {
        $sql = <<<EOT
SELECT j.ref,j.prettyname
   FROM (journo j INNER JOIN person_permission perm ON perm.journo_id=j.id)
   WHERE perm.person_id=? AND perm.permission='edit';
EOT;
        $journo = db_getRow( $sql, $P->id);
        // TODO: compare prettyname?
        if($journo) {
            header("Location: /{$journo['ref']}");
            return;
        }
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
}


function view_claim()
{
    $ref = strtolower( get_http_var( 'ref' ) );

    $iamwhoisay = get_http_var( "iamwhoisay" );
    if( !$iamwhoisay ) {
        // no confirmation box ticked...
        header("Location: /");
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
        tmpl_already_claimed($journo);
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
    tmpl_welcome($journo,$passwordbox);
}


/*
 * TEMPLATES
 */

function tmpl_lookup($fullname, $matching_journos) {
    page_header( "lookup");
    {
        include "../templates/profile_lookup.tpl.php";
    }
    page_footer();
}


function tmpl_create_confirm($fullname, $suggested="") {
    page_header("");
?>
<div class="main">
    <h3>Confirm profile creation...</h3>
    <p>
    Create profile with name <strong><?= h($fullname)?></strong>?
    <?php if($suggested) { ?>
    (perhaps you meant <em><?= h($suggested) ?></em>?)
    <?php } ?>
    </p>
    <form action="/create_profile" method="GET">
        <input type="hidden" name="confirm" value="yes" />
        <input type="hidden" name="action" value="create" />
        <input type="hidden" name="fullname" value="<?= h($fullname) ?>" />

        <input class="btn" type="submit" value="Create profile" />
        <a href="/">no, go back!</a>
    </form>


</div>
<?php
    page_footer();
}



function tmpl_already_claimed($journo) {
    page_header("");
?>
<div class="main">
<p>Sorry - someone has already claimed to be <?= $journo['prettyname'] ?>...</p>
<p>If you are the <em>real</em> <?= $journo['prettyname'] ?>, please <?= SafeMailto( OPTION_TEAM_EMAIL, 'let us know' );?></p>
</div>
<?php
    page_footer();
}



function tmpl_welcome($journo,$passwordbox) {
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



// HELPERS

function toRef( $s )
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
    $ref = toRef( $fullname );

    // special case to deal with one-word names
    if(strpos($ref,'-') === FALSE) {
        $ref .= "-1";
    }

    // make sure ref is unique
    $i=1;
    while( db_getOne( "SELECT id FROM journo WHERE ref=?", $ref ) ) {
        $ref = toRef( $fullname ) . "-" . $i++;
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

function is_name_sensible($fullname) {
    $parts = explode( ' ', $fullname );
    if(sizeof($parts)<2 || sizeof($parts)>3) {
        return false;
    }
    if(ucwords(strtolower($fullname))!=$fullname) {
        return false;
    }

    // looks ok
    return true;
}

view();

?>
