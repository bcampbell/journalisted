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
require_once 'otherarticle_widget.php';



admPageHeader( "Other Articles", "ExtraHead" );
$status = get_http_var('status','unapproved');
?>
<h2>Other articles</h2>

<form method="post" action="">
Show:
  <select name="status">
    <option <?php echo ($status=='')?'selected ':''; ?>value="">All</option>
    <option <?php echo ($status=='unapproved')?'selected ':''; ?>value="unapproved">Unapproved</option>
    <option <?php echo ($status=='approved')?'selected ':''; ?>value="approved">Approved</option>
  </select>
  <input type="submit" name="submit" value="Filter" />
</form>
<?php

$rows = OtherArticleWidget::fetch_lots($status);
foreach( $rows as $r ) {
    $w = new OtherArticleWidget( $r );
    $w->emit_full();
}
admPageFooter();



function ExtraHead()
{
    OtherArticleWidget::emit_head_js();
}





