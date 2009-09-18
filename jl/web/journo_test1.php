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
    <link type="text/css" href="/test.css" rel="stylesheet" /> 

	<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="/js/jquery-ui-1.7.2.custom.min.js"></script>

	<script type="text/javascript">
    $(document).ready(

        function() {

            // set up the Add employer button
            $('#prev_emp_template').hide();
            $("#add_employer").click( function() {
                var c = $('#prev_emp_template').clone()
                c.removeAttr( 'id' );
                // TODO: update ids of children...
                $(this).closest('.field').prepend( c );
                c.show();
                // force accordion to sort itself out
                $("#accordion .box-content").css("height","auto");
                return false;
            } );

            $("#accordion").accordion( {
                header: "h3",
                //animated:false,
                icons: { header: "minimised", headerSelected: "maximised" },
                autoHeight: false
                } )
        } );
	</script>


<?php
}

page_header( "journo profile", array('head_extra_fn' => "extra_head" ) );
?>
<div id="maincolumn">
<?php

    if( get_http_var( "action" ) ) {
        print "<pre>\n";
        print_r( $_POST );
        print "</pre>\n";
    }
    else {
        emit_form();
    }

?>
</div>
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
</div>

<?php
page_footer();






function emit_form()
{
?>

<h2>Welcome back, <em>Phil Notebook</em></h2>
<p>
<a href="#">1. Journalists you admire</a>&nbsp;&nbsp;&nbsp;&nbsp;
<a style="padding: 4px; border: 1px solid black;" href="#">2. Add to your Profile</a>&nbsp;&nbsp;&nbsp;&nbsp;
<a href="#">3. Tell us anything we've missed/got wrong</a>&nbsp;&nbsp;&nbsp;&nbsp;
</p>

<div id="accordion">

<div class="box">
<h3>Employment Information</h3>
<div class="box-content panel">
<form method="POST" action="">

<div class="field">
  <label for="job_title">Job Title</label> <input type="text" name="job_title" id="job_title" value="" />
</div>

<div class="field">
  <label for="current_employer">Current Employer</label> <input type="text" name="current_employer" id="current_employer" value="" />
  <label for="current_from">year from</label> <input type="text" name="current_from" id="current_from" size="4" value="" />
</div>

<div class="field" id="prev_emp_template">
  <label for="prev_emp_0">Previous Employer</label> <input type="text" name="prev_emp[]" id="prev_emp_0" value="" />
  <label for="prev_from_0">year from</label> <input type="text" name="prev_from[]" id="prev_from_0" size="4" value="" />
  <label for="prev_to_0">year to</label> <input type="text" name="prev_to[]" id="prev_to_0" size="4" value="" />
  <a href="#">remove</a>
</div>

<div class="field">
 <a href="#" id="add_employer">add a previous employer</a>
</div>

<div class="field">
  <button name="action" value="save">Save changes</button>
</div>

</form>
</div>  <!-- box-content -->
</div>  <!-- box -->

<!-- Education -->

<div class="box">
<h3>Education</h3>
<div class="box-content panel">
<form method="POST" action="">

<div class="field">
  <label for="school_0">School</label> <input type="text" name="school[]" id="school_0" value="" />
  <label for="school_from_0">year from</label> <input type="text" name="school_from[]" id="school_from_0" size="4" value="" />
  <label for="school_to_0">year to</label> <input type="text" name="school_to[]" id="school_to_0" size="4" value="" />
</div>

<div class="field">
  <label for="university_0">University</label> <input type="text" name="university[]" id="university_0" value="" />
  <label for="university_from_0">year from</label> <input type="text" name="university_from[]" id="university_from_0" size="4" value="" />
  <label for="university_to_0">year to</label> <input type="text" name="university_to[]" id="university_to_0" size="4" value="" />
</div>

<div class="field">
  <label for="degree_0">Degree (eg BA)</label> <input type="text" name="degree[]" id="degree_0" value="" />
</div>

<div class="field">
  <label for="subject_0">Subject Studied</label> <input type="text" name="subject[]" id="subject_0" value="" />
</div>



<div class="field">
  <label for="qual_0">Post graduate qualification (eg NTCJ)</label> <input type="text" name="qual[]" id="qual_0" value="" />
  <label for="qual_from_0">year from</label> <input type="text" name="qual_from[]" id="qual_from_0" size="4" value="" />
  <label for="qual_to_0">year to</label> <input type="text" name="qual_to[]" id="qual_to_0" size="4" value="" />
</div>

<div class="field">
  <label for="place_0">Place</label> <input type="text" name="place[]" id="place_0" value="" />
</div>

<div class="field">
  <label for="subject_0">Subject Studied</label> <input type="text" name="subject[]" id="subject_0" value="" />
</div>

<div class="field">
  <button name="action" value="save">Save changes</button>
</div>

</form>

</div>  <!-- box-content -->
</div>  <!-- box -->


<!-- Awards -->

<div class="box">
<h3>Awards</h3>
<div class="box-content panel">

<form method="POST" action="">

<div class="field">
  <label for="award_name_0">Award</label> <input type="text" name="award_name[]" id="award_name_0" value="" />
  <label for="award_year_0">year</label> <input type="text" name="award_year[]" id="award_year_0" size="4" value="" />
</div>

<div class="field">
 <a href="#" id="add_employer">add an award...</a>
</div>

<div class="field">
  <button name="action" value="save">Save changes</button>
</div>

</form>

</div>  <!-- box-content -->
</div>  <!-- box -->


<!-- Books -->

<div class="box">
<h3>Books</h3>
<div class="box-content panel">

<form method="POST" action="">

<div class="field">
  <label for="book_name_0">Book</label> <input type="text" name="book_name[]" id="book_name_0" value="" />
</div>

<div class="field">
 <a href="#" id="add_employer">add a book...</a>
</div>

<div class="field">
  <button name="action" value="save">Save changes</button>
</div>

</form>

</div>  <!-- box-content -->
</div>  <!-- box -->

</div>  <!-- end accordion -->
<?php
}




?>
