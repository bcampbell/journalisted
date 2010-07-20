<?php

require_once '../conf/general';
require_once '../../phplib/person.php';
require_once 'gatso.php';
require_once 'misc.php';

function page_header( $title, $params=array() )
{
    header( 'Content-Type: text/html; charset=utf-8' );

    if( $title )
        $title .= ' - ' . OPTION_WEB_DOMAIN;
    else
        $title = OPTION_WEB_DOMAIN;

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */

    $datestring = date( 'l d.m.Y' );

    $mnpage = array_key_exists('menupage', $params) ? $params['menupage'] : '';

    $rss_feeds = array();
    if (array_key_exists('rss', $params))
        $rss_feeds = $params['rss'];

    $canonical_url = null;
    if (array_key_exists('canonical_url', $params))
        $canonical_url = $params['canonical_url'];

    $js_files = array( "/jl.js" );
    if (array_key_exists('js_extra', $params))
        $js_files = array_merge( $js_files, $params['js_extra'] );

    $head_extra = '';
    if (array_key_exists('head_extra', $params))
        $head_extra .= $params['head_extra'];

    if (array_key_exists('head_extra_fn', $params)) {
        ob_start();
        call_user_func( $params['head_extra_fn'] );
        $head_extra .= ob_get_contents();
        ob_end_clean();
    }

    $logged_in_user = null;
    $can_edit_profile = FALSE;
    if( $P )
    {
        if ($P->name_or_blank())
            $logged_in_user = $P->name;
        else
            $logged_in_user = $P->email;

        if( db_getOne( "SELECT * FROM person_permission WHERE person_id=? AND permission='edit'", $P->id() ) )
            $can_edit_profile = TRUE;
    }

    $search = array( 'q'=>'', 'type'=>'journo' );
    if (array_key_exists('search_params', $params))
        $search = $params['search_params'];

    include "../templates/header.tpl.php";
}



function page_footer()
{
    include "../templates/footer.tpl.php";
}

?>
