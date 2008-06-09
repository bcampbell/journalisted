<?php
// reports.php
//
// catchall page for assorted admin reports
//

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

/* two sets of buttons/action selectors */
$action = get_http_var( 'action' );

admPageHeader();

?>
<h2>Reports</h2>
<ul>
<li><a href="/adm/reports?action=scrapers">Scrapers</a></li>
</ul>
<?php

if( $action=='scrapers' )
{
    EmitScraperReport();
}

admPageFooter();


/********************************/

function FetchScraperStats( $day )
{
    $results = array();
    $r = db_getAll( "SELECT srcorg, COUNT(*) as cnt FROM article WHERE lastscraped::date=?", $day );
}


function EmitScraperReport( $latest_day='today', $days_back=7 )
{
    $date = new DateTime( $latest_day );
    $orgs = get_org_names();

?>
<h3>Articles scraped over the last <?php echo $days_back; ?> days</h3>

<table>
<thead>
<tr><th>date</th><?php

    foreach( $orgs as $orgid=>$orgname )
        print "<th>{$orgname}</th>";

?></tr>
<tbody>
<?php

    for( $i=0; $i<$days_back; ++$i )
    {
        $d = $date->format( 'Y-m-d' );
        $q = db_query( "SELECT srcorg, COUNT(*) AS cnt FROM article WHERE lastscraped::date=? GROUP BY srcorg", $d );
        $cnts = array();
        while( $row= db_fetch_array( $q ) )
        {
            $cnts[ $row['srcorg'] ] = $row['cnt'];
        }

        printf( "<tr><th>%s</th>", $d );
        foreach( $orgs as $orgid=>$orgname )
        {
            $cnt = 0;
            if( array_key_exists( $orgid, $cnts ) )
                $cnt = $cnts[$orgid];
            printf("<td>%d</td>", $cnt );
        }
        print "</tr>\n";
        $date->modify( '-1 days' );
    }

?>
</tbody>
</table>
<?php

}

