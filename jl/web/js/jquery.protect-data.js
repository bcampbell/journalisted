/**
 * Version: 0.1
 * License: MIT - see License.txt
 * Examples & Help ->
 *
 *
 */
var x = function($){//jQuery wrap

$.extend($, {
  protectData: {
    forms: [],
    unsavedChanges: false,
    protectAjax: false,
    ajaxUnportectNTimes: 0,

    /**
     * Change this to get notified of state changes
     *
     * (status can be read from $.protectData.unsavedChanges too)
     * @param boolean isProtected
     */
    stateChangeCallback: function(isProtected){},

    /**
     * first start
     */
    initialize: function(){
      //only once...
      if(this.running)return;
      this.running = true;

      var self = this;
      //wrap ajaxSubmit, since ajaxSubmit does not trigger submit
      if($.fn.ajaxSubmit) {
        var oldAjaxSubmit = $.fn.ajaxSubmit;
        $.fn.ajaxSubmit = function(e){
          self.unprotect();
          oldAjaxSubmit.call($.fn, e);
        }
      }
      //watch for ajax requests
      $().ajaxSend(function(e, r, s){
          self.ajaxCallback.call(self, e, r, s)
      });
    },

    /**
     * for testing purpose, resets everything to start
     */
    reset: function(){
      this.ajaxUnportectNTimes = 0;
      this.unsavedChanges = false;
      this.ajaxProtect = false;
      this.stateChangeCallback = function(isProtected){};
      window.onbeforeunload = null;

      //forms
      for (var key in this.forms) {
				if($.browser.msie){
					$(':input',this.forms[key].form).unbind('.protectData');
				}
        this.forms[key].form.unbind('.protectData');
      }
      this.forms = [];
    },

    /**
     * used for resetting purpose only
     */
    forms: [],

    message: "Leaving this page means loosing all unsaved changes!\n" +
    "OK -> Loose changes                            Cancel -> Stay\n",

    /**
     * call this if there are unsaved changes
     */
    protect: function(){
      //has to change state ?
      if (this.unsavedChanges) return;//nothing new...
      this.stateChangeCallback(true);

      //adjust internat state
      this.unsavedChanges = true;
			var self=this;
      window.onbeforeunload = function(){
        return $.protectData.message;//is there a better way ?
      }
    },

    /**
     * call this if you saved the formdata
     * (aka nothing can be lost if the user leaves)
     */
    unprotect: function(){
      //has to change state ?
      if (!this.unsavedChanges) return;//nothing new...
      this.stateChangeCallback(false);

      //adjust internat state
      this.unsavedChanges = false;
      window.onbeforeunload = null;
    },

    /**
     * let 'times' requests pass without alerting the user
     * @param int times
     */
    unprotectAjax: function(times){
      if(times===0||times==='0')return;//nothing to do..
      if(times===undefined)times=1;//called without parameter
      times = parseInt(times, 10);
      this.ajaxUnportectNTimes += times;
    },

    /**
     * is called before a request is made
     * @param {Object} event
     * @param {Object} request
     * @param Hash settings
     */
    ajaxCallback: function(event, request, settings){
      if (!this.protectAjax)return;
      if (!this.unsavedChanges)return;
      if (this.ajaxUnportectNTimes) {
        this.ajaxUnportectNTimes--;
        return;
      }
      //ask the user to 'loose changes' or 'abort'
      if (confirm(this.message))this.unprotect();
      else request.abort();
    },

    /**
     * container class form protected form
     * @param form - jQuery object or selector for 1 form
     */
    ProtectedForm: function(form){
      this.form = $(form);
      $.protectData.forms.push(this);

			if($.browser.msie){
				//ie sucks btw...
				$(':input',form).bind('change.protectData',function(){
          $.protectData.protect();
        });
			} else {
	      this.form.bind('change.protectData',function(){
	        $.protectData.protect();
	      });
			}

      this.form.bind('submit.protectData',function(){
        $.protectData.unprotect();
      });
    }
  }
});


$.extend($.fn, {
  protectData: function(){
    $.protectData.initialize();

    $(this).each(function(){
      if ($(this).is('form')) {
        new $.protectData.ProtectedForm(this);
      }
    });
  }
});

}(jQuery);