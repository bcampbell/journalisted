<?php

/*
 Template for the main content of the journo page

 values available are:

 $id           - id of journo in database
 $prettyname   - eg "Bob Smith"
 $ref          - eg "bob-smith"
 $oneliner     - oneline description for journo (eg "The Guardian, The Observer")

 $rssurl       - url of RSS feed for this page

 $picture      - array with picture of journo (or null)
    width
    height
    url        - url of image


 $writtenfor   - eg "The Sun, The Mirror and The Daily Telegraph"

 $known_email  - array of known email details (or null)
    email      - email address
    srcurl     - source of address (eg a news article)
    srcurlname - description of source (eg "The Guardian")

 $guessed      - array of guessed contact details (or null)
    orgname    -
    orgphone   -
    emails     - array of email addresses


 $bios         - a list of bios for this journo
   for each one:
    bio        - a short bio
    srcurl     - eg "http://wikipedia.com/bob_smith"
    srcname    - eg "wikipedia"

 $employers    - list of employers journo has worked for
   for each one:
    employer
    job_title
    year_from  - eg "2005"
    year_to    - null if still employed here

 $education    - list of education entries
   for each one:
    school        - name of school
    field         - name of field studied (or '')
    qualification - qualification attained (or '')
    year_from     -
    year_to       -

 $books        - list of books written by this journo
   for each one:
    title          -
    year_published - 
    publisher      - name of publisher eg "Random House"

 $awards       - list of awards won by this journo
   for each one:
    award          - description of the award (eg "Nobel Prize for Chemistry")
    year            - (eg "2009", or NULL)

 $articles     - list of the most recent articles the journo has written
   for each one:
    id - database id of article (NULL if from a site we don't scrape)
    title - title of article
    permalink - link to original article
    srcorgname - eg "The Daily Mail"
    iso_pubdate - eg "2007-12-28T22:35:00+00:00"
    pretty_pubdate - eg "Fri 28 December 2007"
    buzz - eg "4 blog posts, 96 comments"
    total_bloglinks - num of blog posts known to reference this article
    total_comments - num of comments known about this article around the web

 $most_blogged - the most blogged article in the last 6 months
 $most_commented - the most commented-upon article in the last 6 months
   both of these have:
    id
    title
    permalink
    srcorgname
    iso_pubdate
    pretty_pubdate
    total_bloglinks
    total_comments

 $links
    url
    description

 $can_edit_page - TRUE if journo is logged in and can edit this page


 $recent_changes - list of recent changes to the journo's profile
   for each entry:
    description - eg "added a previous employer"

 $quick_n_nasty  - if true, the rest of the fields are not set
                   (used if the data is not cached and we need to throw up something quickly.
                    the affected fields are all a little slower to calculate, so we don't want
                    to be doing it in response to user request)
 ===== non quick_n_nasty fields =====
 $num_articles - number of articles journo has written (only for publications we cover)
 $first_pubdate - date of earliest article we have for this journo eg "May 2007"
 $wc_avg       - average wordcount of this journos articles
 $wc_min       - min wordcount of an article by this journo
 $wc_max       - max wordcount of an article by this journo

 $toptag_alltime - top tag of all time for this journo
 $toptag_month  - top tag over the last month for this journo
 $tags          - list of tags used by this journo
                  as an array of tag=>freq pairs
                  eg array( 'economy'=>65, 'sucks'=>1023 )

*/



/* build up a list of _current_ employers */
$current_employers = array();
foreach( $employers as $emp ) {
    if( !$emp['year_to'] )
        $current_employers[] = $emp;
}



?>


<?php
/*
 * ******** OVERVIEW *************
 */
?>

<div class="maincolumn journo-profile">

<div class="overview">
  <div class="head"><h2><a href="<?= $rssurl; ?>"><img src="/images/rss.gif" alt="RSS feed" border="0" align="right"></a><?= $prettyname; ?></h2></div>
  <div class="body">

  <div class="picture">
<?php if( $picture ) { ?>
    <img src="<?= $picture['url']; ?>" />
<?php } else { ?>
    <img src="/images/rupe.gif" />
<?php } ?>
    <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_picture?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>

    <ul>
