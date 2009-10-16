<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
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
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<? /*
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
*/
?>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.book' );
    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }
?><h2>Have you published any books?</h2><?php

        $books = db_getAll( "SELECT * FROM journo_books WHERE journo_id=?", $this->journo['id'] );
        foreach( $books as &$book ) {
            $this->showForm($book);
        }
        /* template form for adding new ones */
        $this->showForm( NULL );
    }


    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'remove_link_html'=>$this->genRemoveLink($entry_id),
            );
            print json_encode( $result );
        }
    }



    function showForm( $book )
    {
        static $uniq=0;
        ++$uniq;
        $is_template = is_null( $book );
        if( $is_template )
            $book = array( 'title'=>'', 'publisher'=>'', 'year_published'=>'' );

?>

<form class="book<?= $is_template?' template':''; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="title_<?= $uniq; ?>">Title</label></th>
  <td><input type="text" size="60" name="title" id="title_<?= $uniq; ?>" value="<?= h($book['title']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="publisher_<?= $uniq; ?>">Publisher</label></th>
  <td><input type="text" size="60" name="publisher" id="publisher_<?= $uniq; ?>" value="<?= h($book['publisher']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="year_published<?= $uniq; ?>">Year Published</label></th>
  <td><input type="text" size="60" name="year_published" id="year_published_<?= $uniq; ?>" value="<?= h($book['year_published']); ?>" /></td>
 </tr>
</table>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
<button class="cancel" type="reset">Cancel</button>
<?php if( !$is_template ) { ?>
<input type="hidden" name="id" value="<?= $book['id']; ?>" />
<?= $this->genRemoveLink($book['id']); ?>
<?php } ?>
</form>
<?php

    }




    function handleSubmit()
    {
        $fieldnames = array( 'title', 'publisher', 'year_published' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        $this->genericStoreItem( "journo_books", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_books WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }


}




$page = new BooksPage();
$page->run();


