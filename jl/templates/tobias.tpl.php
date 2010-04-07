<?php

/*
 * special case version of journo.tpl.php for Tobias Grubbe
 */


$MAX_ARTICLES = 5;  /* how many articles to show on journo page by default */


/* build up a list of _current_ employers */
$current_employment = array();
foreach( $employers as $emp ) {
    if( $emp['current'] )
        $current_employment[] = $emp;
}

/* list of previous employers (just employer name, nothing else) */
$previous_employers = array();
foreach( $employers as $emp ) {
    if( !$emp['current'] )
        $previous_employers[] = $emp['employer'];
}
$previous_employers = array_unique( $previous_employers );

?>

<?php if( $can_edit_page && $status != 'a' ) { ?>
<div class="not-public">
  <p><strong>Please Note:</strong>
  Your page is not yet publicly accessible.
  It will be switched on once you have <a href="/missing?j=<?= $ref ?>">added</a> five articles.
  </p>
</div>
<?php } ?>


<div class="main journo-profile">
<div class="head"></div>
<div class="body">


<div class="overview">
  <div class="head"><h2><a href="<?= $rssurl; ?>"><img src="/images/rss.gif" alt="RSS feed" border="0" align="right"></a><?= $prettyname; ?></h2></div>
  <div class="body">

    <div class="photo">
<?php if( $picture ) { ?>
      <img src="<?= $picture['url']; ?>" alt="photo" width="<?= $picture['width'] ?>" height="<?= $picture['height']; ?>" />
<?php } else { ?>
      <img width="135" height="135" src="/img/rupe.png" alt="no photo" />
<?php } ?>
  <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_photo?ref=<?= $ref ?>">edit</a><?php } ?>
    </div>

    <div class="fudge">
          Born - 1667. I am fully occupied writing Essays for the
          broadsheets and news webs, as well as in certain Speculative
                                                                           Journalisted weekly digest
          Undertakings, and in missions for Her Majestie’s Government of
His photo a secret nature. However it is a truth that it is easier to find
          Commissions than to be paid afterward for them
    </div> <!-- end fudge -->
    <div style="clear: both;"></div>

  </div>
</div>  <!-- end overview -->


<?php /* TAB SECTIONS START HERE */ ?>


<div class="tabs">
<ul>
<li><a href="#tab-work">Work</a></li>
<li><a href="#tab-bio">Biography</a></li>
<li><a href="#tab-contact">Contact</a></li>
</ul>
</div>


<div class="tab-content" id="tab-work">

<div class="">
  <div class="head"><h3>Tobias Grubbe's latest adventures</h3></div>
  <div class="body">
  </div>
  <div class="foot"></div>
</div>

<div class="previous-articles">
  <div class="head">
    <h3>Recent articles</h3>
  </div>
  <div class="body">

  <ul class="art-list">

<?php $n=0; foreach( $articles as $art ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<?php } ?><br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
    </li>
<?php ++$n; if( $n>=$MAX_ARTICLES ) break; } ?>
<?php if( !$articles ) { ?>
  <p>None known</p>
<?php } ?>

  </ul>

<?php if($more_articles) { ?>
  (<a href="/<?= $ref ?>?allarticles=yes">Show all articles</a>)
<?php } ?>

  </div>
  <div class="foot"></div>
</div>




</div> <!-- end work tab -->




<div class="tab-content bio" id="tab-bio">


<div id="experience" class="experience">
  <div class="head">
    <h3>Experience</h3>
  </div>
  <div class="body">
    <ul class="bio-list">
      <li><h4>Apren-ticed to the Counting House of Lord Aitchboss</h4></li>
      <li><h4>Clerk to the Counting House of Lord Aitchboss</h4></li>
    </ul>
  </div>
</div>


<div class="education">
  <div class="head">
    <h3>Education</h3>
  </div>
  <div class="body">
    <ul class="bio-list">
      <li>
        <h4>I went to School, where I learned some Latin, some Numbers and many Letters</h4>
      </li>
    </ul>
  </div>
</div>


