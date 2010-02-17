<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class AwardsPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "awards";
        $this->pagePath = "/profile_awards";
        $this->pageTitle = "Awards";
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
            $this->Redirect( "/{$this->journo['ref']}?tab=bio" );
        }
    }


    function display()
    {
        $action = get_http_var( "action" );

        if( $action=='edit' )
        {
            $id = get_http_var('id');
            $entry = db_getRow( "SELECT * FROM journo_awards WHERE journo_id=? AND id=?",
                $this->journo['id'], $id );
?>
<h2>Edit award</h2>
<?php $this->showForm( $entry ); ?>
<a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= h($entry['id']); ?>">Remove this award</a>
<?php
        }

        if( $action=='new' )
        {
?>
<h2>Add award</h2>
<?php
            $this->showForm( null );
        }
    }


    function ajax()
    {
        return NULL;
    }

    function showForm( $award )
    {
        static $uniq=0;
        ++$uniq;
        $formtype = 'edit';
        if( is_null( $award ) ) {
            $formtype = 'new';
            $award = array( 'award'=>'', 'year'=>'' );
        }

?>
<form class="award" method="POST" action="<?= $this->pagePath; ?>">
  <dl>
    <dt><label for="award_<?= $uniq; ?>">Award</label></dt>
    <dd><input type="text" size="60" name="award" id="award_<?= $uniq; ?>" value="<?= h($award['award']); ?>" /></dd>

    <dt><label for="year_<?= $uniq; ?>">Year</label></dt>
    <dd><input type="text" class="year" size="4" name="year" id="year_<?= $uniq; ?>" value="<?= h($award['year']); ?>" /></dd>
  </dl>

<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save</button>
<a class="cancel" href="/<?= $this->journo['ref'] ?>?tab=bio">cancel</a>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $award['id']; ?>" />
<?php } ?>
</form>

<?php

    }


    function handleSubmit()
    {
        $fieldnames = array( 'award', 'year' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year'] )
            $item['year'] = null;

        $this->genericStoreItem( "journo_awards", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_awards WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
        eventlog_Add( 'remove-awards', $this->journo['id'] );
    }
}



$page = new AwardsPage();
$page->run();


