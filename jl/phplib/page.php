<?php

require_once '../conf/general';
require_once '../../phplib/person.php';

function page_header( $title, $params=array() )
{
	header( 'Content-Type: text/html; charset=utf-8' );

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */

//	if( array_key_exists( 'title', $params ) )
//		$title = $params['title'];
//	else
//		$title = OPTION_WEB_DOMAIN;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$title ?></title>
<link href="/style.css" rel="stylesheet" type="text/css">
<?php

	if (array_key_exists('rss', $params))
	{
		foreach ($params['rss'] as $rss_title => $rss_url)
		{
			printf( "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"%s\" href=\"%s\">\n", $rss_title, $rss_url );
		}
	}

?>
<script type="text/javascript" src="/jl.js"></script>
</head>

<body>

<div id="container">

<div id="top">
<?php

	if( $P )
	{
		if ($P->name_or_blank())
			$name = $P->name;
		else
			$name = $P->email;
		print "<div id=\"hellouser\">\n";
		print "Hello, {$name}\n";
//		print "[<a href=\"/logout\">log out</a>]<br>\n";
		print "<small>(<a href=\"/logout\">this isn't you? click here</a>)</small><br>\n";
		print "</div>\n";
	}

?>
<h1>Journa-list</h1>

<ul class="hnav">
<li><a href="/">Home</a></li>
<li><a href="/list">All journalists</a></li>
<li><a href="/tags">Browse terms</a></li>
<li><a href="/alert">Your alerts</a></li>
<?php

	// some extra menu items for logged-in users
	if( $P )
	{
?>
<li><a href="/logout">Log out</a></li>
<?php
	}

?>
</ul>
</div>

<div id="content">
<?php

}


function page_footer( $params=array() )
{

?>
</div>

<div id="footer">
Journa-list is a <a href="http://www.mediastandardstrust.com">Media Standards Trust</a> project.<br>
Questions? Comments? Suggestions? <a href="mailto:team@journa-list.dyndns.org">Let us know!</a>
</div>

</div>

</body>
</html>
<?php

}

?>
