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
        $this->pageTitle = "Books";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocompete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<script type="text/javascript" src="/js/jquery-dynamic-form.js"></script>

<script type="text/javascript">
    $(document).ready(
        function() {
            $("#books").dynamicForm( '#books-plus', '#books-minus', {limit:10} );
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

        $this->showBooks();
        $this->showForm();

    }

    function showForm()
    {

?>

<form method="POST" action="/profile_books">
<fieldset id="books">
<table border="0">
 <tr><th><label for="title">Title</label></td><td><input type="text" size="60" name="title[]" id="title"/></td></tr>
 <tr><th><label for="publisher">Publisher</label></td><td><input type="text" size="60" name="publisher[]" id="publisher"/></td></tr>
 <tr><th><label for="year">Year Published</label></td><td><input type="text" size="60" name="year[]" id="year"/></td></tr>
</table>
<a id="books-minus" href="">[-]</a>
<a id="books-plus" href="">[+]</a>
</fieldset>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
</form>
<?php

    }



    function handleSubmit()
    {
        $titles = get_http_var('title');
        $publishers = get_http_var('publisher');
        $years = get_http_var('year');
        $books = array();
        while( !empty($titles) ) {
            $y = array_shift($years);
            $y = ($y=='') ? NULL : intval($y);
            $books[] = array(
                'title'=>array_shift($titles),
                'publisher'=>array_shift($publishers),
                'year_published'=>$y,
            );
        }

        foreach( $books as $b )
        {
            $sql = "INSERT INTO journo_books (journo_id,title,publisher,year_published) VALUES (?,?,?,?)";
            db_do( $sql, $this->journo['id'], $b['title'], $b['publisher'], $b['year_published'] );
        }
        db_commit();
    }


    function showBooks()
    {

        $books = db_getAll( "SELECT * FROM journo_books WHERE journo_id=?", $this->journo['id'] );

?>
<ul>
<?php foreach( $books as $b ) { ?>
<li><?=h($b['title']);?><br/> <em><?=h($b['publisher']);?>, <?=h($b['year_published']);?></em></li>
<?php } ?>
</ul>
<?php
    }

}




$page = new BooksPage();
$page->display();


