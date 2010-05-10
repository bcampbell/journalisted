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
news_AugmentItem( $post );




$content_html = Markdown( $post['content'] );
$prettydate = pretty_date(strtotime($post['posted']));

page_header( $post['title'] );

$news = news_RecentNews( 10 );


?>
<div class="main">
<div class="head">
 <h2><?= $post['title']; ?></h2>
<?php if( $post['kind']=='newsletter' ) { ?>
 for the week ending <?= $post['pretty_to']; ?>
<?php } ?>
</div>
<div class="body">
<?= $content_html ?>
<?php if( $post['kind'] == 'newsletter' ) { ?>
<hr />
<?php } ?>
</div>
<div class="foot">
  <small>(posted by <em><?= $post['author']; ?></em> on <em><?= $prettydate; ?></em>)</small>
</div>
</div> <!-- end main -->


<div class="sidebar">

<div class="box">
 <div class="head"><h3>Archive</h3></div>
 <div class="body">
 <ul>
<?php foreach( $news as $n ) { ?>
  <li>
<?php if( $n['slug']==$post['slug'] ) { ?>
    <em><?= $n['title'] ?></em><br/>
<?php } else { ?>
    <a href="/news/<?= $n['slug'] ?>"><?= $n['title'] ?></a><br/>
<?php } ?>
<?php if( $n['kind']=='newsletter' ) { ?>
    <small>(week ending <?= $n['pretty_to'] ?>)</small>
<?php } ?>
  </li>
<?php } ?>
 </ul>
 </div>
<div class="foot"></div>
</div>


<div class="box subscribe-newsletter">
  <div class="head"><h3>Subscribe to journa<i>listed</i> weekly</h3></div>
  <div class="body">
    <p>To receive the journa<i>listed</i> digest every Tuesday via email, <a href="/weeklydigest">subscribe here</a></p>
  </div>
  <div class="foot"></div>
</div>


<div class="box actions">
 <div class="head"><h3>Also on journa<i>listed</i>...</h3></div>
 <div class="body">
<p><strong>Build your own newsroom of your favourite journalists</strong><br/>
- just add them to your ‘<a href="/alert">alerts</a>’ and, whenever they write an article, we’ll email you a link to it
</p>
<p>
<strong>Edit your own profile</strong><br/>
- if you appear on journalisted, you can <a href="/profile">register</a> to add articles, add links, add biographical and contact information
</p>
<p>
<strong>Search for journalists</strong><br/>
- <a href="/search?type=journo">search</a> journa<i>listed</i>'s database of over 25,000 journalists
</p>
 </div>
<div class="foot"></div>
</div>

</div> <!-- end sidebar -->
<?php


page_footer();

?>
