<?
// index.php
// blank admin page - just show the admin menu

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

admPageHeader();
admPageFooter();
?>
