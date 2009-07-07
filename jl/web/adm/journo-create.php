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
        $action = get_http_var( 'action' );
        $params = $this->get_params();

        if( $action == 'do_it' ) {
            $errs = $this->validate( $params );
            if( !$errs ) {
                db_do( "INSERT INTO journo (ref,prettyname,firstname,lastname,status,created VALUES (?,?,?,?,?,NOW())",
                    $params['ref'],
                    $params['prettyname'],
                    $params['firstname'],
                    $params['lastname'],
                    'a' );
                db_commit();

                print "Wooooooo!";
            } else {
                $this->emit_form( $params, $errs );
            }
        }
        else
        {
            $this->emit_form( $params );
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
        if( !preg_match( '/[a-z]+(-[a-z0-9]+){1,}/', $params['ref'] ) )
            $err['ref'] = 'bad ref';
        if( !preg_match( '/[a-z]+/', $params['firstname'] ) )
            $err['firstname'] = 'bad first name';
        if( !preg_match( '/[a-z]+/', $params['lastname'] ) )
            $err['lastname'] = 'bad last name';

        return $err;

    }

    function emit_form( $params, $errs=array() ) {
        $p = &$params;

        if( $errs ) {
            print "<strong>ERRORS:</strong><ul>\n";
            foreach( $errs as $e ) {
                printf( "<li>$e</li>\n");
            }
            print "</ul>\n";
        }

?>
<form method="get" action="/adm/journo-create">
  <input type="hidden" name="action" value="do_it" />
  <label for="prettyname">Pretty Name</label> <input type="text" id="prettyname" name="prettyname" value="<?php echo $p['prettyname']; ?>" /><br />
  <label for="ref">ref <small>(lowercase, can leave blank)</small></label> <input type="text" id="ref" name="ref" value="<?php echo $p['ref']; ?>" /><br />
  <label for="firstname">First Name <small>(lowercase, can leave blank)</small></label> <input type="text" id="firstname" name="firstname" value="<?php echo $p['firstname']; ?>" /><br />
  <label for="lastname">Last Name <small>(lowercase, can leave blank)</small></label> <input type="text" id="lastname" name="lastname" value="<?php echo $p['lastname']; ?>" /><br />
  <input type="submit" name="submit" value="Create" />
</form>
<?php
    }

}



function EmitNewJournoForm()
{
    $prettyname = get_http_var( 'prettyname', '' );

?>
<form method="get" action="/adm/journo">
  <input type="hidden" name="action" value="new_journo" /><br />
  <label for="prettyname">Pretty Name</label><input type="text" id="prettyname" name="prettyname" value="<?php echo $prettyname; ?>" /><br />
  <input type="submit" name="submit" value="Create" />
</form>
<?php

}

?>
