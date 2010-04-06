<?php
// page for managing users claiming journo profiles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
//require_once '../../phplib/person.php';
require_once '../phplib/adm.php';
require_once '../phplib/journo.php';

admPageHeader( "Profile claims" );

$action = get_http_var( 'action' );


if( $action == 'approve' ) {
    do_ApproveClaim();
}
if( $action == 'deny' ) {
    do_DenyClaim();
}

ShowPendingClaims();




admPageFooter();



function ShowPendingClaims()
{
?>
<h2>Pending claims</h2>
<p>List of all outstanding claims on journo profile pages:</p>
<?php
    $sql = <<<EOT
SELECT p.id as person_id,p.name,p.email,perm.created,perm.permission,j.id as journo_id,j.ref,j.prettyname,j.oneliner
    FROM ( person p
        INNER JOIN person_permission perm ON perm.person_id=p.id)
            INNER JOIN journo j ON perm.journo_id=j.id
    WHERE perm.permission='claimed'
    ORDER BY perm.created ASC;
EOT;
    $pending = db_getAll( $sql );


    if( $pending ) {

        foreach( $pending as &$p ) {
            $d = new datetime( $p['created'] );
            $p['pretty_created'] = $d->format('Y-m-d' );
        }
        unset( $p );

?>
<p><?= sizeof( $pending ) ?> claims pending: </p>
<ul>
<?php foreach( $pending as $p ) { ?>
  <li>
    <?= $p['pretty_created'] ?>:
    <a href="mailto:<?= $p['email'] ?>"><?= $p['email'] ?></a>
    <?php if($p['name']) { ?> ( <?= $p['name'] ?>) <?php } ?>
    claims to be
    <?= journo_link( $p ) ?>
    [<a href="/adm/claims?person_id=<?= $p['person_id'] ?>&journo_id=<?= $p['journo_id'] ?>&action=approve">approve</a>]
    [<a href="/adm/claims?person_id=<?= $p['person_id'] ?>&journo_id=<?= $p['journo_id'] ?>&action=deny">deny</a>]
  </li>
<?php } ?>
</ul>
<?php
    } else {
?>
        <p>No claims pending</p>
<?php
    }
}


/* turn a claim into an "edit" permission */
function do_ApproveClaim()
{
    $person_id = get_http_var( 'person_id' );
    $journo_id = get_http_var( 'journo_id' );

    $person = db_getRow( "SELECT * FROM person WHERE id=?", $person_id );
    $journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

    /* make sure there _is_ a claim! */
    if( !db_getOne( "SELECT id FROM person_permission WHERE permission='claimed' AND person_id=? AND journo_id=?", $person_id, $journo_id ) ) {
?>
<div class="action_error">
<p>ERROR: claim not found</p>
</div>
<?php
        return;
    }

    /* update permissions */
    db_do( "DELETE FROM person_permission WHERE permission='claimed' OR permission='edit' AND person_id=? AND journo_id=?", $person_id, $journo_id );
    db_do( "INSERT INTO person_permission (person_id,journo_id,permission) VALUES (?,?,'edit')", $person_id, $journo_id ); 

    /* set the person's name if blank */
    if( !$person['name'] ) {
        db_do( "UPDATE person SET name=? WHERE id=?", $journo['prettyname'], $person_id );
    }
    db_commit();


    /* suggested email text */
    $firstname = ucwords( $journo['firstname'] );
    $profile_url = OPTION_BASE_URL . "/" . $journo['ref'] . "?login=1";
    $suggested_text = <<<EOT
Hi {$firstname},
Your account at journalisted has been activated, and you can now edit your profile page at:

{$profile_url}

Have fun!
- The journalisted team
EOT;

?>
<div class="action_summary">
<p><a href="mailto:<?= $person['email'] ?>"><?= $person['email'] ?></a> can now edit profile: <?= journo_link( $journo); ?></p>
<p>Now send them an email and let them know. Suggested text:</p>
<pre>
<?= h( $suggested_text ); ?>
</pre>
</div>
<?php
}


function do_DenyClaim()
{
    $person_id = get_http_var( 'person_id' );
    $journo_id = get_http_var( 'journo_id' );

    $person = db_getRow( "SELECT * FROM person WHERE id=?", $person_id );
    $journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

    db_do( "UPDATE person_permission SET permission='claim_denied' WHERE person_id=? AND journo_id=? AND permission='claimed'",
        $person_id, $journo_id );
    db_commit();
?>
<div class="action_summary">
<p>Denied claim on <?= journo_link($journo) ?> by <?= $person['email'] ?></p>
</div>
<?php
}

