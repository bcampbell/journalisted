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
        
          Do similar with forms - hook the submit button, add "ajax=1",
          submit the GET ourselves and replace the widget contents with the
          returned data.
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
    $("form.widget").live( "submit", function(e) {
        var url = $(this).attr('action');

        var params = $(this).formSerialize() + "&ajax=1";
        var foo = $(this).closest(".widget-container");
        foo.html( "<blink>working...</blink>" );
        foo.load( url, params );
        return false;
        });
});
</script>
<?php

    }


    // process a request (either ajax or not)
    public static function dispatch() {
        $action = get_http_var('action');
        $r=null;
        if( $action == 'update' ) {
            $r = OtherArticleWidget::fetch_from_httpvars();
        } else {
            $id = get_http_var('id');
            $r = OtherArticleWidget::fetch_one( $id );
        }
        $w = new OtherArticleWidget( $r );
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

    public static function fetch_from_httpvars() {
        $r = array(
            'id'=>get_http_var('id'),
            'url'=>get_http_var('url'),
            'title'=>get_http_var('title'),
            'pubdate'=>get_http_var('pubdate'),
            'status'=>get_http_var('status'),
            'publication'=>get_http_var('publication'),
             'journo_id'=>get_http_var('journo_id'),
             'journo_ref'=>get_http_var('journo_ref'),
             'journo_prettyname'=>get_http_var('journo_prettyname'),
            );
        return $r;
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
        } else if( $action == 'update' ) {
            // update the db to reflect the changes
            db_do( "UPDATE journo_other_articles SET url=?, title=?, pubdate=?, publication=? WHERE id=?",
                $this->url,
                $this->title,
                $this->pubdate->format(DateTime::ISO8601),
                $this->publication,
                $this->id );
            db_commit();
            // back to non-editing mode
            $this->state = '';
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

        if( $this->state == 'editing' ) {
            $this->emit_edit_form();
        } elseif( $this->state == 'delete_requested' ){
            $this->emit_details();
?>
[<a class="widget" href="<?php echo $this->action_url('confirm_delete'); ?>">REALLY delete</a>] |
[<a class="widget" href="<?php echo $this->action_url(''); ?>">no</a>]<br/>
<?php
        } else {
            $this->emit_details();

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

<?php if( $this->state == 'deleted' ) { ?></del><?php } else {?></div><?php }
        }
    }



    function emit_details() {
        //
?>
<a href="<?php echo $this->url; ?>"><?php echo $this->url; ?></a><br/>
<small>
journo: <a href="/<?php echo $this->journo['ref']; ?>"><?php echo $this->journo['ref'] ?></a>
(<a href="/adm/<?php echo $this->journo['ref']; ?>">admin</a>)<br/>
title: "<?php echo $this->title; ?>" pubdate: "<?php echo $this->pubdate->format( "Y-m-d" ); ?>" publication: "<?php echo $this->publication; ?>"<br/>
</small>
<?php
    }



    private function emit_edit_form() {
        // form to edit an existing otherarticle
?>
<form class="widget" method="post" action="/adm/widget">
<small>journo: <a href="/<?php echo $this->journo['ref']; ?>"><?php echo $this->journo['ref'] ?></a></small><br/>
<input type="hidden" name="widget" value="otherarticle" />
<input type="hidden" name="id" value="<?php echo $this->id; ?>" />
<input type="hidden" name="status" value="<?php echo $this->status; ?>" />
<input type="hidden" name="journo_id" value="<?php echo $this->journo['id']; ?>" />
<input type="hidden" name="journo_ref" value="<?php echo $this->journo['ref']; ?>" />
<input type="hidden" name="journo_prettyname" value="<?php echo $this->journo['prettyname']; ?>" />
<label>url: <input type="text" size=80 name="url" value="<?php echo $this->url; ?>" /></label><br/>
<label>title: <input type="text" size=80 name="title" value="<?php echo $this->title; ?>" /></label><br/>
<label>pubdate: <input type="text" size=20 name="pubdate" value="<?php echo $this->pubdate->format( 'Y-m-d H:i'); ?>" /></label><br/>
<label>publication: <input type="text" size=40 name="publication" value="<?php echo $this->publication; ?>" /></label><br/>
<input type="hidden" name="action" value="update" />
<button type="submit">apply</button>
<small>[<a class="widget" href="<?php echo $this->action_url(''); ?>">cancel</a>]</small>
</form>
<?php
    }

}
?>
