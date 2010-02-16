<?php
/*
 * login.php:
 * Identification and authentication of users.
 *
 * NOTE: This file is based on login.php from PledgeBank (BenC)
 * 
 * The important thing here is that we mustn't leak information about whether
 * a given email address has an account or not. That means that, until we have
 * either processed a password, or have had the user click through from an
 * email token, we can't give any indication of whether the user has an account
 * or not.
 * 
 */


require_once '../conf/general';
require_once '../../phplib/auth.php';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/stash.php';
require_once '../../phplib/rabx.php';
require_once '../../phplib/importparams.php';


// TODO - make this a site-wide thing?
function jl_handle_error($num, $message, $file, $line, $context)
{
    header('HTTP/1.0 500 Internal Server Error');
    page_header("Sorry! Something's gone wrong.");

?>
<h2>Sorry! Something's gone wrong.</h2>
<em><?php echo $message; ?></em>
<!-- <?php echo "{$file}:{$line}"; ?> -->
<?php
    page_footer();
}


err_set_handler_display('jl_handle_error');


EnsureCookiesEnabled();


/* Get all the parameters which we might use (pulls them into $q_ prefixed vars) */
importparams(
        array('stash',          '/^[0-9a-f]+$/',    '', null),
        array('email',          '/./',              '', null),
        array(array('name',true),           '//',               '', null),
        array('password',       '/[^\s]/',          '', null),
        array('t',              '/^.+$/',           '', null),
        array('rememberme',     '/./',              '', false)
    );


/* General purpose login, asks for email also. */
if( get_http_var("now")) {
    $P = person_signon(array(
        'reason_web' => "Log in",
        'reason_email' => "Log in to Journalisted",
        'reason_email_subject' => 'Log in to Journalisted'
    ));

	// alerts is closest thing we have to an account management page
    header("Location: /alert");
    exit;
}



/* is there a token? (i.e. user coming in via a confirmation email) */
if (!is_null($q_t)) {
    $q_t = preg_replace('#</a$#', '', $q_t);
    /* Process emailed token */
    $d = auth_token_retrieve('login', $q_t);
    if (!$d)
        err(sprintf(_("Please check the URL (i.e. the long code of letters and numbers) is copied correctly from your email.  If you can't click on it in the email, you'll have to select and copy it from the email.  Then paste it into your browser, into the place you would type the address of any other webpage. Technical details: The token '%s' wasn't found."), $q_t));
    $P = person_get($d['email']);
    if (is_null($P)) {
        $P = person_get_or_create($d['email'], $d['name']);
    }

    $P->inc_numlogins();
 
    db_commit();

    /* Now give the user their cookie. */
    set_login_cookie($P);

    RedirectToOriginalDest( $d['stash'] );
    /* NOTREACHED */
}


$P = person_if_signed_on();
if (!is_null($P)) {
    RedirectToOriginalDest( $q_stash );
    /* NOT REACHED */
} else {

    $action = strtolower(get_http_var('action', 'login') );
    if( $action == 'login') {
        DoLoginPage();
    } else if( $action == 'register' or $action == 'sendemail' ) {
        DoLoginViaEmailPage();
    }

}



/* doesn't return */
function RedirectToOriginalDest( $stash ) {
    if (!is_null($stash)) {
        /* just pass them through to the page they actually wanted. */
        stash_redirect($stash);
        /* NOTREACHED */
    } else {
	    // alerts is closest thing we have to a user page
        header("Location: /alert");
        exit;
    }
}



function Reason()
{
    global $q_stash;

    if( is_null( $q_stash ) )
        return "Log in";

    $template_data = rabx_unserialise(stash_get_extra($q_stash));
    return htmlspecialchars($template_data['reason_web']);
}



/* login_page
 * Render the login page, or respond to a button pressed on it. */
function DoLoginPage()
{
    global $q_stash, $q_email, $q_name, $q_rememberme;


    $errs = array();
    if( get_http_var( 'loginsubmit', NULL ) ) {
        $errs = LoginForm_AttemptLogin();
        if( !$errs ) {
            RedirectToOriginalDest( $q_stash );
            /* NOT REACHED */
        }
    }

    $reason = Reason();

    page_header( "Logging in" );

?>
<div class="main">
<h3><?php echo $reason; ?></h3>
<?php loginform_emit( $q_email, $q_stash, $q_rememberme, $errs ); ?>
</div> <!-- end main -->
<?php

    page_footer();
}




/* set_login_cookie PERSON [DURATION]
 * Set a login cookie for the given PERSON. If set, EXPIRES is the time which
 * will be set for the cookie to expire; otherwise, a session cookie is set. */
function set_login_cookie($P, $duration = null) {
    // error_log('set cookie');
    setcookie('pb_person_id', person_cookie_token($P->id(), $duration), is_null($duration) ? null : time() + $duration, '/', person_cookie_domain(), false);
}



