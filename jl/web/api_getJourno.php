<?php


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

function api_getJourno_invoke($params) {
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

?>
