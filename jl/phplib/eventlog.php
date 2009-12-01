<?php




function eventlog_Add( $event_type, $journo_id, $context=NULL )
{
    $context_json = json_encode( $context );

    db_do( "INSERT INTO event_log ( event_type, event_time, journo_id, context_json ) VALUES (?,NOW(),?,?)",
        $event_type, $journo_id, $context_json );
    db_commit();
}



function eventlog_Describe( &$ev )
{
    if( !array_key_exists('context', $ev ) )
        $ev['context'] = json_decode( $ev['context_json'], TRUE );

    $c = &$ev['context'];

    switch( $ev['event_type'] ) {
        case 'add-admired': return 'added an admired journalist';
        case 'modify-admired': return 'updated list admired journalists';
        case 'remove-admired': return 'updated list of admired journalists';

        case 'add-employment': return "added employment information for <em>{$c['employer']}</em>";
        case 'modify-employment': return 'updated employment information';
        case 'remove-employment': return 'updated employment information';

        case 'add-education': return "added <em>{$c['school']}</em> to education information";
        case 'modify-education': return 'updated education information';
        case 'remove-education': return 'updated education information';

        case 'add-books': return "added <em>{$c['title']}</em> to list of books";
        case 'modify-books': return 'updated list of books';
        case 'remove-books': return 'updated list of books';

        case 'add-awards': return "added <em>{$c['award']}</em> to awards information";
        case 'modify-awards': return 'updated awards information';
        case 'remove-awards': return 'updated awards information';

        case 'add-weblinks':
        case 'modify-weblinks':
        case 'remove-weblinks':
            return 'updated links on the web';

        case 'modify-contact': return 'updated contact details';
        case 'modify-photo': return 'updated photo';
    }

    return NULL;
}


?>
