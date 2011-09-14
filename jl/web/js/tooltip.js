(function( $ ){
  $.fn.toptip = function(options) {

    var settings = {
      'content'  : 'text goes here :-)',
      'fetch' : function(trigger,tip) {},
    };

    return this.each(function() {
      if ( options ) {
        $.extend( settings, options );
      }
      var n = $(this);
      var pos = n.offset();
      var tip = $('<div class="tooltip"></div>').css( {
          position: 'absolute',
          top: pos.top - 10,
          left: pos.left + n.outerWidth(),
          display: 'none' }).appendTo( "body" );

      var hover1 = false;
      var hover2 = false;
      var started = false;

      n.hover(
          function() { hover1=true; check(); },
          function() { hover1=false; check(); } );
      tip.hover( 
          function() { hover2=true; check(); },
          function() { hover2=false; check(); } );

      var check = function() {
          if( !hover1 && !hover2 ) {
              tip.hide();
          } else {
              if(!started) {
                tip.html(settings.content);
                started=true;
                if(settings.fetch) {
                  settings.fetch(n,tip);
                }
              }
              tip.show();
          }
      };
    });


  };
}
)( jQuery );

