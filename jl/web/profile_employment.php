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
<script type="text/javascript" src="/js/jquery.form.js"></script>

<script type="text/javascript">


    $(document).ready(
        function() {

	var fieldId = 0;
	var formFields = "input, checkbox, select, textarea";


            function initForm() {
                var f = $(this);

                if( f.hasClass('existing') ) {
                    // add "edit" link
                    f.append( '<a class="thaw" href="">edit</a>')

                    f.find('.thaw').click( function() { var f = $(this).closest('form'); thawForm(f); return false; } );
                    f.find('.cancel').click( function() { var f = $(this).closest('form'); f.resetForm(); freezeForm(f); return false; } );
                    freezeForm(f);
                } else {
                }

                /* set up for ajax submission of form to avoid page reload */
                f.ajaxForm();
                f.append( '<input type="hidden" name="ajax" value="1" />');
            }

            function freezeForm(f) {
                f.addClass('frozen');
                f.find('input').attr("disabled", true);
                f.find('button').hide();
                f.find('.cancel').hide();
                f.find('.remove').show();
                f.find('.thaw').show();
            }

            function thawForm(f) {
                f.removeClass('frozen');
                f.find('input').removeAttr("disabled");
                f.find('button').show();
                f.find('.cancel').show();
                f.find('.thaw').hide();
                f.find('.remove').hide();
            }

    /* based on fn from jquery-dynamic-form */	
    function normalizeElmnt(elmnt){
        elmnt.find(formFields).each(function(){
            var nameAttr = jQuery(this).attr("name"), 
			idAttr = jQuery(this).attr("id");

            /* Normalize field id attributes */
            if (idAttr) {
				/* Normalize attached label */
				jQuery("label[for='"+idAttr+"']").each(function(){
					jQuery(this).attr("for", idAttr + fieldId);
				});
				
                jQuery(this).attr("id", idAttr + fieldId);
            }
            fieldId++;
        });
    };

            $(".employer").each( initForm );

            $(".employer.new").hide().after( '<a href="" class="plus">[+] Add new</a>' );

            // TODO: just use clone() and make up a uniqify-id fn.
            //$(".employer.new").dynamicForm( '.plus', '.minus', { postPlusFn: function() { $(this).show().initForm() } } );
            $(".plus").click( function() {
                alert("bing");
                var f = $(".employer.new:first");
                var c = f.clone();
                normalizeElmnt(c);
                c.show();
                c.each( initForm );
                c.insertBefore( this );
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

        $this->showForm( NULL );
?>
<?php
    }



    function ajax()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }
    }



    function showEmployers( &$employers)
    {
        foreach( $employers as $e ) {
            $this->showForm( $e );
        }

    }


    /* if $emp is null, then display a fresh form for entering a new entry */
    function showForm( $emp )
    {
        static $uniqID=0;

        $uniq = "_{$uniqID}";
        $uniqID++;

        $classes = 'employer';
        if( is_null( $emp ) ) {
            $emp = array( 'employer'=>'', 'job_title'=>'', 'year_from'=>'', 'year_to'=>'' );
            $classes .= " new";
        } else {
            $classes .= " existing";
        }



?>

<form class="<?= $classes; ?>" method="POST" action="/profile_employment">
<table border="0">
 <tr><th><label for="employer<?= $uniq; ?>">Employer</label></td><td><input type="text" size="60" name="employer" id="employer<?= $uniq; ?>" value="<?= h($emp['employer']); ?>"/></td></tr>
 <tr><th><label for="job_title<?= $uniq; ?>">Job Title</label></td><td><input type="text" size="60" name="job_title" id="job_title<?= $uniq; ?>" value="<?= h($emp['job_title']); ?>"/></td></tr>
 <tr><th><label for="year_from<?= $uniq; ?>">Year from</label></td><td><input type="text" size="4" name="year_from" id="year_from<?= $uniq; ?>" value="<?= h($emp['year_from']); ?>"/></td></tr>
 <tr><th><label for="year_to<?= $uniq; ?>">Year to</label></td><td><input type="text" size="4" name="year_to" id="year_to<?= $uniq; ?>" value="<?= h($emp['year_to']); ?>"/></td></tr>
 <tr><th></th><td><input type="checkbox" name="current" id="current<?= $uniq; ?>"/><label for="current<?= $uniq; ?>">I currently work here</label></td></tr>
</table>
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<?php
        if( array_key_exists( 'id', $emp ) ) {
?>
<input type="hidden" name="id" value="<?= $emp['id']; ?>" />
<button class="submit" type="submit" name="action" value="submit">Update</button>
<button class="cancel" type="reset">cancel</button>
<a class="remove" href="/profile_employment?ref=<?= $this->journo['ref']; ?>&remove_id=<?= $emp['id']; ?>">remove</a>
<?php
        } else {
?>
<button type="submit" name="action" value="submit">Save</button>
<?php
        }
?>
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
$page->run();


