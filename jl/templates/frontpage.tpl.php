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
<div class="body">
<img src="/img/front.png" alt="" />
</div>
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

