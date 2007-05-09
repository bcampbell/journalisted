<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';


page_header();

db_connect();

//
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
Find an Article: <small>todo</small>
</p>

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

<?php

page_footer();

?>
