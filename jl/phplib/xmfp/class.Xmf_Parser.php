<?php
/**
 * File containing the Class for extracting the microformats from an HTML document. And Post-Processing it's values.
 * 
 */

/**
 * Required elements for the class to work
 */
require_once(XMFP_INCLUDE_PATH . "class.Node_Value.php");
require_once(XMFP_INCLUDE_PATH . "mfdef.mF_roots.php");
require_once(XMFP_INCLUDE_PATH . "lang.xmfp.php");

/**
 * Class for extracting the microformats from an XML document. And Post-Processing it's values.
 * 
 * @todo skip logic for compound microformats with similar class names within the parent and it's childs (like in hresume)
 * 
 * @author Emiliano Martínez Luque
 * @package xmfp
 *
 */

class Xmf_Parser {
	/**
	 * Tidy configuration Options
	 */
	public $tidy_conf = array('wrap' => 0);
	/**
	 * The Results with node information. Like Line and Column. Used later for Validation and Include Pattern.
	 */
	private $node_results = array();
	/**
	 * The Errors encountered while parsing the microformat.
	 */
	private $errors = array();
	/**
	 * The Clean Results of the microformat
	 */
	private $clean_results = array();
	/**
	 * The Microformats Definitions that will be searched when parsing
	 */
	private $mf_root = array();
	/**
	 * The microformats to be skipped since they are part of a compound microformat. 
	 * (This is used to avoid repeating in the results a MF that is part of a bigger microformat)
	 * Each element in the array is identified by class name, column and line.
	 */
	private $toskip = array();
	/**
	 * The Base of the Document to be used when processing Images and URIS.
	 */
	private $base = "";
	/**
	 * The URI if provided. Used when deciding the base.
	 */
	private $URI = "";
	
	/**
	 * <p>Whether or not to return on the results the microformats with errors.
	 * If set to true then:
	 * <ul><li>if a microformat property is badly formated (for example a date, a URI or an email) or
	 * it has a value outside of the valid ones, it won't be added to the mf.</li>
	 * <li>If a microformat is missing a required property, it won be added to the overall results</li>
	 * </ul>
	 * </p>
	 *  
	 */
	private $be_strict = FALSE;
	
	/**
	 * Private constructor to Guarantee that instances of the class are constructed from one of the factory methods.
	 */
	private function __Construct() {}
	
	/**
	 * Factory construction of the object by URL
	 * 
	 * @param array $mF_roots The Array with all the MF_roots to be searched in the document
	 * @param uri $Uri
	 * @param boolean $be_strict whether or not to be strict when dealing with errors in results.
	 */
	static function create_by_URI($mF_roots, $URI, $be_strict = FALSE) {
		//Create instance of Xmf_Parser
		$xmfp = new Xmf_Parser();
		//Set $URI
		$xmfp->set_URI($URI);
		
		//Fetch URI with CURL
		$options = array(
        	CURLOPT_RETURNTRANSFER => true,     // return web page
        	CURLOPT_HEADER         => true,    // return headers
        	CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        	CURLOPT_ENCODING       => "",       // handle all encodings
        	CURLOPT_USERAGENT      => "XMF_Parser", // The User Agent
	        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
    	    CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        	CURLOPT_TIMEOUT        => 120,      // timeout on response
        	CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    	);

	    $ch = curl_init( $URI );
	    curl_setopt_array( $ch, $options );
    	$content = curl_exec( $ch );
    	$err = curl_errno( $ch );
	    $errmsg  = curl_error( $ch );
    	$header  = curl_getinfo( $ch );
    	//Encoding options for tidy    	
    	if(isset($header['content_type'])) {
    		$exp_header = explode('charset=', $header['content_type']);
			if(isset($exp_header[1])) {
	    		$encoding = str_replace('-', '', $exp_header[1]);
    			$xmfp->tidy_conf['char-encoding'] = $encoding;
    			$xmfp->tidy_conf['input-encoding'] = $encoding;
			   	$xmfp->tidy_conf['output-encoding'] = $encoding;
			}
    	}
	    curl_close( $ch );
		//If there where errors just add to $mf
	    if($err) {
			$error['description'] = 'CURL Error: ' .  $err . '\n' . $errmsg;
			$xmfp->errors[] = $error;
			return $xmfp;
		} else {
			//No CURL Errors. 
			//Set $be_strict
			$xmfp->set_be_strict($be_strict);
			//Parse Content.
			$tidy = tidy_parse_string($content, $xmfp->tidy_conf);
		    //Decide Base
			$xmfp->decide_base( $tidy->head() );
		    //Parse body
		    $xmfp->parse_roots( $mF_roots, $tidy->body());
			//Process results
			$xmfp->process_results();
			return $xmfp;
		}
	}
	
