<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';


/* return all rows as an associative array indexed by a given field */
function getAllAssoc( $idxfield, $query ) {
    $res = array();

    $a = func_get_args();
    array_shift($a);
    $r = call_user_func_array('db_query', $a);
    while( $row = db_fetch_array( $r ) ) {
        $res[ $row[$idxfield] ] = $row;
    }
    return $res;
 
}


function emitTable( &$table, $columnlabels, $columnindexes=null )
{
	if( $columnindexes == null )
		$columnindexes = $columnlabels;

	print "<table border=\"1\">\n<thead>\n<tr>";
	foreach($columnlabels as $label)
		printf( "<th>%s</th>",$label );
	print "</tr>\n</thead>\n<tbody>";

	foreach( $table as $row ) {
		print "<tr>";
		foreach($columnindexes as $idx) {
			printf( "<td>%s</td>", $row[$idx] );
		}
		print "</tr>\n";
	}
	print "</tbody>\n</table>\n";
}



class ADMIN_PAGE_JL_SUMMARY {
    function ADMIN_PAGE_JL_SUMMARY() {
        $this->id = 'summary';
        $this->navname = 'Summary';
    }

    function display() {
        $orgs = get_org_names();

		printf( "%d Journalists in system", db_getOne( 'SELECT COUNT(*) FROM journo' ) );

		print "<h2>Articles</h2>";
		
        $article_counts = getAllAssoc( 'srcorg', 'SELECT srcorg, count(*) FROM article GROUP BY srcorg' );
        $nonblank_bylines = getAllAssoc( 'srcorg', "SELECT srcorg, count(*) FROM article WHERE byline!='' GROUP BY srcorg" );

		$tab = array();

		$total_articles = 0;
		$total_nonblank_bylines = 0;
		foreach( $orgs as $org_id=>$org_name ) {
            if( array_key_exists( $org_id, $article_counts ) ) {
    			$artcount = $article_counts[$org_id]['count'];
	    		$nonblanks = $nonblank_bylines[$org_id]['count'];
            } else {
                $artcount = 0;
                $nonblanks = 0;
            }

			$valid_byline_percent = 0;
			if( $artcount > 0 )
				$valid_byline_percent = ($nonblanks * 100 ) / $artcount;

			$total_articles += $artcount;
			$total_nonblank_bylines += $nonblanks;
			$row = array(
				'Organisation'=>$org_name,
				'Articles'=>$artcount,
				'With-Valid-Bylines'=>sprintf( "%d (%d%%)",
			   		$nonblanks,
					$valid_byline_percent ),
			);
			$tab[] = $row;
		}

		/* add a row for the totals */
		$tab[] = array(
			'Organisation'=>'<strong>TOTAL</strong>',
	   		'Articles'=>sprintf( '<strong>%d</strong>', $total_articles ),
	   		'With-Valid-Bylines'=>sprintf( "<strong>%d (%d%%)</strong>",
				$total_nonblank_bylines,
				$total_articles==0?0:($total_nonblank_bylines*100)/$total_articles ) );

		emitTable( $tab, array('Organisation','Articles', 'With-Valid-Bylines' ) );

    }
}

