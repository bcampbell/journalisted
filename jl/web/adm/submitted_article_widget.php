<?php

//require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';
require_once '../phplib/submitted_article.php';





// class to view and twiddle article_error entries
class SubmittedArticleWidget
{
    const PREFIX = 'submitted_article';

    // a widget for displaying/handling a missing article entry.
    // uses ajax to work in-place if javascript is enabled, but
    // should still work if javascript is disabled (it'll
    // jump to a new page rather than working in-place).


    static $action_defs=array(
        'replace_journo'=>array('label'=>'Replace Journo','class'=>'widget spark'),
        'reject'=>array('label'=>'Reject','class'=>'widget delete'),
        'scrape'=>array('label'=>'Scrape','class'=>'widget spark'),
        'add_manual'=>array('label'=>'Add article by hand','class'=>'add'),
    );

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

        $err = SubmittedArticle::fetch_single($id);

        $w = new SubmittedArticleWidget($err);
        // perform whatever action has been requested
        $w->perform($action);

        // is request ajax?
        $ajax = get_http_var('ajax') ? true:false;
        if( $ajax ) {
            $w->emit_core();
        } else {
            // not an ajax request, so output a full page
            admPageHeader( "Submitted Article", "SubmittedArticleWidget::emit_head_js" );
            print( "<h2>Submitted article</h2>\n" );
            $w->emit_full();
            admPageFooter();
        }
    }

    function __construct($submitted)
    {
        $this->submitted = $submitted;
        $this->scraper_output = null;
    }

    function action_url( $action ) {
        if($action=='add_manual') {
            $url ="/adm/editarticle?url=" . urlencode($this->submitted->url);
            if(!is_null($this->submitted->expected_journo)) {
                $url .= "&journo={$this->submitted->expected_journo->ref}";
            }
        } else {
            $url = "/adm/widget?widget=" . self::PREFIX . "&action={$action}&id={$this->submitted->id}";
        }
        return $url;
    }


    function action_link($action) {
        $def = self::$action_defs[$action];
        return sprintf('<a class="button %s" href="%s">%s</a>', $def['class'], $this->action_url($action), $def['label']);
    }


    function perform( $action ) {
        if($action=='reject') {
            $this->submitted->status = 'rejected';
            $this->submitted->save();
            db_commit();
        }
        if($action=='replace_journo') {
            $this->submitted->replace_journo();
            $this->submitted->save();
            db_commit();
        }
        if( $action == 'scrape' ) {
            $out = $this->submitted->scrape();
            $this->scraper_output = admMarkupPlainText( $out );
            $this->submitted->save();
            db_commit();

        }
    }

    function allowed_actions() {
        $actions = array();
        if(!is_null($this->submitted->id)) {
            $reason = $this->submitted->status;
            if($reason=='journo_mismatch') {
                $actions[] = 'replace_journo';
            }
            if($reason =='scrape_failed' || $reason=='') {
                $actions[] = 'scrape';
                $actions[] = 'add_manual';
            }
            if($reason !='rejected' && $reason != 'resolved' ) {
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
<div class="widget-container" id="submittedarticle-<?= $this->submitted->id;?>">
<?php $this->emit_core(); ?>
</div>
<?php
    }




    function emit_core() {
        $ae = &$this->submitted;

        $actions = array();
        if($ae->expected_journo && $ae->article) {
            $actions[] = 'replace_journo';
        }

        $struck = false;
        if( $ae->status=='resolved' || $ae->status=='rejected' ) {
            $struck = true;
        }

?>

<?php if($struck) { ?>
<del>
<?php } ?>
<small>submitted <?= pretty_date(strtotime($ae->when_submitted)); ?> 
<?php if(!is_null($ae->submitted_by)) { ?>
by <a href="/adm/useraccounts?person_id=<?= $ae->submitted_by->id ?>"><?= $ae->submitted_by->email ?></a> (<?= $ae->submitted_by->name; ?>)
<?php } ?>
</small>
<br/>

<a href="<?= $ae->url ?>"><?= $ae->url ?></a><br/>
problem: <?= $ae->status ?><br/>


<?php if(!is_null($ae->article)) { ?>
article in the database: <a href="<?= article_adm_url($ae->article->id) ?>"><?= $ae->article->title ?></a>
<a class="button edit" href="/adm/editarticle?id36=<?= article_id_to_id36($ae->article->id) ?>">edit</a><br/>
<?php if(sizeof($ae->article->authors)>0) { ?>
&nbsp;&nbsp;attributed to:
<?php     foreach($ae->article->authors as $author) { ?>
<?= admJournoLink($author->ref) ?>&nbsp;
<?php         } ?>
<?php     } ?>
<br/>
&nbsp;&nbsp;raw byline: <?= $ae->article->byline ?><br/>
<?php } ?>


<?php if(!is_null($ae->expected_journo)) { ?>
expected journo: <a class="journo-info" href="/adm/<?= $ae->expected_journo->ref ?>"><?= $ae->expected_journo->ref ?></a><br/>
<?php } ?>


<?php if(!is_null($this->scraper_output)) { ?>
<div>
raw scraper output:
<pre><code>
<?= admMarkupPlainText($this->scraper_output); ?>
</code></pre>
</div>
<?php } ?>

<?php if($struck) { ?>
</del>
<?php } ?>

<?php foreach($this->allowed_actions() as $action) { ?>
<?= $this->action_link($action) ?>
<?php } ?>

<?php

    }
};
?>
