<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';

/*
                db_query( "UPDATE article_tag t1 ".
                    "SET freq=t1.freq+t2.freq ".
                    "FROM article_tag t2 ".
                    "WHERE t2.article_id=t1.article_id AND t2.tag=? and t1.tag=?");
*/

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
        $newtag = strtolower( trim( get_http_var( 'newtag' ) ) );
        $confirmed = get_http_var( 'confirmed' );

        $err = null;
        if( get_http_var('addtag') == 'Add Tag' ) {
            // form submitted, check tag and request confirmation
            $err = $this->check_newtag( $newtag );
            if( !$err ) {
                $cnt = db_getOne( "SELECT count(*) FROM article_tag WHERE tag=?", $newtag );
                $backlink = sprintf( "?page=%s", urlencode( get_http_var('page') ) );
?>
<p>Adding '<?=$newtag;?>' to the blacklist will affect <?=$cnt;?> articles.</p>
<form action="" method="get">
<p>Are you sure you want to add this tag to the blacklist?</p>
<input type="hidden" name="page" value="<?=get_http_var('page');?>" />
<input type="hidden" name="newtag" value="<?=$newtag;?>"/>
<input type="submit" name="addtag" value="Confirm" />
<br>
<br>
<a href="<?=$backlink;?>">No - I've changed my mind!</a>
</form>

<?php
                /* bail out early - don't need to display any more */
                return;
            }
        } elseif( get_http_var('addtag') == 'Confirm' ) {
            $err = $this->check_newtag( $newtag );
            if( !$err ) {
                db_query( "INSERT INTO tag_blacklist (bannedtag) VALUES (?)", $newtag );
                db_query( "DELETE FROM article_tag WHERE tag=?", $newtag );
                db_commit();
                printf( "<p>Added '%s' to blacklist.</p>\n", $newtag);
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
        print( "</table>\n" );

?>
<tr><td>
<form action="" method="get">
<input type="hidden" name="page" value="<?=get_http_var('page');?>" />
<input type="text" name="newtag" value="<?=$newtag;?>"/>
<input type="submit" name="addtag" value="Add Tag" />
</form>
</td></tr>
<?php

    }


    function check_newtag( $newtag ) {
        $err = null;
        if( $newtag == '' ) {
            $err = "Tag is blank!";
        } elseif( db_GetOne( "SELECT bannedtag FROM tag_blacklist " .
            "WHERE bannedtag=?",
            $newtag ) != null ) {
            $err= sprintf( "'%s' is already in the blacklist!", $newtag );
        }
        return $err;
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



?>
