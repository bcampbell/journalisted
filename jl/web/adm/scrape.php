<?php
// scrape.php
// admin page for running scrapers on individual articles

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
//require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';
require_once '../phplib/scrapeutils.php';
require_once '../phplib/tabulator.php';
require_once '../phplib/article.php';
require_once '../phplib/drongo-forms/forms.php';

// form for inputing article URLs
class ArticleURLsForm extends Form
{
    function __construct($data,$files=array(),$opts=array()) {
        parent::__construct($data,$files,$opts);
        $this->fields['url'] = new CharField(array('required'=>TRUE));
#        $this->fields['test'] = new BooleanField(array('help_text'=>"test only - don't modify database",'required'=>FALSE));
    }
}

// custom column types for tabulator
class ArtColumn extends Column {
    function fmt($row) {
        $url = article_url($row['id']);
        $permalink = $row['permalink'];
        $adm_url = article_adm_url($row['id']);
        return sprintf('<a href="%s">%s</a><small> [<a href="%s">source</a>]</small>',
            $adm_url, $row['title'], $permalink );
    }
}

class AttributionColumn extends Column {
    function fmt($row) {
        $journo_frags = array();
        foreach($row['journos'] as $j) {
            $journo_frags[] = sprintf('<a href="/%s/">%s</a> <small>[<a href="/adm/%s">adm</a>]</small>', $j['ref'],$j['ref'],$j['ref']);
        }
        return implode(', ',$journo_frags);
    }
}

// helper
function fetch_art($art_id) {
    $art = article_collect($art_id);
    // journos (article_collect() only grabs active ones)
    $sql = <<<EOT
SELECT j.prettyname, j.ref, j.status, j.oneliner
    FROM ( journo j INNER JOIN journo_attr attr ON j.id=attr.journo_id )
    WHERE attr.article_id=?
EOT;
    $art['journos'] = db_getAll( $sql, $art_id );

    return $art;
}



function view() {
    $url = get_http_var( 'url', '' );
    $action = get_http_var( 'action' );

    $art = null;
    $return_code = null;
    $raw_output = '';
    $summary = 'FAILED';

    $urls_form = new ArticleURLsForm($_GET);
    if($urls_form->is_valid()) {

        $url = $urls_form->cleaned_data['url'];
        // already got it?
        $art_id = article_find($url);
        if(is_null($art_id)) {
            list($return_code,$raw_output) = scrape_ScrapeURL($url);
            $scraped = scrape_ParseOutput($raw_output);
            if(sizeof($scraped)>0) {
                $art_id = $scraped[0]['id'];
                $summary = 'ARTICLE SCRAPED';
            } else {
                $summary = 'NOT SCRAPED';
            }
        } else {
            $summary = 'ALREADY IN DATABASE';
        }

        if(!is_null($art_id)) {
            $art = fetch_art($art_id);
        }
    }

    $v = array('urls_form'=>$urls_form,
        'return_code'=>$return_code,
        'raw_output'=>$raw_output,
        'summary'=>$summary,
        'art'=>$art);

    template($v);
}


function template($vars) {
    extract($vars);

?>
<?php admPageHeader(); ?>
<h2>scrape article(s)</h2>

<form action="/adm/scrape" method="GET">
<table>
<?= $urls_form->as_table(); ?>
</table>
<input type="submit" name="submit" value="go" />
</form>


<?php if($urls_form->is_valid()) { ?>
<h3>Result: <?= $summary ?></h3>

<?php if(!is_null($art)) { ?>

<div style="border: 1px solid black;">
<h4><a href="<?= article_url($art['id']) ?>"><?= $art['title'] ?></a> [<a href="<?= article_adm_url($art['id']) ?>">adm</a>]<h4>
<?=$art['srcorgname'];?>, <?=$art['pretty_pubdate'];?><br/>
url: <a href="<?=$art['permalink'];?>"><?=$art['permalink']?></a><br/>
attributed to:
<?php if(sizeof($art['journos'])>0) { ?>
<ul>
<?php foreach($art['journos'] as $j) {?>
<li><a href="/<?= $j['ref'] ?>"><?= $j['ref'] ?></a> <small>[<a href="/adm/<?= $j['ref'] ?>">adm</a>]</small></li>
<?php } ?>
</ul>
<?php } else { ?>
- nobody -<br/>
<?php } ?>
</div>

<?php }?>

<?php if(!is_null($return_code)) { ?>
<h3>raw scraper output (returncode=<?= $return_code; ?>):</h3>
<div>
<code>
<pre>
<?= admMarkupPlainText($raw_output) ?>
</pre>
</code>
</div>
<?php } ?>
<?php } ?>
<?php admPageFooter(); ?>

<?php
}

view();

