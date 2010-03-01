<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class BooksPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "books";
        $this->pagePath = "/profile_books";
        $this->pageTitle = "Books";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
?>
<?php
    }




    function handleActions()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }

        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

        if( $action != 'edit' && $action != 'new' ) {
            $this->Redirect( "/{$this->journo['ref']}#tab-bio" );
        }
    }


    function display()
    {
        $action = get_http_var( "action" );

        if( $action=='edit' )
        {
            $id = get_http_var('id');
            $entry = db_getRow( "SELECT * FROM journo_books WHERE journo_id=? AND id=?",
                $this->journo['id'], $id );
?>
<h2>Edit book</h2>
<?php $this->showForm( $entry ); ?>
<a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= h($entry['id']); ?>">Remove this book</a>
<?php
        }

        if( $action=='new' )
        {
?>
<h2>Add book</h2>
<?php
            $this->showForm( null );
        }
    }


    function ajax()
    {
        return NULL;
    }



    function showForm( $book )
    {
        static $uniq=0;
        ++$uniq;
        $formtype = 'edit';
        if( is_null( $book ) ) {
            $formtype = 'new';
            $book = array( 'title'=>'', 'publisher'=>'', 'year_published'=>'' );
        }
 
?>

<form class="book" method="POST" action="<?= $this->pagePath; ?>">
  <dl>
    <dt><label for="title_<?= $uniq; ?>">Title</label></dt>
    <dd><input type="text" size="60" name="title" id="title_<?= $uniq; ?>" value="<?= h($book['title']); ?>" /></dd>

    <dt><label for="publisher_<?= $uniq; ?>">Publisher</label></dt>
    <dd><input type="text" size="60" name="publisher" id="publisher_<?= $uniq; ?>" value="<?= h($book['publisher']); ?>" /></dd>

    <dt><label for="year_published<?= $uniq; ?>">Year published</label></dt>
    <dd><input type="text" class="year" size="4" name="year_published" id="year_published_<?= $uniq; ?>" value="<?= h($book['year_published']); ?>" /></dd>
  </dl>

<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save changes</button> or
<a class="cancel" href="/<?= $this->journo['ref'] ?>#tab-bio">cancel</a>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $book['id']; ?>" />
<?php } ?>
</form>
<?php

    }




    function handleSubmit()
    {
        $fieldnames = array( 'title', 'publisher', 'year_published' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year_published'] )
            $item['year_published'] = NULL;
        $this->genericStoreItem( "journo_books", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_books WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
        eventlog_Add( 'remove-books', $this->journo['id'] );
    }


}




$page = new BooksPage();
$page->run();


