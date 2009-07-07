<?

#include_once '../../includes/easyparliament/init.php';
#include_once '../../includes/postcode.inc';

require_once '../phplib/api_functions.php';
require_once '../phplib/page.php';

# XXX: Need to override error handling! XXX


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
<div id="maincolumn">
<h2>Journa<i>listed</i> API - <code><?php echo $method; ?></code></h2>

<code><?php echo $api_url; ?></code>
<?php

 //   include_once 'api_' . $method . '.php';
    api_call_user_func_or_error('api_' . $method . '_front', null, 'No documentation yet', 'html');

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
        print '<pre>' . htmlspecialchars($explorer) . '</pre>';
    }
?>
</div>
<div id="smallcolumn">
<?php
    $sidebar = api_sidebar();
    print $sidebar['content'];
?>
</div>
<?php
    $sidebar = api_sidebar();
    page_footer();
}

function api_front_page($error = '') {
    global $methods;
    page_header('API');
?>
<div id="maincolumn">
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
If there's an error, either in the arguments provided or in trying to perform the request, this is returned as a top-level error string.
<ul>
<li>in XML: <code>&lt;jl&gt;&lt;error&gt;ERROR&lt;/error&gt;&lt;/jl&gt;</code></li>
<li>in JS: <code>{"error":"ERROR"}</code></li>
<li>in PHP and RABX: a serialised array containing one entry with key <code>error</code></li>
</ul>
</p>


</div>
<div id="smallcolumn">
<?php
    $sidebar = api_sidebar();
    print $sidebar['content'];
?>
</div>
<?php
    page_footer();
}

function api_sidebar() {
    global $methods;
    $sidebar = '<div class="box"><h3>API Functions</h3> <div class="box-content">';
    $sidebar .= '<ul>';
    $sidebar .= '<li><a href="/api">Overview</a></li>';
    foreach ($methods as $method => $data){
        $sidebar .= '<li';
        if (isset($data['new']))
            $sidebar .= ' style="border-top: solid 1px #999999;"';
        $sidebar .= '>';
        if (!isset($data['working']) || $data['working'])
            $sidebar .= '<a href="/api/docs/' . $method . '">';
        $sidebar .= $method;
        if (!isset($data['working']) || $data['working'])
            $sidebar .= '</a>';
        else
            $sidebar .= ' - <em>not written yet</em>';
        #       if ($data['required'])
        #           $sidebar .= ' (parameter required)';
        #       else
        #           $sidebar .= ' (parameter optional)';
        $sidebar .= '<br>' . $data['help'];
        #       $sidebar .= '<ul>';
        #       foreach ($data['parameters'] as $parameter) {
            #           $sidebar .= '<li>' . $parameter . '</li>';
            #       }
            #       $sidebar .= '</ul>';
        $sidebar .= '</li>';
    }
    $sidebar .= '</ul></div></div>';
    $sidebar = array(
        'type' => 'html',
        'content' => $sidebar
    );
    return $sidebar;
}
?>
