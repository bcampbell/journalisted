<?php
// journo.php
// admin page for managing journos

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

require_once "HTML/QuickForm.php";

$statusnames = array('i'=>'i - Inactive', 'a'=>'a - Active', 'h'=>'h - Hidden' );

$journo_id = get_http_var( 'journo_id' );
$action = get_http_var( 'action' );

admPageHeader();

switch( $action )
{
	case 'list':
		/* List journos */
		print "<h2>Journalists</h2>\n";
		EmitJournoFilterForm();
		EmitJournoList();
		break;
	case 'change_status':
		ChangeJournoStatus( $journo_id, get_http_var('status') );
		EmitJourno( $journo_id );
		break;
	case "remove_link":
		ConfirmRemoveWeblink( $journo_id, get_http_var('link_id') );
		break;
	case "remove_link_confirmed":
		RemoveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_link":
		ApproveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_link":
		DisapproveWeblink( $journo_id, get_http_var('link_id') );
		EmitJourno( $journo_id );
		break;
	case "approve_bio":
		ApproveBio( $journo_id, get_http_var('bio_id') );
		EmitJourno( $journo_id );
		break;
	case "disapprove_bio":
		DisapproveBio( $journo_id, get_http_var('bio_id') );
		EmitJourno( $journo_id );
		break;
	case "add_link":
		AddWeblink( $journo_id, get_http_var('url'), get_http_var('desc') );
		EmitJourno( $journo_id );
		break;
	default:
		if( $journo_id )
			EmitJourno( $journo_id );
		else
		{
			print "<h2>Journalists</h2>\n";
			EmitJournoFilterForm();
		}
		break;
}

admPageFooter();

/********************************/

function EmitJournoFilterForm()
{
	global $statusnames;

	$s = array('any'=>'Any') + $statusnames;
?>

<form method="get" action="/adm/journo">
 <input type="hidden" name="action" value="list" /><br />
 with status:
 <?= form_element_select( "status", $s, get_http_var( 'status' ) ); ?><br />
 name containing: <input type="text" name="name" size="40" /><br />
 <input type="submit" name="submit" value="Find" />
</form>
<?php

}

function EmitJournoList()
{
?>
<table>
<thead>
 <tr>
  <th>id</th>
  <th>status</th>
  <th>prettyname</th>
  <th>ref</th>
 </tr>
</thead>
<tbody>
<?php

	$status = get_http_var( 'status', 'any' );
	$name = get_http_var( 'name' );

	$conds = array();
	$params = array();

	if( $status != 'any' ) {
		$conds[] = "status = ?";
		$params[] = $status;
	}

	if( $name ) {
		$conds[] = "prettyname ilike ?";
		$params[] = '%' . $name . '%';
	}

	$sql = "SELECT * FROM journo";
    if( $conds ) {
        $sql .= ' WHERE ' . implode( ' AND ', $conds );
    }

	$q = db_query( $sql, $params );

	while( $r = db_fetch_array($q) ) {
		$link = sprintf( "?journo_id=%s", $r['id'] );
		printf( " <tr>\n" );
		printf( "  <td>%s</td>\n", $r['id'] );
		printf( "  <td>%s</td>\n", $r['status'] );
		printf( "  <td><a href=\"%s\">%s</a></td>\n", $link, $r['prettyname'] );
		printf( "  <td>%s</td>\n", $r['ref'] );
		printf( " </tr>\n" );
	}

?>
</tbody>
</table>
<?php
}



function EmitJourno( $journo_id )
{
	global $statusnames;

	$j = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

	printf("<h2>%s</h2>\n", $j['prettyname'] );
	printf("<a href=\"/%s\">Jump to their page</a>\n", $j['ref'] );

	print( "<h3>General</h3>\n");
	printf("<form method='post'>\n");
	printf("<strong>status:</strong> %s\n", $statusnames[ $j['status'] ] );
	print form_element_hidden( 'action', 'change_status' );
	print form_element_hidden( 'journo_id', $journo_id );
	/* only allow setting to active or hidden, not inactive */
	if( $j['status'] == 'a' ) {
		printf("<input type=\"submit\" name=\"submit\" value=\"Change to 'Hidden'\" />\n" );
		print form_element_hidden( 'status', 'h' );
	}
	else
	{
		printf("<input type=\"submit\" name=\"submit\" value=\"Change to 'Active'\" />\n" );
		print form_element_hidden( 'status', 'a' );
	}

	printf("</form>\n");
	printf("<strong>id:</strong> %s<br />\n", $j['id'] );
	printf("<strong>ref:</strong> %s<br />\n", $j['ref'] );
	printf("<strong>prettyname:</strong> %s<br />\n", $j['prettyname'] );
	printf("<strong>firstname:</strong> %s<br />\n", $j['firstname'] );
	printf("<strong>lastname:</strong> %s<br />\n", $j['lastname'] );
	printf("<strong>created:</strong> %s<br />\n", $j['created'] );

	EmitWebLinks( $journo_id );
	EmitBios( $journo_id );
}

function ChangeJournoStatus( $journo_id, $status )
{
	global $statusnames;

	db_query("UPDATE journo SET status=? WHERE id=?", $status, $journo_id );
	/* TODO: LOG THIS ACTION! */
	db_commit();

	printf( "<strong>Journo status changed to '%s'</strong>\n", $statusnames[$status] );
}


