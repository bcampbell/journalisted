<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

include "/usr/share/php5/xapian.php";

$DBPATH = OPTION_JL_XAPDB;

define( 'XAP_ARTICLE_ID', 0 );
define( 'XAP_TITLE_ID', 1 );
define( 'XAP_PUBDATE_ID', 2 );
define( 'XAP_SRCORG_ID', 3 );
define( 'XAP_PERMALINK_ID', 4 );
define( 'XAP_JOURNOS_ID', 5 );

page_header( "" );

$kw = get_http_var( 'kw', '' );

?>

<div id="maincolumn">
<h2>Full text search</h2>
<small>
<p>examples:<br/>
<code>hash browns</code> - (articles with hash OR brown)<br/>
<code>"hash browns"</code> - (use quotes for exact phrase)<br/>
<code>title:grapefruit</code> - (search only titles)<br/>
<code>byline:"ben goldacre"</code><br/>
<code>byline:"ben goldacre" AND gillian</code> - (ben goldacres articles containing "gillian")<br/>
Detailed query syntax <a href="http://www.xapian.org/docs/queryparser.html">here</a>.
</p>

</small>

<form method="get">
Search for: <input type="text" name="kw" value="<?php echo htmlspecialchars($kw); ?>" />

<input type="submit" value="go" />
</form>

<?php

if( $kw )
{
    Doit( $kw );
}

?>
</div>
<?php

page_footer();



function PostedFragment( &$r )
{
    $orgs = get_org_names();
    $org = $orgs[ $r['srcorg'] ];
    $pubdate = pretty_date(strtotime($r['pubdate']));

    return "<cite class=\"posted\"><a href=\"{$r['permalink']}\">{$pubdate}, <em>{$org}</em></a></cite>";
}



function Doit( $kw )
{
    global $DBPATH;

try {
    $orgs = get_org_names();

    $database = new XapianDatabase( $DBPATH );

    // Start an enquire session.
    $enquire = new XapianEnquire($database);

    $query_string = $kw;

    $qp = new XapianQueryParser();
    $stemmer = new XapianStem("english");
    $qp->set_stemmer($stemmer);
    $qp->set_database($database);
    $qp->set_stemming_strategy(XapianQueryParser::STEM_SOME);

    $qp->add_prefix( 'byline', 'B' );
    $qp->add_prefix( 'title', 'T' );

    $query = $qp->parse_query($query_string);
    print "<pre>Parsed query is: {$query->get_description()}</pre>\n";

    // Find the top 10 results for the query.
    $enquire->set_query($query);
    $matches = $enquire->get_mset(0,1000);

    // Display the results.
    print "<p>{$matches->get_matches_estimated()} results found:</p>\n";
    print "<ul>\n";
    $i = $matches->begin();
    while (!$i->equals($matches->end())) {
    	$n = $i->get_rank() + 1;
        $doc = $i->get_document();
    	$data = $doc->get_data();

//ARTICLE_ID = 0
//TITLE_ID = 1
//PUBDATE_ID = 2
//SRCORG_ID = 3
//PERMALINK_ID = 4
        $article_id = $doc->get_value( 0 );

        /* a bunch of values are stored in the xapian db for us, so
         * we don't have to look them up in the main db.
         */
        $art = array(
            'id'=>$article_id,
            'title'=> $doc->get_value(1),
            'pubdate'=> $doc->get_value(2),
            'srcorg' => $doc->get_value(3),
            'permalink' => $doc->get_value(4) );

        $headlinelink = "<a href=\"/article?id={$art['id']}\">{$art['title']}</a>\n";
        $postedfrag = PostedFragment($art);
        $url = "/article?id=" . $article_id;
    	//print "<li>{$i->get_percent()}% \"{$headlinelink}\"<br/>{$postedfrag}</li>\n";
    	print "<li>\"{$headlinelink}\"<br/>{$postedfrag}</li>\n";
    	$i->next();
    }
    print "</ul>\n";
} catch (Exception $e) {
    print $e->getMessage() . "\n";
    exit(1);
}
}
?>

