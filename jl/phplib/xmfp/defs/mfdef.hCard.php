<?php

/**
 * Definition of the hCard properties, and it's post-processing.
 * 
 * For definitions of hCard Properties and Ocurrences see: http://microformats.org/wiki/hcard-cheatsheet as of the version of 11:17, 5 Mar 2008<br>
 * For definitions of hCard Semantic Optimizations see: http://microformats.org/wiki/hcard-parsing As of the version 0f 18:10, 7 Feb 2008 <br>
 * 
 *  @author Emiliano Martínez Luque
 *  @package xmfp
 *  @subpackage mf_definitions
 */



//fn
$xmfp_hcard["fn"] = array( 'ocurrences' => "1");
//n
$xmfp_hcard["n"] = array( 'ocurrences' => "?", "childs" => array(
        //family-name 
        'family-name' => array('ocurrences' => "*"),
        //given-name
        'given-name' => array('ocurrences' => "*"),
        //additional-name
        'additional-name' => array('ocurrences' => "*"),
        //honorifix-prefix
        'honorific-prefix' => array('ocurrences' => '*'),
        //honorifix-sufix
        'honorific-suffix' => array('ocurrences' => '*') ) );
//nickname
$xmfp_hcard['nickname'] = array('ocurrences' => '*');
//url
$xmfp_hcard['url'] = array( 'ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') );
//email
$xmfp_hcard['email'] = array( 'ocurrences' => '*', 'childs' => array(
        'email' => array( 'ocurrences' => '?', 'semopt' => array(
            array( 'tag' => 'a', 'att' => 'href' ),
            array( 'tag' => 'area', 'att' => 'href'),
            array( 'tag' => 'img', 'att' => 'src')), 'postprocessing' => array('clean_email', 'email') 
            //It's ocurrences within this node
        ),
        'value' => array('ocurrences' => '?', 'postprocessing' => array('clean_email', 'email')
        ),
        'type' => array('ocurrences' => '*')) );
//tel
$xmfp_hcard['tel'] = array( 'ocurrences' => '*', 'childs' => array(
        'tel' => array('ocurrences' => '?' ),
            //It's ocurrences within this node
        'value' => array('ocurrences' => '?' ),
        'type' => array('ocurrences' => '*')), 'postprocessing' => array('tel_type_value') );
//adr
$xmfp_hcard['adr'] = array( 'ocurrences' => '*', 'childs' => &$xmfp_adr, 'skip'=> true );

//geo
$xmfp_hcard['geo'] = array( 'ocurrences' => '*', 'childs' => &$xmfp_geo, 'skip' => true );
//tz
$xmfp_hcard['tz'] = array('ocurrences' => '?');
//photo
$xmfp_hcard['photo'] = array('ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') );
//logo
$xmfp_hcard['logo'] = array( 'ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url') );
//sound
$xmfp_hcard['sound'] = array('ocurrences' => '*', 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href') ), 'postprocessing' => array('base', 'url') );
//bday
$xmfp_hcard['bday'] = array( 'ocurrences' => '?', 'postprocessing' => array('date'));
//title
$xmfp_hcard['title'] = array('ocurrences' => '*');
//role
$xmfp_hcard['role'] = array('ocurrences' => '*');
//org
$xmfp_hcard['org'] = array('ocurrences' => '?', 'childs' => array(
        'org' => array('ocurrences' => '?', 'letpass' => true),
        'organization-name' => array('ocurrences' => '?'),
        'organization-unit' => array('ocurrences' => '?') ), 'postprocessing' => array('org_name') );
//category
$xmfp_hcard['category'] = array('ocurrences' => '*');
//note
$xmfp_hcard['note'] = array('ocurrences' => '*');
//class
$xmfp_hcard['class'] = array('ocurrences' => '?');
//note
$xmfp_hcard['key'] = array('ocurrences' => '*');
//mailer
$xmfp_hcard['mailer'] = array('ocurrences' => '*');
//uid
$xmfp_hcard['uid'] = array( 'semopt' => array(
        array( 'tag' => 'a', 'att' => 'href' ),
        array( 'tag' => 'area', 'att' => 'href'),
        array( 'tag' => 'img', 'att' => 'src') ), 'postprocessing' => array('base', 'url'), 'ocurrences' => '?' );
//rev
$xmfp_hcard['rev'] = array('ocurrences' => '*', 'postprocessing' => array('date'));
//sort-string
$xmfp_hcard['sort-string'] = array('ocurrences' => '?');


?>
