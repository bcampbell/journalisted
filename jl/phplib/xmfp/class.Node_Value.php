<?php
/**
 * File for Class Node_Value
 * @package xmfp
 */

/**
 * Class for Taking the value from a tidy (HTML) node including the formatting especified by the Microformats parsing definition.
 * 
 * Based on the Document: http://microformats.org/wiki/hcard-parsing
 * As of the version 0f 18:10, 7 Feb 2008 
 * 
 * @author Emiliano Martínez Luque
 * @package xmfp
 */
class Node_Value {
	/**
	 * The Final Value of the Node
	 */
	private $value;
	/**
	 * The intermediate text when substracting the value from a set of subNodes
	 */
	private $text;
	/**
	 * To calculate when the text to be picked is inside multiple value nodes
	 */
    private $in_class_value = FALSE;
    /**
     * To prevent nested class value picking
     */
    private $nested_class_value = FALSE;
 	/**
	 * Function recursively called to iterate through a set of nodes
	 * to get it's value in a formated way
	 *
	 * @param a-tidy-node $node
	 * @param int $depth
	 */
    private function get_node_value($node, $depth) {
        if($depth==0) {
            //Line breaks
            if( $node->id == TIDY_TAG_BR) {
            	$this->value .= '\n';
                return;
            }
            
            //<Abbr> title
            if($node->id == TIDY_TAG_ABBR && isset($node->attribute['title']) ) {
                $this->value = $node->attribute['title'];
                return;
            }
            //<Img> or <Area> alt
            if($node->id == TIDY_TAG_IMG || $node->id == TIDY_TAG_AREA) {
                $this->value = $node->attribute['alt'];
                return;
            }
        }
        
        //For handling of whitespace
        if(isset($node->id) && $node->id == TIDY_TAG_PRE) $this->in_pre_depth = $depth;
        
        if($node->type == 4) {
        		$this->text .= $node->value;
    	} elseif(isset($node->id) && $node->id == TIDY_TAG_DEL) {
            //<Del> Tag
            return;
        } elseif($node->type == TIDY_NODETYPE_COMMENT) {
            //Comment
            return;
        } else {
            //Line breaks. Only when depth is different than 0..
            if($depth!=0) {
                //Soft \n
               if($node->id == TIDY_TAG_DIV || $node->id == TIDY_TAG_DL || $node->id == TIDY_TAG_DT || $node->id == TIDY_TAG_LI || $node->id == TIDY_TAG_H1 || $node->id == TIDY_TAG_H2 || $node->id == TIDY_TAG_H3 || $node->id == TIDY_TAG_H4 || $node->id == TIDY_TAG_H5 || $node->id == TIDY_TAG_H6 || $node->id == TIDY_TAG_P || $node->id == TIDY_TAG_TABLE || $node->id == TIDY_TAG_TBODY || $node->id == TIDY_TAG_THEAD || $node->id == TIDY_TAG_TFOOT || $node->id == TIDY_TAG_TR || $node->id == TIDY_TAG_CAPTION ) {
                        if( $this->text[ strlen( $this->text ) -1 ] != "\n") {
                            $this->text .= "\n";
                        }
                }
                //Extra Hard \n
                if($node->id == TIDY_TAG_H1 || $node->id == TIDY_TAG_H2 || $node->id == TIDY_TAG_H3 || $node->id == TIDY_TAG_H4 || $node->id == TIDY_TAG_H5 || $node->id == TIDY_TAG_H6 || $node->id == TIDY_TAG_P) $this->text .= "\n";
                //Li
                if($node->id == TIDY_TAG_LI) $this->text .= " * ";
                //DD
                if($node->id == TIDY_TAG_DD) $this->text .= "  ";
            }
            //Opening <q>
            if($node->id == TIDY_TAG_Q) $this->text .= '"';
            //Opening Sub
            if($node->id == TIDY_TAG_SUB) $this->text .= '(';
            //Opening Sup
            if($node->id == TIDY_TAG_SUP) $this->text .= '[';
            //Value substracting. (There might be more than one and they have to be concatenated)
    		if( !$this->nested_class_value  && isset( $node->attribute['class']) &&  $node->attribute['class']  == "value") {
                $child_value = new Node_Value();
				//To prevent nesting of class-value pattern
                $child_value->set_nested_class_value(TRUE);
                //This needs to be done to prevent loop.. if not the function will keep calling iteself.
                $node->attribute['class'] = '';
                if($this->in_class_value) {
                	$this->value .= " ";
                } else {
                	$this->in_class_value = TRUE;
                }
                $this->value .= $child_value->get_value($node);
                unset($child_value);
                return;
    		}
    		//Value title
    		if( isset( $node->attribute['class']) &&  $node->attribute['class']  == "value-title" && isset( $node->attribute['title'])) {
    			$this->value = $node->attribute['title'];
    			return;
    		}
            //Children nodes
            if($node->hasChildren()) {
            	foreach($node->child as $child) {
            		$this->get_node_value($child, $depth+1);
            	}
            }
            //Closing <q>
            if($node->id == TIDY_TAG_Q) $this->text .= '"';
            //Closing <sub>
            if($node->id == TIDY_TAG_SUB) $this->text .= ')';
            //Closing <sup>
            if($node->id == TIDY_TAG_SUP) $this->text .= ']';

            //Line breaks
            if( $depth>0 ) {
            	//Line breaks <br/>
            	if( $node->id == TIDY_TAG_BR) {
            		$this->text .= '
';
                	return;
            	}
            	
            	//Soft \n
                if($node->id == TIDY_TAG_DIV || $node->id == TIDY_TAG_DL || $node->id == TIDY_TAG_LI || $node->id == TIDY_TAG_DD || $node->id == TIDY_TAG_P || $node->id == TIDY_TAG_H1 || $node->id == TIDY_TAG_H2 || $node->id == TIDY_TAG_H3 || $node->id == TIDY_TAG_H4 || $node->id == TIDY_TAG_H5 || $node->id == TIDY_TAG_H6 || $node->id == TIDY_TAG_TABLE || $node->id == TIDY_TAG_TBODY || $node->id == TIDY_TAG_THEAD || $node->id == TIDY_TAG_TFOOT || $node->id == TIDY_TAG_TR || $node->id == TIDY_TAG_CAPTION) {
                    if( $this->text[ strlen( $this->text ) -1 ] != "\n") {
                        $this->text .= "\n";
                    }
                }
                //Hard \n for P (another one) and for DT (only one)
                if($node->id == TIDY_TAG_P || $node->id == TIDY_TAG_DT || $node->id == TIDY_TAG_H1 || $node->id == TIDY_TAG_H2 || $node->id == TIDY_TAG_H3 || $node->id == TIDY_TAG_H4 || $node->id == TIDY_TAG_H5 || $node->id == TIDY_TAG_H6) $this->text .= "\n";
                //Table tabulation
                if($node->id == TIDY_TAG_TD || $node->id == TIDY_TAG_TH) {
                	$colspan = 1;
                    if($node->attribute['colspan']) $colspan = $node->attribute['colspan'];
                    for($x=0; $x<$colspan; $x++) {
                    	$this->text .= " \t";
                    }
                }
            }
    	}
    }
	/**
	 * Public function that is called to get the value
	 *
	 * @param a-tidy-node $node
	 * @return string 
	 */
    public function get_value($node) {
    	$this->get_node_value($node, 0);
//        if($this->value) return html_entity_decode( htmlspecialchars_decode($this->value) );
//        return html_entity_decode( htmlspecialchars_decode($this->text) );
   	
    	if($this->value) return $this->value;
    	return $this->text;
    }

    /**
     * nested_class_value setter
     * @param Boolean $nested_class_value
     */
    public function set_nested_class_value($nested_class_value) {
		$this->nested_class_value = $nested_class_value;    	
    }
}

/**
 * A Helper function used for debugging to dump all the contents 
 * of a Tidy Node. This function was for my own personal help when developing.
 */
function dump_nodes($node, $indent) {

   if($node->hasChildren()) {
       foreach($node->child as $child) {
            if($child->attribute['class'] == 'test') {
               $node_value = new Node_Value();
               echo ("!!!!-" . $node_value->get_value($child) . "-!!!<br/>");
               unset($node_value);
            }
            dump_nodes($child, $indent+1);
       }
   }
}
?>
