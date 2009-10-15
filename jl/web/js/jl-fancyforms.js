/*
 * ajaxified form system designed for editing a list of records:
 *
 * requires jquery and jquery.forms plugin
 *
 * Each record is a separate form.
 * Record forms are read-only until the edit button is pressed.
 * Each form has a "delete" button.
 * There is an "add new" button to add a new, "creator" form.
 * There is a hidden "template" form which is cloned to create the new record form.
 * Once a new record has been submitted to the server, it's form is upgraded from
 *  a "creator" type to a full editing form.
 *
 * System should work when javascript is off:
 *  - all the forms are editable by default
 *  - the template form is displayed, and can be used to enter 
 *
 * General principles:
 *  - the default html is set up to assume javascript is off
 *      (ie forms are editable, template is visible etc)
 *  - the fancy ajax bits ("add new" button, "edit" buttons, ajax form submission
 *      etc) are set up via javascript
 *
 */

function fancyForms( formClass, extraSetupFn ) {

    var fieldId = 0;
    var formFields = "input, checkbox, select, textarea";


    /* ajaxify a form */
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
                f.css("background-color","#ffcccc")
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

        if (typeof extraSetupFn != 'undefined') {
            extraSetupFn.apply( f );
        }
    }

    function freezeForm(f) {
        f.addClass('frozen');
//        f.find('input').attr("disabled", true);
        f.find('input').attr("readOnly", true); /* note CamelCase, needed for IE */
        f.find(':checkbox').attr("disabled", true); /* readonly doesn't stop checkbox twiddling */
        f.find('button').hide();
        f.find('.cancel').hide();
        f.find('.remove').hide();
        f.find('.edit').show();
    }

    function thawForm(f) {
        f.removeClass('frozen');
//        f.find('input').removeAttr("disabled");
        f.find('input').removeAttr("readOnly");
        f.find(':checkbox').removeAttr("disabled" );
        f.find('button').show();
        f.find('.cancel').show();
        f.find('.edit').hide();
        f.find('.remove').show();
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
    $( formClass + ".template").hide().after( '<a href="" class="plus">Add one</a>' );
    $(".plus").click( function() {
        /* add a creator form by cloning the template */
        var f = $(formClass + ".template:first");
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
    $( formClass ).not('.template').each( initForm );
}


