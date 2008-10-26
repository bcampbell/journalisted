<?php
require_once '../phplib/xap.php';


function api_getArticles_front() {
?>
<p><big>Fetch a list of articles.</big></p>

<h4>Arguments</h4>
<dl>
<dt>search (required)</dt>
<dd>search terms</dd>
<dt>start</dt>
<dd>the start offset of result set returned (default 0)</dd>
<dt>num</dt>
<dd>maximum number of results to return (default 100)</dd>
</dl>

<h4>Example Response</h4>
<pre>{
    ...TODO....
}</pre>

<?php	
}

function api_getArticles_search($search) {

    $start = (int)get_http_var( 'start', 0 );
    $num = (int)get_http_var( 'num' );
    if( !$num )
        $num = 100;


    $xap = new XapSearch();
    $xap->set_query( $search );
    $results = $xap->run($start, $num,'date');

	$output = array(
#        'num' => $num,
		'results' => $results,
	);
	api_output($output);

# TODO: catch exceptions here
	#api_error('Sorry, test failed.');
}

?>
