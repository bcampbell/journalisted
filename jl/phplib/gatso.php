<?php

require_once( '../../phplib/debug.php' );

$g_gatsos = array();

gatso_start( '_TOTAL' );

function gatso_start( $name )
{
	global $g_gatsos;
	if( !array_key_exists( $name, $g_gatsos ) )
	{
		$g_gatsos[ $name ] = array( 'tot'=>0.0, 'runs'=>0 );
	}

	$g_gatsos[$name]['start'] = getmicrotime();
}


function gatso_stop( $name )
{
	global $g_gatsos;
	$t = getmicrotime();
	$elapsed = $t - $g_gatsos[$name]['start'];
	$g_gatsos[$name]['tot'] += $elapsed;
	$g_gatsos[$name]['runs'] += 1;
}

function gatso_report_html()
{
	global $g_gatsos;

	gatso_stop('_TOTAL');

	$total = $g_gatsos['_TOTAL']['tot'];
	unset( $g_gatsos['_TOTAL'] );

	print "<pre>\n";
	foreach( $g_gatsos as $name=>$g )
	{
		$percent = 100.0 * $g['tot'] / $total;
		if( $g['runs'] > 1 )
			printf( "%s: %.3fs (%.0f%%) in %d runs\n", $name, $g['tot'], $percent, $g['runs'] );
		else
			printf( "%s: %.3fs (%.0f%%)\n", $name, $g['tot'], $percent );
	}
	printf("TOTAL time: %.3fs\n",$total );
	print "</pre>\n";
}

