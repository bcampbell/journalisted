<?php
// ISSUES:
// - no indication _why_ a journo returns as null.
//   maybe should bail out if _any_ journos are bad?
//

function api_getJourno_front() {
?>
<p><big>Retrive info on a single journalist.</big></p>

<h4>Arguments</h4>
<dl>
<dt><code>journo</code></dt><dd>The <code>id</code> or <code>ref</code> of the journalist you want</dd>
</dl>

<h4>Returned values</h4>

<dl>
<dt><code>id</code></dt><dd>unique, numeric id of journo</dd>
<dt><code>ref</code></dt><dd>unique, human-readable id used as a url (ie slug) for the journalists page. Might change, but generally pretty static.</dd>
<dt><code>prettyname</code></dt><dd>Full name, including prefixes and suffixes</dd>
<dt><code>firstname</code></dt><dd>First name (lowercase)</dd>
<dt><code>lastname</code></dt><dd>Surname name (lowercase)</dd>
<dt><code>oneliner</code></dt><dd>Short description of organisations the person is known to have written for (eg "The Times, BBC News").</dd>
</dl>

<?php
}

function OLD_api_getJourno_invoke($params) {
    $j = $params['journo'];
    if( is_null($j) ) {
        api_error( "missing required parameter: 'journo'" );
        return;
    }

    $jfield = is_numeric( $j ) ? 'id' : 'ref';
	$sql = "SELECT id,ref,prettyname,firstname,lastname,oneliner FROM journo WHERE status='a' AND {$jfield}=?";
    $r = db_getRow( $sql, $j );
    if( is_null($r) )
    {
        api_error( "No matching journalist found" );
        return;
    }

    $journo = array();
    foreach( array('id','ref','prettyname','firstname','lastname','oneliner') as $field )
        $journo[$field] = $r[$field];

	$output = array( 'results' => $journo );
    api_output( $output);
}




// take a mixed list of journo ids/refs and
// return an array of ids, in the same order (with null for any
// refs that weren't found in the db).
function resolve_ids( $idents ) {

    /* build up map for all refs to translate them to id */
    $refs = array();
    $refmap = array();
    $qs = array();
    foreach( $idents as $ident ) {
        if( !is_numeric( $ident ) ) {
            $refs[] = $ident;
            $refmap[$ident] = null;
            $qs[]='?';
        }
    }
    if( $refmap ) {
        // to db lookup to fetch IDs for the refs (any spurious refs will be left with a null id)
        $rows = db_getAll( "SELECT ref,id FROM journo WHERE ref in (" . implode(',',$qs) .")", $refs );
        foreach( $rows as $r ) {
            $refmap[ $r['ref'] ] = intval($r['id']);
        }
    }

    // now we can build up the finished list:
    $journo_ids = array();
    foreach( $idents as $ident ) {
        if( is_numeric( $ident ) ) {
            $journo_ids[] = intval( $ident );
        } else{
            $journo_ids[] = $refmap[$ident];
        }
    }

    return $journo_ids;
}



function api_getJourno_invoke($params) {

    /* input can be a journo or list of journos */
    $j = $params['journo'];
    if( is_null($j) ) {
        api_error( "missing required parameter: 'journo'" );
        return;
    }

    $idents = preg_split('/\s*,\s*/', $j );
    $journo_ids = resolve_ids( $idents );

    // TODO: impose a max number of journos here...

    // fetch cached versions
    $cache_ids = array();
    $qs = array();
    foreach( $journo_ids as $id ) {
        $cache_ids[] = "json_$id";
        $qs[] = '?';
    }

    $q = db_query( "SELECT content FROM htmlcache WHERE name in (".implode(',',$qs).")", $cache_ids );

    // cook up the results!
    $temp_results = array();
    foreach( $journo_ids as $id ) {
        $temp_results[ $id ] = null;
    }

    $basic_fields = array( 'id','ref','prettyname', 'firstname', 'lastname', 'oneliner' );
    while( $row=db_fetch_array($q) ) {
        $cached_json = $row['content'];
        $raw = json_decode( $cached_json,true );
        if( $raw['status'] != 'a' ) {
            //continue;
        }

        // the basics:
        $journo = array_cherrypick( $raw, $basic_fields );

        // pick out contact details
        $known_emails = array();
        if( $raw['known_email'] ) {
            $known_emails[] = $raw['known_email']['email'];
        }

        $guessed_emails = array();
        if( $raw['guessed'] ) {
            $guessed_emails = $raw['guessed']['emails'];
        }

        $contact = array('known_emails'=>$known_emails,
            'guessed_emails'=>$guessed_emails,
            'twitter_id'=>$raw['twitter_id'],
            'phone_number'=>$raw['phone_number'] );
        $journo['contact'] = $contact;

        // tags
        if( !$raw['quick_n_nasty'] ) {
            $journo['tags'] = $raw['tags'];
        } else {
            $journo['tags'] = array();
        }
        
        $id = intval( $journo['id'] );
        $temp_results[ $id ] = &$journo;
    }

    // reorder the results to match input params
    $results = array();
    foreach( $journo_ids as $id ) {
        $results[] = $temp_results[ $id ];
    }

	$output = array( 'results' => $results );
    api_output( $output);
}


?>
