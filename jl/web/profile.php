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
    page_header( "lookup" );

    $fullname = get_http_var( 'fullname','' );

?>
<div class="main">
<div class="head"></div>
<div class="body">
<p>Let's look for your profile...</p>
<form method="get" action="/profile">
 <label for="fullname">My name is:</label>
 <input type="text" name="fullname" id="fullname" value="<?= h($fullname) ?>" />
 <input type="hidden" name="action" value="lookup" />
 <input type="submit" value="<?= ($fullname=='')?"Search":"Search again" ?>" />
</form>
<?php

    if( $fullname ) {
        $matching_journos = journo_FuzzyFind( $fullname );
        $uniq=0;

        if( $matching_journos ) {

?>
<form method="get" action="/profile">
<p>Are you one of these people?</p>

<?php foreach( $matching_journos as $j ) { ?>
<input type="radio" id="ref_<?= $uniq ?>" name="ref" value="<?= $j['ref'] ?>" />
<label for="ref_<?= $uniq ?>"><?= $j['prettyname']; ?> (<?= $j['oneliner'] ?>)</label>
<br/>
<?php ++$uniq; } ?>
<input type="hidden" name="action" value="claim" />
<input type="submit" value="Yes, that's me" />
</form>
<?php     if( 0 ) { /*DISABLED!*/ ?>
or...
<form method="get" action="/profile">
  <input type="hidden" name="action" value="create" />
  <input type="hidden" name="fullname" value="<?= h($fullname) ?>" />
  <input type="submit" value="No, create a new profile for me, <?= h($fullname)?>" />
</form>
<?php     } /* END DISABLED */ ?>

<?php

        } else {
            /* searched, found no matches */
?>
<p>Sorry, we couldn't find any profiles matching your name.</p>
<?php     if( 0 ) { /*DISABLED!*/ ?>
<form method="get" action="/profile">
  <input type="hidden" name="action" value="create" />
  <input type="hidden" name="fullname" value="<?= h($fullname) ?>" />
  <input type="submit" value="Create a new profile for me, <?= h($fullname)?>" />
</form>
<?php     } /* END DISABLED */ ?>
<?php
        }
    }
?>
</div>
<div class="foot"></div>
</div> <!-- end main -->
<?php
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

</div>
<div class="foot"></div>
</div>
<?php

page_footer();
}



function showCreatePage()
{
    return; /* DISABLED! */

    // we need them logged on first
    $P = person_signon(array(
        'reason_web' => "Log in to create a profile",
        'reason_email' => "Log in to Journalisted to create a profile",
        'reason_email_subject' => 'Log in to Journalisted'
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


    page_header("");
?>
<div class="main">
<div class="head"></div>
<div class="body">
<h3>Welcome to journa<i>listed</i>, <?= $journo['prettyname'] ?></h3>
<p>You can now <a href="/<?= $journo['ref'] ?>">edit your profile</a></p>
</div>
<div class="foot"></div>
</div>
<?php
    page_footer();
}


function showClaimPage( $journo )
{
    // we need them logged on first
    $P = person_signon(array(
        'reason_web' => "Log in to claim your profile",
        'reason_email' => "Log in to Journalisted to claim your profile",
        'reason_email_subject' => 'Log in to Journalisted'
    ));

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


    // OK - we're set to go!
    db_do( "INSERT INTO person_permission ( person_id,journo_id,permission) VALUES(?,?,?)",
        $P->id,
        $journo['id'],
        'edit' );
    db_commit();

    // set persons name if blank
    if( $P->name_or_blank() == '' ) {
        $P->name( $journo['prettyname'] );  // (does a commit)
    }

    page_header("");

?>
<div class="main">
<h3>Welcome to journa<i>listed</i>, <?= $journo['prettyname'] ?></h3>
<p>You can now <a href="/<?= $journo['ref'] ?>">edit your profile</a></p>
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

?>
