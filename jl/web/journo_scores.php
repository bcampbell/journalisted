<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
//require_once '../phplib/misc.php';
require_once '../phplib/journo.php';
require_once '../../phplib/db.php';
//require_once '../../phplib/utility.php';

    $weights = array(
        'alerts' => (float)get_http_var( 'alerts_weight', 1.0 ),
        'recommendations' => (float)get_http_var( 'recommendations_weight', 10.0 ),
        'views_week' => (float)get_http_var( 'views_week_weight', 0.5 )
    );

function cmp_score($a,$b) {
    if($a['score']<$b['score'] ) {
        return 1;
    } elseif($a['score']>$b['score'] ) {
        return -1;
    } else {
        return 0;
    }
}

function calc_score($j)
{
    global $weights;
    // HACK HACK HACK
    $max_admirers = 25.0;
    $max_alerts = 707.0;
    $max_views_week = 263.0;

    $j['score'] =
        ( (float)$weights['recommendations'] * (float)$j['num_admirers']/$max_admirers) +
        ( (float)$weights['alerts'] * (float)$j['num_alerts'] / $max_alerts) +
        ( (float)$weights['views_week'] * (float)$j['num_views_week'] /$max_views_week);

    return $j;
}


function view()
{
    global $weights;

    $sql = <<<EOT
    SELECT * FROM journo j INNER JOIN journo_score s ON s.journo_id=j.id ORDER BY SCORE DESC LIMIT 200
EOT;
    $top_journos = db_getAll( $sql );


    /* recalculate scores  and resort */
    $top_journos = array_map( "calc_score", $top_journos);
    usort( $top_journos, "cmp_score" );


    render(array('top_journos'=>$top_journos,'weights'=>$weights));
}

function render($vars)
{
    page_header("Top 200 journos");
    {
        extract( $vars );
        include "../templates/journo_scores.tpl.php";
    }
    page_footer();
}


view();

?>
