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

    // TODO: use compressed jquery.autocompete
?>
<!--    <link type="text/css" href="http://jqueryui.com/themes/base/ui.all.css" rel="stylesheet" /> -->
    <link type="text/css" rel="stylesheet" href="/profile.css" /> 
    <link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
	<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="/js/jquery.protect-data.min.js"></script>
    <script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
    <script type="text/javascript" src="/js/jquery-dynamic-form.js"></script>

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

            $(".admired-journo").autocomplete("ajax_journo_lookup.php", {
//                width: 300,
//        		multiple: true,
//        		matchContains: true,
                formatItem: function(row) { return row[0] + " (<em>" + row[1] + "</em>)"; },

//        		formatItem: function( formatItem,
//        		formatResult: formatResult
        	});

            $("#admired-journo").dynamicForm( '#admired-journo-plus', '#admired-journo-minus', {limit:10} );
            

            $("#awards").dynamicForm( '#awards-plus', '#awards-minus', {limit:10} );
            $("#books").dynamicForm( '#books-plus', '#books-minus', {limit:10} );
            $("#websites").dynamicForm( '#websites-plus', '#websites-minus', {limit:10} );
            $("#microblogs").dynamicForm( '#microblogs-plus', '#microblogs-minus', {limit:10} );
            $("#otherorgs").dynamicForm( '#otherorgs-plus', '#otherorgs-minus', {limit:10} );
            $("#education").dynamicForm( '#education-plus', '#education-minus', {limit:10} );
//            $(".admired-journo").result(function(event, data, formatted) {
//        		var hidden = $(this).parent().next().find(">:input");
//        		hidden.val( (hidden.val() ? hidden.val() + ";" : hidden.val()) + data[1]);
//        	});

        } );
	</script>

<?php
}

page_header( "journo profile", array('head_extra_fn' => "extra_head" ) );

$page = get_http_var('page','admired' );
?>
<h2>Welcome, <em>Phil Notebook</em></h2>

<div class="pipeline">
 <span class="<?=$page=='admired'?'active':'';?>">1. <a href="/journo_test3?page=admired">Journalists you admire</a></span>
 <span class="<?=$page=='profile'?'active':'';?>">2. <a href="/journo_test3?page=profile">Add to your profile</a></span>
 <span class="<?=$page=='missing'?'active':'';?>">3. <a href="/journo_test3?page=missing">Tell us anything we've missed/got wrong</a></span>
</div>

<?php
    if( $page == 'admired' )
        emit_admired_journos_page();
    if( $page == 'missing' )
        emit_missinginfo_page();
    elseif( $page == 'profile' ) {
        if( get_http_var( "action" ) ) {
            print "<pre>\n";
            print_r( $_POST );
            print "</pre>\n";
        } else {
            emit_profile();
    }
}

/*
 ?>

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
*/

page_footer();





/********************************************************/

function emit_profile()
{
    $current_tab = get_http_var('tab', 'employment');

    $tabs = array(
        'employment'=>'Employment',
        'education'=>'Education',
        'awards'=>'Awards',
        'books'=>'Books',
    );


?>
<ul class="tabs">
<?php foreach( $tabs as $tab=>$title ) { ?>
<?php  if($tab==$current_tab) { ?>
<li class="current"><a href="/journo_test3?page=profile&tab=<?php echo $tab; ?>"><?php echo $title; ?></a></li>
<?php  } else{ ?>
<li><a href="/journo_test3?page=profile&tab=<?php echo $tab; ?>"><?php echo $title; ?></a></li>
<?php  } ?>
<?php } ?>
</ul>

<?php


    switch( $current_tab ) {
        case "employment": emit_employment_tab(); break;
        case "education": emit_education_tab(); break;
        case "awards": emit_awards_tab(); break;
        case "books": emit_books_tab(); break;
        }

}






