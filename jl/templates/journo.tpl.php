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
    src        - url of image


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
    year_from
    year_to    - eg "2004" or "present"

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
    award          - description of the award (eg "2009 Nobel Prize for Chemistry")

 $articles     - list of the most recent articles the journo has written
   for each one:
    id - database id of article
    title - title of article
    permalink - link to original article
    srcorgname - eg "The Daily Mail"
    iso_pubdate - eg "2007-12-28T22:35:00+00:00"
    pretty_pubdate - eg "Fri 28 December 2007"
    buzz - eg "4 blog posts, 96 comments"

 $quick_n_nasty  - if true, the rest of the fields are not set
                   (used if the data is not cached and we need to throw up something quickly.
                    the affected fields are all a little slower to calculate, so we don't want
                    to be doing it in response to user request)

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

?>


<?php
/*
 * ******** OVERVIEW *************
 */
?>


<div class="box strong-box overview">
  <h2><a href="<?= $rssurl; ?>"><img src="/images/rss.gif" alt="RSS feed" border="0" align="right"></a><?= $prettyname; ?></h2>
  <div class="box-content">

<?php if( $picture ) { ?>
    <img style="float:right; margin: 0.5em; border: 1px solid #cccccc; padding: 0.1em;" src="<?= $picture['src']; ?>" />
<?php } else { ?>
    <img style="float:right; margin: 0.5em; border: 1px solid #cccccc; padding: 0.1em;" src="/images/rupe.gif" />
<?php } ?>


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


<?php if( $known_email ) { /* we've got a known email address - show it! */ ?>
    <li>
        Email <?= $prettyname ?> at: <span class="journo-email"><?= SafeMailTo( $known_email['email'] ); ?></span>
<?php if( $known_email['srcurl'] ) { ?>
        <div class="email-source">(from <a class="extlink" href="<?= $known_email['srcurl'] ?>"><?= $known_email['srcurlname'] ?></a>)</div>
<?php } ?>
    </li>
<?php } ?>


<?php if( $guessed ) { /* show guessed contact details */ ?>
    <li>No email address known for <?= $prettyname; ?>.<br/>
      You could try contacting <span class="publication"><?= $guessed['orgname']; ?></span>
<?php if( $guessed['orgphone'] ) { ?>
      (Telephone: <?= $guessed['orgphone']; ?>)
<?php } ?>
<?php
        if( $guessed['emails'] )
        {
            $safe_emails = array();
            foreach( $guessed['emails'] as $e )
                $safe_emails[] = SafeMailTo( $e );
?>
      <br/>
      Based on the standard email format for <span class="publication"><?php echo $guessed['orgname']; ?></span>, the email address <em>might</em> be <?php echo implode( ' or ', $safe_emails ); ?>.
<?php } ?>
    </li>
<?php } ?>
    </ul>


    <h3>Employment</h3>
    <ul>
<?php
    foreach( $employers as $e ) {
        $to = $e['year_to'];
        if( !$to )
            $to='present';
?>
      <li><em><?= $e['employer']; ?></em>, <?= $e['job_title']; ?>, <?= $e['year_from']; ?>-<?= $e['year_to']; ?></li>
<?php } ?>
    <ul>
    <div style="clear: both;"></div>
  </div>
</div>


<?php
/*
 * ******** MAIN COLUMN *************
 * (contains tabs)
 */
?>

<div id="maincolumn">

<ul class="tabs">
<li><a href="#tab-work"><?= $journo['prettyname']; ?>'s work</a></li>
<li><a href="#tab-bio">Biography</a></li>
<li><a href="#tab-contact">Contact</a></li>
</ul>


<div id="tab-work">


<div class="box">
  <h3>Most Recent article</h3>
  <div class="box-content art-list">
<?php $art = array_shift( $articles ); if( $art ) { ?>
    <div class="hentry">
      <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink'] ?>"><?= $art['title']; ?></a></h4>
      <span class="publication"><?= $art['srcorgname'] ?>,</span>
      <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
      <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<br /> <?php } ?><br/>
      <blockquote class="entry-summary">
        <?= $art['description']; ?>
      </blockquote>

      <div style="clear:both;"></div>

      <div class="art-info">
        <a href="<?= article_url($art['id']);?>">See similar articles</a><br/>
      </div>
    </div>
<?php } else { ?>
  <p>None known</p>
<?php } ?>
  </div>
</div>


<div class="box">
  <h3>Previous Articles</h3>
  <div class="box-content">
  <ul class="art-list">

