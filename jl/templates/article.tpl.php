<?php
/*
 Template for article page
 $article_id        -
 $id36              - base-36 id, used in url
 $title             - article headline
 $srcorgname        - name of original source of article eg "The Sun"
 $iso_pubdate       - machine-readable publication date eg "2007-12-28T22:35:00+00:00"
 $pretty_pubdate    - human-readable publication date eg "Fri 28 December 2007"
 $byline            - might be hyperlinked eg "by <a href="/fred-smith>Fred Smith</a>"
 $description       - description of article (eg first paragraph)
 $buzz              - eg "15 comments"
 $permalink         - original url of article

 $journos           - array of journos attributed to this article

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


$journo_links = array();
foreach( $journos as $j ) {
    $journo_links[]  = sprintf( '<a href="/%s">%s</a>', $j['ref'], $j['prettyname'] );
}
?>



<div class="main article-summary">

<div class="hentry">

  <div class="cutting">
    <div class="head">
      <abbr class="published" title="<?= $iso_pubdate ?>"><?= $pretty_pubdate ?></abbr>,
      <span class="publication"><?= $srcorgname ?></span>
      <h2 class="entry-title"><?= $title; ?></h2>
    </div>
    <div class="body">
<?php if( $journo_links ) { ?>
      By <?= pretty_implode( $journo_links ) ?><br/>
<?php } ?>
      <blockquote class="entry-summary">
        <?= $description ?>
      </blockquote>
    </div>
  </div>


  <div class="art-info">
    <a class="extlink" href="<?= $permalink ?>" >Read the original article</a>.<br/>
    It was published by
    <span class="published-by vcard"><a class="fn org url extlink" href="<?= $srcorg_url ?>"><?= $srcorgname ?></a></span>,
    which adheres to the <a rel="statement-of-principles" class="extlink" href="<?= $sop_url;?>"><?= $sop_name ?></a>
  </div>

</div>  <!-- end hentry -->



<div class="box">
  <div class="head"><h3>Similar articles</h3></div>
  <div class="body">
    <small>(<a href="/faq/how-does-journalisted-work-out-what-articles-are-similar">what's this?</a>)</small>


    <p>
<?php if( $sim_orderby=='date' ) { ?>
      ordered by date (<a href="<?= article_url( $article_id, 'score', $sim_showall ); ?>">order by similarity</a>)
<?php } else { ?>
      ordered by similarity (<a href="<?= article_url( $article_id, 'date', $sim_showall ); ?>">order by date</a>)
<?php } ?>
    </p>

    <ul class="art-list">
<?php foreach( $sim_arts as $art ) { ?>
      <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
      </li>
<?php /*
    <a href="<?php echo article_url( $sim_art['id'] );?>"><?php echo $sim_art['title'];?></a>
        <?php echo BuzzFragment($sim_art); ?><br />
        <?php print PostedFragment($sim_art); ?>
        <small><?php echo $sim_art['byline'];?></small>
  </li>
*/ ?>
<?php } ?>
    </ul>

  </div>
  <div class="pager">
<?php if( $sim_showall != 'yes' ) { ?>
    <a href="<?= article_url( $article_id, $sim_orderby, 'yes' ); ?>">Show all similar articles</a>
<?php } else { ?>
    <a href="<?= article_url( $article_id, $sim_orderby, 'no' ); ?>">Show first 10 only</a>
<?php } ?>
  </div>
  <div class="foot"></div>
</div>


</div> <!-- end main -->


<div class="sidebar">



<div class="box">
  <div class="head"><h3>Feedback on the web</h3></div>
  <div class="body">
    <h4>Comments about this article</h4>
<?php if( $comment_links ) { ?>
    <ul>
      <?php foreach( $comment_links as $c ) { ?><li>
        <?= $c['source_prettyname'] ?> (<a class="extlink" href="<?= $c['comment_url'] ?>"><?= $c['buzz'] ?></a>)
      </li><?php } ?>
    </ul>
<?php } else { ?>
    <p>None known</p>
<?php } ?>
    <h4>Blogs linking to this article</h4>
<?php if( $blog_links ) { ?>
    <p><?= sizeof( $blog_links ) ?> blog posts link to this article:</p>
    <ul>
    <?php foreach( $blog_links as $bl ) { ?>
        <li><?= article_gen_bloglink( $bl ) ?></li>
    <?php } ?>
    </ul>
<?php } else { ?>
    <p>None known</p>
<?php } ?>
    <p class="disclaimer">Based on blogs recorded by
    <a class="extlink" href="http://technorati.com">Technorati</a> and
    <a class="extlink" href="http://www.icerocket.com">IceRocket</a></p>
  </div>
  <div class="foot"></div>
</div>


<div class="box tags">
  <div class="head"><h3>Topics mentioned</h3></div>
  <div class="body">
<?php tag_display_cloud( $tags ); ?>
  </div>
  <div class="foot"></div>
</div>


</div> <!-- end sidebar -->

