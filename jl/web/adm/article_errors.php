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
    $sql = "SELECT e.*, j.ref, a.title, a.permalink
        FROM ((article_error e LEFT JOIN article a ON a.id=e.article_id)
            LEFT JOIN journo j ON j.id=e.expected_journo)
        ORDER BY e.submitted DESC";

    $errors = db_getAll($sql);

    $v = array('errors'=>$errors);
    template($v);
}

function template($vars)
{
    $tabulator = new Tabulator(array(
        new Column('url'),
        new Column('reason_code'),
        new Column('submitted'),
        new Column('ref'),
        new Column('title')
    ));

    extract($vars);

    admPageHeader();

?>
<h2>Article errors</h2>
<p>Submitted articles needing admin attention</p>

<?php if($errors) { ?>
<table class="results">
<?= $tabulator->as_table($errors); ?>
</table>
<?php }?>

<?php
    admPageFooter();
}

view();

