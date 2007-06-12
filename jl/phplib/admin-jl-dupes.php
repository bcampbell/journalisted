<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';



class ADMIN_PAGE_JL_DUPES {
    function ADMIN_PAGE_JL_DUPES() {
        $this->id = 'dupes';
        $this->navname = 'Dupes';
    }



    function display() {
        $orgs = get_org_names();


        $this->show_dupes();

    }

    function show_dupes() {

        print "<h2Dupes</h2>\n";

        $masters = array();
        $q = db_query( 'SELECT a.id,a.title,a.byline,a.description FROM article a INNER JOIN article_dupe d ON (a.id=d.dupeof_id)' );
        while( $row = db_fetch_array($q) ) {
            $id = $row['id'];
            $masters[ $id ] = $row;
        }

        $q = db_query( 'SELECT a.id,a.title,a.byline,a.description,d.dupeof_id FROM article a INNER JOIN article_dupe d ON (a.id=d.article_id)' );
        while( $row=db_fetch_array($q) ) {
            $masterid = $row['dupeof_id'];
            $masters[$masterid]['DUPES'][] = $row;
        }

        print "<table border='1'>\n";
        foreach( $masters as $art ) {
            print "<tr><td>\n";
            printf( "[%d] %s (%s)<br>\n", intval( $art['id']), $art['title'], $art['byline'] );
            foreach( $art['DUPES'] as $dupe ) {
                printf( "&nbsp;&nbsp;<small>[%d] %s (%s)</small><br>\n", intval( $dupe['id']), $dupe['title'], $dupe['byline'] );
            }
            print "</td></tr>\n";
        }
        print "</table>\n";
    }

}

