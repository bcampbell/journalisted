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
<!DOCTYPE html>
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
<script type="text/javascript" src="/js/tooltip.js"></script>

<script type="text/JavaScript">


$(document).ready(function() {
    $(".journo-lookup").autocomplete("ajax-ref-lookup.php");
    $(".journo-info").toptip({
        'fetch': function(trigger,tip) {
            var txt = trigger.attr('href') || trigger.html();
            var m = txt.match(/[a-zA-Z]+[-][-a-zA-Z0-9]+/g)
            if(m && m[0]) {
                ref = m[0];
                tip.html('<img src="/img/indicator.gif" />Loading...');
                tip.load('/adm/ajax-journo-info?j=' + ref);
            }
        }
        });

    });
</script>

<?php
    if( !is_null( $extra_head_fn ) ) {
    	call_user_func( $extra_head_fn );
    }
?>
</head>
<body>
<h1>Journalisted admin</h1>
<a href="/">Home</a>
<a href="/adm/articles">Articles</a>
 (<small>
 <a href="/adm/submitted_articles">Submitted Articles</a>
 <a href="/adm/scrape">Scrape</a> |
 </small>) |
<a href="/adm/journo">Journos</a>
 (<small>
 <a href="/adm/journo-bios">Bios</a> |
 <a href="/adm/journo-email">Email</a> |
 <a href="/adm/journo-split">Split</a> |
 <a href="/adm/journo-merge">Merge</a> |
 <a href="/adm/journo-create">Create</a>
 </small>) |
<a href="/adm/canned">Canned Queries</a> |
<a href="/adm/news">News</a> |
<a href="/adm/useraccounts">User Accounts</a>
 (<small>
 <a href="/adm/claims">Claims</a>
 </small>) |
<a href="/adm/publication">Publications</a>
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
    return sprintf( "<a class=\"journo-info\" href=\"/%s\">%s</a> <small>(<a href=\"/adm/%s\">admin</a>)</small>",
        $ref,
        $prettyname?$prettyname:$ref,
        $ref );
}
