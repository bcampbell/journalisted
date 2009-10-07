<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';


class MissingInfoPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "missing";
        $this->pageTitle = "Missing Info";
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

<script type="text/javascript">
    $(document).ready(
        function() {
//            $("#admired-journo").dynamicForm( '#admired-journo-plus', '#admired-journo-minus', {limit:10} );
    });
</script>
<?php
    }



    function displayMain()
    {

?>
<h2>Please include these articles I wrote</h2>
      <form action="/missing" method="POST">
        <input type="hidden" name="j" value="<?=$this->journo['ref'];?>" />
        <p>Please enter the urls of the article(s) you want to submit, one per line:</p>
        <textarea name="rawurls" style="width: 100%;" rows="8"></textarea><br/>
        <button type="submit" name="action" value="go">Submit</button>
      </form>
<?php

    }

}



$page = new MissingInfoPage();
$page->display();

