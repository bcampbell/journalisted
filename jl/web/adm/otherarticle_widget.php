<?php

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

class OtherArticleWidget
{
    // a widget for displaying/handling an entry in journo_other_articles table.
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

        $row = OtherArticleWidget::fetch_one( $id );
        $w = new OtherArticleWidget( $row );
        // perform whatever action has been requested
        $w->perform( $action );

        // is request ajax?
        $ajax = get_http_var('ajax') ? true:false;
        if( $ajax ) {
            $w->emit_core();
        } else {
            // not an ajax request, so output a full page
            admPageHeader( "Other Article", "OtherArticleWidget::emit_head_js" );
            print( "<h2>Other article</h2>\n" );
            $w->emit_full();
            admPageFooter();
        }
    }


    public static function fetch_one($id) {
    	$sql = <<<EOT
SELECT o.id, o.journo_id, o.url, o.title, o.pubdate, o.publication, o.status, j.ref AS journo_ref, j.prettyname as journo_prettyname
    FROM journo_other_articles o
    JOIN journo j ON o.journo_id=j.id
    WHERE o.id=?
EOT;
    	return db_getRow( $sql,$id );
    }


    public static function fetch_lots( $status_filter='' ) {
    	$whereclause = '';
    	if( $status_filter=='approved' )
    		$whereclause = "WHERE o.status='a'";
    	elseif( $status_filter=='unapproved' )
	    	$whereclause = "WHERE o.status<>'a'";

    	$sql = <<<EOT
SELECT o.id, o.journo_id, o.url, o.title, o.pubdate, o.publication, o.status, j.ref AS journo_ref, j.prettyname as journo_prettyname
    FROM journo_other_articles o
    JOIN journo j ON o.journo_id=j.id
    $whereclause
	ORDER BY o.id
EOT;

    	return db_getAll( $sql );
    }



    function __construct( $r )
    {
        $this->state = 'default';
        $this->id = $r['id'];
        $this->url = $r['url'];
        $this->title = $r['title'];
        $this->pubdate = new DateTime( $r['pubdate'] );
        $this->publication = $r['publication'];
        $this->status = $r['status'];
        $this->journo = array(
            'id'=>$r['journo_id'],
            'ref'=>$r['journo_ref'],
            'prettyname'=>$r['journo_prettyname'] );
    }

    function action_url( $action ) {
        return "/adm/widget?widget=otherarticle&action={$action}&id={$this->id}";
    }

    function perform( $action ) {
        if( $action == 'delete' ) {
            $this->state = 'delete_requested';
        } elseif( $action == 'confirm_delete' ) {
            //ZAP!
            db_do( "DELETE FROM journo_other_articles WHERE id=?", $this->id );
            db_commit();
            $this->state = 'deleted';
        } else if( $action == 'edit' ) {
            $this->state = 'editing';
        } else if( $action == 'approve' ) {
            $this->status = 'a';
            db_do( "UPDATE journo_other_articles SET status=? WHERE id=?",
                $this->status,
                $this->id );
            db_commit();
        } else if( $action == 'unapprove' ) {
            $this->status = 'h';
            db_do( "UPDATE journo_other_articles SET status=? WHERE id=?",
                $this->status,
                $this->id );
            db_commit();
        }
    }


    // output the widget, including it's outer container (which will contain
    // any future stuff returned via ajax
    function emit_full()
    {
?>
<div class="widget-container" id="otherarticle-<?php echo $this->id;?>">
<?php $this->emit_core(); ?>
</div>
<?php
    }


    function emit_core() {
        if( $this->state == 'deleted' ) {
?><del><?php
        } else {
?><div class="<?php echo $this->status=='a' ? "approved":"unapproved"; ?>"><?php
        }

?>
<a href="<?php echo $this->url; ?>"><?php echo $this->url; ?></a><br/>
<small>
journo: <a href="/<?php echo $this->journo['ref']; ?>"><?php echo $this->journo['ref'] ?></a>
(<a href="/adm/<?php echo $this->journo['ref']; ?>">admin</a>)<br/>
title: "<?php echo $this->title; ?>" pubdate: "<?php echo $this->pubdate->format( "Y-m-d" ); ?>" publication: "<?php echo $this->publication; ?>"<br/>
</small>

<?php
        if( $this->state == 'deleted' ) {
?></del><?php
        } else {
?></div><?php
        }

?>
<?php

        if( $this->state == 'editing' ) {
/*
?>
<form class="widget">
<input type="hidden" name="id" value="<?php echo $this->id; ?>" />
<input type="text" size=80 name="url" value="<?php echo $this->url; ?>" /><br/>
<button type="submit">set</button>
<a class="widget" href="<?php echo $this->action_url(''); ?>">cancel</a>
</form>
<?php
*/
?>
<small>[<a class="widget" href="<?php echo $this->action_url('delete'); ?>">delete</a>]</small>
<?php
        } elseif( $this->state == 'delete_requested' ){
?>
[<a class="widget" href="<?php echo $this->action_url('confirm_delete'); ?>">REALLY delete</a>] |
[<a class="widget" href="<?php echo $this->action_url(''); ?>">no</a>]<br/>
<?php
        } else {

            if( $this->status == 'a' ) {
?>
<small>[<a class="widget" href="<?php echo $this->action_url('unapprove'); ?>">unapprove</a>]</small>
<?php
            } else {
?>
<small>[<a class="widget" href="<?php echo $this->action_url('approve'); ?>">approve</a>]</small>
<?php
            }

?>
<small>[<a class="widget" href="<?php echo $this->action_url('edit'); ?>">edit</a>]</small>
<small>[<a class="widget" href="<?php echo $this->action_url('delete'); ?>">delete</a>]</small>
<br/>
<?php
        }
    }
}

?>
