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
    $sql = <<<EOT
SELECT EXTRACT(EPOCH FROM NOW()-gentime) as elapsed, content
	FROM htmlcache
	WHERE name=?
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
		/* if we got this far the cache entry is missing or expired, so
         * we want to rebuild it (if we can) */
		if( $genfunc )
		{
            /* very first thing - update the gentime to prevent other requests 
             * trying to regenerate the cache!
             * There is still a small window between the SELECT and here where
             * another request could sneak in, but it's probably not a big risk
             * in practice.
             * TODO: look again at getting the SELECT to lock the row!
             */
            db_do( "UPDATE htmlcache SET gentime=NOW() WHERE name=?", $cacheid );
	        db_commit();


			printf( "<!-- cache: '%s' regenerated -->\n", $cacheid );
			ob_start();
			cache_gen_annotated( $cacheid, $genfunc );
			$content = ob_get_contents();
			ob_flush();
			printf( "<!-- cache: end '%s' -->\n", $cacheid );

			db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
			db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)",
				$cacheid, $content );
	        db_commit();
		}
		else
		{
			printf( "<!-- cache: '%s' not found. uhoh. -->\n", $cacheid );
		}
	}

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
