<?php
// missingarticles.php
// admin page for scraping submitted articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once 'missingarticle_widget.php';


/*
view basic details
scrape
delete
*/


$ajax = get_http_var( 'ajax' );
if( $ajax )
{
    $widget = get_http_var( 'widget' );
    if( $widget=='missingarticle' ) {
        MissingArticleWidget::dispatch_ajax();
    }

    return;
}


admPageHeader( "Missing Articles", "ExtraHead" );
$sql = "SELECT m.id,m.journo_id, j.ref, j.prettyname, j.oneliner, m.url, m.submitted
    FROM missing_articles m LEFT JOIN journo j ON m.journo_id=j.id
    ORDER BY submitted DESC";
$rows = db_getAll( $sql );
foreach( $rows as $r ) {
    $w = new MissingArticleWidget( $r );
    $w->emit_full();
}
admPageFooter();



function ExtraHead()
{
    MissingArticleWidget::emit_head_js();
}





