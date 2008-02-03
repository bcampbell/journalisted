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

/* return a select element. $options is array of options. */
function form_element_select( $name, $options, $selected=null )
{
	$out = sprintf( "<select name=\"%s\">\n", $name );
	foreach( $options as $k=>$v )
	{
		$s = ($k==$selected) ? 'selected ' : '';
		$out .= sprintf( " <option %svalue=\"%s\">%s</option>\n", $s, $k, $v );
	}
	$out .= "</select>\n";

	return $out;
}

/* return a hidden element */
function form_element_hidden( $name, $value )
{
	return sprintf( "<input type=\"hidden\" name=\"%s\" value=\"%s\" />\n",
		$name, $value );
}


/* return a submit button */
function form_element_submit( $name, $buttonlabel )
{
	return sprintf("<input type=\"submit\" name=\"%s\" value=\"%s\" />\n", $name, $buttonlabel );
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
	$links = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=?", $journo_id );

	if( $links )
	{
?>
	<ul>
<?php
		foreach( $links as $l )
		{
			$anchor = sprintf( "<a href=\"%s\">%s</a>",$l['url'],$l['url'] );
			$removelink = sprintf( "<a href=\"?action=remove_link&journo_id=%s&link_id=%s\">remove</a>",
				$journo_id, $l['id'] );
			print " <li>\n";
			printf(  " %s - '%s' <small>[%s]</small>", $anchor, $l['description'], $removelink );
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
	$l = db_query( "DELETE FROM journo_weblink WHERE id=?", $link_id );
	printf( "<strong>Removed Weblink</strong>\n" );
	/* TODO: LOG THIS ACTION! */
	db_commit();
}


function AddWeblink( $journo_id, $url, $desc )
{
	db_query( "INSERT INTO journo_weblink (journo_id,url,description) VALUES (?,?,?)",
		$journo_id, $url, $desc );
	/* TODO: LOG THIS ACTION! */
	db_commit();
	printf( "<strong>Added Weblink</strong>\n" );
}

