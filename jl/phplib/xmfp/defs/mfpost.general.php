<?php
/**
 * Set of functions for dealing with general validations and formatting.
 * 
 * @package xmfp
 * @subpackage post_processing
 */

/**
 * Take out mailto: from an email value
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 */

function post_process_clean_email( &$mf_node, &$errors, &$base) {
	if( isset($mf_node['node']['value']) ) {
		//Singular ocurrence
		 $mf_node['node']['value'] = mb_eregi_replace("mailto:", "", $mf_node['node']['value']);
	} else {
		//Multiple Ocurrences
		//Multiple Ocurrences of the element
		$count_nodes = count($mf_node['node']);
		//This is needed instead of a foreach cause we are passing by reference the value.
		for($x=0; $x<$count_nodes; $x++) {
			$mf_node['node'][$x]['value'] = mb_eregi_replace("mailto:", "", $mf_node['node'][$x]['value']);
		}
	}
}

/**
 * Process the value of URIS to generate full URIS from relative ones.
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 */
function post_process_base( &$mf_node, &$errors, &$base) {
	if(isset($mf_node['node']['value'])) {
		//Singular Ocurrence of the element
		post_process_base_node(&$mf_node, &$errors, &$base, false);
	} else {
		//Multiple Ocurrences of the element
		$count_nodes = count($mf_node['node']);
		//This is needed instead of a foreach cause we are passing by reference the value.
		for($x=0; $x<$count_nodes; $x++) {
			post_process_base_node(&$mf_node['node'][$x], &$errors, &$base, true);
		}
	}
}

/**
 * The actual processing of post_process_base, based on wether a property is singular or multivalue. 
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 * @param boolean $is_multi wether it's a single ocurrence or multiple ocurrence property
 */
function post_process_base_node(&$mf_node, &$errors, &$base, $is_multi) {
		if($is_multi) {
			$parsed_uri = parse_url( $mf_node['value'] );
		} else {
			$parsed_uri = parse_url( $mf_node['node']['value'] );
		}
		
		if( isset($parsed_uri['scheme']) ) {
			//It's a full URL, no need to work on this one.
			return;
		} elseif( $base != "" && ( ($is_multi && substr($mf_node['value'], 0 , 1) == "/") || (!$is_multi && substr($mf_node['node']['value'], 0 , 1) == "/"))  ) {
			//It starts in the root of the URL path. We need to reconstruct the base.
			$parsed_base = parse_url($base);
			if($parsed_base['scheme']) {
				//Construct Base
				$base = $parsed_base['scheme'] . '://' . $parsed_base['host'];
				if( isset( $parsed_base['port'] ) ) $base .= ':' . $parsed_base['port'];
				
				if($is_multi) {
					$mf_node['value'] = $base . $mf_node['value'];
				} else {
					$mf_node['node']['value'] = $base . $mf_node['node']['value'];
				}
			}
			//If the base cannot be parsed we leave like it is
			return;
		} else {
			//We check to see if the URI is a fragment
			if($is_multi && substr($mf_node['value'],0,1) == "#") {
				$mf_node['value'] = $base . $mf_node['value'];
				return;
			} elseif(!$is_multi && substr($mf_node['node']['value'],0,1) == "#") {
				$mf_node['node']['value'] = $base . $mf_node['node']['value'];
				return;
			}
			//If not, we have to construct the base and then we simple add the value to the base (browsers, etc, can handle http://www.example.com/dir/../images/pic.jpg)
			$parsed_URI = parse_url($base);
			//Check if it parse_url worked
			if( isset($parsed_URI['scheme']) ) {
				$new_base = "";
				//Construct Base
				$new_base = $parsed_URI['scheme'] . '://' . $parsed_URI['host'];
				if( isset( $parsed_URI['port'] ) ) $new_base .= ':' . $parsed_URI['port'];
				//Check for dirs
				if( isset($parsed_URI['path']) ) {
					$parsed_path = pathinfo($parsed_URI['path']);
					$new_base .= $parsed_path['dirname'] . "/";	
					if( isset($parsed_path['basename']) && isset($parsed_path['filename']) && substr($base, -1) == "/" && $parsed_path['basename'] == $parsed_path['dirname']) {
						//it's of type http://www.example.com/dir1/
						$new_base .= $parsed_path['basename'] ."/"; 
					}
				} else {
					$new_base .= "/";
				}
			} else {
				//Invalid URI
				$error['description'] = XMFP_INVALID_URI;
				$errors[] = $error;
			}
			//Now that we have constructed the base
			//we simple add the value to the base (browsers, etc, can handle http://www.example.com/dir/../images/pic.jpg)
			if($is_multi) {
				$mf_node['value'] = $new_base . $mf_node['value'];
			} else {
				$mf_node['node']['value'] = $new_base . $mf_node['node']['value'];
			}
		}
}