	/**
	 * Factory construction of the object by HTML
	 * 
	 * @param array $mF_roots The Array with all the MF_roots to be searched in the document
	 * @param string $html the html to be parsed
	 * @param uri $URI the uri to be used when deciding base
	 * @param boolean $be_strict whether or not to be strict when dealing with errors in results.
	 */
	static function create_by_HTML($mF_roots, $html, $URI = "", $be_strict = FALSE) {
		//Create instance of Xmf_Parser
		$xmfp = new Xmf_Parser();
		//Set $URI
		$xmfp->set_URI($URI);
		//Set $be_strict
		$xmfp->set_be_strict($be_strict);
		//Parse HTML
		$tidy = tidy_parse_string($html, $xmfp->tidy_conf);
		//Decide Base
		$xmfp->decide_base( $tidy->head() );
		//Parse Body
		$xmfp->parse_roots( $mF_roots, $tidy->body());
		$xmfp->process_results();
		return $xmfp;
	}
	
	/**
	 * Function to decide the base to be used on images and URLs.
	 * 
	 * First we take the base from The URI (if provided), then we look for the base element in the head of the html document.<br/>
	 * A base defined at the document level takes precedence over the URI base.<br/>
	 * For full documentation see: http://www.w3.org/TR/REC-html40/struct/links.html#h-12.4
	 * 
	 * @param a-tidy-node $node the tidy Head node of the html document 
	 */
	private function decide_base( $node ) {
		$base = "";
		//Construct base from URL
		if($this->URI != "") {
			$base = $this->URI;
		}

		//Now parse Head and search for Base, since document defined base takes precedence over URI.
		$html_base = "";
		if( $node->hasChildren() ) {
			//Base is a first child element, it will be in the first set of nodes.
			//So we don't need to do deep parsing of the tree.
			foreach($node->child as $child) {
				if($child->id == TIDY_TAG_BASE && isset($child->attribute['href']) ) {
					$html_base = $child->attribute['href'];
					//Now, here is the interesting part.. since we do have to parse the URI and take out the filename info. 
					//Which may be present and is valid HTML.
					$parsed_html_base = parse_url($html_base);
					//Check if it parse_url worked
					if( isset($parsed_html_base['scheme']) ) {
						//Construct Base
						$html_base = $parsed_html_base['scheme'] . '://' . $parsed_html_base['host'];
						if( isset( $parsed_html_base['port'] ) ) $html_base .= ':' . $parsed_html_base['port'];
						//Check for dirs
						if( isset($parsed_html_base['path']) ) {
							$parsed_html_path = pathinfo($parsed_html_base['path']);
							$html_base .= $parsed_html_path['dirname'] . "/";	
							if( isset($parsed_html_path['basename']) && isset($parsed_html_path['filename']) && substr($html_base, -1) == "/" && $parsed_html_path['basename'] == $parsed_html_path['dirname']) {
								//it's of type http://www.example.com/dir1/
								$html_base .= $parsed_html_path['basename'] ."/"; 
							}
						} else {
							$html_base .= "/";
						}
					} else {
						//Invalid URI In Base
						$error['description'] = XMFP_INVALID_BASE_URI;
					}
				}
			}
		}
		if($html_base != "") $base = $html_base;
		$this->base = $base;
	}
	
