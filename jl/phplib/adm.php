<?php

/* helper functions for admin pages */

// Error display
require_once "../../phplib/error.php";
function admin_display_error($num, $message, $file, $line, $context) {
    print "<p><strong>$message</strong> in $file:$line</p>";
}
err_set_handler_display('admin_display_error');



function admPageHeader()
{
	header( 'Content-Type: text/html; charset=utf-8' );

?>
<html>
<head>
<title>journa-list admin</title>
<style type="text/css" media="all">@import "/adm/admin-style.css";</style>
</head>
<body>
<h1>journa-list admin</h1>
<a href="journo">Journos</a> |
<a href="article">Articles</a> |
<a href="scrape">Scrape</a> |
<a href="journo-bios">Journo-Bios</a>
<hr>
<?php

}




function admPageFooter()
{
?>

</body>
</html>
<?php
}


