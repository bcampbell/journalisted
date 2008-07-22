<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';

admPageHeader();
?>
<h2>Merge Journo</h2>
<?php


$params = FormParamsFromHTTPVars();
if( !$params['action'] )
	EmitForm( $params );
else
{
	$errs = ValidateForm( $params );
	if( $errs )
	{
		EmitForm( $params, $errs );
	}
	else
	{
		if( $params['action'] == 'commit' )
			MergeJourno( $params );
		else
			EmitPreview( $params );
	}
}

admPageFooter();





function FormParamsFromHTTPVars()
{
	$params = array();

	$params['from_ref'] = get_http_var( 'from_ref', '' );
	$params['into_ref'] = get_http_var( 'into_ref', '' );
	$params['to_ref'] = get_http_var( 'to_ref', '' );
	$params['action'] = get_http_var( 'action', '' );
	return $params;
}



function EmitForm( $params, $errs=array() )
{
/*
	print"<pre>\n";
	print_r( $params );
	print"</pre>\n";
*/
	if( $errs )
	{
		print( "<strong>ERRORS:</strong>\n<ul>\n" );
		foreach( $errs as $e )
		{
			printf( "<li>%s</li>\n", $e );
		}
		print( "</ul>\n" );
	}

?>
<form method="POST">
Which journo do you want to merge? (and delete!)<br />
<small>(use journo ref, eg 'fred-smiff')</small><br />
<input type="text" name="from_ref" value="<?=$params['from_ref'];?>" /><br />
<br />
Which journo do you want to merge into?<br />
<small>(eg 'fred-smith')</small><br />
<input type="text" name="into_ref" value="<?=$params['into_ref'];?>" /><br />

<input type="hidden" name="action" value="preview" />
<input type="submit" value="preview" /><br />
</form>
<?php

}


function EmitPreview( $params )
{
?>
<form method="POST">

<p>OK, so you want to merge:</p>
<?php JournoOverview( $params['from_ref'] ); ?>
<p>into:</p>
<?php JournoOverview( $params['into_ref'] ); ?>
<p>is that right?</p>

<input type="hidden" name="from_ref" value="<?=$params['from_ref'];?>" />
<input type="hidden" name="into_ref" value="<?=$params['into_ref'];?>" />
<input type="hidden" name="action" value="commit" />
<input type="submit" value="MERGE THEM" /><br />
</form>
<?php
}



function ValidateForm( $params )
{
	$errs = array();
	$fromj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['from_ref'] );
	if( !$fromj )
		$errs[] = sprintf( "Can't find FROM journo ('%s')", $params['from_ref'] );

	$intoj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['into_ref'] );
	if( !$intoj )
		$errs[] = sprintf( "Can't find INTO journo ('%s')", $params['into_ref'] );

	if( $params['from_ref'] == $params['into_ref'] )
		$errs[] = "FROM and INTO journos can't be the same";

	return $errs;
}


function MergeJourno( $params )
{
	$fromj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['from_ref'] );
	$intoj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['into_ref'] );

	$from_id = $fromj['id'];
	$into_id = $intoj['id'];

	db_do( "UPDATE journo_attr SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
// alias deprecated
//	db_do( "UPDATE journo_alias SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_jobtitle SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_weblink SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_email SET journo_id=? WHERE journo_id=?", $into_id, $from_id );
	db_do( "UPDATE journo_bio SET journo_id=? WHERE journo_id=?", $into_id, $from_id );

	db_do( "DELETE FROM journo WHERE id=?", $from_id );
	db_commit();

	cache_clear( 'j'.$into_id );
	cache_clear( 'j'.$from_id );

    // TODO: LOG THIS ACTION!

    print( "<div class=\"action_summary\">\nDone!\n<ul>\n" );
	printf("<li>Merged '%s' ('%s') into '%s' ('<a href=\"%s\">%s</a>')</li>\n",
		$fromj['prettyname'], $fromj['ref'],
		$intoj['prettyname'], '/' . $intoj['ref'], $intoj['ref'] );
	printf("<li>Deleted '%s' ('%s')</li>\n", $fromj['prettyname'], $fromj['ref'] );
    print( "</ul></div>\n" );

}



function JournoOverview( $ref )
{
	$journo = db_getRow( "SELECT id,ref,prettyname FROM journo WHERE ref=?", $ref );

	$r = db_query( "SELECT a.srcorg as orgid, COUNT(*) as numarticles ".
		"FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) ".
		"WHERE attr.journo_id=? ".
		"GROUP BY a.srcorg",
		$journo['id'] );
	printf( "'%s' (id=%s ref='%s')\n", $journo['prettyname'], $journo['id'], $journo['ref'] );
	print( "<table border=1>\n" );
	print( "<tr><th>Outlet</th><th>Num articles</th></tr>\n" );
	$orgs = get_org_names();
	while( $row = db_fetch_array( $r ) )
	{
		$orgid = $row['orgid'];
		printf("<tr><td>%s</td><td>%d</td></tr>\n", $orgs[$orgid], $row['numarticles'] );
	}
	print( "</table>\n" );
}

