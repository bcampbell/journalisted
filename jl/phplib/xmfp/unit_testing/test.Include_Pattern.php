<? 
/**
 * Set of Unit Tests for the Include Pattern
 * 
 * @author Emiliano Martínez Luque
 * @package xmfp
 * @subpackage unit_testing
 */

define("PATH_GEN_LIBS", "/home/includes/libs/");
require_once(PATH_GEN_LIBS . "simpletest/unit_tester.php");
require_once(PATH_GEN_LIBS . "simpletest/reporter.php");

define('XMFP_INCLUDE_PATH', '/home/www/xmf_parser/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');

class Test_Include_Pattern extends UnitTestCase {
	private $mf_roots = array();

	function __Construct($mf_roots) {
		$this->mf_roots = $mf_roots;
	}

	/**
	 * Test for including a root microformat into another root level microformat.
	 */
	function test_include_of_merging_of_root_mf() {
		$html = <<< HTML
<html><body>
<p class="vcard" id="jimmy-hendrix">
  <span class="fn n" >
    <span class="given-name">Jimmy</span> <span class="family-name">Hendrix</span>
  </span>
</p>

<p class="vcard">
 <object class="include" data="#jimmy-hendrix"></object>
<span class="tel">
 <span class="type">work</span>
 <span class="value">+1-213-555-1234</span>
</span>
</p>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array( "vcard" => array( 0 => array( "tel" => array( 0 => array( "tel" => "+1-213-555-1234" , "type" => array( 0 => "work"))) , "fn" => "Jimmy Hendrix" , "n" => array( "given-name" => array( 0 => "Jimmy") , "family-name" => array( 0 => "Hendrix")))));
		$this->assertEqual($arr, $test_arr, "The Test for including a root microformat into another root level microformat failed");		
	}
	
	
	/**
	 * Test for Including a set of singular subproperies into a root mf. (From the include pattern examples)
	 *
	 */
	function test_include_of_singular_subproperties_into_a_mf_root() {
		$html = <<< HTML
<html><body>
<span class="vcard">
  <span class="fn n" id="james-hcard-name">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
</span>

Elsewhere on the page, an employer's hCard re-uses the "fn n" content from the first hCard:

<span class="vcard">
 <object class="include" data="#james-hcard-name"></object>
 <span class="org">SimplyHired</span>
 <span class="title">Microformat Brainstormer</span>
</span>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array(  "vcard" => array(  0 => array(  "fn" => "James Levine" ,  "n" => array(  "given-name" => array(  0 => "James") ,  "family-name" => array(  0 => "Levine"))) ,  1 => array(  "org" => array(  "organization-name" => "SimplyHired") ,  "title" => array(  0 => "Microformat Brainstormer") ,  "fn" => "James Levine" ,  "n" => array(  "given-name" => array(  0 => "James") ,  "family-name" => array(  0 => "Levine")))));
		$this->assertEqual($arr, $test_arr, "The Test for Including a set of singular subproperies into a root mf failed");		
	}

	/**
	 * Test for Including a set of singular subproperies into a root mf where this subproperties already exist. 
	 *
	 */
	function test_include_of_singular_subproperties_into_a_mf_root_already_filled() {
		$html = <<< HTML
<html><body>
<span class="vcard">
  <span class="fn n" id="james-hcard-name">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
</span>

Elsewhere on the page, an employer's hCard re-uses the "fn n" content from the first hCard:

<span class="vcard">
  <span class="fn n">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
<object class="include" data="#james-hcard-name"></object>
 <span class="org">SimplyHired</span>
 <span class="title">Microformat Brainstormer</span>
</span>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array(  "vcard" => array(  0 => array(  "fn" => "James Levine" ,  "n" => array(  "given-name" => array(  0 => "James") ,  "family-name" => array(  0 => "Levine"))) ,  1 => array(  "fn" => "James Levine" ,  "n" => array(  "given-name" => array(  0 => "James") ,  "family-name" => array(  0 => "Levine")) ,  "org" => array(  "organization-name" => "SimplyHired") ,  "title" => array(  0 => "Microformat Brainstormer"))));
		$this->assertEqual($arr, $test_arr, "The Test for Including a set of singular subproperies into a root mf where this subproperties already exist failed");		
	}
	