	/**
	 * Iterate through all the values of the Document. Looking for Root Microformats
	 * 
	 * <p>It sets the variable $node_results with the results.</p>
	 * <p>From an Algorithmic analysys of the perspective of Iterating through nodes this procedure is O(n). Where n is the number of nodes of the document.</p>
	 *
	 * @param mf-root-elements $mf_root View mfdef.mF_roots.php
	 * @param a-tidy-node $node 
	 */
	private function parse_roots($mf_root, $node) {
		$this->mf_root = $mf_root;
		
		if( isset($node->attribute['class']) ) {
			//Explode classes by space since there can be more than one defined.
			$classes = explode(" ", $node->attribute['class']); 
			foreach($classes as $class) {
				//If the class is in the mf_roots and this node was not set for skipping
				if( isset( $mf_root[ $class ] ) && ! in_array($node->line ."-" . $node->column . "-".  $class, $this->toskip) ) {
					//Start constructing the result.
					$temp_result = $this->parse( $mf_root[ $class ]['childs'], $node  );
					//Add the ID, Line and Column to the result.
					if( isset($node->attribute['id']) ) {
						$id['node']['value'] = $node->attribute['id'];
						$temp_result['id'] = $id;			
					}
					$temp_result['line']['node']['value'] = $node->line;
					$temp_result['column']['node']['value'] = $node->column;
					
					$this->node_results[ $class ][] = $temp_result;
				}
			}
		}
		//Rel Mfs
		if( isset($node->id) && $node->id == TIDY_TAG_A && isset($node->attribute['rel']) ) {
			$rels = explode(" ", $node->attribute['rel']);
			foreach($rels as $rel) {
				//This is necesary to avoid mixing of values, since in xfn there might be more than one value defined in rel.
				unset($temp_result);
				//See if the rel element is defined and it is not marked for skipping
				if(isset( $mf_root[ 'rel-' . $rel ]) && ! in_array($node->line ."-" . $node->column . "-".  $rel, $this->toskip)) {
					//It's a direct getting of value since rel mfs have only one child
					//This is unnecesary. I should do a get_full_value_pure_semopt.
					$node_value = new Node_Value();
					$temp_result[ $rel ]['node'] = $this->get_full_value(&$node, &$node_value, $mf_root[ 'rel-' . $rel ]['childs'][ $rel ] ) ;
					$temp_result['line']['node']['value'] = $node->line;
					$temp_result['column']['node']['value'] = $node->column;
					$this->node_results[ 'rel-' . $rel ][] = $temp_result;
				}
			}
		} 
		//Continue parsing
		if( $node->hasChildren() ) {
			foreach ($node->child as $child) {
				$this->parse_roots($mf_root, $child);
			}
		}
	}
	/**
	 * Heavily recursive function for iterating through a tidy node and extracting it's microformat properties.
	 * 
	 * <p>When first called it recieves the microformat root structure. It iterates through the nodes looking for 
	 * the properties and stores them in $temp_result.</p>
	 * <p>Whean a microformat property has subproperties, it calls itself with a new $temp_result.</p>
	 * <p>From an Algorithmic analysis of the perspective of iterating through nodes this procedure is
	 *  O(n) when the microformat does not have subproperties. And also O(n) for each of the properties with childs.</p>
	 *
	 *
	 * @param a-mf-definition $mf_struct view mfdef.hCard.php
	 * @param a-tidy-node $node
	 * @param array $temp_result This is always passed by reference since it is constructed recursively.
	 * @return array $temp_result
	 */
	private function parse($mf_struct, $node, $temp_result = array(), $skip = false) {
		if( isset($node->attribute['class']) ) {
			//Because of the class="my-info vcard"
			$class_names = explode(" " , $node->attribute['class']);
			//This is necesary for the ocassional class="n fn"
			foreach($class_names as $class ) {
				//See if the class name exists in the microformat and it's not set to  be skipped (letpass was needed for the org to organization-name optimization of vcard)
				if ( isset( $mf_struct[ $class ] )   && ( (!in_array($node->line ."-" . $node->column . "-".  $class, $this->toskip)) || (isset($mf_struct[$class]['letpass']))) ) {
					//Mark for skipping
					if( isset($mf_struct[ $class ]['skip'] ) || $skip ) {
						$this->toskip[] = $node->line . "-" . $node->column . "-" . $class;
					}
					//If the mf property has child subproperties
					if( isset($mf_struct[ $class ]['childs']) ) {
						//Set this to skip child subproperties too.
						if( isset($mf_struct[ $class ]['skip'] ) ) $skip = true;
						//Check of Ocurrences
						if( $mf_struct[ $class ]['ocurrences'] == '1' || $mf_struct[ $class ]['ocurrences'] == '?' ) {			
							//mf property can have only one ocurrence.
							if( !isset( $temp_result[ $class ]['node'] ) ) {
								//Clean Up sub_temp_result
								unset($sub_temp_result);
								//Get the nodes value
								$temp_result[ $class ]['node'] = $this->get_full_value_recursive( $mf_struct[ $class ]['childs'], $node, &$sub_temp_result, $skip );
							} else {
								//It's an error. Since the property has already been set.
								$error['description'] = XMFP_MULTIPLE_OCURRENCE . ": " . "class : " . $class . "\n";
								$error['line'] = $node->line;
								$error['column'] = $node->column;
								$this->errors[] =  $error;
								unset($error);
							}
						} else {
							//mf property may have multiple ocurrences
							//Clean Up sub_temp_result
							unset($sub_temp_result);
							//Get the nodes value
							$temp_result[ $class ]['node'][] = $this->get_full_value_recursive( $mf_struct[ $class ]['childs'], $node, &$sub_temp_result, $skip );
						}
					} else {
						//Microformat has no subproperties. Just get the value.
						$node_value = new Node_Value();
						if( $mf_struct[ $class ]['ocurrences'] == '1' || $mf_struct[ $class ]['ocurrences'] == '?' ) {			
							//mf property can have only one ocurrence.
							//Check of Ocurrences
							if( !isset( $temp_result[ $class ]['node'] ) ) {
								$temp_result[ $class ]['node'] = $this->get_full_value( &$node, &$node_value, &$mf_struct[ $class ], $skip );
							} else {
								//It's an error. Since the property has already been set.
								$error['description'] = XMFP_MULTIPLE_OCURRENCE . ":\n" . "class: " . $class . "\n";
								$error['line'] = $node->line;
								$error['column'] = $node->column;
								$this->errors[] =  $error;
								unset($error);
							}								
						} else {
							//mf property can have multiple ocurrences
							$temp_result[ $class ]['node'][] = $this->get_full_value( &$node, &$node_value, &$mf_struct[ $class ], $skip );
						}
						unset($node_value);
					}
				}
				//Include Pattern
				//Reference http://microformats.org/wiki/include-pattern as of 27/05/2008
				if( $class == "include" && $node->id == TIDY_TAG_A && isset( $node->attribute['href'] ) && ! in_array($node->line ."-" . $node->column . "-".  $class, $this->toskip )) {
					//It's a reference to an object to be included
					$temp_result[ $class ]['node'] = $this->get_line_column_id( &$node );
					$temp_result[ $class ]['node']['value'] = $node->attribute['href'];
					$this->toskip[] = $node->line . "-" . $node->column . "-" . 'include';
				}
				if($class == "include" && $node->id == TIDY_TAG_OBJECT && isset( $node->attribute['data'] ) && ! in_array($node->line ."-" . $node->column . "-".  $class, $this->toskip ) ) {
					//It's an object to be included 
					$temp_result[ $class ]['node'] = $this->get_line_column_id( &$node );
					$temp_result[ $class ]['node']['value'] = $node->attribute['data'];
					$this->toskip[] = $node->line . "-" . $node->column . "-" . 'include';
				}
			}
		}
		//Rel Mfs
		if( isset($node->id) && $node->id == TIDY_TAG_A && isset($node->attribute['rel']) ) {
			$rels = explode(" ", $node->attribute['rel']);
			foreach($rels as $rel) {
				//I check for skipping here too since there might be a chance it parses tag and review tag in hReview
				if(isset( $mf_struct[ $rel ] ) && ! in_array($node->line ."-" . $node->column . "-".  $rel, $this->toskip)) {
					//Mark for skipping
					if( isset($mf_struct[ $rel ]['skip'] ) ) {
							$this->toskip[] = $node->line . "-" . $node->column . "-" . $rel;
					}
					//It's a direct getting of value since rel mfs have only one child
					//This is unnecesary. I should do a get_full_value_pure_semopt.
					if( $mf_struct[$rel]['ocurrences'] == '1' || $mf_struct[$node->attribute['rel']]['ocurrences'] == '?') {
						$temp_result[ $rel ]['node'] = $this->get_full_value_semopt(&$node, $mf_struct[ $node->attribute['rel'] ]['childs'][ $rel  ] ) ;
					} else {
						$temp_result[ $rel ]['node'][] = $this->get_full_value_semopt(&$node, $mf_struct[ $node->attribute['rel'] ]['childs'][ $rel  ] ) ;
					} 
					
				}
			}
		} 
		
		//Continue parsing
		if( $node->hasChildren() ) {
			foreach ($node->child as $child) {
				$this->parse($mf_struct, $child, &$temp_result, $skip);
			}
		}
			return $temp_result;
	}
	
