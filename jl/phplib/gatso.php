<?php
// functions for collecting timing information

require_once( '../../phplib/debug.php' );

$g_gatsos = array();

$g_gatsostart = getmicrotime();

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
	global $g_gatsostart;

	$total = getmicrotime() - $g_gatsostart;

	print "<!--\n";
	printf("TOTAL page time: %.3fs\n",$total );
	foreach( $g_gatsos as $name=>$g )
	{
		$percent = 100.0 * $g['tot'] / $total;
		if( $g['runs'] > 1 )
			printf( "%s: %.3fs (%.0f%%) in %d runs\n", $name, $g['tot'], $percent, $g['runs'] );
		else
			printf( "%s: %.3fs (%.0f%%)\n", $name, $g['tot'], $percent );
	}
	print "-->\n";
}

