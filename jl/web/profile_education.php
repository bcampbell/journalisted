<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class EducationPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "education";
        $this->pageTitle = "Education";
        $this->pagePath = "/profile_education";
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
        if( $action == "submit_uni" ) {
            $added = $this->handleUniSubmit();
        }
        if( $action == "submit_school" ) {
            $added = $this->handleSchoolSubmit();
        }

        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

        if( $action != 'edit' && $action != 'new_school' && $action != 'new_uni' ) {
            $this->Redirect( "/{$this->journo['ref']}?tab=bio" );
        }
    }


    function display()
    {
        $action = get_http_var( "action" );

        if( $action=='edit' )
        {
            $edu_id = get_http_var('id');
            $edu = db_getRow( "SELECT * FROM journo_education WHERE journo_id=? AND id=?",
                $this->journo['id'], $edu_id );
            if( $edu['kind'] == 's' ) {
?>
<h2>Edit education (school)</h2>
<?php $this->showSchoolForm( $edu ); ?>
<a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= h($edu['id']); ?>">Remove this school</a>
<?php
            } else /*'u'*/ {
?>
<h2>Edit education (university)</h2>
<?php $this->showUniForm( $edu ); ?>
<a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= h($edu['id']); ?>">Remove this university</a>
<?php
            }
        }



        if( $action=='new_school' )
        {
?>
<h2>Add education (school)</h2>
<?php
            $this->showSchoolForm( null );
        }
        if( $action=='new_uni' )
        {
?>
<h2>Add education (university)</h2>
<?php
            $this->showUniForm( null );
        }

    }



    function ajax()
    {
        return NULL;
    }


    /* if $edu is null, then display a fresh form for entering a new entry */
    function showSchoolForm( $edu )
    {
        static $uniq=0;
        ++$uniq;
        $formtype = 'edit';
        if( is_null( $edu ) ) {
            $formtype = 'new';
            $edu = array( 'school'=>'', 'year_from'=>'', 'year_to'=>'' );
        }

?>

<form class="education" method="POST" action="<?= $this->pagePath; ?>">
<dl>
  <dt><label for="school_<?= $uniq; ?>">School name</label></dt>
  <dd>
    <input type="text" size="60" name="school" id="school_<?= $uniq; ?>" value="<?= h($edu['school']); ?>" />
    <span class="explain">e.g. "St Augustine's, Canterbury"</span>
  </dd>

  <dt><span class="faux-label">Years attended</span></dt>
  <dd><label for="year_from_<?= $uniq; ?>">from</label>
    <input type="text" class="year" size="4" name="year_from" id="year_from_<?= $uniq; ?>" value="<?= h($edu['year_from']); ?>" />
    <label for="year_to_<?= $uniq; ?>">to</label>
    <input type="text" class="year" size="4" name="year_to" id="year_to_<?= $uniq; ?>" value="<?= h($edu['year_to']); ?>" />
  </dd>
</dl>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit_school" />
<button class="submit" type="submit">Save changes </button> or
<a class="cancel" href="/<?= $this->journo['ref'] ?>?tab=bio">cancel</a>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $edu['id']; ?>" />
<?php } ?>
</form>
<?php if( $formtype=='edit' ) { ?>
<?php } ?>
<?php

    }



    /* if $edu is null, then display a fresh form for entering a new entry */
    function showUniForm( $edu )
    {
        static $uniq=0;
        ++$uniq;
        $formtype = 'edit';
        if( is_null( $edu ) ) {
            $formtype = 'new';
            $edu = array( 'school'=>'', 'field'=>'', 'qualification'=>'', 'year_from'=>'', 'year_to'=>'' );
        }

?>

<form class="education" method="POST" action="<?= $this->pagePath; ?>">
<dl>
  <dt><label for="school_<?= $uniq; ?>">School name</label></dt>
  <dd>
    <input type="text" size="60" name="school" id="school_<?= $uniq; ?>" value="<?= h($edu['school']); ?>" />
    <span class="explain">eg: "St. Cedd's College, Cambridge"</span>
  </dd>

  <dt><label for="field_<?= $uniq; ?>">Field(s) of study</label></dt>
  <dd>
    <input type="text" size="60" name="field" id="field_<?= $uniq; ?>" value="<?= h($edu['field']); ?>" />
    <span class="explain">eg: "Rocket Surgery"</span>
  </dd>

  <dt><label for="qualification_<?= $uniq; ?>">Qualification</label></dt>
  <dd>
   <input type="text" size="30" name="qualification" id="qualification_<?= $uniq; ?>" value="<?= h($edu['qualification']); ?>" />
   <span class="explain">eg: "BA"</span>
  </dd>

  <dt><span class="faux-label">Years attended</span></dt>
  <dd><label for="year_from_<?= $uniq; ?>">from</label>
    <input type="text" class="year" size="4" name="year_from" id="year_from_<?= $uniq; ?>" value="<?= h($edu['year_from']); ?>" />
    <label for="year_to_<?= $uniq; ?>">to</label>
    <input type="text" class="year" size="4" name="year_to" id="year_to_<?= $uniq; ?>" value="<?= h($edu['year_to']); ?>" />
  </dd>
</dl>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit_uni" />
<button class="submit" type="submit">Save changes </button> or
<a class="cancel" href="/<?= $this->journo['ref'] ?>?tab=bio">cancel</a>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $edu['id']; ?>" />
<?php } ?>
</form>
<?php if( $formtype=='edit' ) { ?>
<?php } ?>
<?php

    }



    function handleUniSubmit()
    {
        $fieldnames = array( 'school', 'field', 'qualification', 'year_from', 'year_to' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year_from'] )
            $item['year_from'] = NULL;
        if( !$item['year_to'] )
            $item['year_to'] = NULL;
        $fieldnames[] = 'kind';
        $item['kind'] = 'u';
        $this->genericStoreItem( "journo_education", $fieldnames, $item );
        return $item['id'];
    }

    function handleSchoolSubmit()
    {
        $fieldnames = array( 'school', 'year_from', 'year_to' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year_from'] )
            $item['year_from'] = NULL;
        if( !$item['year_to'] )
            $item['year_to'] = NULL;
        $fieldnames[] = 'field';
        $item['field'] = '';
        $fieldnames[] = 'qualification';
        $item['qualification'] = '';
        $fieldnames[] = 'kind';
        $item['kind'] = 's';
        $this->genericStoreItem( "journo_education", $fieldnames, $item );
        return $item['id'];
    }



    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_education WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        eventlog_Add( 'remove-education', $this->journo['id'] );
        db_commit();
    }


}




$page = new EducationPage();
$page->run();