function EmitWebLinks( $journo_id )
{
	print "<h3>Web links</h3>\n";
	$links = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=? AND type!='cif:blog:feed'", $journo_id );

	if( $links )
	{
?>
	<ul>
<?php
		foreach( $links as $l )
		{
			$id = $l['id'];
			$url = $l['url'];
			$desc = $l['description'];
			$approved = ($l['approved']=='t');
			
			$anchor = "<a href=\"$url\">$url</a>";
			$removelink = (
				"<a href=\"?action=remove_link&journo_id=$journo_id&link_id=$id\">remove</a>");
			
			if ( $approved )
			{
				$divclass = 'bio_approved';
				$approvelink = sprintf(
					"<a href=\"?action=disapprove_link&journo_id=%s&link_id=%s\">disapprove</a>",
					$journo_id, $id );
			}
			else
			{
				$divclass = 'bio_unapproved';
				$approvelink = sprintf(
					"<a href=\"?action=approve_link&journo_id=%s&link_id=%s\">approve</a>",
					$journo_id, $id );
			}
			
			print " <li>\n";
			print (" <div class=\"$divclass\">[$id] $anchor - '$desc' " .
			       "<small>[$removelink] [$approvelink]</small></div>");
			print " </li>\n";
		}
?>
	</ul>
<?php

	}
	else
	{
		print( "<p>-- no links --</p>\n" );
	}

?>
<form method="post">
url: <input type="text" name="url" size="40" />
description: <input type="text" name="desc" size="40" />
<?php
print form_element_hidden( 'action', 'add_link' );
print form_element_hidden( 'journo_id', $journo_id );
?>
<input type="submit" name="submit" value="Add Link" />
</form>
<?php

}


function EmitBios( $journo_id )
{
	print "<h3>Bios</h3>\n";
	$rows = db_getAll( "SELECT * FROM journo_bio WHERE journo_id=?", $journo_id );

	if( $rows )
	{
?>
	<ul>
<?php
		foreach( $rows as $row )
		{
			$id  = $row['id'];
			$srcurl = $row['srcurl'];
			$bio = $row['bio'];
			$bio_type = $row['type'];
			$approved = ($row['approved']=='t');
			
			if ( $approved )
			{
				$divclass = 'bio_approved';
				$approvebio = sprintf(
					"<a href=\"?action=disapprove_bio&journo_id=%s&bio_id=%s\">disapprove</a>",
					$journo_id, $id );
			}
			else
			{
				$divclass = 'bio_unapproved';
				$approvebio = sprintf(
					"<a href=\"?action=approve_bio&journo_id=%s&bio_id=%s\">approve</a>",
					$journo_id, $id );
			}
			
			if ( $bio_type == 'wikipedia:journo' )
				$source = 'Wikipedia';
			else if ( $bio_type == 'cif:contributors-az' )
				$source = 'commentisfree';
			else
				$source = $bio_type;
			
			$source = "<a href=\"$srcurl\">$source</a>";
			
			print " <li>\n";
			print(" <div class=\"$divclass\">[$id] $bio <small>(source: $source)</small><br />" .
			      "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .
			      "<small>[$approvebio]</small></div>");
			print " </li>\n";
		}
?>
	</ul>
<?php

	}
	else
	{
		print( "<p>-- no bios --</p>\n" );
	}

	// TODO: Add bios.
}


/* Form to confirm that weblink _should_ be removed from this journo */
function ConfirmRemoveWeblink( $journo_id, $link_id )
{
	$l = db_getRow( "SELECT * FROM journo_weblink WHERE id=?", $link_id );
	$journo = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

?>
<form method="post" action="/adm/journo">
<p>Are you sure you want to remove
<a href="<?=$l['url']?>"><?=$l['description']; ?></a>
from <?=$journo['prettyname'];?>?<br />
<input type="hidden" name="link_id" value="<?=$link_id;?>" />
<input type="hidden" name="journo_id" value="<?=$journo_id;?>" />
<input type="hidden" name="action" value="remove_link_confirmed" />
<input type="submit" name="submit" value="Yes!" />
<a href="?journo_id=<?=$journo_id;?>">No, I've changed my mind</a>
</form>
<?php

}

function RemoveWeblink( $journo_id, $link_id )
{
	db_query( "DELETE FROM journo_weblink WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	printf( "<strong>Removed Weblink</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveWeblink( $journo_id, $link_id )
{
	db_query( "UPDATE journo_weblink SET approved=true WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	printf( "<strong>Approved Weblink $link_id</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveWeblink( $journo_id, $link_id )
{
	db_query( "UPDATE journo_weblink SET approved=false WHERE id=?", $link_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	printf( "<strong>Disapproved Weblink $link_id</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function ApproveBio( $journo_id, $bio_id )
{
	db_query( "UPDATE journo_bio SET approved=true WHERE id=?", $bio_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	printf( "<strong>Approved Bio $bio_id</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}

function DisapproveBio( $journo_id, $bio_id )
{
	db_query( "UPDATE journo_bio SET approved=false WHERE id=?", $bio_id );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	printf( "<strong>Disapproved Bio $bio_id</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}


function AddWeblink( $journo_id, $url, $desc )
{
	db_query( "INSERT INTO journo_weblink (journo_id,url,description) VALUES (?,?,?)",
		$journo_id, $url, $desc );
	db_query( "DELETE FROM htmlcache WHERE name='j$journo_id'" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
	printf( "<strong>Added Weblink</strong>\n" );
}

