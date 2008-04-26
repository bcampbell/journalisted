<?php

/* Functions for handling cached output of html,
 * using the htmlcache table.
 */


/*
 *
 * If the cache contains a valid copy of the data then it'll just
 * be fetched and output.
 * If the cache entry is missing or out of date, genfunc will
 * be called to generate it, it will be output and a copy will
 * be stored in the cache.
 *
 * Inserts html comments into the output, to annotate the cache
 * operation. (TODO - add a flag to turn this off so it could
 * be used for non-html data)
 *
 * cacheid - name of the entry in the cache (<= 10 chars)
 * genfunc - function which generates and outputs the data
 *           (null = never regenerate. if the entry isn't cached,
 *           output emptiness)
 * maxage  - maximum age (in seconds) before regenerating the data
 *           (null = forever, never time out)
 */
function cache_emit( $cacheid, $genfunc=null, $maxage=null )
{
	/* look for the item in the cache, and lock that row, so if we
     * need to regenerate it, any further accesses will block
     * until we're done. */ 
    $sql = <<<EOT
SELECT EXTRACT(EPOCH FROM NOW()-gentime) as elapsed, content
	FROM htmlcache
	WHERE name=?
	FOR UPDATE
EOT;
	$valid = false;
	$content = '';
    $row = db_getRow( $sql, $cacheid );
	if( $row )
	{
		if( $maxage===null || $row['elapsed'] < $maxage )
			$valid = true;
	}

	if( $valid )
	{
		printf( "<!-- cache: '%s' fetched from cache -->\n", $cacheid );
		print $row['content'];
		printf( "<!-- cache: end '%s' -->\n", $cacheid );
	}
	else
	{
		/* if we got this far the cache entry is invalid (missing or expired) */
		if( $genfunc )
		{
			printf( "<!-- cache: '%s' regenerated -->\n", $cacheid );
			ob_start();
			cache_gen_annotated( $cacheid, $genfunc );
			$content = ob_get_contents();
			ob_flush();
			printf( "<!-- cache: end '%s' -->\n", $cacheid );

			db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
			db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)",
				$cacheid, $content );

		}
		else
		{
			printf( "<!-- cache: '%s' not found. uhoh. -->\n", $cacheid );
		}
	}

	/* release our lock/commit our changes (if any of either :-) */
	db_commit();
}


/* set an entry in the cache */
function cache_set( $cacheid, $content )
{
	db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)",
		$cacheid, $content );
	db_commit();	
}


/* zap a entry in the cache */
function cache_clear( $cacheid )
{
	db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_commit();
}

/* run a function, time it and print out a html annotation afterward */
function cache_gen_annotated( $cacheid, $genfunc )
{
	$start = getmicrotime();
	call_user_func( $genfunc );
	$end = getmicrotime();
	printf( "<!-- ('%s' generated at %s, took %.3fs) -->\n",
		$cacheid,
		strftime('%Y-%m-%d %H:%M:%S'),
		$end-$start );
}


?>
