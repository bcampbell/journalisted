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
require_once 'article_error_widget.php';

require_once '../phplib/drongo-forms/forms.php';


// form for filtering article error list
class ArtErrFilterForm extends Form
{
    function __construct($data,$files,$opts) {
        parent::__construct($data,$files,$opts);
        $this->fields['j'] = new CharField(array(
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Only for journo',
            'help_text'=>'e.g. fred-bloggs'));
    }
}


function view()
{
    $widgets = array();
    foreach(ArticleError::fetch_all() as $err) {
        $widgets[] = new ArticleErrorWidget($err);
    }
    $v = array('widgets'=>&$widgets);
    template($v);
}

function extra_head()
{
    ArticleErrorWidget::emit_head_js();
}


function template($vars)
{
    extract($vars);

    admPageHeader("Article Errors", "extra_head");

?>
<h2>Article errors</h2>
<p>Submitted articles needing admin attention</p>

<?php
    foreach($widgets as $w) {
        $w->emit_full();
    }
?>

<?php
    admPageFooter();
}

view();