/**
 * Validation of URIS
 * 
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 */
 function post_process_url( &$mf_node, &$errors, &$base) {
	if(isset($mf_node['node']['value'])) {
		//Singular Ocurrence of the element
		post_process_url_node(&$mf_node, &$errors, &$base, false);
	} else {
		//Multiple Ocurrences of the element
		$count_nodes = count($mf_node['node']);
		//This is needed instead of a foreach cause we are passing by reference the value.
		for($x=0; $x<$count_nodes; $x++) {
			post_process_url_node(&$mf_node['node'][$x], &$errors, &$base, true);
		}
	}
}

/**
 * The actual processing of post_process_url, based on wether a property is singular or multivalue. 
 * 
 * Regexp was taken from here: http://pear.php.net/package/Validate
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 * @param boolean $is_multi wether it's a single ocurrence or multiple ocurrence property
 */
function post_process_url_node( &$mf_node, &$errors, &$base, $is_multi) {
	$regexp = '&^(?:([a-z][-+.a-z0-9]*):)?                             # 1. scheme
              (?://                                                   # authority start
              (?:((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();:\&=+$,])*)@)?    # 2. authority-userinfo
              (?:((?:[a-z0-9](?:[-a-z0-9]*[a-z0-9])?\.)*[a-z](?:[a-z0-9]+)?\.?)  # 3. authority-hostname OR
              |([0-9]{1,3}(?:\.[0-9]{1,3}){3}))                       # 4. authority-ipv4
              (?::([0-9]*))?)                                        # 5. authority-port
              ((?:/(?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'():@\&=+$,;])*)*/?)? # 6. path
              (?:\?([^#]*))?                                          # 7. query
              (?:\#((?:%[0-9a-f]{2}|[-a-z0-9_.!~*\'();/?:@\&=+$,])*))? # 8. fragment
              $&xi';
	if($is_multi) {
		$match = preg_match($regexp, $mf_node['value']);
	} else {
		$match = preg_match($regexp, $mf_node['node']['value']);
	}
	if(!$match) {
		if($is_multi) {
			$error['description'] = XMFP_INVALID_URI . ": " . $mf_node['value'];
			$error['line'] = $mf_node['line'];
			$error['column'] = $mf_node['column'];
			$mf_node['invalid'] = true;
		} else {
			$error['description'] = XMFP_INVALID_URI . ": " . $mf_node['node']['value'];
			$error['line'] = $mf_node['node']['line'];
			$error['column'] = $mf_node['node']['column'];
			$mf_node['node']['invalid'] = true;
		}
		$errors[] = $error;
		unset($error);
	}
}

/**
 * Validation of Emails
 * 
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 */

function post_process_email( &$mf_node, &$errors, &$base) {
	if(isset($mf_node['node']['value'])) {
		//Singular Ocurrence of the element
		post_process_email_node(&$mf_node, &$errors, &$base, false);
	} else {
		//Multiple Ocurrences of the element
		$count_nodes = count($mf_node['node']);
		//This is needed instead of a foreach cause we are passing by reference the value.
		for($x=0; $x<$count_nodes; $x++) {
			post_process_email_node(&$mf_node['node'][$x], &$errors, &$base, true);
		}
	}
}

/**
 * The actual processing of post_process_email, based on wether a property is singular or multivalue. 
 * 
 * Regexp was taken from here: http://pear.php.net/package/Validate
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 * @param boolean $is_multi wether it's a single ocurrence or multiple ocurrence property
 */
function post_process_email_node( &$mf_node, &$errors, &$base, $is_multi) {
	$regexp = '&^(?:                                               # recipient:
         ("\s*(?:[^"\f\n\r\t\v\b\s]+\s*)+")|                          #1 quoted name
         ([-\w!\#\$%\&\'*+~/^`|{}]+(?:\.[-\w!\#\$%\&\'*+~/^`|{}]+)*)) #2 OR dot-atom
         @(((\[)?                     #3 domain, 4 as IPv4, 5 optionally bracketed
         (?:(?:(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:[0-1]?[0-9]?[0-9]))\.){3}
               (?:(?:25[0-5])|(?:2[0-4][0-9])|(?:[0-1]?[0-9]?[0-9]))))(?(5)\])|
         ((?:[a-z0-9](?:[-a-z0-9]*[a-z0-9])?\.)*[a-z0-9](?:[-a-z0-9]*[a-z0-9])?)  #6 domain as hostname
         \.((?:([^- ])[-a-z]*[-a-z]))) #7 TLD 
         $&xi';
	if($is_multi) {
		$match = preg_match($regexp, $mf_node['value']);
	} else {
		$match = preg_match($regexp, $mf_node['node']['value']);
	}
		
	if(!$match) {
		if($is_multi) {
			$error['description'] = XMFP_INVALID_EMAIL . ": " . $mf_node['value'];
			$error['line'] = $mf_node['line'];
			$error['column'] = $mf_node['column'];
			$mf_node['invalid'] = true;
		} else {
			$error['description'] = XMFP_INVALID_EMAIL . ": " . $mf_node['node']['value'];
			$error['line'] = $mf_node['node']['line'];
			$error['column'] = $mf_node['node']['column'];
			$mf_node['node']['invalid'] = true;
		}
		$errors[] = $error;
		unset($error);
	}	
}

/**
 * Validation of Dates
 * 
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 */

function post_process_date( &$mf_node, &$errors, &$base) {
	if(isset($mf_node['node']['value'])) {
		//Singular Ocurrence of the element
		post_process_date_node(&$mf_node, &$errors, &$base, false);
	} else {
		//Multiple Ocurrences of the element
		$count_nodes = count($mf_node['node']);
		//This is needed instead of a foreach cause we are passing by reference the value.
		for($x=0; $x<$count_nodes; $x++) {
			post_process_date_node(&$mf_node['node'][$x], &$errors, &$base, true);
		}
	}
}

/**
 * The actual processing of post_process_url, based on wether a property is singular or multivalue. 
 * 
 * Regexp was adapted from the one published here: http://www.evotech.net/blog/2007/08/converting-iso-8601-date-in-php/
 *
 * @param a-tidy-node $mf_node
 * @param xmfp->errors $errors passed by reference
 * @param xmfp->base $base
 * @param boolean $is_multi wether it's a single ocurrence or multiple ocurrence property
 */
function post_process_date_node( &$mf_node, &$errors, &$base, $is_multi) {
	$regexp = '/^\d{4}-\d{2}-\d{2}T?(\d{2}:\d{2}:\d{2})?(Z?|([+-])(\d{2}):(\d{2})?)$/';
	if($is_multi) {
		$match = preg_match($regexp, $mf_node['value']);
	} else {
		$match = preg_match($regexp, $mf_node['node']['value']);
	}
		
	if(!$match) {
		if($is_multi) {
			$error['description'] = XMFP_INVALID_DATE . ": " . $mf_node['value'];
			$error['line'] = $mf_node['line'];
			$error['column'] = $mf_node['column'];
			$mf_node['invalid'] = true;
		} else {
			$error['description'] = XMFP_INVALID_DATE . ": " . $mf_node['node']['value'];
			$error['line'] = $mf_node['node']['line'];
			$error['column'] = $mf_node['node']['column'];
			$mf_node['node']['invalid'] = true;
		}
		$errors[] = $error;
		unset($error);
	}	
}

?>