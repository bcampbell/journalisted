<?php
// scrape-errors.php
//
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
if (get_http_var('submit2'))
    $action = get_http_var( 'action2' );

$filter = get_http_var( 'filter', 'undecided' );
$srcids = get_http_var( 'srcid', '' );

admPageHeader();


if( $srcids && !is_array( $srcids ) && $action=='detail' )
{
?>
<h2>Scraper error details</h2>
<?php
    /* show details for a single article failure */
    EmitDetails( $srcids );
}
else
{
    /* list the failed articles */
?>
<h2>Scraper Errors</h2>
<p>This page lists all the articles the scrapers have failed on.
</p>
<?php
    switch( $action )
    {
    	case "skip":
	    	SetActions( $srcids, 's' );
		    break;
    	case "undecided":
	    	SetActions( $srcids, ' ' );
		    break;
    }


    EmitFilterForm( $filter );
    EmitList($filter);
}

admPageFooter();

/********************************/

function EmitFilterForm( $filter )
{
	$f = array( 'all'=>'all', 'undecided'=>'Undecided', 'skip'=>'Skip' );
?>
<form method="get" action="/adm/scrape-errors">
Show articles marked
<?= form_element_select( "filter", $f, $filter ); ?>
 <input type="submit" name="submit" value="Find" />
</form>
<?php

}




function EmitList( $filter )
{
    $actions = array( '' => 'None',
        'skip' => 'Skip (stop trying to scrape)',
        'undecided' => 'Undecided (Continue trying to scrape)' );

	$whereclause = '';
	if( $filter=='undecided' )
		$whereclause = "WHERE action=' '";
	elseif( $filter=='skip' )
		$whereclause = "WHERE action='s'";

	$sql = <<<EOT
	SELECT * FROM error_articlescrape
        {$whereclause}
        ORDER BY firstattempt DESC
EOT;
	$r = db_query( $sql );

    $cnt = db_num_rows($r);
	printf( "<p>Found %d</p>\n", $cnt );
    if( $cnt == 0 )
        return;

?>
<form method="post" action="/adm/scrape-errors">
<?=form_element_hidden( 'filter', $filter ); ?>

Action (with selected articles):
<?=form_element_select( 'action', $actions, $selected='' ); ?>
<input type="submit" name="submit" value="Do it" />

<table>
<thead>
 <tr>
  <th>First attempt</th>
  <th>URL</th>
  <th>Attempts</th>
  <th>Action</th>
  <th>Select</th>
 </tr>
</thead>
<tbody>
<?php

	while( $row = db_fetch_array( $r ) )
	{
		$firstattempt = strftime('%d-%b-%y %H:%M', strtotime($row['firstattempt']) );

		$srcid = $row['srcid'];
        $attempts = $row['attempts'];

        $action = 'undecided';
        $trclass = 'status_red';
        if( $row['action'] == 's' )
        {
            $action = 'Skip.';
    		$trclass = 'status_green';
        }

		$title = $row['title'];     /* title could be blank */
		$srcurl = $row['srcurl'];
        $link = sprintf( "<a href=\"%s\">%s</a>",
            $srcurl,
            $title ? $title:$srcurl );

        $detailsurl = "/adm/scrape-errors?srcid={$srcid}&action=detail";
        $detailslink = sprintf( "<small>[<a href=\"%s\">details</a>]</small>\n",
            $detailsurl );

		/* checkbox element to select this item... */
		$checkbox = "<input type=\"checkbox\" name=\"srcid[]\" value=\"$srcid\" />";

		print(" <tr class=\"$trclass\">\n" .
		      "  <td>{$firstattempt}</td>" .
		        "<td>{$link}</td>" .
		        "<td>{$attempts} {$detailslink}</td>" .
		        "<td>{$action}</td>" .
		        "<td>{$checkbox}</td>\n" .
		      " </tr>\n");
	}
?>
</tbody>
</table>

Action (with selected articles):
<?=form_element_select( 'action2', $actions, $selected='' ); ?>
<input type="submit" name="submit2" value="Do it" />

</form>
<?php
}


function SetActions( $srcids, $val )
{

    $sqlbits = array();
    $sqlparams = array( $val );
    foreach( $srcids as $id )
    {
        $sqlbits[] = '?';
        $sqlparams[] = $id;
    }

    $sql = "UPDATE error_articlescrape SET action=? WHERE srcid IN (" . implode( ',',$sqlbits ) . ")";

    $cnt = db_do( $sql, $sqlparams );
	db_commit();

	printf( "<div class=\"action_summary\">set %d articles to '%s'</div><br />\n", $cnt, $val=='s'?'skip':'undecided' );
}

function EmitDetails( $srcid )
{
    $row = db_getRow( "SELECT * FROM error_articlescrape WHERE srcid=?", $srcid );

    print "<table border=\"1\">\n";

    $fields = array( 'srcid', 'title','srcurl','attempts','action', 'firstattempt','lastattempt','report' );
    foreach( $fields as $f )
    {
        print( "<tr><th>{$f}</th><td><pre>{$row[$f]}</pre></td></tr>\n" );
    }
    print "</table>\n";
}


