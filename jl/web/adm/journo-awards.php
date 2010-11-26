<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';

require_once '../phplib/admmodels.php';



$id = get_http_var( "id",null );
$journo_id = get_http_var( "journo_id",null );
if( is_null( $journo_id ) ) {
    $journo_id = db_getOne( "SELECT journo_id FROM journo_awards WHERE id=?", $id );
}
$journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

admPageHeader( $journo['ref'] . " Award Info" );

$action = get_http_var( '_action' );
if($action == 'update' || $action=='create' ) {
    // form has been submitted
    $obj = new Award();
    $obj->fromHTTPVars( $_POST );
/*
    print"<hr/><pre><code>\n";
    print_r( $_POST );
    print "--------\n";
    print_r( $obj );
    print"</code></pre><hr/>\n";
*/
    $obj->save();

?>
<div class="info">Saved.</div>
<?php

} else {
    $obj = new Award();

    if( !$id ) {
        // it's new.
        $obj->journo_id = $journo_id;
    } else {
        // fetch from db
        $sql = <<<EOT
SELECT e.*,
        l.id as src__id,
        l.url as src__url,
        l.title as src__title,
        l.pubdate as src__pubdate,
        l.publication as src__publication
    FROM (journo_awards e LEFT JOIN link l ON e.src=l.id )
    WHERE e.id=?
EOT;
        $row = db_getRow( $sql, $id );
        $obj->fromDBRow( $row );
    }
/*    print"<pre>\n";
    print_r( $obj );
    print"</pre>\n";
 */
    $form = $obj->buildForm();

?>
    <h2><?= $id ? "Edit" : "Create New" ?> award entry for <?= $journo['ref'] ?></h2>
<form action="" method="POST">
    <?= $form->render(); ?>
</form>
<?php
}

?>
    <a href="/adm/<?= $journo['ref'] ?>">Back to <?= $journo['ref'] ?></a>
<?php

admPageFooter();

?>
