<?php
/**
 * Set of Unit Tests for the NodeValue Class
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


/**
* This is a helper function since we are always iterating from the document root. It recursively calls itself till it founds the class searched
* 
* @param a-tidy-node $node
* @param text $class_name
* @return The found and formated value of the node. Returns Null if it can't find the class searched. 
*/
function mock_node_iteration($node, $class_name) {
	$tr = "";
	if($node->attribute['class'] == $class_name) {
		$node_value = new Node_Value();
		return $node_value->get_value($node);
	} else if( $node->hasChildren() ) {
		foreach($node->child as $child) {
			$tr .= mock_node_iteration($child, $class_name);
		}
	}
	return $tr;
}

class Test_Node_Value extends UnitTestCase {
	/**
	 * The general configuration of tidy
	 */
	private $tidy_conf = array('wrap' => 0);
	
	/**
	 * Test for extracting a value from embedded html nodes
	 *
	 */
	function testMultipleSubNodes() {
		$html = <<< HTML
<html><body>
<p class="test">paragraph <strong>with <small>multiple</small> sub </strong> nodes</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");		
		$this->assertEqual($value, "paragraph with multiple sub nodes", "The text from the subnodes was not picked correctly");		
	}
	
	/**
	 * http://microformats.org/wiki/hcard-parsing#Value_excerpting
	 *
	 */
	function testValueInClass() {
		$html = <<< HTML
<html><body>
<p class="test">paragraph <strong class="value">value to be picked</strong> paragraph</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");		
		$this->assertEqual($value, "value to be picked", "The text was not picked from inside the element with class='value'");		
		
	}

	/**
	 * http://microformats.org/wiki/hcard-parsing#Value_excerpting
	 *
	 */
	function testValueInClassWithSubnodes() {
		//Value Class with subnodes
$html = <<< HTML
<html><body>
<p class="test">paragraph <strong class="value">value <small>to</small> <small>be</small> picked</strong> paragraph</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");		
		$this->assertEqual($value, "value to be picked", "The text picked from the element with class='value' splitted in bettween multiple rows, was not correctly picked");		
	}


/**
 * http://microformats.org/wiki/hcard-parsing#class_value_handling
 *
 */
	function testMultipleValueClasses() {
		//Multiple value classes
$html = <<< HTML
<html><body>
<p class="test">paragraph <strong class="value">value</strong> paragraph<strong class="value"> to be</strong> paragraph<strong class="value"> picked</strong> </p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		//This is giving problems for the concatenation of the different values
		$this->assertEqual($value, "value to be picked", "The values bettween different with class='value' were not properly recovered");		
	}

/**
 * http://microformats.org/wiki/hcard-parsing#class_value_handling
 *
 */
	function testMultipleValueClassesWithSubnodes() {
		//Multiple value classes with subnodes
$html = <<< HTML
<html><body>
<p class="test">paragraph <strong class="value">va<small>lu</small>e</strong> paragraph <strong class="value">to be</strong> paragraph <strong class="value">picked</strong> </p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		//It has the same problem as the one before
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, "value to be picked", "The values bettween different with class='value' and with internal subnodes were not properly recovered");		
		
	}

/**
 * http://microformats.org/wiki/hcard-parsing#properties_not_of_type_URL_or_URI_or_UID
 *
 */
	function testImageAndAreaAlt() {
//IMG, Area alt
$html = <<< HTML
<html><body>
<img src="www.example.com" class="test" alt="the value"/>
<area class="test2" alt="another value"/>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, "the value", "The value was correctly picked from the alt attribute of an image");		
		$value = mock_node_iteration($tidy->root(), "test2");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, "another value", "The value was correctly picked from the alt attribute of an area");		
		
	}

/**
 * http://microformats.org/wiki/hcard-parsing#all_properties
 *
 */
	function testOfAbbrTitle() {
//Abbr title
$html = <<< HTML
<html><body>
<abbr class="test" title="value in title">no to be picked up</abbr>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, "value in title", "The value was not picked from the title attribute of an abbr tag");		
		
	}
/**
 * http://microformats.org/wiki/hcard-parsing#white-space_handling
 *
 */	
	function testBrFormatting() {
$html = <<< HTML
<html><body>
<p class="test">This is, <br/>The value</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$test_value = "This is,
The value";
		$this->assertEqual($value, $test_value, "BR formatting was not correctly applied.");		
	} 

/**
 * http://microformats.org/wiki/hcard-parsing#white-space_handling
 *
 */	
	function testPreFormatting() {
	//Pre formatting
$html = <<< HTML
<html><body>
<div class="test">Hey let's do some space based formatting:
<pre>
1   2   3   4   5
1   2   2   2   5
1   2   3   4   5
</pre>
and see how it went.
</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$pre_value= "Hey let's do some space based formatting:1   2   3   4   5
1   2   2   2   5
1   2   3   4   5
and see how it went.";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $pre_value, "Pre formatting was not correctly applied.");		
		
}
/**
 * http://microformats.org/wiki/hcard-parsing#DEL_element_handling
 *
 */
	function testDelHandling() {
		//Del handling
$html = '<html><body>
<div class="test">Content <del>not</del>valid</div>
</body>
</html>';
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, 'Content valid', "Del tag was not correctly handled.");		
		
	}

