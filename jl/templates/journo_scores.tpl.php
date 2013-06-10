<?php

?>
<div class="main">
<div class="head"><h2>Top <?=sizeof($top_journos) ?> Journalists</h2></div>
<div class="body">
<table>
<thead>
  <tr><th>Journalist</th><th>Score</th><th>Recommendations</th><th>Alerts</th><th>Views (this week)</th></tr>
</thead>
<tbody>
<?php foreach( $top_journos as $j ) { ?>
  <tr>
   <td><?= journo_link( $j ) ?></td>
   <td><?= $j['score']?></td>
   <td><?=$j['num_admirers']?></td>
   <td><?=$j['num_alerts'] ?></td>
   <td><?=$j['num_views_week']?></td>
  </tr>
<?php } ?>
<tbody>
</table>
</div>
<div class="foot"></div>
</div>  <!-- end main -->

<div class="sidebar">
  <div class="box">
    <div class="head"><h3>Tweakage</h3></div>
    <div class="body">

    <form action="" method="get">

<dl>
<dt>
    <label for="recommendations_weight">Recommendations weight</label>
</dt>
<dd>
    <input type="text" id="recommendations_weight" name="recommendations_weight" value="<?=$weights['recommendations'] ?>"/>
</dd>


<dt>
    <label for="alerts_weight">Alerts weight</label>
</dt>
<dd>
    <input type="text" id="alerts_weight" name="alerts_weight" value="<?= $weights['alerts'] ?>"/>
</dd>

<dt>
    <label for="views_week_weight">Weekly views weight</label>
</dt>
<dd>
    <input type="text" id="views_week_weight" name="views_week_weight" value="<?= $weights['views_week'] ?>"/>
</dd>
</dl>
<input type="submit" name="submit" value="Try 'em"/>
    </form>
    </div>
  </div>
</div>

<?php

