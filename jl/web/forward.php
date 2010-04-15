<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/eventlog.php';
require_once '../phplib/recaptchalib.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



$ref = strtolower( get_http_var( 'journo' ) );
$_journo = db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );

$data = journo_collectData( $_journo );


$_keys = parse_ini_file( OPTION_JL_FSROOT . '/conf/recaptcha.ini' );

page_header( "Email {$_journo['prettyname']}'s profile to a friend" );
?>
<div class="main">
<?php

$params = formFetch();
if( $params['action'] == 'go' ) {
    $errs = formCheck( $params );
    if( $errs ) {
        formEmit( $params, $errs );
    } else {
        // cool - all ready to go.
        do_it( $params );
    }
} else {
    formEmit( $params );
}

?>
</div>
<?php
page_footer();



function formFetch()
{
    $p = array();
    $p['action'] = get_http_var( 'action' );
    $p['name'] = get_http_var( 'name' );
    $p['email'] = get_http_var( 'email' );
    $p['message'] = get_http_var( 'message' );
    $p['recaptcha_response_field'] = get_http_var( 'recaptcha_response_field', null );
    $p['recaptcha_challenge_field'] =
        get_http_var('recaptcha_challenge_field' );

    return $p;
}


function formCheck( $params )
{
    global $_keys;

    $recaptcha_valid = FALSE;
    $errs = array();

    if( !is_null($params[ "recaptcha_response_field" ]) )  {
        $resp = recaptcha_check_answer(
            $_keys['private'],
            $_SERVER["REMOTE_ADDR"],
            $params["recaptcha_challenge_field"],
            $params["recaptcha_response_field"] );

        if ($resp->is_valid) {
            $recaptcha_valid = TRUE;
        } else {
            $errs['recaptcha_error'] = $resp->error;
        }
    }

    if( !$recaptcha_valid ) 
        $errs['recaptcha'] = "Please complete the anti-spam test";

    if( !$params['email'] ) {
        $errs['email'] = "Please enter the recipient's email address";
    } else {
        if( !validate_email( $params['email'] ) ) {
            $errs['email'] = "Please enter a valid email address for the recipient";
        }
    }
    if( !$params['name'] )
        $errs['name'] = "Please enter your name";

    return $errs;
}


function formEmit( $params, $errs=array() )
{
    global $_keys;
    global $_journo;

    $resp_error = arr_get( 'recaptcha_error', $errs, '' );
    unset( $errs['recaptcha_error'] );

?>
<h2>Email <?= $_journo['prettyname'] ?>'s profile to a friend</h2>

<form action="/forward" method="post">
<?php if( $errs ) { ?>
  <div class="errormessage">
   There were errors:
   <ul>
  <?php foreach( $errs as $k=>$v ) { ?>
    <li><?= $v ?></li>
  <?php } ?>
   </ul>
  </div>
<?php } ?>
  <dl>
  <dt><label for="email">Recipient</label></dt>
  <dd>
    <input id="email" name="email" value="<?= h($params['email']) ?>" />
    <span class="explain">eg: timmytestfish@example.com</span>
  </dd>

  <dt><label for="name">Your name</label></dt>
  <dd><input id="name" name="name" value="<?= h($params['name']) ?>" /></dd>

  <dt><label for="message">Message</label><br/> (optional)</dt>
  <dd><textarea name="message" id="message" cols="40" rows="7"><?= h($params['message']) ?></textarea></dd>

  <dt><span class="faux-label">anti-spam test</span></dt>
  <dd>
  <?= recaptcha_get_html( $_keys['public'], $resp_error ) ?>
  </dd>
  </dl>
  <input type="hidden" name="journo" value="<?= $_journo['ref'] ?>" />
  <input type="hidden" name="action" value="go" />
  <p>please note: your IP address may be logged</p>
  <input type="submit" value="submit" /> or <a href="/<?= $_journo['ref'] ?>">cancel</a>
</form>
<?php

}


function build_email_body( $params )
{
    global $_journo;
    $data = journo_collectData( $_journo );
    ob_start();
    {
        extract( $data );
        include "../templates/journo_text.tpl.php";
    }
    $profile_text = ob_get_clean();


    $body = "{$params['name']} sent this to you from " . OPTION_BASE_URL . "\n\n";

    if( $params['message'] ) {
        $body .= "Note from {$params['name']}:\n";
        $body .= $params['message'];
        $body .= "\n";
    }

    $body .= str_repeat( '-', 75 ) . "\n\n";
    $body .= $profile_text;
    $body .= str_repeat( '-', 75 ) . "\n";
    $body .= "If you have any questions about this email, please contact " . OPTION_TEAM_EMAIL . "\n";
    return $body;
}



function do_it( $params )
{
    global $_journo;

    $txt = build_email_body( $params );
    $subject = "[from {$params['name']}] {$_journo['prettyname']} on journalisted";

    $success = jl_send_text_email( $params['email'], OPTION_WEB_DOMAIN, OPTION_TEAM_EMAIL, $subject, $txt );
    if( $success ) {
?>
<div class="infomessage">
<p>Thank you - email sent.</p>
</div>
<?php
    } else {
?>
<div class="errormessage">
<p>Sorry, there was a problem, and the email was not sent</p>
</div>
<?php
    }
?>
<p><a href="/<?= $_journo['ref'] ?>">Go back to <?= $_journo['prettyname'] ?>'s page</a></p>
<?php
    $context = array( 'email'=>$params['email'],
        'name'=>$params['name'],
        'message'=>$params['message'],
        'success'=>$success,
        'remote_addr'=>$_SERVER["REMOTE_ADDR"] );
    eventlog_Add( 'forward-profile', $_journo['id'], $context );
}


