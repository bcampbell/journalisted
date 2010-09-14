<?php

/*
Template for showing a publication

 values available are:

*/

?>


<div class="main">
<div class="head"><h2><?= $prettyname ?></h2></div>
<div class="body">

<p>
Homepage: <a href="<?= $home_url ?>"><?= $home_url ?></a>
</p>

<h3>Recently published journalists</h3>

<p>Journalists published by <?= $prettyname ?> over the last week:</p>
<ul>
<?php foreach( $recent_journos as $j ) { ?>
  <li><?= journo_link( $j ) ?></li>
<?php } ?>
</ul>

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

