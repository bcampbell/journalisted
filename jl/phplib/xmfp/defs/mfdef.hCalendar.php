<?php
/**
 * Definition of the hCalendar properties
 * 
 * For definitions of hCalendar Properties and Ocurrences see: http://microformats.org/wiki/hcalendar-cheatsheet as of the version of 2 apr 2008<br>
 *  @author Emiliano Martínez Luque
 *  @package xmfp
 *  @subpackage mf_definitions
 */

$xmfp_hcalendar["dtstart"] = array( 'ocurrences' => '1', 'postprocessing' => array('date'));
$xmfp_hcalendar["summary"] = array( 'ocurrences' => '1');
$xmfp_hcalendar["category"] = array( 'ocurrences' => '*');
$xmfp_hcalendar["class"] = array( 'ocurrences' => '?');
$xmfp_hcalendar["dtend"] = array( 'ocurrences' => '?', 'postprocessing' => array('date'));
$xmfp_hcalendar["duration"] = array( 'ocurrences' => '?');
$xmfp_hcalendar['geo'] = array( 'ocurrences' => '?', 'childs' => &$xmfp_geo, 'skip' => true );
$xmfp_hcalendar['location'] = array( 'ocurrences' => '?');
$xmfp_hcalendar['status'] = array( 'ocurrences' => '?');
$xmfp_hcalendar['uid'] = array( 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url'), 'ocurrences' => '?' );
//url
$xmfp_hcalendar['url'] = array( 'ocurrences' => '?', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') );
$xmfp_hcalendar['last-modified'] = array( 'ocurrences' => '?');
        
?>
