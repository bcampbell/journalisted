<?php
/**
 * Simple example of How to Use the Class by specifying a URL
 * 
 * @package xmfp
 * @subpackage examples
 */

?><html>
<body>
<?php
define('XMFP_INCLUDE_PATH', '/home/www/xmf_parser/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');

$xmfp = Xmf_Parser::create_by_URI($mF_roots, 'http://metonymie.com');

echo('<h1>Results</h1><pre>');
print_r( $xmfp->get_parsed_mfs() );
echo('</pre>');
echo('<h1>Errors</h1><pre>');
print_r( $xmfp->get_errors() );
echo('</pre>');
?>
</body>
</html>
