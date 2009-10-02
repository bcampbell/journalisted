<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class AwardsPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "awards";
        $this->pageTitle = "Awards";
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
            $("#awards").dynamicForm( '#awards-plus', '#awards-minus', {limit:10} );
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

        $this->showAwards();
        $this->showForm();

    }

    function showForm()
    {

?>

<form method="POST" action="/profile_awards">
<fieldset id="awards">
<table border="0">
 <tr><th><label for="award">Award</label></td><td><input type="text" size="60" name="award[]" id="award"/></td></tr>
</table>
<a id="awards-minus" href="">[-]</a>
<a id="awards-plus" href="">[+]</a>
</fieldset>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
</form>
<?php

    }



    function handleSubmit()
    {
        $award_names = get_http_var('award');
        $awards = array();
        foreach( $award_names as $n ) {
            $awards[] = array('award'=>$n);
        }

        foreach( $awards as $a )
        {
            $sql = "INSERT INTO journo_awards (journo_id,award) VALUES (?,?)";
            db_do( $sql, $this->journo['id'], $a['award'] );
        }
        db_commit();
    }


    function showAwards()
    {

        $awards = db_getAll( "SELECT * FROM journo_awards WHERE journo_id=?", $this->journo['id'] );

?>
<ul>
<?php foreach( $awards as $a ) { ?>
<li>
<?=h($a['award']);?>
 [<a href="/profile_awards?ref=<?=$this->journo['ref'];?>&remove_id=<?=$a['id'];?>">remove</a>]
</li>
<?php } ?>
</ul>
<?php
    }



    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_awards WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }

}




$page = new AwardsPage();
$page->display();


