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



$_sortable_fields= array('id','pubdate','lastscraped','title','publication','byline');


class Paginator
{
    /* page indexes begin at 0 (but are displayed to user as pagenum+1) */
    function __construct($total, $per_page, $page_var="p") {
        $this->page_var = $page_var;
        $this->total = $total;
        $this->per_page = $per_page;
        $this->num_pages = intval(($total+$per_page-1)/$per_page);

        /* remember current page might not be valid :-) */
        $page = intval(arr_get($this->page_var, $_GET));
/*        if($page < 0)
            $page = 0;
        if($page > $this->num_pages-1)
            $page = $this->num_pages-1;*/
        $this->page = $page;
    }

    function link($pagenum) {
        if($pagenum == $this->page) {
            return sprintf('<span class="this-page">%d</span>', $pagenum+1);
        }
        $params = array_merge($_GET, array($this->page_var=>$pagenum));
        list($path) = explode("?", $_SERVER["REQUEST_URI"], 2);
        $url = $path . "?" . http_build_query($params);
        # TODO: rel="next/prev" etc...
        return sprintf( '<a href="%s">%d</a>', $url, $pagenum+1);
    }

    function render() {
        $endmargin = 2;
        $midmargin = 3;

        if( $this->num_pages<2 )
            return '';

        $current = $this->page;
/*        if($current<0)
            $current =0;
        if($current>$this->num_pages-1)
            $current = $this->num_pages-1;
 */
        $sections = array();

        // each section is range: [startpage,endpage)
        $sections[] = array(0,$endmargin);
        $sections[] = array($current - $midmargin, $current + $midmargin + 1);
        $sections[] = array($this->num_pages-$endmargin, $this->num_pages);
        // clip sections
        foreach($sections as &$s) {
            if($s[0] < 0)
                $s[0] = 0;
            if($s[1] > $this->num_pages)
                $s[1] = $this->num_pages;
        }
        unset($s);

        // coallese adjoining/overlapping sections
        if($sections[1][1] >= $sections[2][0]) {
            $sections[1][1] = $sections[2][1];
            unset($sections[2]);
        }
        if($sections[0][1] >= $sections[1][0]) {
            $sections[0][1] = $sections[1][1];
            unset($sections[1]);
        }

        $parts = array();
        foreach($sections as $s) {
            $pagelinks=array();
            for($n=$s[0]; $n<$s[1]; ++$n) {
                $pagelinks[] = $this->link($n);
            }
            $parts[] = implode(' ', $pagelinks);
        }

        return implode(' ... ',$parts);
    }
};



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
        $this->fields['title'] = new CharField(array('max_length'=>200,'required'=>FALSE));
        $this->fields['byline'] = new CharField(array('max_length'=>200,'required'=>FALSE));
        $this->fields['url'] = new CharField(array('max_length'=>200,'required'=>FALSE));
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
        $params[] = '%' . $values['byline'] . '%';
	}

/*
	if( $f['url'] ) {
        $conds[] = "permalink ilike ?";
        $params[] = '%' . $values['url'] . '%';
    }
 */

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
    print_r($total);

    return array(&$arts,$total);
}



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
<form action="articles" method="GET">
<table>
<?= $filter->as_table(); ?>
</table>
<input type="submit" name="submit" value="go" />
</form>

<?php if( $arts) { ?>
<p class="paginator">
<?= $paginator->render() ?> <?= $paginator->total ?> articles
</p>
<table>
<?= $tabulator->as_table($arts); ?>
</table>
<?php }?>

<?php
    admPageFooter();
}


view();