<div class="books">
  <div class="head">
    <h3>Books by <?= $prettyname ?></h3>
  </div>
  <div class="body">
    <ul class="bio-list">
      <li>
        <h4>The Concealed Truth Concerning Global Cooling and Sundry other Conspiracies</h4>
        (Printed by <i>Master Bullstrode</i>, at the Sign of the Saracen's Cheek, St Paul's.)
      </li>
    </ul>
  </div>
</div>


<div class="awards">
  <div class="head">
    <h3>Awards won</h3>
  </div>
  <div class="body">
    <p class="not-known">Tobias Grubbe hasn't won any awards</p>
  </div>
</div>



</div> <!-- end bio tab -->



<div class="tab-content contact" id="tab-contact">


<div class="">
  <div class="head">
    <h3>The authors of Tobias Grubbe</h3>
  </div>
  <div class="body">
    <p>...details here...</p>
  </div>
</div>


<div class="">
  <div class="head">
    <h3>The patrons of Tobias Grubbe</h3>
  </div>
  <div class="body">
      <p>
    <?= SafeMailto( "martin.moore@mediastandardstrust.org" );?>
      <br/>
      +44 20 7727 5252
    </p>
  </div>
</div>


</div> <!-- end contact tab -->
</div> <?php /* end main body */ ?>
<div class="foot"></div>
</div> <!-- end main -->




<div class="sidebar">

<a class="donate" href="http://www.justgiving.com/mediastandardstrust">Donate</a>

<div class="box subscribe-newsletter">
  <div class="head"><h3>journa<i>listed</i> weekly digest</h3></div>
  <div class="body">
    <p>To receive the journa<i>listed</i> digest every Tuesday via email, <a href="/weeklydigest">subscribe here</a></p>
  </div>
  <div class="foot"></div>
</div>


<div class="box actions">
  <div class="head"><h3>What can you do on Journalisted?</h3></div>
  <div class="body">
  <ul>
  <li>Build your journalist CV</li>
  <li><a href="/search?type=journo">Find</a> contact details for a journalist</li>
  <li><a href="/search?type=article">Search</a> over 2 million news articles</li>
  </div>
  <div class="foot"></div>
</div>

<div class="box actions you-can-also">
  <div class="head"><h3>You can also...</h3></div>
  <div class="body">
    <ul>
      <li class="add-alert"><a href="/alert?Add=1&amp;j=<?= $ref ?>">Add <?= $prettyname ?>'s articles to my daily alerts</a></li>
      <li class="print-page"><a href="#" onclick="javascript:window.print(); return false;" >Print this page</a></li>
<?php /*      <li class="forward-profile"><a href="#">Forward profile to a friend</a></li> */ ?>
<?php if( !$can_edit_page ) { ?>
      <li class="claim-profile">
        <a href="/profile?ref=<?= $ref ?>">Are you <?= $prettyname ?>?</a></li>
<?php } ?>
    </ul>
  </div>
  <div class="foot"></div>
</div>


<div class="box links">
  <div class="head"><h3><?= $prettyname ?> on the web</h3></div>
  <div class="body">
<?php if( $links ) { ?>
    <ul>
<?php foreach( $links as $l ) { ?>
       <li><a class="extlink" href="<?= $l['url'] ?>"><?= $l['description'] ?></a></li>
<?php } ?>
    </ul>
<?php } else { ?>
  <span class="not-known">No links known</span>
<?php } ?>
  </div>
  <div class="foot">
    <?php if( $can_edit_page ) { ?>
    <a class="edit" href="/profile_weblinks?ref=<?= $ref ?>">edit</a>
    <?php } ?>
  </div>
</div>




<div class="box admired-journos">
 <div class="head"><h3>Journalists more popular than <?= $prettyname ?></h3></div>
 <div class="body">
<?php if( $admired ) { ?>
  <ul>
<?php foreach( $admired as $a ) { ?>
   <li><?=journo_link($a) ?></li>
<?php } ?>
  </ul>
<?php } else { ?>
  <span class="not-known"><?= $prettyname ?> has not added any journalists</span>
<?php } ?>
 </div>
 <div class="foot">
<?php if( $can_edit_page ) { ?>
  <a class="edit" href="/profile_admired?ref=<?= $ref ?>">edit</a>
<?php } ?>
 </div>
</div>

</div>
</div> <!-- end sidebar -->

