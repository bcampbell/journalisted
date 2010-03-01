<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class EmploymentPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "employment";
        $this->pageTitle = "Employment";
        $this->pagePath = "/profile_employment";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocomplete

?>
<script type="text/javascript">

    $(document).ready( function() {

            var f = $('.employer');
            var current = f.find("input[name=current]");

            var year_to = f.find("input[name=year_to]");
            var year_to_label = year_to.prev( "label" );
            year_to.toggle( ! current.attr('checked') );
            year_to_label.toggle( ! current.attr('checked') );
            current.click( function() {
                year_to.toggle( ! current.attr('checked') );
                year_to_label.toggle( ! current.attr('checked') );
            } );

    } );

</script>
<?php
    }




    function handleActions()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
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
            $emp_id = get_http_var('id');
            $emp = db_getRow( "SELECT * FROM journo_employment WHERE journo_id=? AND id=?",
                $this->journo['id'], $emp_id );
?>
<h2>Edit experience</h2>
<?php
            $this->showForm( $emp );
?>
<a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= h($emp['id']); ?>">Remove this experience</a>
<?php
        }

        if( $action=='new' )
        {
?>
<h2>Add experience</h2>
<?php
            $this->showForm( null );
        }
    }

    function ajax()
    {
        return NULL;
    }


    /* if $emp is null, then display a fresh form for entering a new entry */
    function showForm( $emp )
    {
        static $uniq=0;
        ++$uniq;
        $formtype = 'edit';
        if( is_null( $emp ) ) {
            $formtype = 'new';
            $emp = array( 'employer'=>'', 'job_title'=>'', 'year_from'=>'', 'year_to'=>'' );
        }
 
?>

<form class="employer" method="POST" action="<?= $this->pagePath; ?>">

  <dl>
    <dt><label for="employer_<?= $uniq; ?>">Employer</label></dt>
    <dd><input type="text" size="60" name="employer" id="employer_<?= $uniq; ?>" value="<?= h($emp['employer']); ?>"/></dd>

    <dt><label for="job_title_<?= $uniq; ?>">Job title(s)</label></dt>
    <dd><input type="text" size="60" name="job_title" id="job_title_<?= $uniq; ?>" value="<?= h($emp['job_title']); ?>"/></dd>

    <dt><span class="faux-label">Date</span></dt>
    <dd>
      <label for="year_from_<?= $uniq; ?>">Year from:</label>
      <input type="text" class="year" size="4" name="year_from" id="year_from_<?= $uniq; ?>" value="<?= h($emp['year_from']); ?>"/>
      <label for="year_to_<?= $uniq; ?>">Year to:</label>
      <input type="text" class="year" size="4" name="year_to" id="year_to_<?= $uniq; ?>" value="<?= h($emp['year_to']); ?>"/>
      <input type="checkbox" <?php if( !$emp['year_to'] ) { ?>checked <?php } ?>name="current" id="current_<?= $uniq; ?>"/><label for="current_<?= $uniq; ?>">I currently work here</label>
    </dd>
  </dl>

<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= h($emp['id']); ?>" />
<?php } ?>
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save changes</button> or <a class="cancel" href="/<?= $this->journo['ref'] ?>#tab-bio">cancel</a>
<div style="clear:both;"></div>
</form>

<?php

    }



    function handleSubmit()
    {
        $fieldnames = array( 'employer', 'job_title', 'year_from', 'year_to' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year_from'] )
            $item['year_from'] = NULL;
        if( !$item['year_to'] )
            $item['year_to'] = NULL;
        if( get_http_var( 'current' ) )
            $item['year_to'] = NULL;

        $this->genericStoreItem( "journo_employment", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_employment WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();

        eventlog_Add( 'remove-employment', $this->journo['id'] );
    }
}





$page = new EmploymentPage();
$page->run();


