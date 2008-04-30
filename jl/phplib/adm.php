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
<title>Journalisted admin</title>
<style type="text/css" media="all">@import "/adm/admin-style.css";</style>
</head>
<body>
<h1>Journalisted admin</h1>
<a href="journo">Journos</a> |
<a href="article">Articles</a> |
<a href="scrape">Scrape</a> |
<a href="journo-bios">Journo-Bios</a> |
<a href="journo-email">Journo-Email</a>
<a href="journo-split">Split Journos</a>
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


/* helpers for forms - maybe should have their own file... */

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

