<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

function extra_head()
{
?>
<!--    <link type="text/css" href="http://jqueryui.com/themes/base/ui.all.css" rel="stylesheet" /> -->
    <link type="text/css" href="/profile.css" rel="stylesheet" /> 

	<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="/js/jquery.protect-data.min.js"></script>

	<script type="text/javascript">
    $(document).ready(
        function() {
            $('#emp_addnew').click( function() {
                var c = $('#emp_template').clone();
                c.removeAttr('id');
                $(this).before( c );
                return false;
                });
            $('#emp_curr').live( "click", function() {
                var checked = $(this).attr( 'checked' )
                $(this).parent().closest( 'fieldset' ).find('.emp_to_field').toggle( !checked );
                });
            $.protectData.message = "There are unsaved changes.";
            $('form').protectData();
        } );
	</script>

<?php
}

page_header( "journo profile", array('head_extra_fn' => "extra_head" ) );

$current_tab = get_http_var('tab', 'employment');

$tabs = array(
    'admired'=>'Admired Journos',
    'employment'=>'Employment',
    'education'=>'Education',
    'awards'=>'Awards',
    'books'=>'Books',
);


?>
<div id="maincolumn">
<h2>Welcome back, <em>Phil Notebook</em></h2>

<ul class="tabs">
<?php foreach( $tabs as $tab=>$title ) { ?>
<?php  if($tab==$current_tab) { ?>
<li class="current"><a href="/journo_test3?tab=<?php echo $tab; ?>"><?php echo $title; ?></a></li>
<?php  } else{ ?>
<li><a href="/journo_test3?tab=<?php echo $tab; ?>"><?php echo $title; ?></a></li>
<?php  } ?>
<?php } ?>
</ul>

<?php


    if( get_http_var( "action" ) ) {
        print "<pre>\n";
        print_r( $_POST );
        print "</pre>\n";
    }
    else {
        switch( $current_tab ) {
            case "employment": emit_employment_tab(); break;
            case "education": emit_education_tab(); break;
            case "admired": emit_admired_journos_tab(); break;
            case "awards": emit_awards_tab(); break;
            case "books": emit_books_tab(); break;
        }
    }

?>
</div> <!-- end maincolumn -->

<div id="smallcolumn">
<div class="box">
<h3>test box</h3>
<div class="box-content">
blah blah
blah blah
blah blah
blah blah
blah blah
blah blah
blah blah
</div>
</div>

</div>  <!-- end smallcolumn -->

<?php
page_footer();



function emit_admired_journos_tab()
{
?>
<h3>Which journalists do you most admire?</h3>
<?php
}

function emit_education_tab()
{
?>
<h3>Tell us about your education</h3>
<?php
}

function emit_awards_tab()
{
?>
<h3>Have you won any awards?</h3>
<?php
}

function emit_books_tab()
{
?>
<h3>Have you published any books?</h3>
<?php
}



function emit_employment_tab()
{
?>
<h3>Add employment information</h3>



<form method="POST" action="">

<fieldset id="emp_template">
<table border="0">
<tbody>
<tr><th><label for="emp_name">Employer</label></th> <td><input type="text" name="emp_name" id="emp_name" value="" /></td></tr>
<tr><th><label for="emp_title">Job Title</label></th> <td><input type="text" name="emp_title" id="emp_title" value="" /></td></tr>
<tr><th>Time period</th>
 <td>
  <label for="emp_from">year from</label> <input type="text" name="emp_from" id="emp_from" size="4" value="" />
  <span class="emp_to_field"> <label for="emp_to">year to</label> <input type="text" name="emp_to" id="emp_to" size="4" value="" /></span>
  <input type="checkbox" name="emp_curr" id="emp_curr" /><label for="emp_curr">I currently work here</label>
 </td></tr>
</tbody>
</table>
<a href="#">remove this employer</a>
</fieldset>

<a id="emp_addnew" href="#">add another employer</a><br/><br/>

<button name="action" value="save">Submit</button>

</form>


<?php
}

?>
