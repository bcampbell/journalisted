<?php
// admin page for managing publications

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';


function extra_head()
{
}





$pub_id = get_http_var( 'pub_id' );

if( $pub_id ) {
    show_publication( $pub_id );
} else {
    list_publications();
}





function list_publications()
{
    $pubs = db_getAll( "SELECT id,shortname,prettyname,home_url FROM organisation" );

    admPageHeader( "Publications", "extra_head" );

?>
<p><?= sizeof( $pubs ); ?> publications:</p>

<table>
<thead>
 <tr>
  <th>id</th><th>shortname</th><th>prettyname</th><th>home_url</th>
 </tr>
</thead>
<tbody>
<?php foreach ( $pubs as $pub ) { ?>
 <tr>
  <td><?= $pub['id'] ?></td>
  <td><?= $pub['shortname'] ?></td>
  <td><?= $pub['prettyname'] ?> <small>[<a href="/adm/publication?pub_id=<?= $pub['id'] ?>">admin<a/>]</small></td>
  <td><a href="<?= $pub['home_url'] ?>"><?= $pub['home_url'] ?></a></td>
<?php } ?>
</tbody>
</table>
<?php
    admPageFooter();
}


function show_publication( $pub_id )
{
    $pub = db_getRow( "SELECT * FROM organisation WHERE id=?", $pub_id );
    if( !$pub ) {
        admPageHeader( "Publications", "extra_head" );
?>
<p>Bad pub_id: not found</p>
<?php
        admPageFooter();
        return;
    }

    $aliases = db_getAll( "SELECT * FROM pub_alias WHERE pub_id=?", $pub_id );

    $domains = db_getAll( "SELECT * FROM pub_domain WHERE pub_id=?", $pub_id );
    $email_formats = db_getAll( "SELECT * FROM pub_email_format WHERE pub_id=?", $pub_id );
    $phones = db_getAll( "SELECT * FROM pub_phone WHERE pub_id=?", $pub_id );


    admPageHeader( $pub['prettyname'], "extra_head" );
?>
<h2><?= $pub['prettyname'] ?></h2>
<table>
<tbody>
 <tr> <th>id</th><td><?= $pub['id'] ?></td> </tr>
 <tr> <th>prettyname</th><td><?= $pub['prettyname'] ?></td> </tr>
 <tr> <th>shortname</th><td><?= $pub['shortname'] ?></td> </tr>
 <tr> <th>home_url</th><td><a href="<?= $pub['home_url'] ?>"><?= $pub['home_url'] ?></a></td> </tr>
 <tr> <th>aliases</th><td>
<?php foreach( $aliases as $alias ) { ?>
   <?= $alias['alias'] ?><br/>
<?php } ?>
   </td> </tr>
 <tr> <th>domains</th><td>
<?php foreach( $domains as $domain ) { ?>
<a href="http://<?= $domain['domain'] ?>"><?= $domain['domain'] ?></a><br/>
<?php } ?>
   </td> </tr>
</tbody>
</table>

<?php
    admPageFooter();

}

