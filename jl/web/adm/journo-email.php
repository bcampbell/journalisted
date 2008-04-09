<?php
// journo-email.php
// admin page for approving scraped journo bios
//
//

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

require_once "HTML/QuickForm.php";

$action = get_http_var( 'action' );
if (get_http_var('submit2'))
    $action = get_http_var( 'action2' );

$filter = get_http_var( 'filter', 'unapproved' );
$email_ids = get_http_var( 'email_id' );

admPageHeader();

?>
<h2>Journo email addresses</h2>

<p>Select addresses with the checkboxes, then select the action you want
to perform at the top or bottom of the page.</p>
<?php

EmitFilterForm( $filter );
switch( $action )
{
	case "approve":
		SetBios( $email_ids, 't' );
		break;

	case "unapprove":
		SetBios( $email_ids, 'f' );
		break;
}

EmitAddressList($filter);

admPageFooter();

/********************************/

function EmitFilterForm( $filter )
{
	$f = array( 'all'=>'All addresses', 'approved'=>'Approved addresses only', 'unapproved'=>'Unapproved addresses only' );

?>
<form method="get" action="/adm/journo-email">
Show
<?= form_element_select( "filter", $f, $filter ); ?>
 <input type="submit" name="submit" value="Find" />
</form>
<?php

}



function EmitAddressList( $filter )
{
	$whereclause = '';
	if( $filter=='approved' )
		$whereclause = "WHERE approved='t'";
	elseif( $filter=='unapproved' )
		$whereclause = "WHERE approved='f'";

	$sql = <<<EOT
	SELECT e.id AS email_id, journo_id, j.ref AS journo_ref, j.prettyname, email, srcurl, approved
	  FROM journo_email e
	  JOIN journo j ON journo_id=j.id
	$whereclause
	ORDER BY j.lastname, j.firstname, j.prettyname
EOT;


	$r = db_query( $sql );

	printf( "<p>%d addresses:</p>\n", db_num_rows($r) );
?>
<form method="post" action="/adm/journo-email">
<?=form_element_hidden( 'filter', $filter ); ?>

<!-- repeated below -->
    Action (with selected bios):
    <select name="action">
     <option value="">None</option>
     <option value="approve">Approve</option>
     <option value="unapprove">Unapprove</option>
    </select>
    <input type="submit" name="submit" value="Do it" />

<table>
<thead>
 <tr>
  <th>journo</th>
  <th>email</th>
  <th>approved?</th>
  <th>select</th>
 </tr>
</thead>
<tbody>
<?php

	while( $row = db_fetch_array( $r ) )
	{
		$journo_id  = $row['journo_id'];
		$journo_ref = $row['journo_ref'];
		$email_id   = $row['email_id'];
		$approved   = $row['approved'];
		$prettyname = $row['prettyname'];
		$srcurl     = $row['srcurl'];
		$email      = $row['email'];

		/* links to journo page and journo admin page */
		$journo_link = "<a href=\"/$journo_ref\">$prettyname</a>";
		$journo_adm_link = "<small>[<a href=\"/adm/journo?journo_id=$journo_id\">admin</a>]</small>";
		$links = $journo_link . " " . $journo_adm_link;
		
		/* checkbox element to select this bio... */
		$checkbox = "<input type=\"checkbox\" name=\"email_id[]\" value=\"$email_id\" />";

		$tr_class = ($approved == 't' ? "bio_approved":"bio_unapproved");
		
		$col1 = $links;
		$col2 = "<large>$email</large> <small>(<a href=\"$srcurl\">source</a>)</small>";
		$col3 = ($approved=='t' ? 'yes':'no');
		$col4 = $checkbox;
		
		print (" <tr class=\"$tr_class\">\n" .
		       "  <td>$col1</td><td>$col2</td><td>$col3</td><td>$col4</td>\n" .
		       " </tr>\n");
	}
?>
</tbody>
</table>

<!-- repeated above -->
    Action (with selected bios):
    <select name="action2">
     <option value="">None</option>
     <option value="approve">Approve</option>
     <option value="unapprove">Unapprove</option>
    </select>
    <input type="submit" name="submit2" value="Do it" />

</form>
<?php
}



function SetBios( $email_ids, $val )
{
	$cnt = 0;
	foreach( $email_ids as $email_id )
	{
		$row = db_getRow( "SELECT journo_id,approved FROM journo_email WHERE id=?", $email_id );
		if( $row['approved'] != $val ) {
			db_do( "UPDATE journo_email SET approved=? WHERE id=?",
				$val, $email_id );
			db_do( "DELETE FROM htmlcache WHERE name=?",
				'j' . $row['journo_id'] );
			$cnt += 1;
		}


	}
	db_commit();

	printf( "<p><strong>%s %d email address(es)</strong></p>\n", $val=='t'?'approved':'unapproved', $cnt );
}

