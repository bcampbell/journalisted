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

        $edus = db_getAll( "SELECT * FROM journo_education WHERE journo_id=? ORDER BY year_from ASC", $this->journo['id'] );
?>
<h2>Tell us about your education</h2>
<?php
        $this->showEducation( $edus );
        $this->showForm();

    }

    function showForm()
    {

?>


<form method="POST" action="/profile_education">
<fieldset id="education">
<table border="0">
 <tr><th><label for="edu_school">School name</label></td><td><input type="text" size="60" name="edu_school[]" id="edu_school"/></td></tr>
 <tr><th><label for="edu_field">Field(s) of study</label></td><td><input type="text" size="60" name="edu_field[]" id="edu_field"/></td></tr>
 <tr><th><label for="edu_qualification">Qualification</label></td><td><input type="text" size="30" name="edu_qualification[]" id="edu_qualification"/></td></tr>
 <tr>
  <th>Years attended:</th>
  <td>
   <label for="edu_from">from</label>
   <input type="text" size="4" name="edu_from[]" id="edu_from"/>
   <label for="edu_to">to</label>
   <input type="text" size="4" name="edu_to[]" id="edu_to"/>
  </td>
 </tr>

</table>
</fieldset>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
</form>
<?php

    }



    function handleSubmit()
    {
        $schools = get_http_var('edu_school');
        $fields = get_http_var('edu_field');
        $qualifications = get_http_var('edu_qualification');
        $froms = get_http_var('edu_from');
        $tos = get_http_var('edu_to');

        $edus = array();
        while( !empty( $schools ) ) {
            $year_from = array_shift($froms);
            $year_from = $year_from=='' ? NULL:intval($year_from);
            $year_to = array_shift($tos);
            $year_to = $year_to=='' ? NULL:intval($year_to);

            $edus[] = array(
                'school'=>array_shift($schools),
                'field'=>array_shift($fields),
                'qualification'=>array_shift($qualifications),
                'year_from'=>$year_from,
                'year_to'=>$year_to
            );
        }


        foreach( $edus as $edu )
        {
            $sql = <<<EOT
INSERT INTO journo_education (journo_id,school,field,qualification,year_from,year_to)
    VALUES (?,?,?,?,?,?);
EOT;

            db_do( $sql, $this->journo['id'], $edu['school'], $edu['field'], $edu['qualification'], $edu['year_from'],$edu['year_to'] );
        }
        db_commit();
    }


    function showEducation( &$edus )
    {


?>
<ul>
<?php foreach( $edus as $e ) { ?>
<li>
<em><?=h($e['school']);?></em>
<?php if( $e['year_from'] || $e['year_to'] ) { ?>(<?=h($e['year_from']);?>-<?=h($e['year_to']);?>)<?php } ?><br />
field: <?=h($e['field']);?><br />
qualification: <?=h($e['qualification']);?><br />
[<a href="/profile_education?ref=<?=$this->journo['ref'];?>&remove_id=<?=$e['id'];?>">remove</a>]
</li>
<?php } ?>
</ul>
<?php
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_education WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }


}




$page = new EducationPage();
$page->display();


