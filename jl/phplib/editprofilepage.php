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
//        $this->P = person_if_signed_on();
        $ref = get_http_var( 'ref' );
        $this->journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
        $r = array(
            'reason_web' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email' => "Edit Journalisted profile for {$this->journo['prettyname']}",
            'reason_email_subject' => "Edit {$this->journo['prettyname']} on Journalisted"
        );
        // rediect to login screen if not logged in (so this may never return)
        $this->P = person_signon($r);
    }


    function run()
    {
        if( get_http_var('ajax') ) {
            $this->ajax();
        } else {
            $this->display();
        }
    }


    /* to be overriden */
    function ajax() {
    }


    /* to be overriden */
    /* called before displayMain, so can redirect, show error page,
       whatever. return FALSE to suppress normal page */
    function handleActions() {
        return TRUE;    /* show page please! */
    }


    function addError( $msg ) { $this->error_messages[] = $msg; }
    function addInfo( $msg ) { $this->info_messages[] = $msg; }

    /* normal, non-ajax display */
    function display()
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
<h2>Welcome, <a href="/<?=$this->journo['ref'];?>"><em><?=$this->journo['prettyname'];?></em></a></h2>

<!-- <a href="/profile?ref=<?= $this->journo['ref'] ?>">Finish editing</a> -->

<!--
<div class="smallcolumn profile">

<div class="box">
<div class="box-content">
-->
<?php
/*
        $this->showNavigation();
        $this->showPicture();
*/
?>
<!--
</div>
</div>

</div>
<div class="maincolumn profile">
<div class="box">
-->
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

        $this->displayMain();
?>
<!--
</div>
</div>
-->

<!-- <a href="/profile?ref=<?= $this->journo['ref'] ?>">Finish editing</a> -->

<?php
$this->showNavigation();
        page_footer();
    }


    function showPicture()
    {
        $pic = journo_getPicture( $this->journo['id'] );

?>
<div class="picture">
<?php if( $pic ) { ?>
<img src="<?= $pic['url'] ?>" />
<a class="edit" href="/profile_picture?ref=<?= $this->journo['ref'] ?>">Change</a>
<a class="remove" href="/profile_picture?ref=<?= $this->journo['ref']; ?>&action=remove_pic">Remove</a>
<?php } else { ?>
<img src="images/rupe.gif" />
<a class="edit" href="/profile_picture?ref=<?= $this->journo['ref'] ?>">Set a picture</a>
<?php } ?>
</div>
<?php

    }




    function showNavigation()
    {
        $pages = array(
            'picture'=>array( 'title'=>'Photo', 'url'=>'/profile_picture' ),
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


        $this->emitMenu( $pages );
    }


    function emitMenu_SHITTY( $menu )
    {

        $curr = arr_get( $this->pageName, $menu );
        $prev = arr_get( $curr['prev'], $menu );
        $next = arr_get( $curr['next'], $menu );

?>
<div class="profile-nav">
<table border="0">
<tr>
 <td></td>
 <td><a href="/profile?ref=<?= $this->journo['ref'] ?>">back to profile page</a><br/>&uarr;</td>
 <td></td>
</tr>
<tr>
 <td><?php if($prev) {?><a href="<?= $prev['url']; ?>?ref=<?= $this->journo['ref'] ?>"><?= $prev['title']; ?></a> &larr; <?php } ?></td>
 <td><?= $curr['title'] ?></td>
 <td><?php if($next) {?> &rarr; <a href="<?= $next['url']; ?>?ref=<?= $this->journo['ref'] ?>"><?= $next['title']; ?></a> <?php } ?></td>
</tr>
</table>
</div>
<?php


    }

    function emitMenu( $menu )
    {
?>
<div class="profile-nav"> <a href="/profile?ref=<?= $this->journo['ref'] ?>"><strong>Overview</strong></a> |
<?php
        foreach( $menu as $itemname=>$item ) {
            $c = ($itemname==$this->pageName) ?' class="current" ' : '';
            $url = "{$item['url']}?ref={$this->journo['ref']}";
            if( $itemname == $this->pageName ) {
?>
<?= $item['title'] ?> |
<?php } else { ?>
<a href="<?= $url ?>"><?= $item['title'] ?></a> |
<?php } ?>
<?php
        }
?>
</div>
<?php
    }



    // derived pages override this.
    function displayMain()
    {
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
    function genEditLinks( $entry_id ) {
        return <<<EOT
<a class="edit" href="">edit</a>
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
        }
        db_commit();

        return $item['id'];
    }


}


?>
