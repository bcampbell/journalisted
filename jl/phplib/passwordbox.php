<?php
require_once '../conf/general';
require_once 'misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';


// a password editor for currently logged-in user
// all action is performed in ctor
// emit() displays the box
class PasswordBox
{

    function __construct( $pagepath = null ) {
        $this->P = person_if_signed_on();
        // "pwb_" prefix means passwordbox
        $this->action = get_http_var( 'pwb_action' );
        $this->pw1 = get_http_var( 'pw1',null );
        $this->pw2 = get_http_var( 'pw2',null );
        $this->err = null;
        $this->info = null;

        if( is_null( $pagepath ) ) {
            // no specific page - use the _current_ one.
            $foo = crack_url( $_SERVER[ 'REQUEST_URI' ] );
            $this->pagepath = $foo['path'];
        } else {
            // use the one supplied.
            $this->pagepath = $pagepath;
        }

        if( is_null($this->P) )
            return;

        if( $this->action == 'set_password' ) {
            if (is_null($this->pw1) || is_null($this->pw2))
                $this->err = "Please type your new password twice";
            elseif (strlen($this->pw1)<5 || strlen($this->pw2)<5)
                $this->err = "Your password must be at least 5 characters long";
            elseif ($this->pw1 != $this->pw2)
                $this->err = "Please type the same password twice";
            else {
                // all looks good. do it.
                $this->P->password($this->pw1);
                db_commit();
                $this->info = 'Password changed';
            }
        }
    }

    function title() {
        if( $this->P->has_password() )
            return 'Change your password';
        else
            return 'Set a password';
    }

    function emit() {
        if( is_null($this->P) )
            return;


?>
<?php if( !$this->P->has_password() ) { ?>
    <p>Setting a password means you won't have to confirm your
    email address every time you want to log in.</p>
<?php } ?>
    <form name="setpassword" action="<?= $this->pagepath ?>" method="post">
      <input type="hidden" name="pwb_action" value="set_password" />
<?php if (!is_null($this->err)) { ?>
      <div class="errormessage"><?= $this->err ?></div>
<?php } ?>
<?php if (!is_null($this->info)) { ?>
      <div class="infomessage"><?= $this->info ?></div>
<?php } ?>
      <div class="field">
        <label for="pw1">New password</label>
        <input type="password" name="pw1" id="pw1" size="15" />
      </div>
      <div class="field">
        <label for="pw2">and again...</label>
        <input type="password" name="pw2" id="pw2" size="15" />
      </div>
      <input name="submit" type="submit" value="<?=_('Submit') ?>">
    </form>
<?php
    }
}

?>
