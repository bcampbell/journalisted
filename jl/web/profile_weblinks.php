<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class WeblinksPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "weblinks";
        $this->pagePath = "/profile_weblinks";
        $this->pageTitle = "Weblinks";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
?>
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.weblink' );
    });
</script>
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
        return TRUE;
    }


    function display()
    {

        $weblinks = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=?", $this->journo['id'] );

        $home_url = '';
        $twitter_name = '';

?>
<h2>Web links</h2>

<form class="weblink" method="POST" action="<?= $this->pagePath; ?>">

 <div class="field">
  <label for="home_url">Homepage or Blog</label>
  <input type="text" size="60" name="home_url" id="home_url" value="<?= h($home_url) ?>" />
  <span class="explain">eg: http://<?= h($this->journo['ref']) ?>.com</span>
 </div>

 <div class="field">
  <label for="twitter_name">Twitter ID</label>
  <input type="text" size="60" name="twitter_name" id="twitter_name" value="<?= h($twitter_name) ?>" />
  <span class="explain">eg: <?= h($this->journo['ref']) ?></span>
 </div>

 <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
 <input type="hidden" name="action" value="set_special" />
 <button class="submit" type="submit">Save</button>
</form>

<?php


        foreach( $weblinks as &$weblink ) {
            $this->showForm( 'edit', $weblink);
        }

        if( !$weblinks ) {
            /* show a ready-to-go creation form */
            $this->showForm( 'creator', null );
        }

        /* template form for adding new ones */
        $this->showForm( 'template', null );
    }


    function ajax()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array(
                'id'=>$entry_id,
                'editlinks_html'=>$this->genEditLinks($entry_id),
            );
            return $result;
        }
        if( get_http_var("remove_id") )
        {
            $this->handleRemove();
            return array();
        }
        return NULL;
    }


    function showForm( $formtype, $weblink )
    {
        static $uniq=0;
        ++$uniq;
        if( is_null( $weblink ) )
            $weblink = array( 'url'=>'', 'description'=>'' );
 
        $formclasses = 'weblink';
        if( $formtype == 'template' )
            $formclasses .= " template";
        if( $formtype == 'creator' )
            $formclasses .= " creator";

?>

<form class="<?= $formclasses; ?>" method="POST" action="<?= $this->pagePath; ?>">

 <div class="field">
  <label for="url_<?= $uniq; ?>">URL</label>
  <input type="text" size="60" name="url" id="url_<?= $uniq; ?>" value="<?= h($weblink['url']); ?>" />
  <span class="explain">eg: http://<?= h($this->journo['ref']) ?>.com</span>
 </div>

 <div class="field">
   <label for="description_<?= $uniq; ?>">Description</label>
   <input type="text" size="60" name="description" id="description_<?= $uniq; ?>" value="<?= h($weblink['description']); ?>" />
 </div>


 <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
 <input type="hidden" name="action" value="submit" />
 <button class="submit" type="submit">Save</button>
<?php if( $formtype=='edit' ) { ?>
 <input type="hidden" name="id" value="<?= $weblink['id']; ?>" />
<?= $this->genEditLinks($weblink['id']); ?>
<?php } ?>
</form>
<?php

    }




    function handleSubmit()
    {
        $fieldnames = array( 'url', 'description' );
        $weblink = $this->genericFetchItemFromHTTPVars( $fieldnames );

        if( $weblink['id'] ) {
            /* update existing */
            db_do( "UPDATE journo_weblink SET url=?, description=? WHERE id=? AND journo_id=?",
                $weblink['url'],
                $weblink['description'],
                $weblink['id'],
                $this->journo['id'] );
        } else {
            db_do( "INSERT INTO journo_weblink (journo_id,url,description,approved) VALUES (?,?,?,true)",
                $this->journo['id'],
                $weblink['url'],
                $weblink['description'] );
            $weblink['id'] = db_getOne( "SELECT lastval()" );
        }
        db_commit();
        return $weblink['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_weblink WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
        eventlog_Add( 'remove-weblinks', $this->journo['id'] );
    }


}




$page = new WeblinksPage();
$page->run();


