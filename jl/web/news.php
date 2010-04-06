<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/markdown.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

$id = get_http_var('id');

$post = null;
if( is_numeric( $id ) ) {
    $post = db_getRow( "SELECT * FROM news WHERE id=? AND status='a'", $id );
} else {
    $post = db_getRow( "SELECT * FROM news WHERE slug=? AND status='a'", $id );
}

$html = Markdown( $post['content'] );

$prettydate = pretty_date(strtotime($post['posted']));

page_header( $post['title'] );

    $news = db_getAll( "SELECT id,slug,title,posted FROM news WHERE status='a' ORDER BY posted DESC LIMIT 50" );

    foreach( $news as &$n ) {
        $n['prettydate'] = pretty_date( strtotime($n['posted']) );
    }
    unset( $n );

?>
<div class="main">
<div class="head"><h2><?= $post['title']; ?></h2></div>
<div class="body">
<?= $html ?>
</div>
<div class="foot">
  <small>(posted by <em><?= $post['author']; ?></em> on <em><?= $prettydate; ?></em>)</small>
</div>
</div> <!-- end main -->


<div class="sidebar">
<div class="box">
 <div class="head"><h3>Site News</h3></div>
 <div class="body">
 <ul>
<?php foreach( $news as $n ) { ?>
  <li><a href="/news/<?= $n['slug'] ?>"><?= $n['title'] ?></a><br/>
    <small><?= $n['prettydate'] ?></small></li>
<?php } ?>
 </ul>
 </div>
<div class="foot"></div>
</div>

</div> <!-- end sidebar -->
<?php


page_footer();

?>
