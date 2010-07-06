<?php
/**
 * Definition of the hResume properties, and it's post-processing.
 * 
 * For definitions of hReview Properties and Ocurrences see: http://microformats.org/wiki/hresume#Schema
 * 
 *  @author Emiliano Martínez Luque
 *  @package xmfp
 *  @subpackage mf_definitions
 */
$xmfp_hresume['summary'] = array('ocurrences' => '?');
$xmfp_hresume['contact'] = array('ocurrences' => '1', 'childs' => array('vcard' => array('childs' => &$xmfp_hcard, 'skip' => true, 'ocurrences' => '?')) );
$xmfp_hresume['education'] = array('ocurrences' => '*', 'childs' => array('vevent' => array('childs' => &$xmfp_hcalendar, 'skip' => true, 'ocurrences' => '?'), 'vcalendar' => array('childs' => &$xmfp_hcalendar, 'skip' => true, 'ocurrences' => '?'), 'vcard' => array('childs' => &$xmfp_hcard, 'skip' => true, 'ocurrences' => '?')));
$xmfp_hresume['experience'] = array('ocurrences' => '*', 'childs' => array('vevent' => array('childs' => &$xmfp_hcalendar, 'skip' => true, 'ocurrences' => '?'), 'vcard' => array('childs' => &$xmfp_hcard, 'skip' => true, 'ocurrences' => '?')));
$xmfp_hresume['skill'] = array('childs' => array('tag' => array('childs' => &$xmfp_rel_tag, 'ocurrences' => '*', 'skip' => true)), 'ocurrences' => '?');
$xmfp_hresume['affiliation'] = array('ocurrences' => '*', 'childs' => array('vcard' => array('childs' => &$xmfp_hcard, 'skip' => true, 'ocurrences' => '?')));
$xmfp_hresume['publications'] = array('ocurrences' => '*');

?>
