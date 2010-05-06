<?php

/* helper functions for admin pages */

// Error display
require_once "../../phplib/error.php";
require_once "../../phplib/person.php";
function admin_display_error($num, $message, $file, $line, $context) {
    print "<p><strong>$message</strong> in $file:$line</p>";
}
err_set_handler_display('admin_display_error');


function admCheckAccess()
{
    $P = person_if_signed_on();
    if( !is_null( $P ) ) {
        // check for admin permission
        $perm = db_getOne( "SELECT id FROM person_permission WHERE permission='admin' AND person_id=?", $P->id() );
        if( !is_null( $perm ) ) {
            return TRUE;
        }
    }
    return FALSE;
}


// only returns if logged-in user has admin access
function admEnforceAccess()
{
    if( !admCheckAccess() ) {
        // no access. stop right now.

        // should print error message, but hey. let's just dump back to homepage
        header( "Location: /" ); 
        exit;
    }
}


function admPageHeader( $title = '', $extra_head_fn=null )
{
    admEnforceAccess();

	header( 'Content-Type: text/html; charset=utf-8' );

?>
<html>
<head>
<title>JL admin<?php if( $title ) { print " - $title"; }; ?></title>
<style type="text/css" media="all">@import "/adm/admin-style.css";</style>
<style type="text/css" media="all">@import "/js/jquery.autocomplete.css";</style>

<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.stylish-select.min.js"></script>
<script type="text/javascript" src="/js/jl-util.js"></script>

<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>

<?php
    if( !is_null( $extra_head_fn ) ) {
    	call_user_func( $extra_head_fn );
    }
?>
</head>
<body>
<h1>Journalisted admin</h1>
<a href="article">Articles</a>
 (<small>
 <a href="scrape">Scrape</a> |
 <a href="missingarticles">MissingArticles</a> |
 <a href="otherarticles">OtherArticles</a>
 </small>) |
<a href="journo">Journos</a>
 (<small>
 <a href="journo-bios">Bios</a> |
 <a href="journo-email">Email</a> |
 <a href="journo-split">Split</a> |
 <a href="journo-merge">Merge</a> |
 <a href="journo-create">Create</a>
 </small>) |
<a href="canned">Canned Queries</a> |
<a href="news">News</a> |
<a href="useraccounts">User Accounts</a>
 (<small>
 <a href="claims">Claims</a>
 </small>) |
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



/* marks up links in plain text:
 * [jNNNNNN <name>] - journalist admin page
 * [aNNNNNN '<headline>'] - article admin page
 * http://....
 */
function admMarkupPlainText( $txt )
{
    $html = $txt;
	$html = str_replace( "<", "&lt;", $html );
	$html = str_replace( ">", "&gt;", $html );

    /* articles */
	$html = preg_replace( "/\\[a([0-9]+)(\\s*'(.*?)')?\\s*\\]/", "<a href=\"/adm/article?article_id=\\1\">\\0</a>", $html );

    /* journos */
	$html = preg_replace( "/\\[j([0-9]+)(\\s*(.*?))?\\s*\\]/", "<a href=\"/adm/journo?journo_id=\\1\">\\0</a>", $html );

    /* http:// */
    $html = preg_replace( "%http://\\S+%", "<a href=\"\\0\">\\0</a>", $html );

	return $html;
}


// return a link to a journo page, with a little [admin] link beside it
function admJournoLink( $ref, $prettyname=null )
{
    return sprintf( "<a href=\"/%s\">%s</a> <small>(<a href=\"/adm/%s\">admin</a>)</small>",
        $ref,
        $prettyname?$prettyname:$ref,
        $ref );
}
