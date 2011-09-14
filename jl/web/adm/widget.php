<?php
/* frontend dispatcher for various widgets, to save lots of messy little php files.
 *  using this file means widgets don't have to care which page they're embedded on
 */

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

require_once 'missingarticle_widget.php';
require_once 'otherarticle_widget.php';
require_once 'weblink_widget.php';
require_once 'submitted_article_widget.php';

if( !admCheckAccess() )
    exit;   // should return error code?

header("Cache-Control: no-cache");

$widget = get_http_var( 'widget' );
switch( $widget )
{
    case 'missingarticle':
        MissingArticleWidget::dispatch();
        break;
    case 'otherarticle':
        OtherArticleWidget::dispatch();
        break;
    case WeblinkWidget::PREFIX:
        WeblinkWidget::dispatch();
        break;
    case SubmittedArticleWidget::PREFIX:
        SubmittedArticleWidget::dispatch();
        break;
}

?>
