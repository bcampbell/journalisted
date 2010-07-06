<?php
/**
 * Definition of the root elements of all microformats to be picked, and of the post-processing needed for them.
 *
 * <p>
 * So far:
 * <ul>
 * <li><b>rel-*</b> xfn</li>
 * <li><b>rel-license</b> license</li>
 * <li><b>rel-tag</b> tag</li>
 * <li><b>geo</b> geo</li>
 * <li><b>adr</b> adr</li>
 * <li><b>vcard</b> hCard</li>
 * <li><b>vcalendar</b> hCalendar</li>
 * <li><b>vevent</b> hClendar</li>
 * <li><b>hreview</b> hReview</li>
 * <li><b>hresume</b> hResume</li>
 * </ul>
 *  </p>
 * 
 *  @author Emiliano Martínez Luque
 *  @package xmfp
 */

//All the Microformats definitions
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.xfn.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.rel-license.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.rel-tag.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.geo.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.adr.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.hCard.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.hCalendar.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.hReview.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfdef.hResume.php");
//All the Microformats Post Processing Functions
require_once(XMFP_INCLUDE_PATH . "/defs/mfpost.general.php");
require_once(XMFP_INCLUDE_PATH . "/defs/mfpost.hCard.php");

//microformats definitions

//XFN
$mF_roots['rel-friend'] = array('childs' => &$xmfp_rel_friend);
$mF_roots['rel-acquaintance'] = array('childs' => &$xmfp_rel_acquaintance);
$mF_roots['rel-contact'] = array('childs' => &$xmfp_rel_contact);
$mF_roots['rel-met'] = array('childs' => &$xmfp_rel_met);
$mF_roots['rel-co-worker'] = array('childs' => &$xmfp_rel_coworker);
$mF_roots['rel-colleage'] = array('childs' => &$xmfp_rel_colleage);
$mF_roots['rel-co-resident'] = array('childs' => &$xmfp_rel_coresident);
$mF_roots['rel-neighbour'] = array('childs' => &$xmfp_rel_neighbour);
$mF_roots['rel-child'] = array('childs' => &$xmfp_rel_child);
$mF_roots['rel-parent'] = array('childs' => &$xmfp_rel_parent);
$mF_roots['rel-sibling'] = array('childs' => &$xmfp_rel_sibling);
$mF_roots['rel-spouse'] = array('childs' => &$xmfp_rel_spouse);
$mF_roots['rel-kin'] = array('childs' => &$xmfp_rel_kin);
$mF_roots['rel-muse'] = array('childs' => &$xmfp_rel_muse);
$mF_roots['rel-crush'] = array('childs' => &$xmfp_rel_crush);
$mF_roots['rel-date'] = array('childs' => &$xmfp_rel_date);
$mF_roots['rel-sweetheart'] = array('childs' => &$xmfp_rel_sweetheart);
$mF_roots['rel-me'] = array('childs' => &$xmfp_rel_me);
//Other Rel Based MFs
$mF_roots['rel-license'] = array('childs' => &$xmfp_rel_license);
$mF_roots['rel-tag'] = array('childs' => &$xmfp_rel_tag);
//Simple Compound MFs
$mF_roots['geo'] = array('childs' => &$xmfp_geo);
$mF_roots['adr'] = array('childs' => &$xmfp_adr);
//Complex Compound MFs
$mF_roots['vcard'] = array('childs' => &$xmfp_hcard, 'postprocessing' => array('fn_optimizations'));
$mF_roots['vevent'] = array('childs' => &$xmfp_hcalendar);
//$mF_roots['vcalendar'] = array('childs' => array('vevent' => array('ocurrences' => '*', 'childs' => &$xmfp_hcalendar) ) );
$mF_roots['hreview'] = array('childs' => &$xmfp_hreview);
$mF_roots['hresume'] = array('childs' => &$xmfp_hresume);

?>
