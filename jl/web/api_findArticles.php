<?php
require_once '../phplib/xap.php';


function api_findArticles_front() {
?>
<p><big>Find articles using the Journa<i>listed</i> search facility.</big></p>

<h4>Arguments</h4>
<dl>
<dt><code>search</code> (required)</dt><dd>search terms</dd>
<dt><code>offset</code></dt><dd>the start offset of result set returned (default 0)</dd>
<dt><code>limit</code></dt><dd>maximum number of results to return (default 100)</dd>
</dl>

<h4>Returned values</h4>
<dl>
<dt><code>id</code></dt><dd>unique, numeric id of article</dd>
<dt><code>title</code></dt><dd>article headline</dd>
<dt><code>srcorg</code></dt><dd>organisation which published article</dd>
<dt><code>permalink</code></dt><dd>original URL of article</dd>
<dt><code>description</code></dt><dd>short summary of article</dd>
<dt><code>pubdate</code></dt><dd>publication timestamp, in ISO 8601 format<br/>
 note: some articles have reasonable time component, others do not - it varies by news outlet.</dd>
<dt><code>journos</code></dt><dd>list of journalists attributed to this article. Each journo has: <code>id</code>, <code>ref</code>, <code>prettyname</code></dd>

</dl>

<?php
}

function api_findArticles_invoke($params) {
    $search = $params['search'];
    if(!$search)
    {
        api_error( "required parameter 'search' is missing" );
        return;
    }

    $start = (int)$params['offset'];
    $num = (int)$params['limit'];

    if( !$num )
        $num = 100;
    if(!$start )
        $start = 0;

    try {
        $xap = new XapSearch();
        $xap->set_query( $search );
        $results = $xap->run($start, $num,'date');

//        foreach( $results as &$a ) {
//            // convert datetime objects to strings
//            $a['pubdate'] = $a['pubdate']->format('c');
//        }

    	$output = array(
	    	'results' => $results,
    	);
	    api_output($output);
    } catch (Exception $e) {
        api_error( $e->getMessage() );
    }
}

?>
