<?php


function api_findJournos_front() {
?>
<p><big>Find/list journalists.</big></p>

<h4>Arguments</h4>
<dl>
<dt><code>name</code></dt><dd>Only return names containing this string (eg "bert" would match "robert smith")</dd>
<dt><code>offset</code></dt><dd>the start offset of result set returned (default 0)</dd>
<dt><code>limit</code></dt><dd>maximum number of results to return (default 100)</dd>
</dl>

<p>If no arguments are given, all journos will be listed.</p>

<h4>Returned values</h4>

<p>The returned list is ordered by surname.</p>

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

function api_findJournos_invoke($params) {
    $name = $params['name'];
    $offset = (int)$params['offset'];
    $limit = (int)$params['limit'];
    if( !$limit )
        $limit = 100;

    $order = 'lastname';
	$orderfields = ($order=='firstname') ?
         'firstname,lastname' : 'lastname,firstname';

    $vals = array();
	$sql = "SELECT id,ref,prettyname,firstname,lastname,oneliner FROM journo WHERE status='a'";
    if( $name )
    {
        $sql .= " AND prettyname ILIKE( ? )";
        $vals[] = "%{$name}%";
    }
    $sql .= " ORDER BY {$orderfields}";
    $sql .= " LIMIT ?";
    $vals[] = $limit;
    $sql .= " OFFSET ?";
    $vals[] = $offset;

	$output = array( 'results' => db_getAll( $sql, $vals ) );
    api_output( $output);
}

?>