	/**
	 * Function to get the line, column and id of an item.
	 * 
	 * @param a-tidy-node $node passed by reference
	 */
	private function get_line_column_id( &$node) {
		$full["line"] =  $node->line;
		$full['column'] = $node->column;
		if( isset($node->attribute['id']) ) {
			$full['id'] = $node->attribute['id'];
		} else {
			$full['id'] = false;
		}
		return $full;
		
	}

	/**
	 * Function to get the Full value of a node including semopts, and node value parsing, when the mf elements does not have childs.
	 * 
	 * @param a-tidy-node $node passed by reference
	 * @param a-node-value $node_value passed by reference
	 * @param a-mf_root-element-def passed by reference
	 */
	private function get_full_value( &$node, &$node_value, &$mf_elem_struct, $skip = false ) {
		//Get Line, Column, ID Data
		$full = $this->get_line_column_id( $node );
		//Check for Semantic Optimizations
		if( isset($mf_elem_struct["semopt"] ) ) {
			//There are semantic optimizations.
			foreach( $mf_elem_struct["semopt"] as $semopt ) {
				$tidy_node_id = "TIDY_TAG_" . strtoupper( $semopt['tag'] );
				//See if they apply
				if( $node->id == constant($tidy_node_id)  && isset( $node->attribute[ $semopt['att'] ] )  ) {
					$full["value"] = $node->attribute[ $semopt['att'] ];
				}
			}
		}
		//No Semantic Optimizations
		if(!isset($full["value"])) {
			$full["value"] = $node_value->get_value($node);
		}
		return $full;		
	}

	/**
	 * Function to get the Full value of an element that has childs.
	 * 
	 * @param a-mf_root-element-def $mf_struct_childs by reference
	 * @param a-tidy-node $node passed by reference
	 * @param temp-results $sub_temp_results passed by reference for construction of the full result.
	 */
	
	private function get_full_value_recursive( $mf_struct_childs, $node, &$sub_temp_result, $skip ) {
		$full = $this->get_line_column_id( $node );
		$full["value"] = $this->parse( $mf_struct_childs, $node, &$sub_temp_result, $skip );
		return $full;		
	}
	
	/**
	 * Function to get the Full value of a node that only has semopts (rels).
	 * 
	 * @param a-tidy-node $node passed by reference
	 * @param a-mf_root-element-def passed by reference
	 */
		private function get_full_value_semopt(&$node, &$mf_struct) {
		$full = $this->get_line_column_id( $node );
		
		foreach( $mf_struct["semopt"] as $semopt ) {
				$tidy_node_id = "TIDY_TAG_" . strtoupper( $semopt['tag'] );
				//See if they apply
				if( $node->id == constant($tidy_node_id)  && isset( $node->attribute[ $semopt['att'] ] )  ) {
					$full["value"] = $node->attribute[ $semopt['att'] ];
				}
			}
		return $full;
	}
	