function emit_admired_journos_page()
{

?>
<?php

    $action = get_http_var( "action" );
    $admired = array();
    if( $action ) {
        foreach( get_http_var( 'admired_name' ) as $foo ) {
            if( $foo )
                $admired[] = "<a href=\"\">$foo</a> <em>(The daily wotsit)</em>";
        }
        $admired[] = "<a href=\"/polly-filler\">Polly Filler</a> <em>(The Daily Gnome)</em>";
        $admired[] = "George Orwell";
    }

?>

<?php if( $admired ) { ?>
<h2>Thanks for telling us which journalists do you most admire</h2>
<ul>
<?php foreach( $admired as $a ) { ?> <li><?=$a;?> <small><a href="">[remove]</a></small></li><?php } ?>
</ul>
<p>You can add more if you'd like:</p>
<?php } else { ?>
<h2>Which journalists do you most admire?</h2>
<?php } ?>



<form method="POST" action="">
<table border="0">
<tbody>
<tr id="admired-journo">
 <td>
  <label for="admired_name">Journalist's name</label>
 </td>
 <td>
  <input class="admired-journo" type="text" name="admired_name[]" id="admired_name" value="" />
  <a id="admired-journo-minus" href="">[-]</a>
  <a id="admired-journo-plus" href="">[+]</a>
 </td>
</tr>
</tbody>
</table>
<button name="action" value="save">Submit</button>
</form>
<?php


    // if they've entered any journos already, encourage them to add more to their profile */
    if( $admired ) {
?>
<div class="donate-box">
 <div class="donate-box_top"><div></div></div>
  <div class="donate-box_content exhort">

    Would you like to add to your profile page on Journalisted?<br/>
<a href="/journo_test3?page=profile">Add to my profile</a><br/>
<a href="">View my Journalisted page</a><br/>

  </div>
 <div class="donate-box_bottom"><div></div></div>
</div>
<?php
    }

}

function emit_education_tab()
{
?>
<h3>Tell us about your education</h3>


<form method="POST" action="">
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
<a id="education-minus" href="">[-]</a>
<a id="education-plus" href="">[+]</a>
</fieldset>
<button name="action" value="submit_education">Submit</button>
</form>

<?php
}

function emit_awards_tab()
{
?>
<h3>Have you won any awards?</h3>

<form method="POST" action="">

<div id="awards">
<label for="award_name">Award</label> <input type="text" size="40" name="award_name[]" id="award_name" />
<label for="award_year">Year</label> <input type="text" size="4" name="award_year[]" id="award_year" />
<a id="awards-minus" href="">[-]</a>
<a id="awards-plus" href="">[+]</a>
</div>
<button name="action" value="submit_awards">Submit</button>
</form>

<?php
}

function emit_books_tab()
{
?>
<h3>Have you published any books?</h3>

<form method="POST" action="">

<div id="books">
 <label for="book_name">Title</label> <input type="text" name="book_name[]" id="book_name" size="60" />
 <a id="books-minus" href="">[-]</a>
 <a id="books-plus" href="">[+]</a>
</div>
<button name="action" value="submit_books">Submit</button>
</form>

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



function emit_missinginfo_page()
{
?>
<form method="POST" action="">
<fieldset>
<legend>I have a personal Website/blog at:</legend>
<div id="websites">
 <label for="website">Website / blog URL</label> <input type="text" size="40" id="website" name="website[]" />
 <a id="websites-minus" href="">[-]</a>
 <a id="websites-plus" href="">[+]</a>
</div>
</fieldset>

<fieldset>
<legend>I micro-blog (e.g. Twitter) at:</legend>
<div id="microblogs">
 <label for="microblog">URL</label> <input type="text" size="40" id="microblog" name="microblog[]" />
 <a id="microblogs-minus" href="">[-]</a>
 <a id="microblogs-plus" href="">[+]</a>
</div>
</fieldset>

<fieldset>
<legend>I also write for</legend>
<div id="otherorgs">
 <label for="otherorg">Organisation</label> <input type="text" size="40" id="otherorg" name="otherorg[]" />
 <a id="otherorgs-minus" href="">[-]</a>
 <a id="otherorgs-plus" href="">[+]</a>
</div>
</fieldset>

<button name="action" value="save">Submit</button>

</form>


<?php
}


