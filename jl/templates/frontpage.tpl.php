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


?>
<div class="greenbox">
Journa<i>listed</i> is an independent, non-profit site run by the <a class="extlink" href="http://www.mediastandardstrust.org">Media Standards Trust</a> to help the public navigate the news
</div>

<div class="maincolumn">

<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">

   <form action="/list" method="get" class="frontform">
    <label for="name">Find a journalist</label>
    <input type="text" value="" title="type journalist name here" id="name" name="name" placeholder="type journalist name here" class="text" />
    <input type="submit" value="Find" alt="find" />
<!--    <input type="image" src="images/white_arrow.png" alt="find" /> -->
   </form>

   <form action="/list" method="get" class="frontform">
    <label for="outlet">See journalists by news outlet</label>

     <select id="outlet" name="outlet" class="select" >
<?php foreach( $orgs as $o ) { ?>
		<option value="<?php echo $o['shortname'];?>"><?php echo $o['prettyname']; ?></option>
<?php } ?>
     </select>
    <input type="submit" value="Find" alt="find" />
   </form>


   <form action="/search" method="get" class="frontform">
    <label for="q">Search articles</label>
    <input type="text" value="" title="type subject here" id="q" name="q" class="text" placeholder="type subject here"/>
<!--    <input type="submit" value="Find" /><br /> -->
    <input type="submit" value="Find" alt="find" />
   </form>

  </div>
 <div class="action-box_bottom"><div></div></div>
</div>

<p>Journa<i>listed</i> is also for: &nbsp;&nbsp;&nbsp;<a href="/forjournos">journalists</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="/pressofficers">press officers</a></p>

</p>

</div>  <!-- end maincolumn -->
<div class="smallcolumn">
<div class="box">
 <div class="head"><h3>Recent changes</h3></div>
 <div class="body">
 <ul>
<?php foreach( $events as $ev ) { ?>
  <li><a href="<?= $ev['journo_ref'] ?>"><?= $ev['journo_prettyname']; ?></a>: <?= $ev['description'] ?></li>
<?php } ?>
 </ul>
 </div>
</div>
</div> <!-- end smallcolumn -->
<?php