	/**
	 * Function to process the original Node parsing on a Root Element Basis.
	 * 
	 * Does the post_processing of elements then processes includes and verifies required elements.
	 */
	private function process_results() {
		//Pre process includes
		$this->pre_process_includes();	
		//For each type of Mf found-
		foreach( $this->node_results as $mf_type => $mf_arr ) {
			//For each mf of that type found
			foreach($mf_arr as $mf_elems) {
				//Do post processing on an element by element basis.
				$temp_result = $this->process_nodes($this->mf_root[$mf_type], &$mf_elems);
				//Do Postprocessing on a root level basis.
				if( isset($this->mf_root[$mf_type]['postprocessing']) ) {
					foreach($this->mf_root[$mf_type]['postprocessing'] as $postproc ) {
						$postproc = "post_process_" . $postproc;
						$postproc(&$temp_result, &$this->errors, &$this->base);
					}
				}
				//This is for root mfs that may end up being childless due to the include pattern.
				if( count($temp_result) > 0) {
					//Add to Clean Results
					$this->clean_results[$mf_type][] = $temp_result;
				} 					
			}
		}
		//Now verify required values and clean lines and columns
		$this->verify_required();
	}
	
	/**
	 * Function to process the original Node parsing on a child Element Basis. It's done recursively.
	 * 
	 * Does the post_processing of elements.
	 * 
	 * @param string $mf_type the microformat element to be processed
	 * @param a-mf-elem-definition $mf_elems the elements of the mf being processed.
	 */

	private function process_nodes(&$mf_def, &$mf_elems) {
		$temp_results = array();
		//This is because an element might be deleted when doing includes pre processing.
		if(!is_array($mf_elems)) return;
				
		foreach($mf_elems as $elem_name => $mf_elem ) {
			if($elem_name == 'id' || $elem_name == 'include') continue;
			//Do postprocessing on the element
			if( isset( $mf_def['childs'][$elem_name]['postprocessing'] ) ) {
				foreach( $mf_def['childs'][$elem_name]['postprocessing'] as $postproc ) {
					$postproc = "post_process_" . $postproc;
					$postproc(&$mf_elem, &$this->errors, &$this->base);
				}
			}
			//See if it has subproperties
			if( isset($mf_elem['node']['value']) && is_array($mf_elem['node']['value']) ) {
				$temp_results[$elem_name] = $this->process_nodes(&$mf_def['childs'][$elem_name], $mf_elem['node']['value']);
			} else {
				//Single ocurrence Microformat
				if( isset( $mf_elem['node']['value'] ) ) {
					//Check for strictness
					if( !($this->be_strict && isset($mf_elem['node']['invalid']) ) ) {
						$temp_results[$elem_name] = $mf_elem['node']['value'];
					}
				} else {
					//I do the check first since it might have been deleted when processing includes
					if(isset($mf_elem['node']) && is_array($mf_elem['node'])) {
						//multiple ocurrence microformats, or subproperties with multiple values.
						foreach($mf_elem['node'] as $values) {
							if(!is_array($values['value'])) {
								if( !($this->be_strict && isset($values['invalid']) ) ) {
									$temp_results[$elem_name][] = $values['value'];
								}
							} else {
								$temp_results[$elem_name][] = $this->process_nodes(&$mf_def['childs'][$elem_name], $values['value']);
							}
						}
					}
				}
			}
		}
		return $temp_results;
	}
	
