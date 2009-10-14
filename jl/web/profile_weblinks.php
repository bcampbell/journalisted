<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class WeblinksPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "weblinks";
        $this->pageTitle = "Weblinks";
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
?><h2>Where else are you on the web?</h2><?php
        $this->showWeblinks();
        $this->showForm();
    }

    function showForm()
    {

?>

<form method="POST" action="/profile_weblinks">
<div id="weblinks">

<h3>I have a personal website / blog at</h3>
  <label for="homepage">website/blog URL</label> <input type="text" size="60" name="homepage[]" id="homepage"/>
<h3>I micro-blog (e.g. Twitter) at</h3>
  <label for="blog">URL</label> <input type="text" size="60" name="blog[]" id="blog"/>
<h3>I have pages on facebook/linkedin/myspace/wherever</h3>
  <label for="social">URLs</label> <textarea name="social" id="social" cols="60" rows="5"></textarea>
</div>
<p>(<small>TODO: profile pages - wikipedia/cif/nuj freelance directory/whereever</small>)</p>
<br/>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
</form>
<?php

    }



    function handleSubmit()
    {
    }


    function showWeblinks()
    {

        $weblinks = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=?", $this->journo['id'] );

?>
<ul>
<?php foreach( $weblinks as $a ) { ?>
<li><?=h($a['description']);?> (<a class="extlink" href="<?=$a['url'];?>"><?=$a['url'];?></a>)</li>
<?php } ?>
</ul>
<?php
    }



    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_weblink WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }

}




$page = new WeblinksPage();
$page->run();


