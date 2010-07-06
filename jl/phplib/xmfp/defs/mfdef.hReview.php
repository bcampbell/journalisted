<?php
/**
 * Definition of the hReview properties, and it's post-processing.
 * 
 * For definitions of hReview Properties and Ocurrences see: http://microformats.org/wiki/hreview
 * 
 *  @author Emiliano Martínez Luque
 *  @package xmfp
 *  @subpackage mf_definitions
 */

$xmfp_hreview["item"] = array( 'ocurrences' => '1', 'childs' => array(
	'fn' => array('ocurrences' => '?'), 
	'url' =>  array( 'ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') ), 
    'photo' =>  array('ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') ),
     'vcard' => array('childs' => &$xmfp_hcard, 'ocurrences' => '?', 'skip' => true),
     'vevent' => array('childs' => &$xmfp_hcalendar, 'ocurrences' => '?', 'skip' => true)) )   ;
$xmfp_hreview["version"] = array( 'ocurrences' => '?');
$xmfp_hreview["summary"] = array( 'ocurrences' => '?');
/**
 * @todo type, rating have postprocessing
 * @todo tag, license, etc.. that have rel semopt.
 */
$xmfp_hreview["type"] = array( 'ocurrences' => '?');
$xmfp_hreview["dtreviewed"] = array( 'ocurrences' => '?', 'postprocessing' => array('date'));
$xmfp_hreview["rating"] = array( 'ocurrences' => '*', 'childs' => array(
'rating' => array('ocurrences' => '?'),
'best'=> array('ocurrences' => '?'),
'worst' => array('ocurrences' => '?'),
'tag' => array('childs' => &$xmfp_rel_tag, 'ocurrences' => '?', 'skip' => true)
));
$xmfp_hreview["description"] = array( 'ocurrences' => '?');
$xmfp_hreview["reviewer"] = array( 'ocurrences' => '?', 'childs' => ( array('vcard' => array('childs' => &$xmfp_hcard, 'ocurrences' => '1', 'skip' => true))));
$xmfp_hreview["tag"] = array( 'ocurrences' => '*', 'childs' => &$xmfp_rel_tag, 'skip' => true);


?>
