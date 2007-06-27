<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';


page_header();
db_connect();
$orgs = db_getAll( "SELECT shortname,prettyname FROM organisation ORDER BY prettyname" );

?>

<h2>At Journa-list, you can:</h2>

<p>
<form action="list" method="get">
Find a journalist by name:
<input type="text" name="name" value=""/>
<input type="submit" value="Find" />
</form>
</p>

<p>
<form action="article" method="get">
Find articles containing:
<input type="text" name="find" value=""/>
<input type="submit" value="Find" />
</form>
</p>

<p>
<form action="/list" method="get">
Search Journalists by news outlet:
<select name="outlet">
<?php

	foreach( $orgs as $o )
	{
		print "<option value=\"{$o['shortname']}\">{$o['prettyname']}</option>\n";
	}

?>
</select>
<input type="submit" value="Find">
</form>
</p>

<br />
<hr>
<br />
<p>During the last 24 Hours, the most frequently cited terms were:</p>
<div class="block">
<?php

	$sql = "SELECT t.tag AS tag, SUM(t.freq) AS freq ".
		"FROM ( article a INNER JOIN article_tag t ON a.id=t.article_id ) ".
		"WHERE a.pubdate > NOW() - interval '24 hours' ".
		"GROUP BY t.tag ".
		"ORDER BY freq DESC " .
		"LIMIT 32";
	$q = db_query( $sql );
	tag_cloud_from_query( $q );

?>
</div>
<?php

page_footer();

?>
