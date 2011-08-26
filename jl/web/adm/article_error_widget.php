<?php

//require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';

class ArticleErrorWidget
{
    const PREFIX = 'article_error';

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

        $row = ArticleErrorWidget::fetch_single($id);

        $w = new ArticleErrorWidget( $row );
        // perform whatever action has been requested
        $w->perform($action);

        // is request ajax?
        $ajax = get_http_var('ajax') ? true:false;
        if( $ajax ) {
            $w->emit_core();
        } else {
            // not an ajax request, so output a full page
            admPageHeader( "Article Error", "ArticleErrorWidget::emit_head_js" );
            print( "<h2>Missing article</h2>\n" );
            $w->emit_full();
            admPageFooter();
        }
    }

    function __construct($art_err)
    {
        $this->art_err = $art_err;
        $this->id = $art_err['id'];
        $this->state = "initial";
    }

    function action_url( $action ) {
        return "/adm/widget?widget=" . self::PREFIX . "&action={$action}&id={$this->id}";
    }


    function action_link($action) {
        return sprintf('[<a class="widget" href="%s">%s</a>]', $this->action_url($action),$action);
    }


    function perform( $action ) {

        if($action=='delete') {
            $this->state='deleted';
        }
        return;

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
<div class="widget-container" id="missingarticle-<?php echo $this->art_err['id'];?>">
<?php $this->emit_core(); ?>
</div>
<?php
    }


    function emit_core() {
        extract($this->art_err);

?>
<?php if($this->state == 'deleted') { ?>
<del>
<?php } ?>
<a href="<?= $url ?>"><?= $url ?></a><br/>
<?= $reason_code ?><br/>
<?= $submitted ?><br/>
<?php if( !is_null($expected_journo)) { ?>
expected journo: <a href="/adm/<?= $expected_ref ?>"><?= $expected_ref ?></a><br/>
<?php } ?>
<?php if(!is_null($submitted_by)) { ?>
submitted by: <a href="/adm/useraccounts?person_id=<?= $submitted_by ?>"><?= $submitted_by_email ?></a><br/>
<?php } ?>
<?php if(!is_null($article_id)) { ?>
article in the database: <a href="<?= article_adm_url($article_id) ?>"><?= $article_title ?></a><br/>
raw byline: <?= $article_byline ?><br/>
<?php } ?>
<?php if($this->state == 'deleted') { ?>
</del>
<?php } else { ?>
<?= $this->action_link('delete'); ?>
<?php } ?>
<?php

    }

    static function fetch_single($id) {
        $sql = <<<EOT
SELECT e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email
            FROM (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
            WHERE e.id=?
            ORDER BY e.submitted DESC
EOT;
        return db_getRow($sql,$id);
    }


    // fetch all article_errors, returning an array of widgets
    static function fetch_all() {
        $sql = "SELECT e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email
            FROM (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
            ORDER BY e.submitted DESC";

        $rows = db_getAll($sql);
        $errors = array();

        foreach($rows as $err) {
            $errors[] = new ArticleErrorWidget($err);
        }
        return $errors;
    }
}
?>
