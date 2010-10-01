<?php

/*
Template for showing a publication

 values available are:

*/

?>


<div class="main publication-profile vcard">
<div class="head"><h2 class="fn org"><?= $prettyname ?></h2></div>
<div class="body">

<dl>
<dt>Homepage:</dt>
<dd><a class="url extlink" href="<?= $home_url ?>"><?= $home_url ?></a></dd>

<?php if( $adr ) { ?>
<dt>Address:</dt>
<dd class="adr">
<?php
foreach( vcard_adr_fields() as $f ) {
    if( array_key_exists( $f, $adr ) ) {
?>
<span class="<?= $f ?>"><?= h($adr[$f]) ?></span><br/>
<?php
    }
}
?>
</dd>
<?php } ?>

<?php if( $tel ) { ?>
<dt>Telephone:</dt>
<dd class="tel"><?= $tel ?></dd>
<?php } ?>

<?php if( $principles ) { ?>
<dt>Statement of principles:</dt>
<dd><a href="<?= $principles['url'] ?>"><?= h($principles['name']) ?></a></dd>
<?php } ?>
</dl>



<h3>Journalists published by <?= $prettyname ?> over the last week</h3>

<div style="-moz-column-count: 3; -webkit-column-count: 3;">

<?php if( $recent_journos ) { ?>
<ul>
<?php foreach( $recent_journos as $j ) { ?>
  <li><?= journo_link( $j ) ?></li>
<?php } ?>
</ul>
<?php } else { ?>
<p class="not-known">None known</p>
<?php } ?>

</div>

<?php if( 0 ) { ?>
<h3>Recent Articles</h3>
  <ul class="art-list">

<?php foreach( $recent_articles as $art ) { ?>
    <li>
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr><br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
    </li>
<?php } ?>
  </ul>

<?php } ?>

</div>
<div class="foot"></div>
</div> <!-- end main -->


<div class="sidebar">

<a class="donate" href="http://www.justgiving.com/mediastandardstrust">Donate</a>

<div class="box subscribe-newsletter">
  <div class="head"><h3>journa<i>listed</i> weekly</h3></div>
  <div class="body">
    <p>To receive the journa<i>listed</i> digest every Tuesday via email, <a href="/weeklydigest">subscribe here</a></p>
  </div>
  <div class="foot"></div>
</div>




</div> <!-- end sidebar -->

