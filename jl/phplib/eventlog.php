<?php




function eventlog_Add( $event_type, $journo_id, $extra=NULL )
{
    db_do( "INSERT INTO event_log ( event_type, event_time, journo_id,extra ) VALUES (?,NOW(),?,'')",
        $event_type, $journo_id );
    db_commit();
}



function eventlog_Describe( $ev )
{
    switch( $ev['event_type'] ) {
        case 'modify-admired': return 'updated admired journalists';
        case 'modify-education': return 'updated education information';
        case 'modify-employment': return 'updated employment information';
        case 'modify-books': return 'updated list of books';
        case 'modify-awards': return 'updated awards list';
        case 'modify-contact': return 'updated contact details';
        case 'modify-weblinks': return 'updated web links';
        case 'modify-photo': return 'updated photo';
    }

    return NULL;
}


?>