<?php foreach( $articles as $art ) { ?>
    <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <?php if( $art['buzz'] ) { ?> (<?= $art['buzz']; ?>)<?php } ?><br/>
        <div class="art-info">
          <a href="<?= article_url($art['id']);?>">See similar articles</a><br/>
        </div>
    </li>
<?php } ?>
<?php if( !$articles ) { ?>
  <p>None known</p>
<?php } ?>

  </ul>
  <p class="disclaimer">Published on one or more of <a href="/faq/what-news-outlets-does-journalisted-cover"><?= OPTION_JL_NUM_SITES_COVERED; ?> websites</a>.</p>

<?php if($more_articles) { ?>
  (<a href="/<?= $ref ?>?allarticles=yes">Show all articles</a>)
<?php } ?>

<p>Article(s) missing? If you notice an article is missing,
<a href="/missing?j=<?= $ref ?>">click here</a></p>
</div>
</div>



<div class="box bynumbers">
  <h3><?= $prettyname; ?> by numbers...</h3>
  <div class="box-content">

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


<div class="box friendlystats">
  <h3><?= $prettyname ?> has written...</h3>
  <div class="box-content">
    <ul>
<?php if( !$quick_n_nasty && $toptag_alltime ) { ?>
      <li>More about '<a href ="<?= tag_gen_link( $toptag_alltime, $ref ) ?>"><?= $toptag_alltime ?></a>' than anything else</li>
<?php } ?>
<?php if( !$quick_n_nasty && toptag_month ) { ?>
      <li>A lot about '<a href ="<?= tag_gen_link( $toptag_month, $ref ) ?>"><?= $toptag_month ?></a>' in the last month</li>
<?php } ?>
    </ul>
<?php /* journo_emitBasedDisclaimer(); */ ?>
  </div>
</div>


<div class="box">
  <h3>Education</h3>
  <div class="box-content">
    <ul>
<?php foreach( $education as $edu ) { ?>
      <li>studied <?= $edu['field']; ?> at <?= $edu['school']; ?>, (<?= $edu['year_from']; ?>-<?= $edu['year_to']; ?>)<br/>
      attained <?= $edu['qualification']; ?>
      </li>
<?php } ?>
    </ul>
  </div>
</div>


<div class="box">
  <h3>Books by <?= $prettyname ?></h3>
  <div class="box-content">
    <ul>
<?php foreach( $books as $b ) { ?>
    <li><?= $b['title']; ?> (<?= $b['year_published']; ?>, <?= $b['publisher']; ?>)</li>
<?php } ?>
    </ul>
  </div>
</div>


<div class="box">
  <h3>Awards awarded to <?= $prettyname ?></h3>
  <div class="box-content">
    <ul>
<?php foreach( $awards as $a ) { ?>
    <li><?= $a['award']; ?></li>
<?php } ?>
    </ul>
  </div>
</div>

</div> <!-- end bio tab -->

<div id="tab-contact">
</div> <!-- end contact tab -->

</div> <!-- end maincolumn -->



<?php
/*
 * ******** SMALL COLUMN *************
 */
?>
<div id="smallcolumn">


<div class="action-box">
 <div class="action-box_top"><div></div></div>
  <div class="action-box_content">
        <a href="/alert?Add=1&amp;j=<?= $ref ?>">Email me</a> when <?= $prettyname ?> writes an article
  </div>
 <div class="action-box_bottom"><div></div></div>
</div>


<div class="box links">
  <h3>More useful links for <?= $prettyname ?></h3>
  <div class="box-content">
    <ul>
<?php foreach( $links as $l ) { ?>
       <li><a class="extlink" href="<?= $l['url'] ?>"><?= $l['description'] ?></a></li>
<?php } ?>
    </ul>
    <div class="box-action"><a href="/forjournos?j=<?= $ref ?>">Suggest a link for <?= $prettyname ?></a></div>
    <div style="clear: both;"></div>
  </div>
</div>



<div class="box">
  <h3>The topics <?= $prettyname; ?> mentions most:</h3>
  <div class="box-content">
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



<div class="box">
 <h3>Journalists admired by <?= $prettyname ?></h3>
 <div class="box-content">
  <ul>
<?php foreach( $admired as $a ) { ?>
   <li><?=journo_link($a) ?></li>
<?php } ?>
  </ul>
 </div>
</div>



<div class="box similar-journos">
  <h3>Journalists who write similar articles</h3>
  <small>(<a class="tooltip" href="/faq/how-does-journalisted-work-out-what-journalists-write-similar-stuff">what's this?</a>)</small>
  <div class="box-content">
    <ul>
<?php foreach( $similar_journos as $j ) { ?>
      <li><?=journo_link($j) ?></li>
<?php } ?>
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


</div> <!-- end smallcolumn -->

