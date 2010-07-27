<?php
/**
 * Set of Functions for dealing with hCard specific optimizations and validations.
 * 
 * @package xmfp
 * @subpackage post_processing
 * @author Emiliano Martínez Luque
 */

/**
 * Check if Type of Tel is valid according to the values in the hCard Specification.
 *
 * @param unknown_type $mf_node
 * @param unknown_type $errors
 * @param unknown_type $base
 */
function post_process_tel_type_value( &$mf_node, &$errors, &$base) {
	//If we are in the top level tel.
	if(isset( $mf_node['node'] ) && is_array( $mf_node['node'] ) && !isset( $mf_node['node']['line'] ) ) {
		//Valid types
		$valid_types = array("home", "work", "pref", "fax", "cell", "pager");
		//Iterate through tel childs
		$count_nodes = count($mf_node['node']);
		for($x=0; $x<$count_nodes; $x++) {
			//Check if this is type
			if(isset($mf_node['node'][$x]['value']['type'])) {
				$count_tels = count($mf_node['node'][$x]['value']['type']['node']);
				//Iterate through types
				for($y=0; $y<$count_tels; $y++) {
					//Do validation
					if(!in_array($mf_node['node'][$x]['value']['type']['node'][$y]['value'], $valid_types)) {
						$error['description'] = XMFP_HCARD_INVALID_TEL_TYPE_VALUE . ": " . $mf_node['node'][$x]['value']['type']['node'][$y]['value'];
						$error['line'] = $mf_node['node'][$x]['value']['type']['node'][$y]['line'];
						$error['column'] = $mf_node['node'][$x]['value']['type']['node'][$y]['column'];
						$errors[] =  $error;
						unset($error);
						$mf_node['node'][$x]['value']['type']['node'][$y]['invalid'] = true;
					} 					
				}
			}
		}
	}
}

/**
 * Implied organization-name optimization as defined in: http://microformats.org/wiki/hcard#Implied_.22organization-name.22_Optimization as of may 31 2008
 *
 * @param unknown_type $mf_result
 * @param unknown_type $errors
 */

function post_process_org_name( &$mf_node, &$errors, &$base) {
	if( isset($mf_node['node']['value']['org']) && !isset($mf_node['node']['value']['organization-name']) ) {
		$mf_node['node']['value']['organization-name']['node']['value'] = $mf_node['node']['value']['org']['node']['value'];
		unset($mf_node['node']['value']['org']);
	}
}

/**
 * Implied n and nickname optimization as defined in: http://microformats.org/wiki/hcard#Implied_.22n.22_Optimization and http://microformats.org/wiki/hcard#Implied_.22nickname.22_Optimization as of may 31 2008
 * This is done on the already cleaned up vcard
 *
 * @param unknown_type $mf_result
 * @param unknown_type $errors
 */
function post_process_fn_optimizations( &$mf, &$errors ) {
	//If fn is different than org
	if( isset($mf['fn']) && !( isset($mf['org']['organization-name']) && ( $mf['org']['organization-name'] == $mf['fn'] ))  ) {
		$exp = explode(" ", $mf['fn'] );
		//And fn is 2 words and n does not exist in the mf.
		if( count($exp) == 2 && !isset($mf['n'])) {
			$mf['n']['given-name'][] = $exp[0];
			$mf['n']['family-name'][] = $exp[1];
		} elseif(count($exp) == 1 && !isset($mf['nickname'] ) )  {
			//And fn is only one word and nickname does not exist in the mf.
			$mf['nickname'] = $exp;			
		}
	}
}

?>