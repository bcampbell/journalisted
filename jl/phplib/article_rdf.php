<?php
/* support for RDF output of article data */

require_once '../conf/general';
require_once 'misc.php';
require_once 'arc2/ARC2.php';

$JL = 'http://'. OPTION_WEB_DOMAIN . '/';

$ns = array(
    'rdf'=>"http://www.w3.org/1999/02/22-rdf-syntax-ns#",
    'rdfs'=>"http://www.w3.org/2000/01/rdf-schema#",
    'foaf'=>"http://xmlns.com/foaf/0.1/",
    'doac'=>"http://ramonantonio.net/content/xml/doac01",
    'dc' => 'http://purl.org/dc/elements/1.1/',
    );
$_conf = array('ns' => $ns);




function article_asARC2Index( &$art ) {
    global $JL;
    extract( $art, EXTR_PREFIX_ALL, 'a' );

    $art_uri = "{$JL}id/article/{$a_id36}";

    $a = array();
    //$a['rdf:type'] = "foaf:Document";
    $a['dc:title'] = array( x($a_title) );
    $a['dc:date'] = array( x($a_iso_pubdate) );
    $a['dc:publisher'] = array( x($a_srcorgname) );
    $a['dc:description'] = array( x($a_description) );
    $a['foaf:made_by'] = array();
    foreach( $a_journos as $j ) {
        $journo_uri = "$JL/id/journo/{$j['ref']}";
        $a['foaf:maker'][] = $journo_uri;
    }
    return array( $art_uri => $a );
}

// TODO:
// permalink
//
// statement of principles
// blog links
// comment links
// similar_articles




function article_emitRDFXML( &$a ) {
    global $_conf;
    $ser = ARC2::getRDFXMLSerializer($_conf);
    $idx = article_asARC2Index($a);
    $doc = $ser->getSerializedIndex($idx);
    print $doc;
}



