<?php
require_once '../phplib/article.php';

function api_getArticles_front() {
?>
<p><big>Retrieve information about an article or set of articles</big></p>

<h4>Arguments</h4>
<dl>
<dt><code>id36</code></dt>
<dd>The article(s) you want. This can be a single id or a list separated by commas or whitespace. Each id is a base-36 identifier (ie alphanumeric 0-9a-z).</dd>
</dl>

<h4>Returned values</h4>

The results are an array of articles, each with the following fields:
<dl>
<dt><code>title</code></dt><dd>Title (headline) of article</dd>
<dt><code>id36</code></dt><dd>article id</dd>
<dt><code>iso_pubdate</code></dt><dd>Date of publication, in iso 8601 format</dd>
<dt><code>srcorgname</code></dt><dd>the 'pretty' name of the publication the article is from</dd>
<dt><code>permalink</code></dt><dd>url of the original article</dd>
<dt><code>journos</code></dt>
<dd>The journalists this article is attributed to (an array, possibly empty). Each journo will have the following fields:
  <dl>
    <dt><code>ref</code></dt><dd>the unique ref for the journo</dd>
    <dt><code>prettyname</code></dt><dd>the display name of the journo</dd>
  </dl>
</dd>
<dt><code>description</code></dt><dd>short article description/summary</dd>
</dl>


<p>
This API will either return _all_ the articles requested, or fail.
</p>
<?php
}

function api_getArticles_invoke($params) {
    if( !$params['id36'] ) {
        api_error( "missing required parameter: 'id36'" );
        return;
    }
    #$brief = $params['brief'] ? TRUE : FALSE;
    $ids = preg_split( "/[\s,]+/",$params['id36'] );
    $fields = array( 'title','id36','srcorgname','iso_pubdate','permalink', 'journos','description' );

    $results = array();
    foreach( $ids as $id36 ) {
        $id = article_id36_to_id($id36);
        $raw = article_collect( $id );
        $art = array_cherrypick( $raw, $fields );
        $results[] = $art;
    }

    $output = array( 'status'=>0, 'results' => $results);
    api_output( $output);
}

?>
