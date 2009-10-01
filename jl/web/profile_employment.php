<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class EmploymentPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "employment";
        $this->pageTitle = "Employment";
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
            $("#employment").dynamicForm( '#employment-plus', '#employment-minus', {limit:10} );
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

        $this->showEmployment();
        $this->showForm();

    }

    function showForm()
    {

?>

<form method="POST" action="/profile_employment">
<fieldset id="employment">
<table border="0">
 <tr><th><label for="employer">Employer</label></td><td><input type="text" size="60" name="employer[]" id="employer"/></td></tr>
 <tr><th><label for="job_title">Job Title</label></td><td><input type="text" size="60" name="job_title[]" id="job_title"/></td></tr>
 <tr><th><label for="year_from">from</label></td><td><input type="text" size="4" name="year_from[]" id="year_from"/></td></tr>
 <tr><th><label for="year_to">to</label></td><td><input type="text" size="4" name="year_to[]" id="year_to"/></td></tr>
</table>
<a id="employment-minus" href="">[-]</a>
<a id="employment-plus" href="">[+]</a>
</fieldset>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
</form>
<?php

    }



    function handleSubmit()
    {
        $employers = get_http_var('employer');
        $job_titles = get_http_var('job_title');
        $year_froms = get_http_var('year_from');
        $year_tos = get_http_var('year_to');
        $employment = array();
        while( !empty($employers) ) {
            $from = array_shift($year_froms);
            $from = ($from=='') ? NULL : intval($from);
            $to = array_shift($year_tos);
            $to = ($to=='') ? NULL : intval($to);
            $employment[] = array(
                'employer'=>array_shift($employers),
                'job_title'=>array_shift($job_titles),
                'year_from'=>$from,
                'year_to'=>$to,
            );
        }

        foreach( $employment as $b )
        {
            $sql = "INSERT INTO journo_employment (journo_id,employer,job_title,year_from,year_to) VALUES (?,?,?,?,?)";
            db_do( $sql, $this->journo['id'], $b['employer'], $b['job_title'], $b['year_from'], $b['year_to'] );
        }
        db_commit();
    }


    function showEmployment()
    {

        $employment = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=?", $this->journo['id'] );

?>
<ul>
<?php foreach( $employment as $e ) { ?>
<li><?=h($e['employer']);?><br/> <em><?=h($e['job_title']);?>, <?=h($e['year_from']);?>-<?=h($e['year_to']);?></em></li>
<?php } ?>
</ul>
<?php
    }

}




$page = new EmploymentPage();
$page->display();


