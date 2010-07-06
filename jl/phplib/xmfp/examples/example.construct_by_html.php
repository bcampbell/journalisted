<?php
/**
 * Simple example of How to Use the Class by specifying a Microformated HTML to be parsed.
 * 
 * @package xmfp
 * @subpackage examples
 */

?><html>
<body>
<?php
define('XMFP_INCLUDE_PATH', '/home/www/xmf_parser/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');


$html = <<< HTML
<html>
<body>
<div class="vevent">
 <a class="url" href="http://www.web2con.com/">http://www.web2con.com/</a>
  <span class="summary">Web 2.0 Conference</span>: 
  <abbr class="dtstart" title="2007-10-05">October 5</abbr>-
  <abbr class="dtend" title="2007-10-20">19</abbr>,
 at the <span class="location">Argent Hotel, San Francisco, CA</span>
 </div>

 <div class="hreview">
 <span>
 <h4 class="summary">Crepes on Cole is awesome</h4>
 <span class="reviewer vcard">Reviewer: <span class="fn">Tantek</span> - 
 <abbr class="dtreviewed" title="20050418T2300-0700">April 18, 2005</abbr></span>
 <div class="description item vcard"><p>
  <span class="fn org">Crepes on Cole</span> is one of the best little 
  creperies in <span class="adr"><span class="locality">San Francisco</span></span>.
  Excellent food and service. Plenty of tables in a variety of sizes 
  for parties large and small.  Window seating makes for excellent 
  people watching to/from the N-Judah which stops right outside.  
  I've had many fun social gatherings here, as well as gotten 
  plenty of work done thanks to neighborhood WiFi.
 </p></div>
 <p>Visit date: <span>April 2005</span></p>
 <p>Food eaten: <span>Florentine crepe</span></p>
 <ul>
  <li class="rating"><a href="http://en.wikipedia.org/wiki/Food" rel="tag">
   Food: <span class="value">18</span>/<span class="best">30</span></a>;</li>
  <li class="rating"><a href="http://flickr.com/photos/tags/Ambience" rel="tag">
   Ambience: <span class="value">19</span>/<span class="best">30</span></a>;</li>
  <li class="rating"><a href="http://en.wikipedia.org/wiki/Service" rel="tag">
   Service: <span class="value">15</span>/<span class="best">30</span></a>;</li>
  <li class="rating"><a href="http://en.wikipedia.org/wiki/Price" rel="tag">
   Price: <abbr class="value" title="2">$$</abbr>...</a></li>
 </ul>
 </div>
<a href="http://creativecommons.org/licenses/by/2.0/" rel="license">cc by 2.0</a>
 

</body>
</html>
HTML;


$xmfp = Xmf_Parser::create_by_HTML($mF_roots, $html);

echo('<h1>Results</h1><pre>');
print_r( $xmfp->get_parsed_mfs() );
echo('</pre>');
echo('<h1>Errors</h1><pre>');
print_r( $xmfp->get_errors() );
echo('</pre>');
?>
</body>
</html>