<?php foreach($bios as $bio ) { ?>
    <li class="bio-para"><?= $bio['bio']; ?>
      <div class="disclaimer">
        (source: <a class="extlink" href="<?= $bio['srcurl'];?>"><?= $bio['srcname'];?></a>)
      </div>
    </li>
<?php } ?>

<?php if( !$quick_n_nasty && $writtenfor ) { ?>
    <li>
      <?= $prettyname; ?> has written articles published in <?= $writtenfor; ?>.
    </li>
<?php } ?>

<?php foreach( $current_employers as $e ) { ?>
    <li>Current: <span class="jobtitle"><?= $e['job_title'] ?></span> at <span class="publication"><?= $e['employer'] ?></span></li>
<?php } ?>

    <div style="clear: both;"></div>
  </div>
</div>  <!-- end overview -->




<?php /* END OVERVIEW */ ?>

<?php /* TAB SECTIONS START HERE */ ?>

<div style="clear: both;"></div>


<ul class="tabs">
<li><a href="#tab-work"><?= $journo['prettyname']; ?>'s work</a></li>
<li><a href="#tab-bio">Biography</a></li>
<li><a href="#tab-contact">Contact</a></li>
</ul>


<div class="tab" id="tab-work">


<div class="section">
  <div class="head"><h3>Most Recent article</h3></div>
  <div class="body art-list">
<?php $art = array_shift( $articles ); if( $art ) { ?>
    <div class="hentry">
      <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink'] ?>"><?= $art['title']; ?></a></h4>
      <span class="publication"><?= $art['srcorgname'] ?>,</span>
      <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
      <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<br /> <?php } ?><br/>
      <blockquote class="entry-summary">
        <?= $art['description']; ?>
      </blockquote>

      <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">See similar articles</a><br/> <?php } ?>
    </div>
<?php } else { ?>
  <p>None known</p>
<?php } ?>
  </div>
</div>


<div class="section">
  <div class="head"><h3>Previous Articles</h3></div>
  <div class="body">
  <ul class="art-list">

<?php foreach( $articles as $art ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<?php } ?><br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">See similar articles</a><br/> <?php } ?>
    </li>
<?php } ?>
<?php if( !$articles ) { ?>
  <p>None known</p>
<?php } ?>

  </ul>

<?php if($more_articles) { ?>
  (<a href="/<?= $ref ?>?allarticles=yes">Show all articles</a>)
<?php } ?>

<p>Article(s) missing? If you notice an article is missing,
<a href="/missing?j=<?= $ref ?>">click here</a></p>
</div>
</div>



<div class="section bynumbers">
  <div class="head"><h3><?= $prettyname; ?> by numbers...</h3></div>
  <div class="body">

<?php if( !$quick_n_nasty ) { ?>
    <ul>
      <li>
        <?= $num_articles ?> articles <?php if( $num_articles>0) { ?> (since <?= $first_pubdate ?>) <?php } ?>
      </li>
      <li>Average article: <?php printf( "%.0f", $wc_avg/30); ?> column inches (<?php printf( "%.0f", $wc_avg); ?> words)</li>
      <li>Shortest article: <?php printf( "%.0f", $wc_min/30); ?> column inches (<?php printf( "%.0f", $wc_min); ?> words)</li>
      <li>Longest article: <?php printf( "%.0f", $wc_max/30); ?> column inches (<?php printf( "%.0f", $wc_max); ?> words)</li>
    </ul>
    <small>(<a href="/faq/what-are-column-inches">what are column inches?</a>)</small>
<?php } else { ?>
    <p>(sorry, information not currently available)</p>
<?php } ?>
  </div>
</div>




</div> <!-- end work tab -->




<div id="tab-bio">


<div class="section">
  <div class="head"><h3>Experience</h3></div>
  <div class="body">
    <ul>
<?php foreach( $employers as $e ) { ?>
 <?php if( $e['year_to'] ) { ?>
      <li><span class="jobtitle"><?= $e['job_title'] ?></span>, <span class="publication"><?= $e['employer'] ?></span><br/>
        <span class="daterange"><?= $e['year_from'] ?>-<?= $e['year_to'] ?></span></li>
 <?php } else { ?>
      <li class="current-employer" ><span class="jobtitle"><?= $e['job_title'] ?></span>, <span class="publication"><?= $e['employer'] ?></span><br/>
        <span class="daterange"><?= $e['year_from'] ?>-Present</span></li>
 <?php } ?>
<?php } ?>
    </ul>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_employment?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
