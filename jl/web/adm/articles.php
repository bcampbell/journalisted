<?php
// articles.php
// admin page for browsing articles

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

$_time_choices = array('all'=>'All', '1hr'=>'Last 1 hr', '12hrs'=>'Last 12 hrs', '24hrs'=>'Last 24 hrs', '48hrs'=>'Last 48 hrs', '7days'=>'Last 7 days', '30days'=>'Last 30 days');
// map to postgres intervals
$_time_intervals = array( 'all'=>null, '1hr'=>'1 hour', '12hrs'=>'12 hours', '24hrs'=>'24 hours', '48hrs'=>'48 hours', '7days'=>'7 days', '30days'=>'30 days');



$_sortable_fields= array('id','pubdate','lastscraped','title','publication','byline');




// form for filtering article list
class FilterForm extends Form
{

    function __construct($data,$files,$opts) {
        global $_time_choices;

        $publication_choices = array('any'=>'Any');
        $r = db_query("SELECT shortname, prettyname FROM organisation");
        while($row = db_fetch_array($r)) {
            $publication_choices[$row['shortname']]= $row['prettyname'];
        }

        parent::__construct($data,$files,$opts);
        $this->fields['publication'] = new ChoiceField(array('choices'=>$publication_choices,'required'=>FALSE));
        $this->fields['title'] = new CharField(array(
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Title containing'));
        $this->fields['byline'] = new CharField(array(
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Byline containing'));
        $this->fields['url'] = new CharField(array(
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'URL containing'));
        $this->fields['pubdate'] = new ChoiceField(array('choices'=>$_time_choices,'required'=>FALSE));
        $this->fields['lastscraped'] = new ChoiceField(array('choices'=>$_time_choices,'required'=>FALSE));
    }
}


function build_query($f)
{
    global $_time_intervals;
    $params = array();
    $conds = array();

    $interval = arr_get($f['lastscraped'], $_time_intervals);
    if($interval) {
        $conds[] = "lastscraped >= (now() - interval ?)";
        $params[] = $interval;
    }

    $interval = arr_get($f['pubdate'], $_time_intervals);
    if($interval) {
        $conds[] = "pubdate >= (now() - interval ?)";
        $params[] = $interval;
    }

    if( $f['publication'] != 'any' ) {
        $conds[] = "o.shortname=?";
        $params[] = $f['publication'];
    }

	if( $f['title'] ) {
        $conds[] = "title ilike ?";
        $params[] = '%' . $f['title'] . '%';
	}

	if( $f['byline'] ) {
        $conds[] = "byline ilike ?";
        $params[] = '%' . $f['byline'] . '%';
	}

	if( $f['url'] ) {
        $conds[] = "permalink ilike ?";
        $params[] = '%' . $f['url'] . '%';
    }

    return array($conds,$params);
}


function grab_articles($f, $o, $ot, $offset, $limit)
{
    global $_time_intervals;
    global $_sortable_fields;

    list($conds, $params) = build_query($f);

    // make sure ordering params are sensible
    $o = strtolower($o);
    assert(in_array($o,$_sortable_fields));
    $ot = strtolower($ot);
    assert($ot=='asc' || $ot=='desc');

    $from_clause = "  FROM (article a INNER JOIN organisation o ON o.id=a.srcorg)\n";

    $where_clause = '';
    if( $conds )
        $where_clause = '  WHERE ' . implode( ' AND ', $conds ) . "\n";

    if( $o=='publication' )
        $o = 'lower(o.prettyname)';
    if( $o =='byline')
        $o = 'lower(byline)';
    if( $o =='title')
        $o = 'lower(title)';

    $order_clause = sprintf("  ORDER BY %s %s\n",
        $o, $ot );
    $limit_clause = sprintf("  OFFSET %d LIMIT %d\n",
        $offset, $limit );

    $sql = "SELECT a.id,a.title,a.byline,a.description,a.permalink, a.pubdate, a.lastscraped, ".
       "o.id as pub_id, o.shortname as pub_shortname, o.prettyname as pub_name, o.home_url as pub_home_url\n" .
       $from_clause .
       $where_clause .
       $order_clause .
       $limit_clause;

    $arts = db_getAll($sql, $params);

    $sql = "SELECT COUNT(*)\n" . $from_clause . $where_clause;
    $total = intval(db_getOne($sql, $params));

    return array(&$arts,$total);
}


// pull together everything we need to display the page, then invoke the template
function view()
{
    $per_page = 100;

    $f= new FilterForm($_GET,array(),array());
    $arts = null;
    $pager = null;
    $total = null;
    if($f->is_valid()) {
        $page = arr_get('p',$_GET,0);
        $o = arr_get('o',$_GET,'pubdate');
        $ot = arr_get('ot',$_GET,'desc');
        $offset = $page * $per_page;
        $limit = $per_page;
        list($arts, $total) = grab_articles($f->cleaned_data, $o, $ot, $offset, $limit);
        $pager = new Paginator($total,$per_page,'p');
    }

    $v = array('filter'=>$f, 'arts'=>$arts, 'paginator'=>$pager);
    template($v);
}


// custom column types for tabulator used in the template
class ArtColumn extends Column {
    function fmt($row) {
        $url = article_url($row['id']);
        $permalink = $row['permalink'];
        $adm_url = article_adm_url($row['id']);
        return sprintf('<a href="%s">%s</a><small> [<a href="%s">source</a>]</small>',
            $adm_url, $row['title'], $permalink );
    }
}

class LinkColumn extends Column {
    function fmt($row) {
        $text = $row[$this->opts['text']];
        $url = $row[$this->opts['href']];
        return sprintf('<a href="%s">%s</a>', $url, $text);
    }
}

function template($vars)
{
    $tabulator = new Tabulator(array(
        new Column('id',array('sortable'=>TRUE)),
        new Column('pubdate',array('sortable'=>TRUE)),
        new LinkColumn('publication',array('sortable'=>TRUE, 'text'=>'pub_name', 'href'=>'pub_home_url')),
        new ArtColumn('title',array('sortable'=>TRUE)),
        new Column('byline',array('sortable'=>TRUE))));
    // article_admin_link($a)


    extract($vars);
    admPageHeader();

?>
<h2>Show articles</h2>
<form action="articles" method="GET">
<table>
<?= $filter->as_table(); ?>
</table>
<input type="submit" name="submit" value="go" />
</form>

<?php if( $arts) { ?>
<p class="paginator"><?= $paginator->render() ?> <?= $paginator->total ?> articles</p>
<table class="results">
<?= $tabulator->as_table($arts); ?>
</table>
<p class="paginator"><?= $paginator->render() ?> <?= $paginator->total ?> articles</p>
<?php }?>

<?php
    admPageFooter();
}


view();

