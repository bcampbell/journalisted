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
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

require_once '../phplib/drongo-forms/lib/forms.php';

$_time_choices = array('all'=>'All', '1hr'=>'Last 1 hr', '24hrs'=>'Last 24 hrs', '7days'=>'Last 7 days', '30days'=>'Last 30 days');
// map to postgres intervals
$_time_intervals = array( 'all'=>null, '1hr'=>'1 hour', '24hrs'=>'24 hours', '7days'=>'7 days', '30days'=>'30 days');


$_order_choices = array('pubdate'=>'PubDate', 'lastscraped'=>"LastScraped", "title"=>'title');

// form for filtering article list
class FilterForm extends Form {

    function __construct($data,$files,$opts) {
        global $_time_choices;
        global $_order_choices;

        $publication_choices = array('any'=>'Any');
        $r = db_query("SELECT shortname, prettyname FROM organisation");
        while($row = db_fetch_array($r)) {
            $publication_choices[$row['shortname']]= $row['prettyname'];
        }

        parent::__construct($data,$files,$opts);
        $this->fields['pub'] = new ChoiceField(array('choices'=>$publication_choices,'required'=>FALSE));
        $this->fields['headline'] = new CharField(array('max_length'=>200,'required'=>FALSE));
        $this->fields['byline'] = new CharField(array('max_length'=>200,'required'=>FALSE));
        $this->fields['url'] = new CharField(array('max_length'=>200,'required'=>FALSE));
        $this->fields['pubdate'] = new ChoiceField(array('choices'=>$_time_choices,'required'=>FALSE));
        $this->fields['lastscraped'] = new ChoiceField(array('choices'=>$_time_choices,'required'=>FALSE));
        $this->fields['orderby'] = new ChoiceField(array('choices'=>$_order_choices,'required'=>FALSE));
    }
}



function grab_articles($f, $offset, $limit)
{
    global $_time_intervals;
    global $_order_choices;

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

/*    if( $f['srcorg'] != 'any' ) {
        $conds[] = "srcorg = ?";
        $params[] = $values['srcorg'];
    }
 */

	if( $f['headline'] ) {
        $conds[] = "title ilike ?";
        $params[] = '%' . $f['headline'] . '%';
	}

	if( $f['byline'] ) {
        $conds[] = "byline ilike ?";
        $params[] = '%' . $values['byline'] . '%';
	}

/*
	if( $f['url'] ) {
        $conds[] = "permalink ilike ?";
        $params[] = '%' . $values['url'] . '%';
    }
 */

    $sql = "SELECT id,title,byline,description,permalink,srcorg,pubdate " .
       'FROM article';

    if( $conds ) {
        $sql = $sql . ' WHERE ' . implode( ' AND ', $conds );
    }

    $orderby = arr_get('orderby',$f,'pubdate');
    if(!isset($_order_choices[$orderby]))
        throw new Exception("bad orderby.");

    $sql = $sql . ' ORDER BY ' . $orderby . ' DESC OFFSET ? LIMIT ?';
    $params[] = $offset;
    $params[] = $limit;

//    $q = db_query( $sql, $params );
    return db_getAll( $sql, $params );
}



function view()
{
    $f= new FilterForm($_GET,array(),array());
    $arts = null;
    if($f->is_valid()) {
        $arts = grab_articles($f->cleaned_data, 0, 200);
    }
    $v = array('filter'=>$f, 'arts'=>$arts );
    template($v);
}


// custom column type for tabulator
class ArtColumn extends Column {
    function fmt($row) {
        $url = article_url($row['id']);
        $permalink = $row['permalink'];
        $adm_url = article_adm_url($row['id']);
        return sprintf('<a href="%s">%s</a><small> [<a href="%s">source</a>]</small>',
            $adm_url, $row['title'], $permalink );
    }
}


function template($vars)
{

    $tabulator = new Tabulator(array(
        new Column('id'),
        new Column('pubdate',array('sortable'=>TRUE)),
//        new LinkColumn('publication',array('sortable'=>TRUE,'display'=>'srcorgname', href=>'srcorgurl')),
        new ArtColumn('article'),
        new Column('byline')));
    // article_admin_link($a)

    extract($vars);
    admPageHeader();

?>
<form action="articles" method="GET">
<table>
<?= $filter->as_table(); ?>
</table>
<input type="submit" name="submit" value="go" />
</form>

<?php if( $arts) { ?>
<table>
<?= $tabulator->as_table($arts); ?>
</table>
<?php }?>

<?php
    admPageFooter();
}


view();

