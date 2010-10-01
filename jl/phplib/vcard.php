<?php 
/* helpers for vcard/hcard stuff

vcard spec: http://www.ietf.org/rfc/rfc2426.txt

   ADR
   The structured type value corresponds,
   in sequence, to the post office box; the extended address; the street
   address; the locality (e.g., city); the region (e.g., state or
   province); the postal code; the country name. When a component value
   is missing, the associated component separator MUST still be
   specified.
*/

$_vcard_adr_fields = array( "post-office-box", "extended-address", "street-address","locality","region","postal-code","country-name" );

// eg "ADR;TYPE=WORK:;;100 Waters Edge;Baytown;LA;30314;United States of America"

function vcard_parse_adr( $adr_string )
{
    global $_vcard_adr_fields;
    $m=null;
    if( preg_match('/([^;]*);([^;]*);([^;]*);([^;]*);([^;]*);([^;]*);([^;]*)/', $adr_string, $m ) != 1 ) {
        return NULL;
    }

    $out = array();
    $i = 1;
    foreach( $_vcard_adr_fields as $f ) {
        if( $m[$i] ) {
            $out[$f] = $m[$i];
        }
        ++$i;
    }
    return $out;
}


function vcard_build_adr( $adr_array )
{
    global $_vcard_adr_fields;

    $bits = array();
    foreach( $_vcard_adr_fields as $f ) {
        $bits[] = array_key_exists($f,$adr_array) ? $adr_array[$f] : '';
    }

    $adr = implode( ';', $bits );
    return $adr;
}

function vcard_adr_fields()
{
    global $_vcard_adr_fields;
    return $_vcard_adr_fields;
}

?>
