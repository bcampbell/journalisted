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
    };



// basic chart-plotting library, uses jquery and raphael

// TODO
// tidy up and generalise as a standalone component
// support multiple series
// automatically determine sensible tick steps for axes labeling
// support non-linear axis scapes (via mapx()/mapy()?)
function chart( placeholder, series_, opts_ ) {
    var R = Raphael( placeholder );
    var series = series_;
    var opts =  {
        xaxis: {
            label: "X Axis",
            extent: null,   // [xmin,xmax] or null
            pad: [0,0],     // [left,right] - expands the extent
            step: null,     // a number, "month" or null
            gutter: 40,      // space left for labels, etc...
            ticksize: 5,    // size of interval markers
            tickText: defaultTickFmt,
            which: 'bottom'
        },
        yaxis: { label: "Y Axis",
            extent: null,
            pad: [0,0],
            step: null,
            gutter: 40,
            ticksize: 5,
            tickText: defaultTickFmt,
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

        // work out X extents
        if( !opts.xaxis.extent ) {
            var e = [ Number.POSITIVE_INFINITY, Number.NEGATIVE_INFINITY ];
            $.each( series.data, function() {
                e[0] = Math.min(this.x,e[0]);
                e[1] = Math.max(this.x,e[1]);
            } );
            opts.xaxis.extent = e;
        }
        opts.xaxis.extent[0] -= opts.xaxis.pad[0];
        opts.xaxis.extent[1] += opts.xaxis.pad[1];

        // TODO: work out a good default step value
        if( !opts.xaxis.step ) {
            opts.xaxis.step = 1;
        }


        // Y extents
        if( !opts.yaxis.extent ) {
            var e = [ Number.POSITIVE_INFINITY, Number.NEGATIVE_INFINITY ];
            $.each( series.data, function() {
                e[0] = Math.min(this.y,e[0]);
                e[1] = Math.max(this.y,e[1]);
            } );
            opts.yaxis.extent = e;
        }
        opts.yaxis.extent[0] -= opts.yaxis.pad[0];
        opts.yaxis.extent[1] += opts.yaxis.pad[1];

        // TODO: work out a good default step value
        if( !opts.yaxis.step ) {
            opts.yaxis.step = 1;
        }

        // set up the plotarea
        plotarea = {
            x: opts.yaxis.gutter,
            y: 0,
            w: R.width - opts.yaxis.gutter,
            h: R.height - opts.xaxis.gutter };
    }


    function genticks( axis, tickfn ) {
        if( axis.step == "month" ) {
            // work out starting year/month, round up to nearest month boundary.
            // (-1 ms to handle border case of starting _exactly_ at month boundary)
            dstart = new Date( axis.extent[0]-1 );
            year = dstart.getUTCFullYear();
            month = dstart.getUTCMonth();
            if( ++month > 11 )      // round up to nearest month
                { month=0; ++year; }

            for( var t = (new Date( Date.UTC(year,month,1,0,0,0) )).getTime();
                t <= axis.extent[1];
                t = (new Date( Date.UTC(year,month,1,0,0,0) )).getTime() )
            {
                tickfn(t)
                if( ++month > 11 )
                    { month=0; ++year; }
            }
        } else {
            // assume numeric
            var e = axis.extent;
            for( var t=axis.step * Math.ceil( e[0]/axis.step );
                t<=e[1];
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
        var xext = opts.xaxis.extent;
        var xs = plotarea.w / (xext[1]-xext[0]);
        return plotarea.x + (x-xext[0])*xs;
    }

    // map y coord of a data point to plotarea
    function mapy( y ) {
        var yext = opts.yaxis.extent;
        var ys = plotarea.h / (yext[1]-yext[0]);
        return plotarea.y + (plotarea.h-(y-yext[0])*ys);
    }

    function renderSeries( s ) {
        $.each( s.data, function() {
            var x = mapx(this.x);
            var y = mapy(this.y);

//            var w=50, h=50;
//            R.rect( x-w/2,y-(this.radius+h+10),w,h,10);

            var c = R.circle( x, y, this.r ).attr('stroke','none');
            c.attr('opacity', 0.7);
            c.attr("fill", this.colour );
            c.attr("title", this.colour );

            var d=this;
            $(c.node).hover(
                function() { c.attr('opacity',1).attr('r',d.r*1.1); },
                function() { c.attr('opacity',0.7).attr('r',d.r); }
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

