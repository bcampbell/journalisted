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


        chart: chart,
        readTable: readTable
    };


    function readTable(t) {

        $( 'thead tr th',t ).each( function() {
        });

        $( 'tbody tr',t ).each( function() {
            var cells = $(this).find( 'td,th' );
            var foo = [];
            for( var i=0; i<cells.length; ++i ) {
                foo[foo.length] = $(cells[i]).text();
            }
        } );

    }



// basic chart-plotting library, uses jquery and raphael

// TODO:
// tidy up and generalise as a standalone component
// support multiple series
// support non-linear axis scales
// rename ticksize and step options
// rename gutter options
// add auto-calculation of label sizes
function chart( placeholder, series_, opts_ ) {
    var R = Raphael( placeholder );
    var series = series_;
    var opts =  {
        xaxis: {
            label: "X Axis",
            min: null,      // extent of axes, in data units
            max: null,
            pad: [0,0],     // [left,right] - expands the extent
            step: null,     // tick step interval: a number, "month" or null
            gutter: 40,      // space left for labels, etc...
            ticksize: 5,    // size of interval markers
            tickText: defaultTickFmt,
            tickDecimals: null, // max num of decimal places on tick steps
            which: 'bottom'
        },
        yaxis: { label: "Y Axis",
            extent: null,
            min: 0,
            max: null,
            step: null,
            gutter: 40,
            ticksize: 5,
            tickText: defaultTickFmt,
            tickDecimals: null,
            which: 'left'
        },
        monthNames: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"]
    }

    // the area inside the axis (shrink to fit labels etc)
    var plotarea = null;

    var myplot = this;


    // default behaviour for generating text for tick markers
    function defaultTickFmt( axis, t ) {
        if( axis.step=='month' ) {
            var d = new Date(t);
            var m = d.getUTCMonth();
            if( m==0 )
                return opts.monthNames[ m ] + "\n" + d.getUTCFullYear();
            else
                return opts.monthNames[ m ] + "\n.";
        } else {
            return t.toString();
        }
    }

    function initData() {

        function calcExtents( axis, val ) {
            if( axis.min === null ) {
                axis.min = Number.POSITIVE_INFINITY;
                $.each( series.data, function() { axis.min=Math.min(val(this),axis.min); } );
            }
            axis.min -= axis.pad[0];

            if( axis.max === null ) {
                axis.max = Number.NEGATIVE_INFINITY;
                $.each( series.data, function() { axis.max=Math.max(val(this),axis.max); } );
            }
            axis.max += axis.pad[1];
        }

        function calcStepInterval( axis ) {
            if( axis.step !== null ) {
                return;
            }

            // tick interval algorithm pasted from flot
            var noTicks;
            if( axis.which == 'bottom' ) {
                noTicks = 0.3 * Math.sqrt(plotarea.w);
            }
            if( axis.which == 'left' ) {
                noTicks = 0.3 * Math.sqrt(plotarea.h);
            }
            var delta = (axis.max - axis.min) / noTicks;

            // pretty rounding of base-10 numbers
            var maxDec = axis.tickDecimals;
            var dec = -Math.floor(Math.log(delta) / Math.LN10);
            if (maxDec != null && dec > maxDec)
                dec = maxDec;

            var magn = Math.pow(10, -dec);
            var norm = delta / magn; // norm is between 1.0 and 10.0

            if (norm < 1.5)
                size = 1;
            else if (norm < 3) {
                size = 2;
                // special case for 2.5, requires an extra decimal
                if (norm > 2.25 && (maxDec == null || dec + 1 <= maxDec)) {
                    size = 2.5;
                    ++dec;
                }
            }
            else if (norm < 7.5)
                size = 5;
            else
                size = 10;

            size *= magn;

//            if (axisOptions.minTickSize != null && size < axisOptions.minTickSize)
//                size = axisOptions.minTickSize;

//            if (axisOptions.tickSize != null)
//                size = axisOptions.tickSize;

            //axis.tickDecimals = Math.max(0, (maxDec != null) ? maxDec : dec);
            axis.step = size;

        }

        calcExtents( opts.xaxis, function(d) { return d.x; } );
        calcExtents( opts.yaxis, function(d) { return d.y; } );

        // set up the plotarea
        plotarea = {
            x: opts.yaxis.gutter,
            y: 0,
            w: R.width - opts.yaxis.gutter,
            h: R.height - opts.xaxis.gutter };


        calcStepInterval( opts.xaxis );
        calcStepInterval( opts.yaxis );

    }


    function genticks( axis, tickfn ) {
        if( axis.step == "month" ) {
            // work out starting year/month, round up to nearest month boundary.
            // (-1 ms to handle border case of starting _exactly_ at month boundary)
            dstart = new Date( axis.min-1 );
            year = dstart.getUTCFullYear();
            month = dstart.getUTCMonth();
            if( ++month > 11 )      // round up to nearest month
                { month=0; ++year; }

            for( var t = (new Date( Date.UTC(year,month,1,0,0,0) )).getTime();
                t <= axis.max;
                t = (new Date( Date.UTC(year,month,1,0,0,0) )).getTime() )
            {
                tickfn(t)
                if( ++month > 11 )
                    { month=0; ++year; }
            }
        } else {
            // assume numeric
            for( var t=axis.step * Math.ceil( axis.min/axis.step );
                t<=axis.max;
                t+=axis.step ) {
                tickfn(t);
            }
        }
    }

    // helper to create text object, with given anchor point (eg "top-left") on x,y
    function text2( x, y, msg, anchor, attrs ) {
        var t = R.text( 0,0,msg );
        // apply attrs first, as they may affect bbox
        if( typeof(attrs) != 'undefined' ) 
            t.attr(attrs);
        var b = t.getBBox();
        bits = anchor.split("-");
        for( var i in bits ) {
            where = bits[i];
            if( where=='left' )   { x -= b.x; }
            if( where=='right' )  { x -= (b.x+b.width); }
            if( where=='top' )    { y -= b.y; }
            if( where=='bottom' ) { y -= (b.y+b.height); }
        }
        t.translate(x,y);
        return t;
    }

    // helper to render a single line
    function line( from, to ) {
        return R.path( "M" + from[0] + " " + from[1] +
            "L" + to[0] + " " + to[1] );
    }


    function renderAxis( axis_ ) {
        var pa = plotarea;
        var label = null;
        var axis=axis_;

        var axisstyle = { stroke: '#666' };
        var gridstyle = { stroke: '#ccc' };

        if( axis.which == 'left' ) {
            // normal vertical Y axis on left
            var x=pa.x;
            //line( [x, pa.y], [x, pa.y+pa.h] ).attr( axisstyle );


            // ticks
            genticks( axis, function(t) {
                var y=mapy(t);
                // grid
                line( [pa.x,y],[pa.x+pa.w,y] ).attr(gridstyle);
                // tick + text
                //line( [x-axis.ticksize, y ], [x,y ] ).attr( axisstyle );
                var tickfudge = 0;
                txt = axis.tickText(axis, t);
                R.text( x-(axis.ticksize+tickfudge),y,txt ).attr( { 'text-anchor': 'end', fill: '#666'} );
            });

            // label
            if( axis.label ) {
                var labeloffset = 32;
                label = R.text( pa.x-labeloffset, pa.y+pa.h/2, axis.label );
                label.attr("font-size",15);
                label.rotate( -90 );
            }
        }

        if( axis.which == 'bottom' ) {
            var y=pa.y+pa.h;
            // normal horizontal X axis at bottom
            line( [pa.x, y], [pa.x+pa.w, y] ).attr( axisstyle );

            // ticks
            genticks( axis, function(t) {
                var x=mapx(t);
                // grid
                line( [x,pa.y],[x,pa.y+pa.h] ).attr( gridstyle );
                // tick + text
                line( [x,y], [x,y+axis.ticksize] ).attr( axisstyle );
                text2( x,y+axis.ticksize,
                    axis.tickText(axis, t),
                    'top',
                    { 'text-anchor':'start', 'font-size':12, fill: '#666'} );
            });

            // label
            if( axis.label ) {
                var labeloffset = 24;
                label = R.text( pa.x+pa.w/2, y+labeloffset, axis.label );
                label.attr("font-size",15);
            }

        }
    }




    // map x coord of a data point to plotarea
    function mapx( x ) {
        var a = opts.xaxis;
        var xs = plotarea.w / (a.max - a.min);
        return plotarea.x + (x-opts.xaxis.min)*xs;
    }

    // map y coord of a data point to plotarea
    function mapy( y ) {
        var a = opts.yaxis;
        var ys = plotarea.h / (a.max-a.min);
        return plotarea.y + (plotarea.h-(y-a.min)*ys);
    }

    function renderSeries( s ) {

        var toolTip = function( n,content ) {
            var pos = $(n).offset();
            var tip = $('<div class="tooltip">' + content + '</div>').css( {
                position: 'absolute',
                top: pos.top - 10,
                left: pos.left + 10,
                display: 'none' }).appendTo( "body" );

            tip.hovered1 = false;
            tip.hovered2 = false;

            $(n).hover(
                function() { tip.hovered1=true; check(); },
                function() { tip.hovered1=false; check(); } );

            tip.hover( 
                function() { tip.hovered2=true; check(); },
                function() { tip.hovered2=false; check(); } );

            var check = function() {
                if( !tip.hovered1 && !tip.hovered2 ) {
                    tip.hide();
                } else {
                    tip.show();
                }
            }


        }


        $.each( s.data, function() {
            var x = mapx(this.x);
            var y = mapy(this.y);

//            var w=50, h=50;
//            R.rect( x-w/2,y-(this.radius+h+10),w,h,10);

            // calc and cache radius
            var radius = 5 + (this.avg_words*15)/1000;

            var c = R.circle( x, y, this.r ).attr('stroke','none');
            c.attr('opacity', 0.7);
            c.attr("fill", this.colour );
//            c.attr("title", this.colour );

            var d=this;
            toolTip( c.node, '' + d.y + ' articles (<a href="' + d.search_url + '">list them</a>)<br/>average ' + Math.round((d.avg_words/30)*10)/10 + ' column inches' );
            $(c.node).hover(
                function() { c.attr('opacity',1).attr('r',radius*1.1); },
                function() { c.attr('opacity',0.7).attr('r',radius); }
            );
        } );
    }

    $.extend( true, opts, opts_ );

    initData();

    // draw axes,grid,labels etc...
    renderAxis( opts.xaxis );
    renderAxis( opts.yaxis );

    renderSeries( series );
}






    return public;
}();