	/**
	 * Test for Including a multiple ocurrence subproperty into a root mf. 
	 *
	 */
	function test_include_of_multi_subproperties_into_a_mf_root() {
		$html = <<< HTML
<html><body>
   <span class="vcard">
  <span class="fn n">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
   <a href="http://www.poronogo.com" class="url" id="jamesurl">dfas</a>
</span>

<span class="vcard">
 <object class="include" data="#jamesurl"></object>
   <span class="fn n">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
 </span>

</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array( "vcard" => array( 0 => array( "fn" => "James Levine" , "n" => array( "given-name" => array( 0 => "James") , "family-name" => array( 0 => "Levine")) , "url" => array( 0 => "http://www.poronogo.com")) , 1 => array( "fn" => "James Levine" , "n" => array( "given-name" => array( 0 => "James") , "family-name" => array( 0 => "Levine")) , "url" => array( 0 => "http://www.poronogo.com"))));
		$this->assertEqual($arr, $test_arr, "The Test for Including a multiple ocurrence subproperty into a root mf failed");		
	}
	/**
	 * Test for Including a  multiple ocurrence subproperty with subproperties into a root mf. 
	 *
	 */
	function test_include_of_multi_subproperties_with_subproperties_into_a_mf_root() {
		$html = <<< HTML
<html><body>
   <span class="vcard">
<span class="org fn">Jame's home business</span>   
<span class="tel" id="jamestel">
<span class="value">897898923</span>
<span class="type">work</span></span>
</span>

<span class="vcard">
 <object class="include" data="#jamestel"></object>
   <span class="fn n">
    <span class="given-name">James</span> <span class="family-name">Levine</span>
  </span>
 </span>

</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array( "vcard" => array( 0 => array( "org" => array( "organization-name" => "Jame's home business") , "fn" => "Jame's home business" , "tel" => array( 0 => array( "tel" => "897898923" , "type" => array( 0 => "work")))) , 1 => array( "fn" => "James Levine" , "n" => array( "given-name" => array( 0 => "James") , "family-name" => array( 0 => "Levine")) , "tel" => array( 0 => array( "tel" => "897898923" , "type" => array( 0 => "work"))))));
		
		$this->assertEqual($arr, $test_arr, "The Test for Including a  multiple ocurrence subproperty with subproperties into a root mf failed");		
	}
	
	
	
	/**
	 * Test for including of a root mf into the subproperty of another. (From the examples)
	 *
	 */
	function test_include_of_root_mf_into_a_mf_subproperty() {
		$html = <<< HTML
<html><body>
<div class="vcard" id="bricklayers-card">
  <div class="fn org">The Bricklayers Arms</div>
  <div class="adr">
     <span class="street-address">31 Gresse Street</span>,
     <span class="locality">Fitzrovia</span>
     <span class="region">London</span>
     <span class="postal-code">W1T 1QS</span>
  </div>
</div>

Elsewhere on the page, a number of reviews reference the hcard in the item property. Rather than repeat all the verbose detail, only the name is reprinted and the detailed hcard referenced using the include-pattern.

<div class="hreview" id="the-review">
   <h1 class="summary">A great venue for monthly gatherings!</h1>
   <div class="item"><a class="include" href="#bricklayers-card">The Bricklayers Arms</a></div>
   <p class="description">Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.</p>
</div>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array( "vcard" => array( 0 => array( "fn" => "The Bricklayers Arms" , "org" => array( "organization-name" => "The Bricklayers Arms") , "adr" => array( 0 => array( "street-address" => array( 0 => "31 Gresse Street") , "locality" => "Fitzrovia" , "region" => "London" , "postal-code" => "W1T 1QS")))) , "hreview" => array( 0 => array( "summary" => "A great venue for monthly gatherings!" , "item" => array( "vcard" => array( "fn" => "The Bricklayers Arms" , "org" => array( "organization-name" => "The Bricklayers Arms") , "adr" => array( 0 => array( "street-address" => array( 0 => "31 Gresse Street") , "locality" => "Fitzrovia" , "region" => "London" , "postal-code" => "W1T 1QS")) , "line" => 2 , "column" => 1)) , "description" => "Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.")));
		$this->assertEqual($arr, $test_arr, "Test for including of a root mf into the subproperty of another failed");		
	}