/**
 * http://microformats.org/wiki/hcard-parsing#Plain_Text_Formatting_of_Structural.2FSemantic_HTML
 *
 */
	function testQFormatting() {
		//Q formatting
$html = <<< HTML
<html><body>
<p class="test">This is <q>The</q> value</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, 'This is "The" value', "Q formatting was not correctly applied.");		
	}
/**
 * http://microformats.org/wiki/hcard-parsing#Plain_Text_Formatting_of_Structural.2FSemantic_HTML
 *
 */	
	function testSupSubHandling() {
//Sub, Sup handling
$html = <<< HTML
<html><body>
<div class="test">Content <sub>is</sub> <sup>so</sup> valid</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, 'Content (is) [so] valid', "Sup and Sub tags were not correctly handled.");		
		
	}
/**
 * http://microformats.org/wiki/hcard-parsing#Plain_Text_Formatting_of_Structural.2FSemantic_HTML
 *
 */	
	function testTableHandling() {
		//Table formatting
$html = <<< HTML
<html><body>
<div class="test">
<table><tr><th>c1,r1</th><td>c2,r1</td><td>c3,r1</td></tr>
<tr><td colspan="3">c1,r2</td></tr>
<tr><td>c1,r3</td><th colspan="2">C2,r3</th></tr>
</table>
</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$table_value = "
c1,r1 	c2,r1 	c3,r1 	
c1,r2 	 	 	
c1,r3 	C2,r3 	 	
";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $table_value, "Table tags were not correctly handled.");		
	}
/**
 * http://microformats.org/wiki/hcard-parsing#Plain_Text_Formatting_of_Structural.2FSemantic_HTML
 *
 */
	function testOfLiHandling() {
$html = <<< HTML
<html><body>
<ol class="test">
<li>One Value
<li>Second Value
<li>Third <strong>Value</strong>
</ol>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$test_value = "
 * One Value
 * Second Value
 * Third Value
";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $test_value, "li tags were not correctly handled.");		
		
}

/**
 *  This is not specified in the specification, but is a logical addition
 *
*/
function testNotProcessingOfComments() {
		//Comments non processing
$html = <<< HTML
<html><body>
<p class="test">This is <!-- Comment not to be picked -->the value</p>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$this->assertNotEqual($value, "", "The Class attribute was not found.");		
		$this->assertEqual($value, "This is the value", "The comments in bettween the texts were not discarded");		

	}

/**
 *  This is not specified in the specification, but is a logical addition
 *
*/
	function testOfSingleValueInMoreThanOneNode() {
$html = <<< HTML
<html><body>
<div class="test">This is the beggining of the sentence..
</div>
<p>This is irrelevant data</p>
<div class="test">This is the continuation of the sentence.</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		$test_value = "This is the beggining of the sentence..This is the continuation of the sentence.";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $test_value, "Single Value in multiple nodes was not found.");		
	}
	function testLongLines() {
$html = <<< HTML
<html><body>
<div class="test">This is the beggining of the sentence.. and it's a really long sentence, with a lot of words that should not be wrapped.</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html, $this->tidy_conf);
		$value = mock_node_iteration($tidy->root(), "test");
		$test_value = "This is the beggining of the sentence.. and it's a really long sentence, with a lot of words that should not be wrapped.";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $test_value, "Long values are being wrapped.");		
		
	}
	
	/**
	 *
	 * Value-title pattern
	 *
	 */
	function testValueTitle() {
$html = <<< HTML
<html><body>
<div class="test">Not <span class="value-title" title="the value">to be fetched</span>.</div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html, $this->tidy_conf);
		$value = mock_node_iteration($tidy->root(), "test");
		$test_value = "the value";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $test_value, "Value-title is not working.");		
		
	}
	
	function testNestedValue() {
$html = <<< HTML
<html><body>

<p class="test">
  <span class="value">
    <span class="value">Puppies Rule!</span>
    <span>But kittens are better!</span>
 </span>
</p>

</body>
</html>
HTML;
		$tidy = tidy_parse_string($html, $this->tidy_conf);
		$value = mock_node_iteration($tidy->root(), "test");
		$test_value = "Puppies Rule! But kittens are better!";
		$this->assertNotEqual($value, "", "The Class attribute was not found.");
		$this->assertEqual($value, $test_value, "Value-title is not working.");		
		
	}
	

/*
function testOfMixedStuff() {
		//A mix of everything so far..
$html = <<< HTML
<html><body>
<div class="test">Text <sub class="value">some</sub> <sup>text</sup> text
<p class="value"> value to <!-- not --> <sup>be taken</sup><br/>
Into</p>
text text text
<p class="value">  consideration <strong>by</strong> <small><em>the</em></small> parser..</p>
text text
<pre class="value">and it                           is
f o r m a t e d too!!!</pre>
<span class="value"><q>Dig!</q></span>
<img class="value" alt="even from an alt"/>
</div>
<div class="test"><ol><li>hola</li><li>que</li><li>tal</li></ol></div>
</body>
</html>
HTML;
		$tidy = tidy_parse_string($html);
		$value = mock_node_iteration($tidy->root(), "test");
		echo("----<pre>" . $value . "</pre>----");
	}
*/
}


//Actual execution of tests
$test = &new Test_Node_Value();
$test->run( new HTMLReporter() );

?>