<?php

function api_getJournoArticles_front() {
?>
<p><big>Get articles attributed to a particular journalist.</big></p>

<h4>Arguments</h4>
<dl>
<dt><code>journo</code> (required)</dt><dd><code>id</code> or <code>ref</code> of journalist</dd>
<dt><code>offset</code></dt><dd>the start offset of result set returned (default 0)</dd>
<dt><code>limit</code></dt><dd>maximum number of results to return (default 100)</dd>
</dl>

<h4>Returned values</h4>

<p>A list of articles is returned, with these fields:</p>

<dl>
<dt><code>id</code></dt><dd>unique, numeric id of article</dd>
<dt><code>title</code></dt><dd>article headline</dd>
<dt><code>srcorg</code></dt><dd>organisation which published article</dd>
<dt><code>permalink</code></dt><dd>original URL of article</dd>
<dt><code>description</code></dt><dd>short summary of article</dd>
<dt><code>pubdate</code></dt><dd>publication timestamp, in ISO 8601 format<br/>
 note: some articles have reasonable time component, others do not - it varies by news outlet.</dd>
</dl>

<?php
}

function api_getJournoArticles_invoke($params) {
    $offset = (int)$params['offset'];
    $limit = (int)$params['limit'];
    if( !$limit )
        $limit = 100;
    if(!$offset )
        $offset = 0;

    $j = $params['journo'];
    if(!$j)
    {
        api_error( "required parameter 'journo' is missing" );
        return;
    }

    $jfield = is_numeric( $j ) ? 'id' : 'ref';

    $sql = <<<EOT
SELECT a.id, a.title, a.srcorg, a.permalink, a.description, a.pubdate
    FROM ((article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
        INNER JOIN journo j ON j.id=attr.journo_id)
    WHERE a.status='a' AND j.status='a' AND j.{$jfield}=?
    ORDER BY a.pubdate DESC
    LIMIT ?
    OFFSET ?
EOT;

    $articles = db_getAll( $sql, $j, $limit, $offset );

    foreach( $articles as &$a )
    {
        $d = new DateTime( $a['pubdate'] );
        $a['pubdate'] = $d->format('c');
    }

  	$output = array(
    	'results' => $articles,
   	);
    api_output($output);
}

?>