	/**
	 * Test for including of a subproperty of an mf into the subproperty of another (Single Ocurrences).
	 *
	 */
	function test_include_of_subproperty_mf_into_a_mf_subproperty() {
		$html = <<< HTML
<html><body>

<div class="hreview" id="another-review">
   <h1 class="summary">Superb!</h1>
   <div class="item">
	<div class="vcard" id="bricklayers-card">
  	<div class="fn org">The Bricklayers Arms</div>
	</div>
   </div>
   <p class="description">Wonderfull litle place to get drunk.</p>
</div>


Elsewhere on the page, a number of reviews reference the hcard in the item property. Rather than repeat all the verbose detail, only the name is reprinted and the detailed hcard referenced using the include-pattern.

<div class="hreview" id="the-review">
   <h1 class="summary">A great venue for monthly gatherings!</h1>
   <div class="item"><a class="include" href="#bricklayers-card">The Bricklayers Arms</a></div>
   <p class="description">Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.</p>
</div>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array( "hreview" => array( 0 => array( "summary" => "Superb!" , "item" => array( "vcard" => array( "fn" => "The Bricklayers Arms" , "org" => array( "organization-name" => "The Bricklayers Arms"))) , "description" => "Wonderfull litle place to get drunk.") , 1 => array( "summary" => "A great venue for monthly gatherings!" , "item" => array( "vcard" => array( "fn" => "The Bricklayers Arms" , "org" => array( "organization-name" => "The Bricklayers Arms"))) , "description" => "Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.")));
		$this->assertEqual($arr, $test_arr, "The Test for including of a subproperty of an mf into the subproperty of another (Single Ocurrences) failed");		
	}

	/**
	 * Test for including of a subproperty of one mf into the subproperty of another subproperty (Multiple Ocurrences).
	 *
	 */
	function test_include_of_subproperty_mf_into_a_nested_mf_subproperty() {
		$html = <<< HTML
<html><body>

	<div class="vcard">
  	<div class="fn org">The Bricklayers Arms</div>
<span class="tel" id="bricktel">
<span class="value">897898923</span>
<span class="type">work</span></span>
  	</div>


Elsewhere on the page, a number of reviews reference the hcard subproperty in the item property. Rather than repeat all the verbose detail, only the name is reprinted and the detailed hcard referenced using the include-pattern.

<div class="hreview" id="the-review">
   <h1 class="summary">A great venue for monthly gatherings!</h1>
   <div class="item">
	<div class="vcard" id="bricklayers-card">
  	<div class="fn org">The Bricklayers Arms</div>
 	<object class="include" data="#bricktel"></object>
  	</div>
   </div>
   <p class="description">Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.</p>
</div>
</body>
</html>		
HTML;
		$xmfp = Xmf_Parser::create_by_HTML($this->mf_roots, $html, "");
		$arr = $xmfp->get_parsed_mfs();
		$test_arr = array(  "vcard" => array(  0 => array(  "fn" => "The Bricklayers Arms" ,  "org" => array(  "organization-name" => "The Bricklayers Arms") ,  "tel" => array(  0 => array(  "tel" => "897898923" ,  "type" => array(  0 => "work"))))) ,  "hreview" => array(  0 => array(  "summary" => "A great venue for monthly gatherings!" ,  "item" => array(  "vcard" => array(  "fn" => "The Bricklayers Arms" ,  "org" => array(  "organization-name" => "The Bricklayers Arms") ,  "tel" => array(  0 => array(  "tel" => "897898923" ,  "type" => array(  0 => "work"))))) ,  "description" => "Wonderful pub, cheap beer, open fire to warm mince pies at Christmas.")));
		$this->assertEqual($arr, $test_arr, "The Test for including of a subproperty of one mf into the subproperty of another subproperty (Multiple Ocurrences) failed");		
	}
	
/**
  * 
  */
}
	
	
//Actual execution of tests
$test = &new Test_Include_Pattern($mF_roots);
$test->run( new HTMLReporter() );

?>