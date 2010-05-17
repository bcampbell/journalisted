<?php
define('XMFP_INCLUDE_PATH', OPTION_JL_FSROOT . '/phplib/xmfp/');
require_once(XMFP_INCLUDE_PATH . 'class.Xmf_Parser.php');


// only need the year
function hresume_year( $dt ) {#
    $m = array();
    if( preg_match( '/\d\d\d\d/', $dt, $m ) ) {
        return (int)$m[0];
    } else {
        return null;
    }
}


// pull in hResume from some url, returning the data munged into
// the JL data model
// NOTE: encoding of returned text is probably dependent on source data...
// But it's likely to be ascii/utf-8 compatable enough that we won't
// stress about it right now.
function hresume_import( $url )
{
    global $mF_roots;
    $out = null;

    $xmfp = Xmf_Parser::create_by_URI( $mF_roots, $url );
    $mf = $xmfp->get_parsed_mfs();
    // hmm... what to do with errors?
    //print_r( $xmfp->get_errors() );
    if( array_key_exists( 'hresume', $mf ) ) {
        // only use first one...
        $out = hresume_slurp(  $mf['hresume'][0] );
    }

    return $out;
}


function hresume_slurp( $hr ) {

    $out = array(
        'experience'=>array(),
        'education'=>array(),
    );

    if( array_key_exists( 'experience', $hr ) ) {
        foreach( $hr['experience'] as $exp ) {
            $e = hresume_slurpexperience( $exp );
            if( $e )
                $out['experience'][] = $e;
        }
    }

    if( array_key_exists( 'education', $hr ) ) {
        foreach( $hr['education'] as $edu ) {
            $e = hresume_slurpeducation( $edu );
            if( $e )
                $out['education'][] = $e;
        }
    }

    return $out;
}


function hresume_slurpexperience( $exp ) {
    $out = array(
        'year_from'=>null,
        'year_to'=>null,
        'current'=>FALSE,
        'employer'=>'',
        'job_title'=>'' );

    if( !array_key_exists( 'vevent', $exp ) )
        return null;

    $vevent = $exp['vevent'];
    if( array_key_exists( 'dtstart', $vevent ) ) {
        $out['dtstart'] = $vevent['dtstart'];
        $out['year_from'] = hresume_year($vevent['dtstart']);
    }
    if( array_key_exists( 'dtend', $vevent ) ) {
        $out['year_to'] = hresume_year($vevent['dtend']);
    }
    // consider it active if start date but no end date
    if( !is_null($out['year_from']) && is_null($out['year_to']) ) {
        $out['current'] = TRUE;
    }


    $vcard = $exp['vcard'];
    if( $vcard ) {
        $out['job_title'] = $vcard['title'][0]; // could be multiple titles?
        $out['employer'] = $vcard['org']['organization-name'];
    } else {
        $out['job_title'] = '';
        $out['employer'] = $vevent['summary'];
    }

    $out['job_title'] = html_entity_decode( $out['job_title'] );
    $out['employer'] = html_entity_decode( $out['employer'] );

    return $out;
}


function hresume_slurpeducation( $edu ) {
    $out = array(
        'year_from'=>null,
        'year_to'=>null,
        'current'=>FALSE,
        'school'=>'',
        'field'=>'',
        'qualification'=>'' );

    if( !array_key_exists( 'vevent', $edu ) )
        return null;
    $vevent = $edu['vevent'];
    if( array_key_exists( 'dtstart', $vevent ) ) {
        $out['year_from'] = hresume_year( $vevent['dtstart'] );
    }
    if( array_key_exists( 'dtend', $vevent ) ) {
        $out['year_to'] = hresume_year($vevent['dtend']);
    }
    // consider it active if start date but no end date
    if( !is_null($out['year_from']) && is_null($out['year_to']) ) {
        $out['current'] = TRUE;
    }

    $vcard = $edu['vcard'];
    if( $vcard ) {
        $out['school'] = $vcard['org']['organization-name'];
    } else {
        $out['school'] = $vevent['summary'];
    }

    $out['school'] = html_entity_decode( $out['school'] );
    $out['field'] = '';
    $out['qualification'] = '';
    $out['kind'] = 'u';
    return $out;
}

