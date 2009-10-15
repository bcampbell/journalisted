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

                function addEditButton( f ) {
                    f.append( '<a class="edit" href="">edit</a>')
                    f.find('.edit').click( function() {
                        var f = $(this).closest('form');
                        thawForm(f);
                        return false;
                    });
                }

                function ajaxifyRemoveLink( a ) {
                    a.click( function() {
                        $.ajax( { url: $(this).attr('href'),
                            success: function() {
                                var f=a.closest("form");
                                f.css("background-color","#ffcccc")
                                f.fadeOut(500, function() { $(this).remove(); });
                            },
                        } );
                        return false;
                    });

                }


                f.append( '<input type="hidden" name="ajax" value="1" />'); // so server know's it's ajax
                f.append( '<span class="ajax-msg"></span>');    // add a place to plonk messages

                /* add some extra elements, but only if it's an editing form rather than a creation form */
                if( !f.hasClass( 'creator' )) {
                    addEditButton( f );
                    ajaxifyRemoveLink( f.find('.remove') );
                    freezeForm(f);
                }

                f.find('.cancel').click( function() {
                    var f = $(this).closest('form');
                    if( f.hasClass( 'creator' ) ) {
                        f.fadeOut(500, function() { $(this).remove(); });
                    } else {
                        /* go back to read-only */
                        f.resetForm();
                        freezeForm(f);
                    }
                    return false;
                });


                /* set up for ajax submission of form to avoid page reload */
                f.ajaxForm( {
                    dataType: "json",
                    beforeSend: function() {
                        f.find('button').attr("disabled", true);
                        f.find('.ajax-msg').html( '<img src="/css/indicator.gif" /><em>working...</em>' );
                    },
                    success: function(result) {
                        f.find('button').removeAttr("disabled");
                        f.find('.ajax-msg').html( '' );

                        if( result.status=='success' ) {
                            // if a creator form, turn it into a full editing form
                            if( f.hasClass("creator") ) {
                                f.removeClass("creator");
                                f.append( ' ' + result.remove_link_html );
                                ajaxifyRemoveLink( f.find('.remove') );
                                addEditButton(f);
                                f.append( '<input type="hidden" name="id" value="' + result.id + '" />' );
                                // TODO: add remove button
                            }

                            freezeForm(f);
                        }
                    },
                    error: function() {
                        f.find('button').removeAttr("disabled");
                        // hmm... could show an error message... but... well...
                        f.find('.ajax-msg').html( '' );
                        freezeForm(f);
                    },
                } );
            }

            function freezeForm(f) {
                f.addClass('frozen');
                f.find('input').attr("disabled", true);
                f.find('button').hide();
                f.find('.cancel').hide();
                f.find('.remove').show();
                f.find('.edit').show();
            }

            function thawForm(f) {
                f.removeClass('frozen');
                f.find('input').removeAttr("disabled");
                f.find('button').show();
                f.find('.cancel').show();
                f.find('.edit').hide();
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

            /* hide the new-entry template form, add the "Add new" link */
            /* (could use jquery-dynamic-form plugin but it turns field names into arrays [], which
               we don't want in this case) */
            $(".employer.template").hide().after( '<a href="" class="plus">[+] Add new</a>' );
            $(".plus").click( function() {
                /* add a creator form by cloning the template */
                var f = $(".employer.template:first");
                var c = f.clone();
                normalizeElmnt(c);
                c.removeClass('template');
                c.addClass('creator');
                c.insertBefore( this );

                c.each( initForm );
                c.fadeIn();
                return false;
            });

            /* set up fanciness on all forms except the hidden template */
            $(".employer").not('.template').each( initForm );

    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
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
//        header( "Cache-Control: no-cache" );
//        header( "HTTP/1.0 500 Internal Server Error" );
//        return;

        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();


            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'remove_link_html'=>"<a class=\"remove\" href=\"/profile_employment?ref={$this->journo['ref']}&remove_id={$entry_id}\">remove</a>",
            );
            print json_encode( $result );
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

        /* the way the template form is used depends on if javascript is in use:
         * javascript on: template is hidden, and cloned to add new entries
         * javascript off: template is used to submit a new entry
         */

        $is_template = is_null( $emp );

        $uniq = "_{$uniqID}";
        $uniqID++;

        $classes = 'employer';
        if( $is_template ) {
            /* a dummy, blank entry */
            $emp = array( 'employer'=>'', 'job_title'=>'', 'year_from'=>'', 'year_to'=>'' );
            $classes .= " template";
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
<?php if( !$is_template ) { ?>
<input type="hidden" name="id" value="<?= $emp['id']; ?>" />
<a class="remove" href="/profile_employment?ref=<?= $this->journo['ref']; ?>&remove_id=<?= $emp['id']; ?>">remove</a>
<?php } ?>
<button class="submit" type="submit" name="action" value="submit">Save</button>
<button class="cancel" type="reset">Cancel</button>
</form>
<?php

    }


    // returns id of entry (either new or existing)
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
            $b['id'] = db_getOne( "SELECT lastval()" );
        }
        db_commit();

        return $b['id'];
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


