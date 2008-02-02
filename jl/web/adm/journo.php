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

$statusnames = array('i'=>'Inactive', 'a'=>'Active', 'h'=>'Hidden' );

$journo_id = get_http_var( 'journo_id' );
$action = get_http_var( 'action' );

admPageHeader();

if( $action == "change_status" )
{
	ChangeJournoStatus( $journo_id, get_http_var('status') );
	EmitJourno( $journo_id );
}
else
{
	if( $journo_id )
		EmitJourno( $journo_id );
	else
		EmitJournoList();
}

admPageFooter();

/********************************/




function EmitJournoList()
{
?>
<h2>Journalists</h2>

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

	$q = db_query( "SELECT * FROM journo" );

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


function form_element_select( $name, $options, $selected )
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

function form_element_hidden( $name, $value )
{
	return sprintf( "<input type=\"hidden\" name=\"%s\" value=\"%s\" />\n",
		$name, $value );
}

function EmitJourno( $journo_id )
{
	global $statusnames;

	$j = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

	printf("<h2>Journo: %s</h2>\n", $j['prettyname'] );

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

	print "<table border=\"0\">\n";
	foreach( $links as $l )
	{
		$anchor = sprintf( "<a href=\"%s\">%s</a>",$l['url'],$l['url'] );
		print " <tr>\n";
		printf( "  <td>%s</td><td>%s</td><td>%s</td><td>%s</td>\n",
			$l['id'],$anchor,$l['description'],$l['source'] );
		print " </tr>\n";
	}
	print "</table>\n";

}

