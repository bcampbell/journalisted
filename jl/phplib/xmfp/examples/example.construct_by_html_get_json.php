<?php
/**
 * Simple example of How to Use the Class to get a JSON String by specifying a Microformated HTML to be parsed.
 * 
 * @package xmfp
 * @subpackage examples
 */
?>
<?php
define('XMFP_INCLUDE_PATH', '/home/www/xmf_parser/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');


$html = <<< HTML
<html>
<body>
<p class="vcard">
<span class="fn">Juan Manuel</span>
<span class="tel">
 <span class="type">work</span>
 <span class="value">+1-213-555-1234</span>
<abbr class="type" title="voice">phone</abbr>
</span></p>
<div class="vevent">
 <a class="url" href="http://www.web2con.com/">http://www.web2con.com/</a>
  <span class="summary">Web 2.0 Conference</span>: 
  <abbr class="dtstart" title="2007-10-05">October 5</abbr>-
  <abbr class="dtend" title="2007-10-20">19</abbr>,
 at the <span class="location">Argent Hotel, San Francisco, CA</span>
 </div>
<div class="hreview">
 <div class="item vcard">
  <div class="fn org summary">Cafe Borrone</div>
  <span class="adr">
   <span class="street-address">1010 El Camino Real</span>,
   <span class="locality">Menlo Park</span>,
   <span class="region">CA</span>
   <span class="postal-code">94025</span>,
  </span>
  <span class="tel">+1-650-327-0830</span>;
  <a class="url" href="http://cafeborrone.com">cafeborrone.com</a>
 </div>
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
 <div class="description"><p>
  This <abbr class="type" title="business">
  <a href="http://en.wikipedia.org/wiki/cafe" rel="tag">cafe</a></abbr> 
  is a welcoming oasis on the Peninsula.  
  It even has a fountain outside which nearly eliminates 
  the sounds of El Camino traffic.  Next door to a superb indy bookstore, 
  Cafe Borrone is an ideal spot to grab a 
  <a href="http://en.wikipedia.org/wiki/coffee" rel="tag">coffee</a> 
  or a meal to accompany a newly purchased book or imported periodical.  
  <a href="http://technorati.com/tag/soup" rel="tag">Soups</a> and 
  <a href="http://technorati.com/tag/sandwich" rel="tag">sandwich</a> 
  specials rotate daily.  The corn chowder with croutons and big chunks of cheese 
  goes especially well with a freshly toasted mini-baguette.  Evenings are 
  often crowded and may require sharing a table with a perfect stranger. 
  <a href="http://flickr.com/photos/tags/espresso" rel="tag">Espresso</a> 
  afficionados will appreciate the 
  <a href="http://en.wikipedia.org/wiki/Illy" rel="tag">Illy</a> coffee.  
  Noise levels can vary from peaceful in the late mornings to nearly overwhelming on 
  <a href="http://en.wikipedia.org/wiki/jazz" rel="tag">jazz</a> band nights.
 </p></div>
 Review (<a href="http://microformats.org/wiki/hreview"> 
  hReview v<span class="version">0.3</span></a>)
 by <span class="reviewer vcard"><span class="fn">anonymous</span></span>, 
 <abbr class="dtreviewed" title="20050428T2130-0700">April 28th, 2005</abbr>.
 <a href="http://creativecommons.org/licenses/by/2.0/" rel="license">cc by 2.0</a>
 </div>

</body>
</html>
HTML;

$xmfp = Xmf_Parser::create_by_HTML($mF_roots, $html);
$json = $xmfp->get_parsed_mfs_as_JSON(true, true);
echo('<h1>JSON Result</h1>');
echo('<p>');
echo($json);
echo('</p>');

$json_arr = (Array) json_decode($json);;
echo('<h2>Processed JSON </h2>');
echo('<pre>');
print_r($json_arr);
echo('</pre>');
exit;
?>