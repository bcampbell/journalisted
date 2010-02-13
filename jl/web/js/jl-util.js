/* utility fns for journalisted */

var jl = function() {
    var fieldId = 0;
    var formFields = "input, checkbox, select, textarea";

    var public = {

        /* make elements and labels uniq */
        /* based on fn from jquery-dynamic-form */
        normalizeElement: function(elmnt) {
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
        },
    };

    return public;
}();

