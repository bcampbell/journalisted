<?
/*
 * index.php:
 * Admin pages for PledgeBank.
 * 
 * Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: index.php,v 1.13 2006/07/10 10:20:53 francis Exp $
 * 
 */

//set_include_path( dirname( dirname( __FILE__ )) . ':' . get_include_path() );

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once "../conf/general";
require_once "../phplib/admin-jl.php";
require_once "../phplib/admin-jl-summary.php";
require_once "../phplib/admin-jl-dupes.php";
require_once "../phplib/admin-jl-checkscrapers.php";
require_once "../../phplib/template.php";
require_once "../../phplib/admin-phpinfo.php";
require_once "../../phplib/admin-serverinfo.php";
require_once "../../phplib/admin-configinfo.php";
require_once "../../phplib/admin.php";

$pages = array(
	new ADMIN_PAGE_JL_SUMMARY,
	new ADMIN_PAGE_JL_ARTICLES,
	new ADMIN_PAGE_JL_ARTICLE,
	new ADMIN_PAGE_JL_CHECKSCRAPERS,
	new ADMIN_PAGE_JL_DUPES,
	null,
    new ADMIN_PAGE_SERVERINFO,
    new ADMIN_PAGE_CONFIGINFO,
    new ADMIN_PAGE_PHPINFO,
);


jl_admin_page_display(str_replace("http://", "", OPTION_BASE_URL), $pages, new ADMIN_PAGE_JL_ARTICLES );


function jl_admin_page_display($site_name, $pages ) {
    $maintitle = "$site_name admin";
    $id = get_http_var("page");
	if( !$id )
		$id = $pages[0]->id;
	foreach ($pages as $page) {
		if (isset($page) && $page->id == $id) {
			break;
		}
	}

	// display
	ob_start();
	if (isset($page->contenttype)) {
		header($page->contenttype);
	} else {
		header("Content-Type: text/html; charset=utf-8");
		$title = $page->navname . " - $maintitle";
		admin_html_header($title);

		jl_admin_show_navbar( $pages );

		print "<h1>$title</h1>\n";
	}
	$self_link = "?page=$id";
	$page->self_link = $self_link;
	$page->display($self_link); # TODO remove this as parameter, use class member
	if (!isset($page->contenttype)) {
		admin_html_footer();
	}
}

function jl_admin_show_navbar( &$pages ) {
	// generate navigation bar
	$navlinks = "";
	foreach ($pages as $page) {
		if (isset($page) && !isset($page->notnavbar)) {
			if (isset($page->url)) {
				$navlinks .= "<a href=\"". $page->url."\">" . $page->navname. "</a> |";
			} else {
				$navlinks .= "<a href=\"?page=". $page->id."\">" . $page->navname. "</a> |";
			}
		} else {
			$navlinks .= '';
		}
	}
	$navlinks .= '';
	print $navlinks;
	print "\n";
}

?>
