<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
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
        fancyForms( '.education' );
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

        $edus = db_getAll( "SELECT * FROM journo_education WHERE journo_id=? ORDER BY year_from ASC", $this->journo['id'] );
?>
<h2>Tell us about your education</h2>
<?php
        foreach( $edus as &$edu ) {
            $this->showForm( $edu );
        }

        /* output the template form */
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



    /* if $edu is null, then display a fresh form for entering a new entry */
    function showForm( $edu )
    {
        static $uniq=0;
        ++$uniq;
        $is_template = is_null( $edu );
        if( $is_template )
            $edu = array( 'school'=>'', 'field'=>'', 'qualification'=>'', 'year_from'=>'', 'year_to'=>'' );

?>

<form class="education<?= $is_template?' template':''; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="school_<?= $uniq; ?>">School name:</label></th>
  <td><input type="text" size="60" name="school" id="school_<?= $uniq; ?>" value="<?= h($edu['school']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="field_<?= $uniq; ?>">Field(s) of study:</label></th>
  <td><input type="text" size="60" name="field" id="field_<?= $uniq; ?>" value="<?= h($edu['field']); ?>" /></td></tr>
 <tr>
  <th><label for="qualification_<?= $uniq; ?>">Qualification:</label></th>
  <td><input type="text" size="30" name="qualification" id="qualification_<?= $uniq; ?>" value="<?= h($edu['qualification']); ?>" /></td>
 </tr>
 <tr>
  <th>Years attended:</th>
  <td>
   <label for="year_from_<?= $uniq; ?>">from</label>
   <input type="text" size="4" name="year_from" id="year_from_<?= $uniq; ?>" value="<?= h($edu['year_from']); ?>" />
   <label for="year_to_<?= $uniq; ?>">to</label>
   <input type="text" size="4" name="year_to" id="year_to_<?= $uniq; ?>" value="<?= h($edu['year_to']); ?>" />
  </td>
 </tr>
</table>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save</button>
<button class="cancel" type="reset">Cancel</button>
<?php if( !$is_template ) { ?>
<input type="hidden" name="id" value="<?= $edu['id']; ?>" />
<?= $this->genRemoveLink($edu['id']); ?>
<?php } ?>
</form>
<?php

    }



    function handleSubmit()
    {
        $fieldnames = array( 'school', 'field', 'qualification', 'year_from', 'year_to' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        $this->genericStoreItem( "journo_education", $fieldnames, $item );
        return $item['id'];
    }



    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_education WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }


}




$page = new EducationPage();
$page->run();


