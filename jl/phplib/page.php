<?php

require_once '../conf/general';

function page_header( $params=array() )
{
	header( 'Content-Type: text/html; charset=utf-8' );

	if( array_key_exists( 'title', $params ) )
		$title = $params['title'];
	else
		$title = OPTION_WEB_DOMAIN;
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
</head>

<body>

<div id="container">

<div id="top">
<h1>Journa-list</h1>

<ul class="hnav">
<li><a href="/">Home</a></li>
<li><a href="/list">All Journalists</a></li>
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
<p>This is the footer</p>
</div>

</div>

</body>
</html>
<?php

}

?>
