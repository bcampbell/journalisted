<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

$query = get_http_var( 'q', '' );

if( $query )
    $journos = journo_FuzzyFind( $query );


page_header( "" );

?>
<h2>Find Journalist</h2>

<form method="get" action="/journo_search">
 <input type="text" size="40" name="q" value="<?= h($query) ?>" />
 <input type="submit" name="action" value="Find Journalist" />
</form>

<?php if( $query ) { ?>

<p><?= sizeof($journos) ?> matches:</p>
<ul>
<?php   foreach( $journos as $j ) { ?>
  <li><?= journo_link($j); ?></li>
<?php   } ?>
</ul>
<?php } ?>

<?php

page_footer();
?>
