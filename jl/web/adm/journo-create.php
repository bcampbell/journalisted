<?php

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';


$page = new CreateJourno();
$page->run();



class CreateJourno {

    function __construct() {
    }

    function run() {
        admPageHeader( "Create new journo" );
?>
<h2>Create new Journo</h2>
<?php
        $action = get_http_var( 'action','' );
        $params = $this->get_params();

        if( $action =='' ) {
            $this->emit_form( $params );
        } else if( $action == 'preview' ) {
            $errs = $this->validate( $params );
            $this->emit_form( $params, $errs, $errs?false:true );
        } else if( $action == 'back' ) {
            $errs = $this->validate( $params );
            $this->emit_form( $params, $errs );
        } else if( $action == 'do_it' ) {
            $errs = $this->validate( $params );
            if( !$errs ) {
                // thunderbirds are go.
                $this->create_journo( $params );
            } else {
                $this->emit_form( $params, $errs );
            }
        }
        admPageFooter();
    }

    function get_params() {
        $p['prettyname'] = trim( get_http_var( 'prettyname' ) );
        $p['ref'] = trim( strtolower( get_http_var( 'ref' ) ) );
        $p['firstname'] = trim( strtolower( get_http_var( 'firstname' ) ) );
        $p['lastname'] = trim( strtolower( get_http_var( 'lastname' ) ) );

        if( $p['prettyname'] ) {
            $def_ref = strtolower($p['prettyname'] );
            $def_ref = preg_replace( '/[^a-z]+/', '-', $def_ref );

            $parts = explode( ' ', strtolower($p['prettyname']) );
            if( $parts ) {
                $def_lastname = array_pop($parts);
                $def_firstname = array_shift($parts);
            }

            if(!$p['ref'])
                $p['ref'] = $def_ref;
            if(!$p['lastname'])
                $p['lastname'] = $def_lastname;
            if(!$p['firstname'])
                $p['firstname'] = $def_firstname;

        }
        return $p;
    }


    function validate( $params ) {
        $err = array();
        if( $params['prettyname'] == '' )
            $err['prettyname'] = 'blank Pretty Name';
        if( !preg_match( '/^[a-z]+(-[a-z0-9]+){1,}$/', $params['ref'] ) )
            $err['ref'] = 'bad ref (needs at least one hyphen)';
        if( !preg_match( '/^[a-z]+$/', $params['firstname'] ) )
            $err['firstname'] = 'bad first name';
        if( !preg_match( '/^[a-z]+$/', $params['lastname'] ) )
            $err['lastname'] = 'bad last name';

        return $err;

    }

    function emit_form( $params, $errs=array(), $preview = false ) {
        $p = &$params;

        if( $errs ) {
            print "<strong>ERRORS:</strong><ul>\n";
            foreach( $errs as $e ) {
                printf( "<li>$e</li>\n");
            }
            print "</ul>\n";
        }
        $ro = $preview ? 'readonly ':'';    // for preview, make everything readonly

        if( $preview ) {
?>
<p>Does this look OK?</p>
<?php
        }
?>
<form method="post" action="/adm/journo-create">
  <label for="prettyname">Pretty Name</label> <input type="text" id="prettyname" name="prettyname" value="<?php echo $p['prettyname']; ?>" <?php echo $ro; ?>/><br />
  <label for="ref">ref <small>(lowercase, can leave blank)</small></label> <input type="text" id="ref" name="ref" value="<?php echo $p['ref']; ?>" <?php echo $ro; ?>/><br />
  <label for="firstname">First Name <small>(lowercase, can leave blank)</small></label> <input type="text" id="firstname" name="firstname" value="<?php echo $p['firstname']; ?>" <?php echo $ro; ?>/><br />
  <label for="lastname">Last Name <small>(lowercase, can leave blank)</small></label> <input type="text" id="lastname" name="lastname" value="<?php echo $p['lastname']; ?>" <?php echo $ro; ?>/><br />
<?php if( $preview ) { ?>
  <button type="submit" name="action" value="do_it">Yes! - Create that journo</button></br />
  <button type="submit" name="action" value="back">No - I want to go back and twiddle values</button>
<?php } else { ?>
  <button type="submit" name="action" value="preview">Preview</button>
<?php } ?>


</form>
<?php
    }


    function create_journo( $params ) {
        db_do( "INSERT INTO journo (ref,prettyname,firstname,lastname,status,created) VALUES (?,?,?,?,?,NOW())",
            $params['ref'],
            $params['prettyname'],
            $params['firstname'],
            $params['lastname'],
            'a' );
            db_commit();
?>
        <p>Created new journo: <a href="/<?php echo $params['ref'];?>"><?php echo $params['ref'];?></a>
            [<a href="/adm/<?php echo $params['ref'];?>">admin page</a>]</p>
<?php
    }
}

?>
