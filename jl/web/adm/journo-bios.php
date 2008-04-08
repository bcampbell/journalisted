<?php
// journo-bios.php
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

admPageHeader();

?>
<h2>Journo bios</h2>

<p>Select bios with the checkboxes, then select the action you want
to perform at the bottom of the page.</p>
<?php

switch( $action )
{
	case "approve":
		$bio_ids = get_http_var( "bio_id" );
		SetBios( $bio_ids, 't' );
		EmitBioList();
		break;
	case "unapprove":
		$bio_ids = get_http_var( "bio_id" );
		SetBios( $bio_ids, 'f' );
		EmitBioList();
		break;
	default:
		EmitBioList();
		break;
}

admPageFooter();

/********************************/

function EmitBioList()
{
	$sql = <<<EOT
	SELECT j.prettyname, j.ref, b.journo_id, b.bio, b.approved, b.id as bio_id, w.url
		FROM (journo_bio b INNER JOIN journo j ON j.id=b.journo_id
		                   INNER JOIN journo_weblink w ON w.journo_id=b.journo_id)
		ORDER BY j.id
EOT;
	$r = db_query( $sql );

	printf( "<p>%d bios:</p>\n", db_num_rows($r) );
?>
<form method="post" action="/adm/journo-bios">
<table>
<thead>
 <tr>
  <th>journo</th>
  <th>bio</th>
  <th>approved?</th>
  <th>select</th>
 </tr>
</thead>
<tbody>
<?php

	while( $row = db_fetch_array( $r ) )
	{
		$journo_id = $row['journo_id'];
		$bio_id = $row['bio_id'];

		/* links to journo page and journo admin page */
		$journo_link = sprintf( "<a href=\"%s\">%s</a>", "/".$row['ref'], $row['prettyname'] );
		$journo_adm_link = sprintf( "<small>[<a href=\"/adm/journo?journo_id=%s\">admin</a>]</small>", $journo_id );

		/* checkbox element to select this bio... */
		$checkbox = sprintf( "<input type=\"checkbox\" name=\"bio_id[]\" value=\"%s\" />", $bio_id );

		printf( " <tr class=\"%s\">\n  <td>%s</td><td>%s</td><td>%s</td><td>%s</td>\n </tr>\n",
			$row['approved'] == 't' ? "bio_approved":"bio_unapproved",
			$journo_link . " " . $journo_adm_link,
			"<small>" . $row['bio'] . " (<a href=\"". $row['url'] ."\">source</a>)</small>",
			$row['approved']=='t' ? 'yes':'no',
			$checkbox );
	}
?>
</tbody>
</table>
Action (with selected bios):
<select name="action">
 <option value="">None</option>
 <option value="approve">Approve</option>
 <option value="unapprove">Unapprove</option>
</select>
<br />
<input type="submit" name="submit" value="Do it" />

</form>
<?php
}



function SetBios( $bio_ids, $val )
{
	$cnt = 0;
	foreach( $bio_ids as $bio_id )
	{
		$row = db_getRow( "SELECT journo_id,approved FROM journo_bio WHERE id=?", $bio_id );
		if( $row['approved'] != $val ) {
			db_do( "UPDATE journo_bio SET approved=? WHERE id=?",
				$val, $bio_id );
			db_do( "DELETE FROM htmlcache WHERE name=?",
				'j' . $row['journo_id'] );
			$cnt += 1;
		}


	}
	db_commit();

	printf( "<p><strong>%s %d bios</strong></p>\n", $val=='t'?'approved':'unapproved', $cnt );
}

