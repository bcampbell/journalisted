<?php
/*
 * tags.php - the "subject index" page, with tagclouds covering various
 *            periods of time.
 *
 * Most of this page is generated offline and cached.
 * see ../bin/offline-page-build tool for details
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/cache.php';

page_header( 'Subject Index', array( 'menupage'=>'subject') );

// the tag summary is generated offline and never
// updated in response to web access because the queries are so slow.
// (see "../phplib/offline_tags.php")
cache_emit( 'tags', null, null );
page_footer();

?>
