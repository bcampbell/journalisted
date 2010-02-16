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
<div class="head"><h3>Recently Viewed</h3></div>
<div class="body">

<ul>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
</ul>
</div>
</div>

<div class="box recently-updated">
<div class="head"><h3>Recently Updated</h3></div>
<div class="body">

<ul>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
</ul>

</div>
</div>

<div class="box most-blogged">
<div class="head"><h3>Most blogged-about Articles</h3></div>
<div class="body">
<ul>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
<li>blah</li>
</ul>
</div>
</div>

<div style="clear:both;"></div>

<div class="box">
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

</div>  <!-- end main -->
<?php

