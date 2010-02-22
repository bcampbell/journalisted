<?php

// template for site front page
//
// params:
//
// $orgs - list of organisations the site covers
//  for each entry
//   shortname   - the internal name eg 'thesun'
//   prettyname  - eg "The Sun"
//
// $news - list of recent news entries
//
//


?>
<div class="main front-page">

<div class="box front">
  <span class="brag">140,000 people used journa<i>listed</i> last month!</span>
  <span class="youcan">At journa<i>listed</i> you can:</span>
  <ul class="nav">
   <li><a class="articles" href="#">articles</a></li>
   <li><a class="link" href="#">link</a></li>
   <li><a class="rate" href="#">rate</a></li>
   <li><a class="search" href="#">search</a></li>
   <li><a class="subjects" href="#">subjects</a></li>
  </ul>
</div>


<div class="box recently-viewed">
<div class="head"><h3>Recently viewed</h3></div>
<div class="body">

<ul>
<?php foreach( $recently_viewed as $j ) { ?>
  <li><a href="/<?= $j['ref'] ?>"><?= $j['prettyname'] ?></a></li>
<?php } ?>
</ul>
</div>
</div>

<div class="box recently-updated">
<div class="head"><h3>Recently updated</h3></div>
<div class="body">

<ul>
<?php foreach( $events as $e ) { ?>
  <li><a href="/<?= $e['journo_ref'] ?>"><?= $e['journo_prettyname'] ?></a></li>
<?php } ?>
</ul>

</div>
</div>

<div class="box most-blogged">
<div class="head"><h3>Most blogged-about articles</h3></div>
<div class="body">
  <ul>
<?php foreach( $most_blogged_about as $art ) { ?>
      <li><a href="<?php echo article_url( $art['id'] ); ?>"><?php echo $art['title']; ?></a> (<?php echo $art['total_bloglinks'];?> blogs)
      </li>
<?php } ?>
  </ul>
</div>
</div>

<div style="clear:both;"></div>
<?php /* ?>
<div class="box thisweek">
<div class="head"><h3>This Week on Journalisted</h3></div>
<div class="body">
 <ul>
<?php foreach( $news as $n ) { ?>
  <li><a href="/news/<?= $n['slug'] ?>"><?= $n['title'] ?></a><br/>
    <small><?= $n['prettydate'] ?></small></li>
<?php } ?>
 </ul>
</div>
</div>
<?php */ ?>
</div>  <!-- end main -->
<?php

