<?php

/* helper functions for journo profile data */


require_once '../conf/general';
//require_once 'misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../phplib/page.php';
require_once '../../phplib/utility.php';


// base class for profile-editing pages
class EditProfilePage
{

    public $pageName = '';
    public $pagePath = '';  // eg "/profile_hobbies"
    public $P = null;   // currently logged-on user
    public $journo = null;
    public $pageTitle = '';
    public $pageParams = array();

    public $error_messages = array();
    public $info_messages = array();

    function __construct()
    {
        $ref = get_http_var( 'ref' );
        $this->journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
        $r = array(
            'reason_web' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email_subject' => "Edit {$this->journo['prettyname']} on Journalisted"
        );

        if( get_http_var('ajax') ) {
            $this->P = person_if_signed_on();
        } else {
            // if not ajax, it's ok to redirect to login screen
            $this->P = person_signon($r);
        }
    }


    function run()
    {
        if( get_http_var('ajax') ) {
            $this->run_ajax();
        } else {
            $this->run_normal();
        }
    }


    function run_ajax() {
        header( "Cache-Control: no-cache" );
        header('Content-type: application/json');

        // check we're logged in
        if( is_null($this->P) ) {
            $result = array( 'success'=>FALSE,
                'errmsg'=>"Not logged in" );
            print json_encode( $result );
            return;
        }

        // make sure we've specified a journo
        if( is_null( $this->journo ) ) {
            $result = array( 'success'=>FALSE,
                'errmsg'=>"No journalist specified" );
            print json_encode( $result );
            return;
        }

        // is this person allowed to edit this journo?
        if( !db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
            $this->P->id(), $this->journo['id'] ) ) {
            $result = array( 'success'=>FALSE,
                'errmsg'=>"Not allowed" );
            print json_encode( $result );
            return;
        }

        // got this far, so let derived class do it's thing.
        $result = $this->ajax();

        // assume success if unspecified
        if( is_array($result) && !array_key_exists('success',$result) ) {
            $result['success']=TRUE;
        }

        if( is_null( $result ) ) {
            $result = array( 'success'=>FALSE, 'errmsg' => 'Failed.' );
        }

        print json_encode( $result );
    }


    /* to be overriden */
    /* called before display(), so can redirect, show error page,
       whatever. return FALSE to suppress normal page */
    function handleActions() {
        return TRUE;    /* show page please! */
    }


    function addError( $msg ) { $this->error_messages[] = $msg; }
    function addInfo( $msg ) { $this->info_messages[] = $msg; }

    /* normal, non-ajax display */
    function run_normal()
    {


        // check we're logged in
        if( is_null($this->P) ) {
            page_header( $this->pageTitle, $this->pageParams );
            print( "<p>Sorry, you need to be logged in to edit your profile.</p>\n" );
            page_footer();
            return;
        }

        // make sure we've specified a journo
        if( is_null( $this->journo ) ) {
            page_header( $this->pageTitle, $this->pageParams );
            print( "<p>No journalist specified.</p>\n" );
            page_footer();
            return;
        }

        // is this person allowed to edit this journo?
        if( !db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
            $this->P->id(), $this->journo['id'] ) ) {
            page_header( $this->pageTitle, $this->pageParams );
            print( "<p>Not allowed.</p>\n" );
            page_footer();
            return;
        }

        if( $this->handleActions() == FALSE )
            return;

        page_header( $this->pageTitle, $this->pageParams );

?>

<div class="main">
<?php
        if( $this->error_messages ) {
?>
<ul class="errors">
<?php foreach( $this->error_messages as $msg ) { ?> <li><?= $msg ?></li><?php } ?>
</ul>
<?php
        }
        if( $this->info_messages ) {
?>
<ul class="infomessage">
<?php foreach( $this->info_messages as $msg ) { ?> <li><?= $msg ?></li><?php } ?>
</ul>
<?php
        }


        // now let derived class show the important bits
        $this->display();

?>

<br/>
<br/>
<a href="/<?= $this->journo['ref'] ?>">Finish editing</a>

