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

<script type="text/javascript">

    function unlockform() {
    };


    $(document).ready(
        function() {
            $(".employer input").autocomplete("ajax_employer_lookup.php", {
//              matchContains: true,
            });

            $('.employer .current').click( function() {
                var checked = $(this).attr( 'checked' )
                $( '#year_to' ).parent().parent().toggle( !checked );
                });

            // freeze the form
//            $('.employer input').attr("readOnly", true);
            $('.employer').addClass('locked');
            $('.employer input').attr("disabled", true);
            $('.employer button').hide();
            $('.employer .cancel').hide();

            $('.employer .unlock').click( function( ) {
                // thaw the form for editing
                var f = $(this).closest( 'form' );
                f.removeClass( 'locked' );
                f.find( 'input' ).removeAttr('disabled');
                f.find( 'button' ).show();
                f.find( '.cancel' ).show();
                $(this).hide();
                return false;
            });

            $('.employer .cancel').click( function( ) {
                // stop editing, freeze the form
                var f = $(this).closest( 'form' );
                f.find( 'input' ).attr('disabled', true );
                f.find( 'button' ).hide();
                f.find( '.unlock' ).show();
                f.addClass( 'locked' );
                f.reset();
                $(this).hide();
                return false;
            });

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

?>
<h2>Add Employment Information</h2>
<?php
        $employers = db_getAll( "SELECT * FROM journo_employment WHERE journo_id=? ORDER BY year_from DESC", $this->journo['id'] );
        $this->showEmployers( $employers );

?><h3>Add another:</h3><?php
            $this->showForm( NULL );
    }


    function showEmployers( &$employers)
    {
        foreach( $employers as $e ) {
            $this->showForm( $e );
        }

    }


    function showForm( $emp )
    {
        if( !$emp ) {
            $emp = array( 'employer'=>'', 'job_title'=>'', 'year_from'=>'', 'year_to'=>'', 'id'=>NULL );
        }

        $id = $emp['id'] ? $emp['id'] : '';
?>

<form class="employer" method="POST" action="/profile_employment">
<table border="0">
 <tr><th><label for="employer_<?= $id; ?>">Employer</label></td><td><input type="text" size="60" name="employer" id="employer<?= $id; ?>" value="<?= h($emp['employer']); ?>"/></td></tr>
 <tr><th><label for="job_title<?= $id; ?>">Job Title</label></td><td><input type="text" size="60" name="job_title" id="job_title<?= $id; ?>" value="<?= h($emp['job_title']); ?>"/></td></tr>
 <tr><th><label for="year_from<?= $id; ?>">Year from</label></td><td><input type="text" size="4" name="year_from" id="year_from<?= $id; ?>" value="<?= h($emp['year_from']); ?>"/></td></tr>
 <tr><th><label for="year_to<?= $id; ?>">Year to</label></td><td><input type="text" size="4" name="year_to" id="year_to<?= $id; ?>" value="<?= h($emp['year_to']); ?>"/></td></tr>
 <tr><th></th><td><input type="checkbox" name="current" id="current<?= $id; ?>"/><label for="current<?= $id; ?>">I currently work here</label></td></tr>
</table>
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<input type="hidden" name="id" value="<?= $id; ?>" />
<button type="submit" name="action" value="submit">Save</button>
<button type="reset" class="cancel">Cancel</button>
<a class="unlock" href="">edit</a>
<a href="/profile_employment?ref=<?= $this->journo['ref']; ?>&remove_id=<?= $id; ?>">remove</a>
</form>
<?php

    }



    function handleSubmit()
    {
        $b = array(
            'employer' => get_http_var('employer'),
            'job_title' => get_http_var('job_title'),
            'year_from' => intval( get_http_var('year_from') ),
            'year_to' => intval( get_http_var('year_to') ),
            'id'=> get_http_var('id') );

        if( $b['id'] ) {
            $sql = "UPDATE journo_employment SET journo_id=?,employer=?,job_title=?,year_from=?,year_to=? WHERE id=?";
            db_do( $sql, $this->journo['id'], $b['employer'], $b['job_title'], $b['year_from'], $b['year_to'], $b['id'] );
        } else {
            $sql = "INSERT INTO journo_employment (journo_id,employer,job_title,year_from,year_to) VALUES (?,?,?,?,?)";
            db_do( $sql, $this->journo['id'], $b['employer'], $b['job_title'], $b['year_from'], $b['year_to'] );
        }
        db_commit();
    }


    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_employment WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }
}





$page = new EmploymentPage();
$page->display();