</div>


<div class="section">
  <div class="head"><h3>Education</h3></div>
  <div class="body">
    <ul>
<?php foreach( $education as $edu ) { ?>
      <li>
        <?= $edu['school']; ?><br/>
        <?= $edu['qualification']; ?>, <?=$edu['field']; ?><br/>
        <span class="daterange"><?= $edu['year_from']; ?>-<?= $edu['year_to']; ?></span><br/>
      </li>
<?php } ?>
    </ul>
    <?php if( $can_edit_page ) { ?><a class="edit" href="/profile_education?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
</div>


<div class="section">
  <div class="head"><h3>Books by <?= $prettyname ?></h3></div>
  <div class="body">
    <ul>
<?php foreach( $books as $b ) { ?>
    <li><?= $b['title']; ?> (<?= $b['publisher']; ?>, <?= $b['year_published']; ?>)</li>
<?php } ?>
    </ul>
    <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_books?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
</div>


<div class="section">
  <div class="head"><h3>Awards Won</h3></div>
  <div class="body">
    <ul>
<?php foreach( $awards as $a ) { ?>
    <li><?php if( $a['year'] ) { ?><?= $a['year'] ?>: <?php } ?><?= $a['award']; ?></li>
<?php } ?>
    </ul>
    <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_awards?ref=<?= $ref ?>">edit</a><?php } ?>
  </div>
</div>



</div> <!-- end bio tab -->



<div id="tab-contact">


<div class="section">
  <div class="head"><h3></h3></div>
  <div class="body">
<?php if( $known_email ) { /* we've got a known email address - show it! */ ?>
    <p>Email <?= $prettyname ?> at: <span class="journo-email"><?= SafeMailTo( $known_email['email'] ); ?></span></p>
<?php if( $known_email['srcurl'] ) { ?>
        <div class="email-source">(from <a class="extlink" href="<?= $known_email['srcurl'] ?>"><?= $known_email['srcurlname'] ?></a>)</div>
<?php } ?>
<?php } ?>


<?php if( $guessed ) { /* show guessed contact details */ ?>
    <p>No email address known for <?= $prettyname; ?>.</p>
    <p>You could try contacting <span class="publication"><?= $guessed['orgname']; ?></span>
    <?php if( $guessed['orgphone'] ) { ?> (Telephone: <?= $guessed['orgphone']; ?>) <?php } ?></p>
<?php
        if( $guessed['emails'] )
        {
            $safe_emails = array();
            foreach( $guessed['emails'] as $e )
                $safe_emails[] = SafeMailTo( $e );
?>
      <p>
      Based on the standard email format for <span class="publication"><?php echo $guessed['orgname']; ?></span>, the email address <em>might</em> be <?php echo implode( ' or ', $safe_emails ); ?>.
<?php } ?>
    </p>
<?php } ?>

<?php if( !$guessed && !$known_email ) { ?>
    <p>Sorry, no contact details known.</p>
<?php } ?>


  </div>
</div>



</div> <!-- end contact tab -->

</div> <!-- end maincolumn -->




<div class="smallcolumn">

<div class="box">
 <div class="head"><h2>Recent changes</h2></div>
 <div class="body">
  <ul>
<?php foreach( $recent_changes as $recent ) { ?>
   <li><?= $recent['description'] ?></li>
<?php } ?>
  </ul>
 </div>
</div>

<div class="box">
<div class="head"><h2></h2></div>
<div class="body">
<ul>
<li><a href="/alert?Add=1&amp;j=<?= $ref ?>">Email me</a> when <?= $prettyname ?> writes an article</li>
<li><a href="<?= $rssurl ?>">RSS Feed</a></li>
<?php if( !$can_edit_page ) { ?><li>
<a href="/profile?ref=<?= $ref ?>">Are you <?= $prettyname ?>?</a></li>
<?php } ?>
<li><a href="#">Report any incorrect information</a></li>
</ul>
</div>
</div>


<div class="action-box">
  <div class="action-box_top"><div></div></div>
  <div class="action-box_content">

    <form action="/search" method="get">
    <p>Find articles by <?= $prettyname ?> containing:</p>
    <input id="findarticles" type="text" name="q" value="" />
    <input type="hidden" name="j" value="<?= $ref ?>" />
    <input type="submit" value="Find" />
    </form>

  </div>
  <div class="action-box_bottom"><div></div></div>
</div>





<?php if( !$quick_n_nasty && $most_blogged ) { ?>
<div class="box">
  <div class="head"><h3>Most blogged-about</h3></div>
  <div class="body">
    <a href="<?= article_url( $most_blogged['id'] );?>"><?= $most_blogged['title'];?></a>
    (<?= $most_blogged['total_bloglinks'] ?> blog posts)
  </div>
</div>
<?php } ?>

<?php if( !$quick_n_nasty && $most_commented ) { ?>
<div class="box">
  <div class="head"><h3>Most commented-on</h3></div>
  <div class="body">
    <a href="<?= article_url( $most_commented['id'] );?>"><?= $most_commented['title'];?></a>
    (<?= $most_commented['total_comments'] ?> comments)
  </div>
</div>
<?php } ?>


<div class="box">
  <div class="head"><h3>Most mentioned topics</h3></div>
  <div class="body">
    <div class="tags">
<?php
    if( !$quick_n_nasty ) {
        tag_cloud_from_getall( $tags, $ref );
    } else {
?>
      <p>(sorry, information not currently available)</p>
<?php
    }
?>
    </div>
  </div>
</div>


<div class="box friendlystats">
  <div class="head"><h3><?= $prettyname ?> has written...</h3></div>
  <div class="body">
    <ul>
<?php if( !$quick_n_nasty && $toptag_alltime ) { ?>
      <li>More about '<a href ="<?= tag_gen_link( $toptag_alltime, $ref ) ?>"><?= $toptag_alltime ?></a>' than anything else</li>
<?php } ?>
<?php if( !$quick_n_nasty && $toptag_month ) { ?>
      <li>A lot about '<a href ="<?= tag_gen_link( $toptag_month, $ref ) ?>"><?= $toptag_month ?></a>' in the last month</li>
<?php } ?>
    </ul>
<?php /* journo_emitBasedDisclaimer(); */ ?>
  </div>
</div>




<div class="box similar-journos">
  <div class="head"><h3>Similar journalists</h3></div>
  <small>(<a class="tooltip" href="/faq/how-does-journalisted-work-out-what-journalists-write-similar-stuff">what's this?</a>)</small>
  <div class="body">
    <ul>
<?php foreach( $similar_journos as $j ) { ?>
      <li><?=journo_link($j) ?></li>
<?php } ?>
    </ul>
  </div>
</div>





<div class="box links">
  <div class="head"><h3><?= $prettyname ?> on the web</h3></div>
  <div class="body">
    <ul>
<?php foreach( $links as $l ) { ?>
       <li><a class="extlink" href="<?= $l['url'] ?>"><?= $l['description'] ?></a></li>
<?php } ?>
    </ul>
    <?php if( $can_edit_page ) { ?>
    <a class="edit" href="/profile_weblinks?ref=<?= $ref ?>">edit</a>
    <?php } else { ?>
    <div class="box-action"><a href="/forjournos?j=<?= $ref ?>">Suggest a link for <?= $prettyname ?></a></div>
    <?php } ?>
    <div style="clear: both;"></div>
  </div>
</div>

<div class="box">
 <div class="head"><h3>Journalists admired by <?= $prettyname ?></h3></div>
 <div class="body">
  <ul>
<?php foreach( $admired as $a ) { ?>
   <li><?=journo_link($a) ?></li>
<?php } ?>
  </ul>
  <?php if( $can_edit_page ) { ?> <a class="edit" href="/profile_admired?ref=<?= $ref ?>">edit</a><?php } ?>
 </div>
</div>

<div class="box">
  <div class="head"><h3>Press contacts</h3></div>
  <div class="body">

  </div>
</div>
</div> <!-- end smallcolumn -->

