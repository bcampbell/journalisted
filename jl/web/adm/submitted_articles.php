<?php
// article_errors.php
// admin page for browsing errors resulting from submitted articles
// (didn't scrape, didn't pick up right journo from byline etc...)

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../phplib/adm.php';
require_once '../phplib/article.php';
require_once '../phplib/tabulator.php';
require_once '../phplib/paginator.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/validators.php';
require_once 'submitted_article_widget.php';

require_once '../phplib/drongo-forms/forms.php';


// form for filtering article error list
class FilterForm extends Form
{
    function __construct($data,$files,$opts) {
        parent::__construct($data,$files,$opts);
        $this->error_css_class = 'errors';
        $this->fields['expected_ref'] = new CharField(array(
            // journo-lookup to enable fancy autocomplete
            'widget'=> new TextInput(array('class'=>'journo-lookup')),
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Filter by journo',
            'validators'=>array(array(new JournoValidator(),"execute")),
            'help_text'=>'e.g. fred-bloggs, or leave blank',
        ));
    }
}


function view()
{
    $per_page = 100;
    $page = arr_get('p',$_GET,0);
    $offset = $page * $per_page;
    $limit = $per_page;

    $widgets = array();
    $total = 0;

    $f = new FilterForm($_GET,array(),array());
    if($f->is_valid()) {
        $total = SubmittedArticle::count($f->cleaned_data);
        
        foreach(SubmittedArticle::fetch($f->cleaned_data,$offset,$limit) as $err) {
            $widgets[] = new SubmittedArticleWidget($err);
        }
    }

    $paginator = new Paginator($total,$per_page,'p');
    $v = array('filterform'=>&$f, 'widgets'=>&$widgets,'paginator'=>$paginator);
    template($v);
}

function extra_head()
{
    SubmittedArticleWidget::emit_head_js();
}


function template($vars)
{
    extract($vars);

    admPageHeader("Submitted Articles", "extra_head");

?>
<h2>Submitted Articles</h2>
<p>Submitted articles needing admin attention</p>

<form action="/adm/submitted_articles" method="GET">
<table>
<?= $filterform->as_table(); ?>
</table>
<input type="submit" name="submit" value="Go" />
</form>
<?php if($filterform->is_valid()) { ?>
<p class="paginator"><?= $paginator->render() ?> <?= $paginator->total ?> matching</p>
<?php
    foreach($widgets as $w) {
        $w->emit_full();
    }
?>
<p class="paginator"><?= $paginator->render() ?> <?= $paginator->total ?> matching</p>
<?php } ?>
<?php
    admPageFooter();
}

view();

