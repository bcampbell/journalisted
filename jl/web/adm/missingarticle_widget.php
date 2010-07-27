<?php

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

class MissingArticleWidget
{
    // a widget for displaying/handling a missing article entry.
    // uses ajax to work in-place if javascript is enabled, but
    // should still work if javascript is disabled (it'll
    // jump to a new page rather than working in-place).

    // output javascript block to put in page <head>
    public static function emit_head_js()
    {
        /* set up all link elements with class "widget" to:
            1) GET the href url, with the extra param: "ajax=1"
        2) find the nearest parent with class ".widget-container", and replace it's content
           with the html returned in step 1)
        */
?>
<script type="text/JavaScript">
$(document).ready(function(){
    $("a.widget").live( "click", function(e) {
        var url = $(this).attr('href');
        var foo = $(this).closest(".widget-container");
        foo.html( "<blink>working...</blink>" );
        foo.load( url, "ajax=1" );
        return false;
        });
});
</script>
<?php

    }


    // process a request (either ajax or not)
    public static function dispatch() {
        $id = get_http_var('id');
        $action = get_http_var('action');

        $sql = <<<EOT
SELECT m.id,m.journo_id, j.ref, j.prettyname, j.oneliner, m.url, m.submitted, m.reason
    FROM missing_articles m LEFT JOIN journo j ON m.journo_id=j.id
    WHERE m.id=?;
EOT;
        $row = db_getRow( $sql, $id );


        $w = new MissingArticleWidget( $row );
        // perform whatever action has been requested
        $w->perform( $action );

        // is request ajax?
        $ajax = get_http_var('ajax') ? true:false;
        if( $ajax ) {
            $w->emit_core();
        } else {
            // not an ajax request, so output a full page
            admPageHeader( "Missing Article", "MissingArticleWidget::emit_head_js" );
            print( "<h2>Missing article</h2>\n" );
            $w->emit_full();
            admPageFooter();
        }
    }

    function __construct( $r )
    {
        $this->state = 'default';   // delete_requested
        $this->scraper_output = null;
        $this->id = $r['id'];
        $this->url = $r['url'];
        $this->submitted = new DateTime($r['submitted']);
        $this->reason = $r['reason'];
        $this->journo = ($r['journo_id']===null) ? null : array(
            'id'=>$r['journo_id'],
            'ref'=>$r['ref'],
            'prettyname'=>$r['prettyname'],
            'oneliner'=>$r['oneliner'] );
    }

    function action_url( $action ) {
        return "/adm/widget?widget=missingarticle&action={$action}&id={$this->id}";
    }

    function perform( $action ) {
        if( $action == 'scrape' ) {
            // INVOKE THE SCRAPER...
            $out = scrape_ScrapeURL( $this->url );
            if($out===NULL) {
               $this->scraper_output = "<strong>UHOH... ERROR</strong>";
            } else {
               $this->scraper_output = admMarkupPlainText( $out );
            }
        } elseif( $action == 'delete' ) {
            $this->state = 'delete_requested';
        } elseif( $action == 'confirm_delete' ) {
            //ZAP!
            db_do( "DELETE FROM missing_articles WHERE id=?", $this->id );
            db_commit();
            $this->state = 'deleted';
        } else if( $action == 'edit' ) {
            $this->state = 'editing';
        }
    }


    // output the widget, including it's outer container (which will contain
    // any future stuff returned via ajax
    function emit_full()
    {
?>
<div class="widget-container" id="missingarticle-<?php echo $this->id;?>">
<?php $this->emit_core(); ?>
</div>
<?php
    }


    function emit_core() {
        if( $this->state == 'deleted' ) {
?>
<del><a href="<?php echo $this->url; ?>"><?php echo $this->url; ?></a><br/>
<small>submitted <?php echo $this->submitted->format( 'Y-m-d H:i' ); ?>
<?php if( $this->journo ) { ?> for <a href="/<?php echo $this->journo['ref']; ?>"><?php echo $this->journo['prettyname']; ?></a><?php } ?></small>
</del>
<br/>
<?php
        } else if( $this->state == 'editing' ) {
?>
<form class="widget">
<input type="hidden" name="id" value="<?php echo $this->id; ?>" />
<input type="text" size=80 name="url" value="<?php echo $this->url; ?>" /><br/>
<button type="submit">set</button>
<a class="widget" href="<?php echo $this->action_url(''); ?>">cancel</a>
</form>
<?php

        } else {

?>
<a href="<?php echo $this->url; ?>"><?php echo $this->url; ?></a><br/>
<small>submitted <?php echo $this->submitted->format( 'Y-m-d H:i' ); ?>
<?php if( $this->journo ) { ?> for <a href="/<?php echo $this->journo['ref']; ?>"><?php echo $this->journo['prettyname']; ?></a><?php } ?></small>
<br/>
<?php if( $this->reason ) { ?>
<small><pre>reason: <?= h($this->reason) ?></pre></small>
<?php } ?>
<?php
            if( $this->state == 'delete_requested' ){
?>
[<a class="widget" href="<?php echo $this->action_url('confirm_delete'); ?>">REALLY delete</a>] |
[<a class="widget" href="<?php echo $this->action_url(''); ?>">no</a>]<br/>
<?php
            } else {
?>
[<a class="widget" href="<?php echo $this->action_url('scrape'); ?>">scrape</a>] |
<small>[<a class="widget" href="<?php echo $this->action_url('delete'); ?>">delete</a>]</small>
<br/>
<?php
            }

            if( $this->scraper_output !== null ) {
?>
<blockquote>
<pre><?php echo $this->scraper_output; ?></pre>
</blockquote>
<?php
            }
        }
    }
}

?>
