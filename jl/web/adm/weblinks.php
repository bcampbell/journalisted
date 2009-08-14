<?php
// admin page for managing submitted web links for journos

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once 'weblink_widget.php';



admPageHeader( "Web Links", "ExtraHead" );
$status = get_http_var('status','unapproved');
?>
<h2>Web Links</h2>
<p>Web links for journos</p>

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

$rows = WeblinkWidget::fetch_lots(null,$status);
foreach( $rows as $r ) {
    $w = new WeblinkWidget( $r );
    $w->emit_full();
}
admPageFooter();



function ExtraHead()
{
    WeblinkWidget::emit_head_js();
}