	/**
	 * Function to pre process includes. We only delete when it's root level inclusion of same type microformats.
	 * 
	 * Based on http://microformats.org/wiki/include-pattern 
	 */	
	private function pre_process_includes() {
		$sources = array();
		$targets = array();
		
		//Iterate through all microformats.
		foreach($this->node_results as $mf_type => $mfs) {
			foreach($mfs as $mf_key => $mf) {
				$this->search_for_target_includes($mf, $mf_type, $mf_key, $mf_type, $this->mf_root[$mf_type], $this->node_results[$mf_type][$mf_key], $targets, $sources );
			}
		}
		
		$count = count($targets);
		for($x = 0; $x<$count; $x++) {
			$include_id = mb_eregi_replace("#", "", $targets[$x]['id']);
			$source = $this->find_sources_from_id(&$sources, $include_id);
			$count_s = count($source);
			for($y=0; $y<$count_s; $y++) {
				//Check that we are not	loop including
				if( !($targets[$x]['parent_type'] == $source[ $y ]['parent_type']
				 && $targets[$x]['parent_key'] == $source[ $y ]['parent_key'])) {
					
				 	if($targets[$x]['parent_type'] == $source[ $y ]['parent_type']) {
						//we are merging from the same type.
						if($targets[$x]['elem'] == $source[ $y ]['elem'] && $targets[$x]['elem'] == $targets[$x]['parent_type']) {
							//They are both root level microformats. Merge them.
							$targets[$x]['node'] = array_merge($source[ $y ]['node'], $targets[$x]['node']);
							//Unset source
							$source[ $y ]['node'] = "";
						} else {
							//It's a subelement of the root microformat
							if( isset($targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]) ) {
								//It's a valid element.. process.
								if($targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]['ocurrences'] == '1' || $targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]['ocurrences'] == '?') {
									//It's a unique property check if it already exists
									if(isset($targets[$x]['node'][ $source[ $y ]['elem'] ])) {
										//It already exists. Do Nothing.
										$error['description'] = XMFP_INCLUDE_MULTIPLE_OCURRENCE . ": " . $source[ $y ]['elem'];
										$error['line'] = $targets[$x]['node']['include']['node']['line'];
										$error['column'] = $targets[$x]['node']['include']['node']['column'];
//										$source[ $y ]['node'] = "";
									} else {
										//Add
										$targets[$x]['node'][ $source[ $y ]['elem']] = $source[ $y ]['node'];
										//Unset source
//										$source[ $y ]['node'] = "";
									}
								} else {
									//It's a multi property, just add it.
									$targets[$x]['node'][ $source[ $y ]['elem']]['node'][] = $source[ $y ]['node'];
									//Unset source
//									$source[ $y ]['node'] = "";
								}
							}
						}
					} else {
						//We are merging of different types
						//So it must be a subelement of the root microformat
						if( isset($targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]) ) {
							//It's a valid element.. process.
							if($targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]['ocurrences'] == '1' || $targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]['ocurrences'] == '?') {
								//It's a unique property check if it already exists
								if(isset($targets[$x]['node'][ $source[ $y ]['elem'] ])) {
									//It already exists. Do Nothing.
									$error['description'] = XMFP_INCLUDE_MULTIPLE_OCURRENCE . ": " . elem;
									$error['line'] = $targets[$x]['node']['line'];
									$error['column'] = $targets[$x]['node']['line'];
								} else {
									//check whether the element has childs itself
									if( isset( $targets[$x]['def']['childs'][ $source[ $y ]['elem'] ]['childs'] ) ) {
										//It does, construct the full node
										$new_node[$source[ $y ]['elem']]['node']['value'] = $source[ $y ]['node'];
										$targets[$x]['node'] = $new_node;
										unset($new_node);
									} else {
										$targets[$x]['node'][ $source[ $y ]['elem']] = $source[ $y ]['node'];
									}
									//Unset source
//									$source[ $y ]['node'] = "";
								}
							} else {
								//It's a multi property, just add it.
								$targets[$x]['node'][$source[ $y ]['elem']]['node'][] = $source[ $y ]['node'];
							}
						}
					}
				}
			}
		}
	}

	/**
	 * function to get the array of sources that have the id needed.
	 * 
	 * @param array $sources
	 * @param string $include_id 
	 */
	private function find_sources_from_id(&$sources, $include_id) {
		$tr = array();
		foreach($sources as $source) {
			if($source['id'] == $include_id) $tr[] = $source;
		}
		return $tr;
	}
	
	/**
	 * Function for finding all the nodes that have either an ID (sources) or an Include (targets) setted up
	 * 
	 * @param node $mf the node to do the searching in
	 * @param string $parent_type the type of the parent
	 * @param int $parent_key the key of the parent
	 * @param string $elem the microformat property name
	 * @param array $mf_def the microformat definition for this element
	 * @param node $node the node we are parsing
	 * @param array $targets the nodes with include set
	 * @param array $sources the nodes with id set
	 */
	private	function search_for_target_includes($mf, $parent_type, $parent_key, $elem, &$mf_def, &$node, &$targets, &$sources) {
		if(!is_array($mf)) return;
		if(isset($mf['node']['id']) && trim($mf['node']['id'])!="" ) {
			$new_source['parent_type'] = $parent_type;
			$new_source['parent_key'] = $parent_key;
			$new_source['node'] = &$node;
			$new_source['id'] = $mf['node']['id'];
			$new_source['elem'] = $elem;
			$sources[] = $new_source;
		} elseif(isset($mf['id']['node']['value']) && trim($mf['id']['node']['value']) != "") {
			$new_source['parent_type'] = $parent_type;
			$new_source['parent_key'] = $parent_key;
			$new_source['node'] = &$node;
			$new_source['id'] = $mf['id']['node']['value'];
			$new_source['elem'] = $elem;
			$sources[] = $new_source;
		} elseif(isset($mf['node']) && is_array($mf['node']) && key($mf['node']) === 0) {
			//Multivalue subproperties
			foreach($mf['node'] as $multi_key => $multi_value) {
				if( isset($mf['node'][$multi_key]['id']) && is_string($mf['node'][$multi_key]['id']) )	{		
					$new_source['parent_type'] = $parent_type;
					$new_source['parent_key'] = $parent_key;
					$new_source['node'] = &$node['node'][$multi_key];
					$new_source['id'] = $mf['node'][$multi_key]['id'];
					$new_source['elem'] = $elem;
					$sources[] = $new_source;
				}
			}
		}
		if(isset($mf['include']['node']['value'])) {
			$new_target['parent_type'] = $parent_type;
			$new_target['parent_key'] = $parent_key;
			$new_target['node'] = &$node;
			$new_target['id'] = $mf['include']['node']['value'];
			$new_target['def'] = &$mf_def;
			$new_target['elem'] = $elem;
			$targets[] = $new_target;					
		}
		foreach($mf as $key => $val) {
			if( isset($mf_def['childs'][$key]) ) {
				$this->search_for_target_includes($val, $parent_type, $parent_key, $key, $mf_def['childs'][$key], $node[$key],  $targets, $sources);
			} else {
				$this->search_for_target_includes($val, $parent_type, $parent_key, $elem, $mf_def, $node[$key], $targets, $sources);
			}
		}
	}

	/**
	 * Verifies required elements on a Root level Basis.
	 * @todo verify recursively on child elements. (Don't know if this is needed since the mf specs are pretty open)
	 */
	private function verify_required() {
		foreach($this->clean_results as $mf_type => $mfs) {
			foreach($mfs as $mf_key => $mf) {
				
				//Check to see if it's an orphaned mf
				if(count($mf) == 2 && isset($mf['line']) && isset($mf['column'])) {
					unset($this->clean_results[$mf_type][$mf_key]);
					continue;
				}

				foreach( $this->mf_root[ $mf_type ]['childs'] as $mf_elem => $mf_elem_def) {
					if(isset($mf_elem_def['ocurrences']) && $mf_elem_def['ocurrences'] == '1' ) {
						//Check if the element exist in the microformat
						if(!isset( $mf[ $mf_elem ] ) ) {
							$error['description'] = XMFP_MISSING_REQUIRED . ": " . $mf_elem . "\n";
							$error['line'] = $mf['line'];
							$error['column'] = $mf['column'];
							$this->errors[] =  $error;
							unset($error);
							if($this->be_strict) {
								unset($this->clean_results[$mf_type][$mf_key]);
							}
						}
					}
					if( isset( $mf_elem_def['childs'] ) && isset( $mf[ $mf_elem ] ) ) {
						$this->verify_required_childs($mf_elem_def, $mf[ $mf_elem ], $this->clean_results[$mf_type][$mf_key]['line'], $this->clean_results[$mf_type][$mf_key]['column'], $mf_type );	
					}
				}
				//clean up line numbering since it's not needed anymore
				unset($this->clean_results[$mf_type][$mf_key]['line']);
				unset($this->clean_results[$mf_type][$mf_key]['column']);
				if(isset($this->clean_results[$mf_type][$mf_key]['include'])) unset($this->clean_results[$mf_type][$mf_key]['include']);
			}
			//Reorder array
			$this->clean_results[$mf_type] = array_values($this->clean_results[$mf_type]);
		}
	}
	
	private function verify_required_childs($mf_node_def, $node, $line, $column, $path) {
		foreach($mf_node_def['childs'] as $mf_prop => $mf_prop_def) {
			if(isset($mf_prop_def['ocurrences']) && $mf_prop_def['ocurrences'] == '1' ) {
				if(!isset( $node[ $mf_prop ] ) ) {
					$error['description'] = XMFP_MISSING_REQUIRED_SUB . ": " . $path . '->' . $mf_prop . "\n";
					$error['line'] = $line;
					$error['column'] = $column;
					$this->errors[] =  $error;
					unset($error);
				}
			}
			if( isset( $mf_prop_def['childs'] ) && isset( $node[ $mf_prop ] ) ) {
				$this->verify_required_childs($mf_prop_def, $node[ $mf_prop], $line, $column, $path . '->' . $mf_prop);
			}
		}
	} 
	
	/**
	 * URI setter
	 */
	private function set_URI($URI) {
		$this->URI = $URI;
	}
	/**
	 * be_strict setter
	 */
	private function set_be_strict($be_strict) {
		$this->be_strict = $be_strict;
	}
	/**
	 * Results getter.
	 */
	public function get_parsed_mfs() {
		return $this->clean_results;
	}
	/**
	 * Errors getter.
	 */
	public function get_errors() {
		return $this->errors;
	}
	
	/**
	 * Get as XML
	 * 
	 * @param boolean $show_errors whether or not to show errors on the results
	 * @param boolean $nice whether or not to show in nice format, turn to false for less verbosity.
	 */
	public function get_parsed_mfs_as_XML($show_errors = false, $nice = true) {
		$xml = '<microformats';
		//Add Uri to the microformats definition
		if($this->URI) {
			$xml .= ' from="' . $this->URI . '"';
		}
		$xml .= '>' . ($nice ? "\n" : '') ;
		//Start with all root elements
		foreach($this->clean_results as $mf_name => $mf_roots) {
			foreach($mf_roots as $mf) {
				$xml .= $this->parse_to_xml($mf_name, $mf, 0, $nice);
			}
		}
		//Show errors if set
		if($show_errors && count($this->errors) > 0) {
			$xml .= ($nice ? "\n" : '') . '<errors>' . ($nice ? "\n" : '');
			foreach($this->errors as $error) {
				$xml .= '<error>' . ($nice ? "\n" : '') . '<description>' . $error['description'] . '</description>' . ($nice ? "\n" : '');
				if(isset($error['line'])) 	$xml .= '<line>' . $error['line'] . '</line>' . ($nice ? "\n" : '');
				if(isset($error['column'])) $xml .= '<column>' . $error['column'] . '</column>' . ($nice ? "\n" : '');
				$xml .= '</error>' . ($nice ? "\n" : '');
			}
			$xml .= '</errors>' . ($nice ? "\n" : '');
		}
		$xml .= '</microformats>';
		return $xml;
	}
	
	/**
	 * Function that recursively processes the microformats found and generates the appropiate xml.
	 * 
	 * @param string $elem_name the name of the element
	 * @param clean-results-node $elem the element to be processed
	 * @param int $depth used for tabbing when using nice
	 * @param boolean $nice wether to do nice or just send a full long string
	 */
	private function parse_to_xml($elem_name, $elem, $depth, $nice) {
		$xml = "";
		//If the element is an array
		if( is_array($elem) ) {
			//If it is of multiple values rather than childs
			if(key($elem) == '0') {
				//Iterate through multiple values
				foreach($elem as $el) {
					//If the values have childs themselves
					if(!is_array($el)) {
						$xml .= ($nice ? $this->tab_depth($depth) : '') . '<' . $elem_name . '>' . $el . '</' . $elem_name . '>' . ($nice ? "\n" : '');
					} else {
						$xml .=  $this->parse_to_xml($elem_name, $el, $depth, $nice);
					}
				}
			} else {
				//It's a child arrays (with meaningfull keys)
				$xml .= ($nice ? $this->tab_depth($depth) : '') . '<' . $elem_name . '>'. ($nice ? "\n" : '');
				//Iterate through childs
				foreach($elem as $el_name => $el) {
					$xml .= $this->parse_to_xml($el_name, $el, $depth+1, $nice); 
				}
				$xml .= ($nice ? $this->tab_depth($depth) : '') . '</' . $elem_name . '>' . ($nice ? "\n" : '');
			}
		} else {
			//It's a single ocurrence element
			$xml .= ($nice ? $this->tab_depth($depth) : '') . '<' . $elem_name . '>' . $elem . '</' . $elem_name . '>' . ($nice ? "\n" : '');
		}
		return $xml;
	}
	/**
	 * helper function to tab the xml results
	 */	
	private function tab_depth($depth) {
		$tab = '';
		for($x=0; $x<$depth; $x++) {
			$tab .= "\t";
		}
		return $tab;
	}
	/**
	 * Get as JSON
	 * 
	 * @param boolean $show_errors whether or not to show errors on the results
	 * @param boolean $no_line_breaks whether or not to show the result with line breaks, since PHP will not parse a JSON string that has line breaks.
	 */
	public function get_parsed_mfs_as_JSON($show_errors = false, $no_line_breaks = true) {
		$json = '{ ';
		//Add Uri to the microformats definition
		if($this->URI) {
			$json .= ' from:"' . $this->URI . '",';
		}
		$json .= ' "microformats":{ ' ;
//		print_r($this->clean_results);
		//Start with all root elements
		$passed_comma_roots = false;
		foreach($this->clean_results as $mf_name => $mf_roots) {
			$json .= ($passed_comma_roots ? ', ' : '') . '"' . $mf_name . '":[ ';
			$passed_comma = false;
			foreach($mf_roots as $mf) {
				$json .= ($passed_comma ? ', ' : '') . $this->parse_to_json($mf_name, $mf, true);
				$passed_comma = true;
			}
			$json .= '] ';
			$passed_comma_roots = true;
		}
		
		$json .= ' }';
		
		//Show errors if set
		if($show_errors && count($this->errors) > 0) {
			$json .= ', "errors":[ ';
			$passed_errors = false;
			foreach($this->errors as $error) {
				$json .= ($passed_errors ? ', ' : '') . '{ "description":"' . $error['description'] . '"';
				if(isset($error['line'])) 	$json .= ', "line":"' . $error['line'] . '"';
				if(isset($error['column'])) $json .= ', "column":"' . $error['column'] . '"';
				$json .= '}';
				$passed_errors = true;
			}
			$json .= ' ]';
		}
		$json .= '}';
		if($no_line_breaks) $json = str_replace("\n", "", $json);
		return $json;
	}
	
	/**
	 * Function that recursively processes the microformats found and generates the appropiate JSON notation.
	 * 
	 * @param string $elem_name the name of the element
	 * @param clean-results-node $elem the element to be processed
	 * @param int $depth used for tabbing when using nice
	 * @param boolean $nice wether to do nice or just send a full long string
	 */
	private function parse_to_json($elem_name, $elem, $elem_name_done=false) {
		$json = "";
		//If the element is an array
		if( is_array($elem) ) {
			//If it is of multiple values rather than childs
			if(key($elem) == '0') {
				$json .= ($elem_name_done ? '' : ('"' .$elem_name .'":')) . '[ ';
				$passed_comma = false;
				//Iterate through multiple values
				foreach($elem as $el) {
					$json .= ($passed_comma ? ', ' : '');
					//If the values have childs themselves
					if(!is_array($el)) {
						$json .=  '"' . $el . '"';
					} else {
						$json .=  $this->parse_to_json($elem_name, $el, true);
					}
					$passed_comma = true;
				}
				$json .= '] ';
			} else {
				//It's a child arrays (with meaningfull keys)
				$json .= ($elem_name_done ? '' : ('"' .$elem_name .'":')) . '{ ';
				$passed_comma = false;
				//Iterate through childs
				foreach($elem as $el_name => $el) {
					$json .= ($passed_comma ? ', ' : '') . $this->parse_to_json($el_name, $el); 
					$passed_comma = true;
				}
				$json .= '} ';
			}
		} else {
			//It's a single ocurrence element
			$json .= '"' . $elem_name . '":"' . $elem . '"';
		}
		return $json;
	}
	
}

?>
