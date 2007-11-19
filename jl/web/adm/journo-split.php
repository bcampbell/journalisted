<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

?>
<html>
<head></head>
<body>
<h1>Split Journo</h1>

<?php


$params = FormParamsFromHTTPVars();
if( $params['action'] == 'preview' )
	EmitPreview( $params );
elseif( $params['action'] == 'confirm' )
	SplitJourno( $params );
else
	EmitForm( $params );

?>
</body>
</html>

<?php




function FormParamsFromHTTPVars()
{
	$params = array();

	$params['from_ref'] = get_http_var( 'from_ref', '' );
	$params['split_orgids'] = get_http_var( 'split_orgids', array() );
	$params['to_ref'] = get_http_var( 'to_ref', '' );
	$params['action'] = get_http_var( 'action', '' );
	return $params;
}


function aget( $ar, $key, $defaultval=null )
{
	if( array_key_exists( $key, $ar ) )
		return $ar[$key];
	else
		return $defaultval;
}



function EmitForm( $params )
{
/*
	print"<pre>\n";
	print_r( $params );
	print"</pre>\n";
*/
?>
<form>
Which journo do you want to split up?<br />
<small>(use journo ref, eg 'fred-smith')</small><br />
<input type="text" name="from_ref" value="<?=$params['from_ref'];?>" /><br />
<br />
Articles from which outlets should be split out to the new journo?<br />
<?php

	$orgs = get_org_names();
	foreach( $orgs as $orgid=>$orgname )
	{
		if( in_array( $orgid, $params['split_orgids'] ) )
			$sel = 'checked';
		else
			$sel = '';
?>
<input type="checkbox" name="split_orgids[]" <?=$sel;?> value="<?=$orgid;?>" /> <?=$orgname;?><br />
<?php

	}

?>
<br />
Which journo should the articles be moved to?<br />
<small>(eg 'fred-smith-2'. will be created if it doesn't exist)</small><br />
<input type="text" name="to_ref" value="<?=$params['to_ref'];?>" /><br />
<input type="hidden" name="action" value="preview" />
<input type="submit" value="Submit" /><br />
</form>
<?php

}


function EmitPreview( $params )
{
	$orgs = get_org_names();
	$journo=null;
	if( $params['from_ref'] )
		$journo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $params['from_ref'] );
	if( !$journo )
	{
		printf( "<p>Can't find '%s'</p>\n", $params['from_ref'] );
		return;
	}

	printf("<h2>%s</h2>\n", $journo['prettyname'] );
	$r = db_query( "SELECT a.srcorg as orgid, COUNT(*) as numarticles ".
		"FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) ".
		"WHERE attr.journo_id=? ".
		"GROUP BY a.srcorg",
		$journo['id'] );
	print( "<table border=1>\n" );
	print( "<tr><th>Outlet</th><th>Num articles</th><th>split out to new journo?</th></tr>\n" );
	while( $row = db_fetch_array( $r ) )
	{
		$orgid = $row['orgid'];
		if( in_array( $orgid, $params['split_orgids'] ) )
			$yesno = "YES";
		else
			$yesno = 'no';
		printf("<tr><td>%s</td><td>%d</td><td>%s</td></tr>\n", $orgs[$orgid], $row['numarticles'], $yesno );
	}
	print( "</table>\n" );

?>
<form>
<input type="hidden" name="from_ref" value="<?=$params['from_ref'];?>" />
<input type="hidden" name="to_ref" value="<?=$params['to_ref'];?>" />
<?php
	foreach( $params['split_orgids'] as $idx=>$val )
	{
?>
<input type="hidden" name="split_orgids[<?=$idx;?>]" value="<?=$val;?>" />
<?php
	}
?>
<input type="hidden" name="action" value="confirm" />
<input type="submit" value="SPLIT JOURNO!" /><br />
</form>
<?php

}

/*
 * adds an 'id' field to $j
 */
function journoCreate( &$j )
{
	db_do( "INSERT INTO journo (ref,prettyname,lastname,firstname,created) VALUES (?,?,?,?,NOW())",
		$j['ref'],
		$j['prettyname'],
		$j['lastname'],
		$j['firstname'] );
	$j['id'] = db_getOne( "SELECT currval( 'journo_id_seq' )" );

// deprecated
	// TODO: should handle multiple aliases
//	$alias = $j['alias'];

//	db_do( "INSERT INTO journo_alias (journo_id,alias) VALUES (?,?)",
//		$j['id'], $alias );
}


function SplitJourno( $params )
{
	$fromj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['from_ref'] );

	if( !$params['to_ref'] )
	{
		print "<p>ABORTED: no destination journo specified</p>\n";
		return;
	}

	$toj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=?", $params['to_ref'] );
	if( !$toj )
	{
		// to_ref doesn't exist - Create New Journo!

		// just take a copy of 'from' journo
		$toj = $fromj;
		unset( $toj['id'] );
		$toj['ref'] = $params['to_ref'];

		// copy alias too (should be array really)
//		$alias = db_getOne( "SELECT alias FROM journo_alias WHERE journo_id=?", $fromj['id'] );
//		$toj['alias'] = $alias;

		// create the new journo
		journoCreate( $toj );

		printf("<p>Created new journo, '%s' (id=%d)</p>\n", $toj['ref'], $toj['id'] );
	}

	// move articles
	$orglist = implode( ',', $params['split_orgids'] );
	$sql = <<<EOD
UPDATE journo_attr SET journo_id=?
	WHERE journo_id=? AND article_id IN
		(
		SELECT a.id
			FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
			WHERE journo_id=? AND a.srcorg IN ({$orglist})
		)
EOD;

	db_do( $sql, $toj['id'], $fromj['id'], $fromj['id'] );

	// update jobtitles (could create dupes, but hey)
	db_do( "UPDATE journo_jobtitle SET journo_id=? WHERE journo_id=? AND org_id in ({$orglist})", $toj['id'], $fromj['id'] );

	// TODO: other data to move??? links? email?

	db_commit();

	print "<p>It worked!</p>\n";

	printf( "from: <a href=\"/%s\">%s (id %d)</a><br />\n", $fromj['ref'],$fromj['ref'], $fromj['id'] );
	printf( "to: <a href=\"/%s\">%s (id %d)</a><br />\n", $toj['ref'],$toj['ref'], $toj['id'] );



}


