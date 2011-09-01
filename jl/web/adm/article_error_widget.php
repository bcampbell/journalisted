<?php

//require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';


// class for modeling article_error entries
class ArticleError
{
    function __construct($row)
    {
        $this->from_db_row($row);
    }

    function from_db_row($row) {
        set_fields($this, $row, array('id','url','reason_code','submitted'));
        if(!is_null($row['expected_journo'])) {
            $this->expected_journo = array_to_object($row, array('expected_journo'=>'id', 'expected_ref'=>'ref'));
        } else {
            $this->expected_journo = null;
        }
        if(!is_null($row['submitted_by'])) {
            $this->submitted_by = array_to_object($row, array('submitted_by'=>'id', 'submitted_by_name'=>'name', 'submitted_by_email'=>'email'));
        } else {
            $this->submitted_by = NULL;
        }
        if(!is_null($row['article_id'])) {
            $this->article = array_to_object($row, array('article_id'=>'id', 'article_title'=>'title', 'article_byline'=>'byline'));
        } else {
            $this->article = NULL;
        }
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
        return new ArticleError(db_getRow($sql,$id));
    }


    // fetch all article_errors, returning an array of widgets
    static function fetch_all() {
        $sql = <<<EOT
SELECT e.id, e.url, e.reason_code, e.submitted, e.submitted_by, e.article_id, e.expected_journo,
                j.ref as expected_ref,
                a.title as article_title, a.permalink as article_permalink, a.byline as article_byline,
                p.name as submitted_by_name,
                p.email as submitted_by_email
            FROM (((article_error e LEFT JOIN article a ON a.id=e.article_id)
                LEFT JOIN journo j ON j.id=e.expected_journo)
                LEFT JOIN person p ON p.id=e.submitted_by)
            ORDER BY e.submitted DESC
EOT;
        $rows = db_getAll($sql);
        $art_errs = array();

        foreach($rows as $row) {
            $art_errs[] = new ArticleError($row);
        }
        return $art_errs;
    }


    function attribute_journo() {
        assert(!is_null($this->article));
        assert(!is_null($this->expected_journo));

        db_do("DELETE FROM journo_attr WHERE journo_id=? AND article_id=?",
            $this->expected_journo->id, $this->article->id);
        db_do("INSERT INTO journo_attr (journo_id,article_id) VALUES (?,?)",
            $this->expected_journo->id, $this->article->id);
    }

    function save() {
        db_do("UPDATE article_error SET url=?, reason_code=?, submitted=?, submitted_by=?, article_id=?, expected_journo=? WHERE id=?",
            $this->url,
            $this->reason_code,
            $this->submitted,
            is_null($this->submitted_by) ? null : $this->submitted_by->id,
            is_null($this->article) ? null : $this->article->id,
            is_null($this->expected_journo) ? null : $this->expected_journo->id,
            $this->id );
    }

    function zap() {
        db_do("DELETE FROM article_error WHERE id=?", $this->id);
        $this->id = null;
    }



};



// class to view and twiddle article_error entries
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

        // TODO: check adm permissions!!!

        $id = get_http_var('id');
        $action = get_http_var('action');

        $err = ArticleError::fetch_single($id);

        $w = new ArticleErrorWidget($err);
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

        $this->scraper_ret = NULL;
        $this->scraper_output = '';
    }

    function action_url( $action ) {
        return "/adm/widget?widget=" . self::PREFIX . "&action={$action}&id={$this->art_err->id}";
    }


    function action_link($action) {
        return sprintf('[<a class="widget" href="%s">%s</a>]', $this->action_url($action),$action);
    }


    function perform( $action ) {
        if($action=='reject') {
            $this->art_err->reason_code = 'rejected';
            $this->art_err->save();
            db_commit();
        }
        if($action=='add_journo') {
            $this->art_err->attribute_journo();
            // article error can be considered cleared now
            $this->art_err->zap();
            db_commit();
        }
        if( $action == 'scrape' ) {
            list($ret, $out) = scrape_ScrapeURL($this->art_err->url);
            $this->scraper_ret = $ret;
            $this->scraper_output = $out;

            if($ret == 0) {
                $arts = scrape_ParseOutput($out);
                if(sizeof($arts)<1) {
                    // uhoh... none scraped...
                } else {
                    // got one - check to make sure it's got the right journo
                    $art_id = $arts[0];
                    $got_expected = false;
                    if(!is_null($this->art_err->expected_journo)) {
                        foreach(db_getAll("SELECT journo_id FROM journo_attr WHERE article_id=?",$art_id) as $row) {
                            if($row['journo_id'] == $this->art_err->expected_journo->id) {
                                $got_expected=true;
                                break;
                            }
                        }
                    } else {
                        $got_expected = true;   // not expecting any particular journo
                    }

                    if($got_expected) {
                        // article error can be considered fixed now. yay!
                        $this->art_err->zap();
                        db_commit();
                    }
                }
            }

            $this->scraper_output = admMarkupPlainText( $out );
        }
    }

    function allowed_actions() {
        $actions = array();
        if(!is_null($this->art_err->id)) {
            $reason = $this->art_err->reason_code;
            if($reason=='journo_mismatch') {
                $actions[] = 'add_journo';
            }
            if($reason =='scrape_failed') {
                $actions[] = 'scrape';
            }
            if($reason !='rejected') {
                $actions[] = "reject";
            }
        }
        return $actions;
    }



    // output the widget, including it's outer container (which will contain
    // any future stuff returned via ajax
    function emit_full()
    {
?>
<div class="widget-container" id="articleerror-<?= $this->art_err->id;?>">
<?php $this->emit_core(); ?>
</div>
<?php
    }




    function emit_core() {
        $ae = &$this->art_err;

        $actions = array();
        if($ae->expected_journo && $ae->article) {
            $actions[] = 'add_journo';
        }
?>

<?php if(is_null($ae->id)) { ?>
<del>
<?php } ?>
<small>submitted <?= pretty_date(strtotime($ae->submitted)); ?>)</small><br/>
<a href="<?= $ae->url ?>"><?= $ae->url ?></a>
<?php if(!is_null($ae->submitted_by)) { ?>
(submitted by <a href="/adm/useraccounts?person_id=<?= $ae->submitted_by->id ?>"><?= $ae->submitted_by->email ?></a> (<?= $ae->submitted_by->name; ?>) )
<?php } ?>
<br/>
problem: <?= $ae->reason_code ?><br/>

<?php if(!is_null($ae->expected_journo)) { ?>
expected journo: <a href="/adm/<?= $ae->expected_journo->ref ?>"><?= $ae->expected_journo->ref ?></a><br/>
<?php } ?>


<?php if(!is_null($ae->article)) { ?>
article in the database: <a href="<?= article_adm_url($ae->article->id) ?>"><?= $ae->article->title ?></a> (raw byline: <?= $ae->article->byline ?>)<br/>
<?php } ?>
<?php if(is_null($ae->id)) { ?>
</del>
<?php } ?>

<?php if(!is_null($this->scraper_ret)) { ?>
<div>
<?php if($this->scraper_ret!=0) { ?>
SCRAPER FAILED (code <?= $this->scraper_ret; ?>)
<?php } ?>
raw scraper output:
<pre><code>
<?= admMarkupPlainText($this->scraper_output); ?>
</code></pre></div>
<?php } ?>

<?php foreach($this->allowed_actions() as $action) { ?>
<?= $this->action_link($action) ?>
<?php } ?>

<?php

    }
};
?>
