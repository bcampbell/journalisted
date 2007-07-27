<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';




class ADMIN_PAGE_JL_TAGS {
    function ADMIN_PAGE_JL_TAGS() {
        $this->id = 'tags';
        $this->navname = 'Tags';
    }

    function display() {
        $this->display_blacklist();
    }


    function display_blacklist() {
        print( "<h2>Tag blacklist</h2>\n" );

        $err = null;
        if( get_http_var('addtag') == 'Add' ) {
            /* submitted - validate input */
            $newtag = trim( get_http_var( 'newtag' ) );

            if( $newtag == '' ) {
                $err = "Tag is blank!";
            } elseif( db_GetOne( "SELECT bannedtag FROM tag_blacklist " .
                "WHERE bannedtag=?",
                $newtag ) != null ) {
                $err= sprintf( "'%s' is already in the blacklist!", $newtag );
            }

            if( !$err ) {
                db_query( "INSERT INTO tag_blacklist (bannedtag) VALUES (?)", $newtag );
                // TODO: remove uses of the tag!


                db_commit();

                print"<p>Added Tag</p>\n";
            }
            


        }

        if( $err ) {
            printf( "<p>ERROR: %s</p>\n", $err );
        }


        /* show existing tag blacklist */
        $blacklist = tag_get_blacklist();

        print( "<table>\n" );
        foreach( $blacklist as $t ) {
            printf( "<tr><td>%s</td></tr>\n", $t );
        }

?>
<tr><td>
<form action="" method="get">
<input type="hidden" name="page" value="<?=get_http_var('page');?>" />
<input type="text" name="newtag" />
<input type="submit" name="addtag" value="Add" />
</form>
</td></tr>
<?php
        print( "</table>\n" );


/*
        print "<pre>\n";
        print_r( $_GET );
        print "</pre>\n";
*/
    }
}


function tag_get_blacklist() {
    static $blacklist = null;
    if( $blacklist === null ) {
        $q = db_query( "SELECT bannedtag FROM tag_blacklist" );
        $blacklist = array();
        while( $r=db_fetch_row($q) )
            $blacklist[] = $r[0];
    }
    return $blacklist;
}


function update_blacklist( $values ) {
    $blacklist = tag_get_blacklist();

    $newlist = explode( "\n", $values['blacklist'] );
    print "<pre>\n";
    print_r( $newlist );
    print "</pre>\n";

    foreach( $newlist as $t ) {
        $t=trim($t);
        if(!$t)
            continue;
        if( in_array( $t, $blacklist ) ) {
        }
    }
}



?>
