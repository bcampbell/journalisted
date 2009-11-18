<?php
/*
 Template for article page

 $title             - article headline
 $srcorgname        - name of original source of article eg "The Sun"
 $iso_pubdate       - machine-readable publication date eg "2007-12-28T22:35:00+00:00"
 $pretty_pubdate    - human-readable publication date eg "Fri 28 December 2007"
 $byline            - might be hyperlinked eg "by <a href="/fred-smith>Fred Smith</a>"
 $description       - description of article (eg first paragraph)
 $buzz              - eg "15 comments"
 $permalink         - original url of article

 $srcorgurl         - homepage of source organisation (eg "http://dailymail.com")
 $sop_url           - url of statement of principles
 $sop_name          - name of statement of principles

 $blog_links       - list of blog posts which reference this article
    nearestpermalink - url of blog post (will be same as blogurl if we don't have a proper permalink)
    blogname        - name of blog
    blogurl         - homepage of blog
    posted          - when the blog was posted


 $sim_arts          - list of similar articles
    id
    title
    srcorgname
    byline
    ...etc...
    
 $sim_total         - total number of similar articles ($sim_arts might be just the first few)
 $sim_showall       - are we showing all of them?
 $sim_orderby       - how the sim_arts is ordered ("date" or "score")
 
 $tags              - array of tag=>frequency pairs

 $comment_links
   source            - eg "digg", "guardian"
   source_prettyname - eg "Digg", "The Guardian"
   comment_url       - url on source site
   num_comments      - null if source doesn't have comments
   score             - eg number of diggs (null if source doesn't have a score metric)
   buzz              - eg "10 diggs, 15 comments"   "20 votes, 4 comments"   "14 comments"
*/
?>

<div class="maincolumn">

<div class="box clipping hentry">
 <div class="head"><h2 class="entry-title"><?= $title; ?></h2></div>
 <div class="body">
  <span class="publication"><?= $srcorgname ?>,</span>
  <abbr class="published" title="<?= $iso_pubdate ?>"><?= $pretty_pubdate ?></abbr>,
  <?= $byline; ?><br/>
  <blockquote class="entry-summary">
    <?= $description ?>
  </blockquote>
  <div class="art-info">
    <?php if( $buzz ) { ?> (<?= $buzz ?>)<br /> <?php } ?>
    <a class="extlink" href="<?= $permalink ?>" >Original article at <?= $srcorgname ?></a><br/>
  </div>
  (This article was originally published by
  <span class="published-by vcard"><a class="fn org url extlink" href="<?= $srcorg_url ?>"><?= $srcorgname ?></a></span>,
  which adheres to the <a rel="statement-of-principles" class="extlink" href="<?= $sop_url;?>"><?= $sop_name ?></a>)<br/>
 </div>
</div>



<div class="box tags">
  <div class="head"><h2>Subjects mentioned</h2></div>
  <div class="body">
<?php tag_display_cloud( $tags ); ?>
  </div>
</div>



<div class="box">
<div class="head"><h2>Similar articles</h2></div>
<div class="body">
<small>(<a class="tooltip" href="/faq/how-does-journalisted-work-out-what-articles-are-similar">what's this?</a>)</small>


<p>
<?php if( $sim_orderby=='date' ) { ?>
  ordered by date (<a href="<?= article_url( $article_id, 'score', $sim_showall ); ?>">order by similarity</a>)
<?php } else { ?>
  ordered by similarity (<a href="<?= article_url( $article_id, 'date', $sim_showall ); ?>">order by date</a>)
<?php } ?>
</p>

<ul>
<?php foreach( $sim_arts as $sim_art ) { ?>
  <li>
    <a href="<?php echo article_url( $sim_art['id'] );?>"><?php echo $sim_art['title'];?></a>
        <?php /*TODO: expand these fns out into template! */ echo BuzzFragment($sim_art); ?><br />
        <?php print PostedFragment($sim_art); ?>
        <small><?php echo $sim_art['byline'];?></small>
  </li>
<?php } ?>
</ul>

<?php if( $sim_total > sizeof( $sim_arts ) && $sim_showall != 'yes' ) { ?>
<a href="<?php echo article_url( $article_id, $sim_orderby, 'yes' ); ?>">Show all <?php print $sim_total; ?> similar articles.</a>
<?php } ?>
</div>
</div>


</div> <!-- end maincolumn -->


<div class="smallcolumn">



<div class="box">
  <div class="head"><h2>Feedback on the web</h2></div>
  <div class="body">
    <h3>Comments about this article</h3>
<?php if( $comment_links ) { ?>
    <ul>
      <?php foreach( $comment_links as $c ) { ?><li>
        <?= $c['source_prettyname'] ?> (<a class="extlink" href="<?= $c['comment_url'] ?>"><?= $c['buzz'] ?></a>)
      </li><?php } ?>
    </ul>
<?php } else { ?>
    <p>None known</p>
<?php } ?>
    <h3>Blogs linking to this article</h3>
<?php if( $blog_links ) { ?>
    <p><?= sizeof( $blog_links ) ?> blog posts link to this article:</p>
    <ul>
    <?php foreach( $blog_links as $bl ) { ?>
        <li><?= gen_bloglink( $bl ) ?></li>
    <?php } ?>
    </ul>
<?php } else { ?>
    <p>None known</p>
<?php } ?>
    <p class="disclaimer">Based on blogs recorded by
    <a class="extlink" href="http://technorati.com">Technorati</a> and
    <a class="extlink" href="http://www.icerocket.com">IceRocket</a></p>
  </div>
</div>

</div>

</div> <!-- end smallcolumn -->