</div> <!-- end main -->

<div class="sidebar">
<?php $this->navBox(); ?>
</div> <!-- end sidebar -->

<?php
//$this->showNavigation();

        page_footer();
    }



    function navBox()
    {
        $pages = array(
            'photo'=>array( 'title'=>'Photo', 'url'=>'/profile_photo' ),
            'admired'=>array( 'title'=>'Admired Journalists', 'url'=>'/profile_admired' ),
            'employment'=>array( 'title'=>'Employment', 'url'=>'/profile_employment' ),
            'education'=>array( 'title'=>'Education', 'url'=>'/profile_education' ),
            'awards'=>array( 'title'=>'Awards', 'url'=>'/profile_awards' ),
            'books'=>array( 'title'=>'Books', 'url'=>'/profile_books' ),
            'contact'=>array( 'title'=>'Contact', 'url'=>'/profile_contact' ),
            'weblinks'=>array( 'title'=>'On the web', 'url'=>'/profile_weblinks' ), 
        );

        $prev = NULL;
        foreach( $pages as $pagename=>&$p ) {
            $p['prev'] = $prev;
            $p['next'] = NULL;
            if( !is_null( $prev ) )
                $pages[$prev]['next'] = $pagename;
            $prev = $pagename;
        }
        unset( $p );

?>
<div class="box">
<div class="head"><h4>Edit your profile</h4></div>
<div class="body">
<ul>
<?php foreach( $pages as $n=>$p ) { ?>
<li>
<?php if( $n == $this->pageName ) { ?>
<?= $p['title'] ?>
<?php } else { ?>
<a href="<?= $p['url'] ?>?ref=<?=$this->journo['ref'] ?>" ><?= $p['title'] ?></a>
<?php } ?>
</li>
<?php } ?>
</ul>
</div>
</div>

<?php
    }





    // main display function
    // derived pages override this.
    function display()
    {
    }

    // like display() but for ajax requests.
    function ajax()
    {
        return NULL;
    }


    /* helper for submitting items */
    function genericFetchItemFromHTTPVars( $fieldnames ) {
        $item = array();
        foreach( $fieldnames as $f )
            $item[$f] = get_http_var($f);
        $item['id'] = get_http_var('id');
        return $item;
    }


    /* construct "remove" and "edit" links for the given item */
    /* TODO: only removelink now. change name? */
    function genEditLinks( $entry_id ) {
        return <<<EOT
<a class="remove" href="{$this->pagePath}?ref={$this->journo['ref']}&remove_id={$entry_id}">remove</a>
EOT;
    }


    /* helper for submitting items */
    /* for new items, adds the id field upon insert */
    function genericStoreItem( $tablename, $fieldnames, &$item )
    {

        if( $item['id'] ) {
            /* update existing entry */
            
            $frags = array();
            $params = array();
            foreach( $fieldnames as $f ) {
                $frags[] = "$f=?";
                $params[] = $item[$f];
            }

            /* note, restrict by journo id to stop people hijacking others entries! */
            $sql = "UPDATE {$tablename} SET " . implode( ',', $frags ) . " WHERE id=? AND journo_id=?";
            $params[] = $item['id'];
            $params[] = $this->journo['id'];

            db_do( $sql, $params );

            eventlog_Add( "modify-{$this->pageName}", $this->journo['id'], $item );

        } else {
            /* insert new entry */

            $frags = array( '?' );
            $params = array( $this->journo['id'] );
            foreach( $fieldnames as $f ) {
                $frags[] = "?";
                $params[] = $item[$f];
            }
            $sql = "INSERT INTO {$tablename} (journo_id," . implode( ",", $fieldnames) . ") ".
                "VALUES (" . implode(',',$frags) . ")";
            db_do( $sql, $params );
            $item['id'] = db_getOne( "SELECT lastval()" );
            eventlog_Add( "add-{$this->pageName}", $this->journo['id'], $item );
        }
        db_commit();

        return $item['id'];
    }


}


?>
