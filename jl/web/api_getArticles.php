<?php
require_once '../phplib/article.php';
require_once '../phplib/scrapeutils.php';


$api_getArticles_params = array(
    'id36' => array( 'desc'=><<<EOT
Journalisted Identifiers of article(s) you want. This can be a single
id or a list separated by commas or whitespace.<br/>
Each id is the base-36 identifier (ie [0-9a-z]+) assigned to the article
by journa<i>listed</i>. eg <code>http://journalisted.com/article/&lt;id36&gt;</code>
EOT
    ),
    'url' => array( 'desc'=>"The original url of the article you want (eg <code>http://www.examplenewspaper.com/big-story.html</code>)" )
);



function api_getArticles_front() {
    global $api_getArticles_params;
?>
<p><big>Retrieve information about an article or set of articles</big></p>

<h4>Arguments</h4>
<dl>
<?php foreach( $api_getArticles_params as $name=>$info ) { ?>
  <dt><code><?= $name ?></code></dt>
  <dd><?= $info['desc'] ?></dd>
<?php } ?>
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
    $article_ids = array();
    if( $params['id36'] ) {
        $id36s = preg_split( "/[\s,]+/",$params['id36'] );
        foreach( $id36s as $id36 ) {
            $article_ids[] = article_id36_to_id( $id36 );
        }
    }

    if( $params['url'] ) {
        // look up article by its original url
        $url = $params['url'];
        $srcid = scrape_CalcSrcID( $url );
        if( is_null($srcid) ) {
            api_error( "couldn't find article with url: '" . $url . "'" );
            return;
        }
        $id = db_getOne( 'SELECT id FROM article WHERE srcid=?', $srcid );
        if( is_null($id) ) {
            api_error( "don't have article with url: '" . $url . "'" );
            return;
        }
        $article_ids[] = $id;
    }

    if( !$article_ids ) {
        api_error( "No articles specified - use 'id36' and/or 'url'" );
        return;
    }


    #$brief = $params['brief'] ? TRUE : FALSE;
    $fields = array( 'title','id36','srcorgname','iso_pubdate','permalink', 'journos','description' );

    $results = array();
    foreach( $article_ids as $id ) {
        $raw = article_collect( $id );
        $art = array_cherrypick( $raw, $fields );
        $results[] = $art;
    }

    $output = array( 'status'=>0, 'results' => $results);
    api_output( $output);
}

?>
