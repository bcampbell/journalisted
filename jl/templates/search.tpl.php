<?php
    // search results page

    // q               - query string
    // journos  - 
    // arts -
?>
<div class="main">


  <div class="search">
    <form action="/search" method="get">
      <input type="text" value="<?= h($q) ?>" id="q2" name="q" />
      <input class="" type="submit" alt="search" value="Search" />
      <br/>
      <input type="radio" id="t1" name="type" value="journo" <?=$kind=="journo"?"checked":""?>/><label for="t1">journalists</label>
      <input type="radio" id="t2" name="type" value="article" <?=$kind=="article"?"checked":""?>/><label for="t2">articles</label>

      <input type="radio" id="t3" name="type" value="" <?=$kind==""?"checked":""?>/><label for="t3">both</label>

    </form>
  </div>


<?php
if( $journos !== null ) {
    /**** show journo results ****/
?>

<div class="search-results">
  <div class="head">
      <h4>
          <? if($journos->total==0) { ?>
          no matching journalists
          <? } elseif($journos->total ==1) { ?>
          1 matching journalist
          <? } else { ?>
          <?= $journos->total ?> matching journalists
          <? } ?>
      </h4>
  </div>

  <div class="body">
    <ul>
<?php   foreach( $journos->data as $j ) { ?>
      <li><?= journo_link($j); ?></li>
<?php   } ?>
    </ul>

    <? if ($journos->multi_page()) { ?>
    <div class="paginator">page <?= $journos->paginator()->render(); ?></div>
    <? } ?>
  </div>
  <div class="foot">
  </div>
</div> <!-- end .search-results -->

<?php } /**** end of journo results ****/ ?>


<?php
if( $arts !== null ) {
    /**** show article results ****/
?>
<div class="search-results">
  <div class="head">
      <h4>
          <? if($arts->total==0) { ?>
          no matching articles
          <? } else if($arts->total==1) { ?>
          1 matching article
          <? } else if($arts->total <= $arts->per_page) { ?>
          <?= $arts->total ?> matching articles
          <? } else { ?>
          around <?= $arts->total ?> matching articles
          <? } ?>
    </h4>
    <? if($arts->total>1) { ?>
    <? if($sort_order=='date') { ?>
    <span class="filters">order by: <a href="<?= modify_url(array('o'=>'')) ?>">relevance</a> | date</span>
    <? } else {?>
    <span class="filters">order by: relevance | <a href="<?= modify_url(array('o'=>'date')) ?>">date</a></span>
    <? }?>
    <? }?>
  </div>

  <div class="body">
    <ul class="art-list hfeed">
<?php
    foreach( $arts->data as $art ) {
        $journolinks = array();
        foreach( $art['journos'] as $j ) {
            $journolinks[] = sprintf( "<a href=\"%s\">%s</a>", '/'.$j['ref'], h( $j['prettyname'] ) );
        }
?>
      <li class="hentry">
        <h4 class="entry-title"><a class="extlink" href="<?= $art['permalink']; ?>"><?= $art['title']; ?></a></h4>
        <?php if( $journolinks ) { ?><small><?= implode( ', ', $journolinks ); ?></small><br/><?php } ?>
        <span class="publication"><?= $art['srcorgname']; ?>,</span>
        <abbr class="published" title="<?= $art['iso_pubdate']; ?>"><?= $art['pretty_pubdate']; ?></abbr>
        <br/>
        <?php if( $art['id'] ) { ?> <a href="<?= article_url($art['id']);?>">More about this article</a><br/> <?php } ?>
      </li>
<?php } ?>
    </ul>
    <? if ($arts->multi_page()) { ?>
    <div class="paginator">page <?= $arts->paginator()->render(); ?></div>
    <? } ?>
  </div>
  <div class="foot">
  </div>

</div> <!-- end .search-results -->
<?php } /**** end of article results ****/ ?>

</div>  <!-- end .main -->
