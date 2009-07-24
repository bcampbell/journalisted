<?php
/* frontend dispatcher for various ajax requests */

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
//require_once '../phplib/misc.php';
//require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
//require_once '../phplib/adm.php';

require_once 'missingarticle_widget.php';

header("Cache-Control: no-cache");

$widget = get_http_var( 'widget' );
if( $widget=='missingarticle' ) {
    MissingArticleWidget::dispatch_ajax();
}


