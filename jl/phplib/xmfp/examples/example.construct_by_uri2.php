<?php
/**
 * Simple example of How to Use the Class by specifying a URL. This one gives a lot of non real errors since it's and hresume
 * 
 * @package xmfp
 * @subpackage examples
 */

header('Content-Type: text/html; charset=UTF-8');
?><html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head>
<body><?php

define('XMFP_INCLUDE_PATH', '/home/www/xmf_parser/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');

//$xmfp = Xmf_Parser::create_by_URI($mF_roots, 'http://valeurdusage.net/drupal62/cv');
$xmfp = Xmf_Parser::create_by_URI($mF_roots, 'http://www.linkedin.com/in/steveganz');
echo('<h1>Results</h1><pre>');
$results = $xmfp->get_parsed_mfs();
print_r($results);
echo('</pre>');
echo('<h1>Errors</h1><pre>');
print_r( $xmfp->get_errors() );
echo('</pre>');

?>
</body>
</html>
