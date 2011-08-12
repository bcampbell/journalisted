<?

#include_once '../../includes/easyparliament/init.php';
#include_once '../../includes/postcode.inc';

require_once '../phplib/api_functions.php';
require_once '../phplib/page.php';

# XXX: Need to override error handling! XXX



/* TODO: move params out of this array and into a fn in the separate API files */
$methods = array(
    'findArticles' => array(
        'parameters' => array('search','offset','limit'),
        'help' => 'Fetch a list of articles matching search criteria',
    ),
    'findJournos' => array(
        'parameters' => array('name','offset','limit'),
        'help' => 'Look for journalists',
    ),
    'getJourno' => array(
        'parameters' => array('journo' ),
        'help' => 'Get details about a single journalist',
    ),
    'getJournoArticles' => array(
        'parameters' => array('journo','offset','limit'),
        'help' => 'Fetch a list of articles attributed to a journalist',
    ),
    'getArticles' => array(
        'parameters' => array('id36','url'),
        'help' => 'Retrieve information about an article or set of articles',
    ),
);




$q_method = get_http_var('method');

if( !$q_method ) {
    api_front_page();
    return;
}

$match = 0;
foreach ($methods as $method=>$data) {
    if (strtolower($q_method) == strtolower($method)) {
        $match = 1;
        break;
    }
}

if( !$match ) {
    api_front_page('Unknown function "' . htmlspecialchars($q_method) .
        '". Possible functions are: ' .
        join(', ', array_keys($methods)) );
   return;
}

include_once 'api_' . $method . '.php';

if (get_http_var('docs')) {
    $_GET['verbose'] = 1;
    ob_start();
}

// collect params (null for missing ones)
$params = array();
foreach ($data['parameters'] as $parameter) {
    $params[$parameter] = get_http_var($parameter,null);
}


if( get_http_var('output') || !get_http_var('docs') ) {
    call_user_func('api_' . $method . '_invoke', $params );
}

if (get_http_var('docs')) {
    $explorer = ob_get_clean();
    api_documentation_front($method, $explorer);
}




function api_documentation_front($method, $explorer) {
    global $methods;

    page_header('API');

    $api_url = OPTION_BASE_URL . '/api/' . $method;
?>
<div class="main">
<h2>Journa<i>listed</i> API - <code><?php echo $method; ?></code></h2>

<code><?php echo $api_url; ?></code>
<?php

 //   include_once 'api_' . $method . '.php';
    api_call_user_func_or_error('api_' . $method . '_front', array(), 'No documentation yet', 'html');

?>
<h4>Explorer</h4>
<p>Try out this function without writing any code!</p>
<form method="get" action="?#output">
<p>
<?php foreach ($methods[$method]['parameters'] as $parameter) {
    print $parameter . ': <input type="text" name="'.$parameter.'" value="';
    if ($val = get_http_var($parameter))
        print htmlspecialchars($val);
    print '" size="30"><br>';
}
?>
Output:
<input id="output_js" type="radio" name="output" value="js"<? if (get_http_var('output')=='js' || !get_http_var('output')) print ' checked'?>>
<label for="output_js">JS</label>
<input id="output_xml" type="radio" name="output" value="xml"<? if (get_http_var('output')=='xml') print ' checked'?>>
<label for="output_xml">XML</label>
<input id="output_php" type="radio" name="output" value="php"<? if (get_http_var('output')=='php') print ' checked'?>>
<label for="output_php">Serialised PHP</label>
<input id="output_rabx" type="radio" name="output" value="rabx"<? if (get_http_var('output')=='rabx') print ' checked'?>>
<label for="output_rabx">RABX</label>

<input type="submit" value="Go">
</p>
</form>
<?php
    if ($explorer) {
        $qs = array();
        foreach ($methods[$method]['parameters'] as $parameter) {
            if (get_http_var($parameter))
                $qs[] = htmlspecialchars(rawurlencode($parameter) . '=' . urlencode(get_http_var($parameter)));
        }
        print '<h4><a name="output"></a>Output</h4>';
        print '<p>URL for this: <strong>' . OPTION_BASE_URL . '/api/';
        print $method . '?' . join('&amp;', $qs) . '&amp;output='.get_http_var('output').'</strong></p>';
        print '<pre><code>' . htmlspecialchars($explorer) . '</code></pre>';
    }
?>
</div>  <!-- end main -->
<div class="sidebar">
<?php
    api_sidebar();
?>
</div> <!-- end sidebar -->
<?php
    page_footer();
}



function api_front_page($error = '') {
    global $methods;
    page_header('API');
?>
<div class="main">
<h2>Journa<i>listed</i> API - Overview</h2>

<p>All requests take a number of parameters. <em>output</em> is optional, and defaults to <kbd>js</kbd>.</p>

<p align="center"><code><?php print OPTION_BASE_URL; ?>/api/<em>function</em>?output=<em>output</em>&amp;<em>other_variables</em></code></p>

<p><strong>The current API is still in flux and subject to change and improvement!</strong>
if this is likely to cause problems for you, please let us know so we can coordinate!</p>

<h3>Outputs</h3>
<p>The <em>output</em> argument can take any of the following values:
<ul>
<li><strong>xml</strong></li>
<li><strong>php</strong>. Serialized PHP, that can be turned back into useful information with the unserialize() command. Quite useful in Python as well, using <a href="http://hurring.com/code/python/serialize/">PHPUnserialize</a>.</li>
<li><strong>js</strong>. A JavaScript object. You can provide a callback
function with the <em>callback</em> variable, and then that function will be
called with the data as its argument.</li>
<li><strong>rabx</strong>. "RPC over Anything But XML".</li>
</ul>

<p>All text is encoded as UTF-8.</p>

<h3>Errors</h3>

<p>
Upon any sort of error, a non-zero <code>status</code> will be returned,
along with an <code>error</code> field containing a human-readable error message.
<ul>
<li>
in XML:<pre><code>
  &lt;jl&gt;
    &lt;status&gt;-1&lt;/status&gt;
    &lt;error&gt;Too many fish&lt;/error&gt;
  &lt;/jl&gt;

</code></pre>
</li>
<li>in JS: <code>{"status":-1,"error":"Too many fish"}</code></li>
<li>in PHP and RABX: a serialised array containing <code>status</code> and <code>error</code></li>
</ul>
</p>


</div>
<div class="sidebar">
<?php
    api_sidebar();
?>
</div>
<?php
    page_footer();
}


function api_sidebar() {
    global $methods;
?>
<div class="box">
<div class="head"><h3>API Functions</h3></div>
<div class="body">
  <ul>
    <li><a href="/api">Overview</a></li>
<?php foreach ($methods as $method => $data) { ?>
<?php /*        if (!isset($data['working']) || $data['working']) */ ?>
    <li><a href="/api/docs/<?= h($method) ?>"><?= h($method) ?></a><br/><?= h($data['help']) ?></li>
<?php } ?>
  </ul>
</div>
<div class="foot"></div>
</div>
<?php

}


?>
