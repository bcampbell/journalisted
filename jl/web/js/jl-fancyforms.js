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


function fancyForms( formClass, options ) {

    var fieldId = 0;
    var formFields = "input, checkbox, select, textarea";

    settings = jQuery.extend({
      extraSetupFn: function() {},
      plusLabel: "AND ANOTHER THING!!!",
    }, options);


    function initForm() {
        var f = $(this);
//        f.find('.edit').hide();
        f.find('.submit').hide();
//        f.find( '.cancel' ).hide();
        f.find(formFields).each(function(){ $(this).change( function() {
            f.find('.submit').show();
//            f.find( '.cancel' ).show();
            f.addClass('modified');
            } )
         } );

        f.append( '<input type="hidden" name="ajax" value="1" />'); // so server knows it's ajax
        f.append( '<span class="ajax-msg"></span>');    // add a place to plonk messages

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

                if( result.success ) {
                    // if a creator form, turn it into a full editing form
                    if( f.hasClass("creator") ) {
                        f.removeClass("creator");
/*
                        f.append( ' ' + result.editlinks_html );
                        ajaxifyEditLinks( f );
*/
                        f.append( '<input type="hidden" name="id" value="' + result.id + '" />' );
                    }

                    f.find( '.submit' ).hide();
//                    f.find( '.cancel' ).hide();
                    f.removeClass('modified');
/*                    freezeForm(f); */
                } else {
                    /* show an app-level error */
                    f.find('.ajax-msg').html( result.errmsg );
                    f.find('button').removeAttr("disabled");
                }
            },
            error: function(result,textStatus) {
                /* a low-level (timeout, http, json syntax, whatever) error */
                if( textStatus == 'timeout' ) {
                    f.find('.ajax-msg').html( "Timed out." );
                } else {
                    f.find('.ajax-msg').html( "Failed." );
                }
                f.find('button').removeAttr("disabled");

                /* this gets called on IE in _addition_ to success callback... ??? */
            }
        } );

        settings.extraSetupFn.apply( f );
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
    $( formClass + ".template").hide().after( '<a href="" class="plus">' + settings.plusLabel + '</a>' );
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