/* only returns if cookies are enabled. otherwise outputs error page and exits */
function EnsureCookiesEnabled()
{
    /* As a first step try to set a cookie and read it on redirect, so that we can
     * warn the user explicitly if they appear to be refusing cookies. */
    if (!array_key_exists('test_cookie', $_COOKIE)) {
        if (array_key_exists('test_cookie', $_GET)) {
            page_header(_("Please enable cookies"));
    ?>
    <p>It appears that you don't have "cookies" enabled in your browser.</p>
    <p><strong>To continue, you must enable cookies</strong>.</p>
    <p>Please read <a href="http://www.google.com/cookies.html">this page from Google
    explaining how to do that</a>, then click the "back" button and try again.</p>
    <?php
            page_footer();
            exit();
        } else {
            setcookie('test_cookie', '1', null, '/', person_cookie_domain(), false);
            header("Location: /login.php?" . $_SERVER['QUERY_STRING'] . "&test_cookie=1\n");
            exit();
        }
    }
}

// returns array of error messages for the login form
function LoginForm_AttemptLogin()
{
    global $q_stash, $q_email, $q_name, $q_rememberme;


    /* User has tried to log in. */
    if (is_null($q_email)) {
        return array('email'=>'Please enter your email address');
    }
    if (!validate_email($q_email)) {
        return array('email'=>'Please enter a valid email address');
    }

    global $q_password;
    $P = person_get($q_email);
    if (is_null($P) || !$P->check_password($q_password)) {
        return( array('badpass'=>'Either your email or password weren\'t recognised.  Please try again.') );
    } else {
        /* User has logged in correctly. Decide whether they are changing
         * their name. */
        set_login_cookie($P, $q_rememberme ? 28 * 24 * 3600 : null); // one month
        $P->inc_numlogins();
        db_commit();
        return array();
    }
}




function DoLoginViaEmailPage()
{
    global $q_stash, $q_email, $q_name, $q_rememberme;

    $action = strtolower( get_http_var('action') );

    $errs = array();
    if( get_http_var( 'registersubmit', NULL ) ) {
        /* check the inputs... */
        if (is_null($q_email)) {
            $errs['email'] = 'Please enter your email address';
        } else if (!validate_email($q_email)) {
            $errs[ 'email' ] = 'Please enter a valid email address';
        }

        if( !$errs )
        {
            // send the email...
            DoConfirmationEmail();

            page_header("Now check your email!" );
?>
<p class="loudmessage">
Now check your email!<br/>
<br/>
We've sent you an email, and you'll need to click the link in it to log in.
</p>
<?php

            page_footer();
            return;
        }
    }

    page_header( "Register" );

?>
<div class="main">
<?php if( $action =='register' ) { ?>
  <h3>Register new account</h3>
  <p>To register, please tell us your email address.</p>
  <p>We'll send you an email, click the link in it to confirm your email is working.</p>
<?php } else { ?>
  <h3>Lost/forgotten/missing password</h3>
  <p>To log in, please tell us your email address.</p>
  <p>We'll send you an email containing a link.<br/>Click that link to log in.</p>
<?php } ?>
<?php RegisterForm_Emit( $errs ); ?>
</div>
<?php

    page_footer();
}


function DoConfirmationEmail()
{
    global $q_stash, $q_email, $q_name, $q_rememberme;

    if( is_null( $q_stash ) ) {
        // create a default stashed request to take returning user to "/alert",
        // the closest thing we have to a user profile page
        $template_data = array(
            'reason_web' => "Log in",
            'reason_email' => "Log in to Journalisted",
            'reason_email_subject' => 'Log in to Journalisted' );

        $q_stash = stash_new_request( "POST", "/alert", null, rabx_serialise($template_data), null );
    }


    $token = auth_token_store('login', array(
                    'email' => $q_email,
                    'name' => $q_name,
                    'stash' => $q_stash
                ));
    db_commit();


    /* send out a confirmation email */
    $url = OPTION_BASE_URL . "/login?t={$token}";

    $values = rabx_unserialise(stash_get_extra($q_stash));
    $body = "Please click on the link below to confirm your email address.\n" .
        "{$values['reason_email']}\n" .
        "\n" .
        "{$url}\n" .
        "\n";

    $subject = $values['reason_email_subject'];
    $from_name = "Journalisted";
    $from_email = OPTION_TEAM_EMAIL;
    jl_send_text_email($q_email, $from_name, $from_email, $subject, $body);

}

    
function RegisterForm_Emit($errs = array())
{
    global $q_h_stash, $q_h_email, $q_h_name, $q_stash, $q_email, $q_name, $q_rememberme;

    $action = strtolower( get_http_var('action', '' ) );
?>

<form action="/login" name="register" class="login" method="POST" accept-charset="utf-8">
<input type="hidden" name="stash" value="<?=$q_h_stash?>" />
<input type="hidden" name="action" value="<?php echo $action; ?>" />

<dl>
  <dt><label for="email">Email address</label></dt>
  <dd>
    <input type="text" size="30" name="email" id="email" value="<?php echo $q_h_email; ?>" />
<?php if(array_key_exists('email',$errs) ) { ?><span class="errhint"><?php echo $errs['email'];?></span><br/><?php } ?>
  </dd>
</dl>

<p>
<input type="submit" name="registersubmit" value="Continue" />
</p>

</form>
<?

}




?>
