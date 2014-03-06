<?php

/* just redirect user to their profile page (if they're a journo) */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../phplib/passwordbox.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



function view()
{
    $P = person_if_signed_on();
    if(is_null($P)) {
        // only for logged-in users
        header( "Location: /" );
        return;
    }

    /* they might have multiple profiles, thus option to specify one here */
    $ref = strtolower( get_http_var( 'ref' ) );
    $journo = NULL;

    if( $ref ) {
        $journo = db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );
        if( !$journo ) {
            header("HTTP/1.0 404 Not Found");
            return;
        }
    }

    if( is_null($journo) ) {
        // no journo given - if person is logged on, see if they are associated with a journo (or journos)
        $editables = db_getAll( "SELECT j.* FROM ( journo j INNER JOIN person_permission p ON p.journo_id=j.id) WHERE p.person_id=? AND p.permission='edit'", $P->id() );

        if( sizeof($editables)==0 ) {
            header( "Location: /" );
            return;
        } elseif( sizeof($editables)>1 ) {
            /* let user pick which one... */
            tmpl_pickjourno($editables);
            return;
        } else {// sizeof($editables) == 1
            $journo = $editables[0];        // just one journo.
        }
    }


    // is this person allowed to edit this journo?
    if( !db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo['id'] ) ) {
            // nope
            $journo = null;
    }

    if(!is_null($journo)){
        header( "Location: /{$journo['ref']}" );
    } else {
        header( "Location: /fuck" );
    }
}


function tmpl_pickjourno($candidates) {
    page_header("");

?>
<p>Which one do you want to edit?</p>
<ul>
    <?php foreach( $candidates as $j ) { ?>
    <li><a href="/profile?ref=<?= $j['ref'] ?>"><?= $j['prettyname'] ?></a></li>
    <?php } ?>
</ul>
<?php

    page_footer();
}


view();

?>
