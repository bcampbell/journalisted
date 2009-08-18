<?php
// alerts.php
//
// admin for email alerts
//

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

/* two sets of buttons/action selectors */
$action = get_http_var( 'action' );

admPageHeader( "Alerts" );
?>
<h2>Alerts - email addresses</h2>
<p><em>
NOTE:<br/>
remember that email from: fields can be easily faked!<br/>
Ideally, you should send a request for confirmation to the OLD address and make sure you
get a confirmation back before changing anything...
</em>
</p>
<?php

switch( $action ) {
    case 'edit': do_edit(); break;
    case 'change': do_change(); break;
    case 'lookup':
    default:
        do_lookup();
        break;
}


admPageFooter();


/*****************************/


function do_lookup()
{

?>
<h3>lookup email address</h3>
<form method="POST" action="/adm/alerts">
<label for="email">Look up email addresses containing:</label>
<input id="lookup" name="lookup" value="<?php echo htmlentities(get_http_var('lookup'));?>" />
<button type="submit" name="action" value="lookup">Look up</button>
</form>
<?php

    $lookup = get_http_var( 'lookup' );
    if( !$lookup)
        return;

    $rows = db_getAll( "SELECT * FROM person WHERE email ilike ?", '%'.$lookup.'%' );
?>
<p>Found <?php echo sizeof($rows); ?>:</p>
<ul>
<?php foreach( $rows as $p ) { ?>
  <li><code><?php echo htmlentities($p['email']); ?></code> [<a href="/adm/alerts?action=edit&person_id=<?php echo $p['id']; ?>">edit</a>]</li>
<?php } ?>
</ul>
<?php

}


function do_edit()
{
    $person = db_getRow( "SELECT * FROM person WHERE id=?", get_http_var('person_id') );
?>
<h3>Change email address</h3>


<p>Current email address: <code><?php echo htmlentities( $person['email'] ); ?></code></p>
<form method="POST" action="/adm/alerts">
<input type="hidden" id="person_id" name="person_id" value="<?php echo $person['id'];?>" />
<label for="new_email">New email address:</label>
<input id="new_email" name="new_email" value="<?php echo htmlentities( $person['email'] );?>" />
<button type="submit" name="action" value="change">Change</button>
</form>
<?php
}

function do_change()
{
    $person_id = get_http_var( "person_id" );
    $person = db_getRow( "SELECT * FROM person WHERE id=?", get_http_var('person_id') );
    $old_email = $person['email'];
    $new_email = get_http_var( "new_email" );

    db_do("UPDATE person SET email=? WHERE id=?", $new_email, $person_id );
    db_commit();

?>
<h3>Email address changed</h3>
<div class="action_summary">
Changed email address<br/>from: <code><?php echo $old_email; ?><br/></code> to: <code><?php echo $new_email; ?></code>
</div>
<?php

}

?>
