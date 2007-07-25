<?php
/* vim:set expandtab tabstop=4 shiftwidth=4 autoindent smartindent: */

require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/misc.php';




class ADMIN_PAGE_JL_JOURNO {
    function ADMIN_PAGE_JL_JOURNO() {
        $this->id = 'journos';
        $this->navname = 'Journos';
    }

    function display() {

        $journo_id = get_http_var( 'journo_id' );
        if( $journo_id )
        {
            $this->do_journo( $journo_id );
        }
        else
        {
            $this->do_browse_journos();
        }

    }

    function do_journo( $journo_id ) {
        $j = db_getRow( "SELECT * FROM journo WHERE id=?", $journo_id );

        $form = new HTML_QuickForm('journo_main','get','','',null, TRUE );

        $form->setDefaults( array(
            'ref' => $j['ref'],
            'prettyname' => $j['prettyname'],
            'lastname' => $j['lastname'],
            'firstname' => $j['firstname'],
            'created' => $j['created'] ) );

        // kludge to keep on this admin page
        $form->addElement( 'hidden', 'page', get_http_var( 'page' ) );

        $form->addElement( 'hidden', 'journo_id', $journo_id, null );
        $form->addElement( 'text', 'ref', 'ref', null );
        $form->addElement( 'text', 'prettyname', 'prettyname', null );
        $form->addElement( 'text', 'lastname', 'lastname', null );
        $form->addElement( 'text', 'firstname', 'firstname', null );

        $form->addElement( 'submit', 'update', 'Update' );

        $form->display();


        $aliasrows = db_getAll( "SELECT * FROM journo_alias WHERE journo_id=?", $journo_id );

        foreach( $aliasrows as $a )
        {
            print "<code>" . $a['alias'] . "</code><br>\n";
        }
        $form = new HTML_QuickForm('journo_newalias','get','','',null, TRUE );
        $form->addElement( 'hidden', 'page', get_http_var( 'page' ) );
        $form->addElement( 'hidden', 'journo_id', $journo_id, null );
        $form->addElement( 'text', 'new_alias', 'New Alias', null );
        $form->addElement( 'submit', 'add', 'Add' );
        $form->display();
    }

    function do_browse_journos()
    {
    }
}








?>
