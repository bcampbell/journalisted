<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

/* hmm... not happy about this absolute path here... */
include "/usr/share/php5/xapian.php";

$DBPATH = OPTION_JL_XAPDB;

# IDs for extra values tied to articles in the xapian db
# NOTE: these need to be kept in sync with bin/indexer! (written in python)
define( 'XAP_ARTICLE_ID', 0 );
define( 'XAP_TITLE_ID', 1 );
define( 'XAP_PUBDATE_ID', 2 );
define( 'XAP_SRCORG_ID', 3 );
define( 'XAP_PERMALINK_ID', 4 );
define( 'XAP_JOURNOS_ID', 5 );

define( 'DEFAULT_NUM_PER_PAGE', 100 );

page_header( "" );

$query = get_http_var( 'q', '' );
$num_per_page = (int)get_http_var('num', DEFAULT_NUM_PER_PAGE );
$start = (int)get_http_var('start', '0' );


?>

<div id="maincolumn">
<h2>Full text search</h2>

<?php

EmitQueryForm( $query, $num_per_page );

if( $query )
{
    DoQuery( $query, $start, $num_per_page );
}

?>
</div>
<?php

page_footer();



function EmitQueryForm( $query, $num_per_page )
{

?>
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
Search for: <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" />

<input type="submit" value="go" />
</form>

<?php

}



/* helper for formatting results */
function PostedFragment( &$r )
{
    $orgs = get_org_names();
    $org = $orgs[ $r['srcorg'] ];
    $pubdate = pretty_date(strtotime($r['pubdate']));

    return "<cite class=\"posted\"><a href=\"{$r['permalink']}\">{$pubdate}, <em>{$org}</em></a></cite>";
}


/* helper for formatting results */
function DecodeJournoList( $jlist )
{
    $journos = array();

    if( $jlist )
    {
        foreach( explode( ',', $jlist ) as $s )
        {
            $parts = explode('|',$s);
            $journos[] = array( 'ref'=>$parts[0], 'prettyname'=>$parts[1] );
        }
    }
    return $journos;
}


function PageURL( $query, $start, $num_per_page )
{
    return sprintf( "/search?q=%s&start=%d&num=%d",
        urlencode($query), $start, $num_per_page );
}


function EmitPageControl( $query, $start, $num_per_page, $total )
{
    $pagecnt = $total/$num_per_page;
    $currentpage = $start/$num_per_page;

    $firstpage = max( 0, $currentpage-3 );
    $lastpage = min( $pagecnt, $currentpage+3 );

    if( $start>0 && $total>0 )
    {
        printf( "<a href=\"%s\">%s</a> ",
            PageURL($query,
                max(0,$start-$num_per_page),
                $num_per_page),
            "Previous" );
    }

    for( $page=$firstpage; $page<=$lastpage; ++$page )
    {
        if( $page == $currentpage ) {
            printf("%s ", $page+1 );
        } else {
            printf( "<a href=\"%s\">%s</a> ",
                PageURL($query, $page*$num_per_page, $num_per_page),
                $page+1 );
        }
    }

    if( $start+$num_per_page < $total )
    {
        printf( "<a href=\"%s\">%s</a> ",
            PageURL($query, $start+$num_per_page, $num_per_page),
            "Next" );
    }

    print "<br/>\n";


}



/* perform query, display results */
function DoQuery( $query_string, $start, $num_per_page )
{
    global $DBPATH;

    try {
        $orgs = get_org_names();

        $database = new XapianDatabase( $DBPATH );

        // Start an enquire session.
        $enquire = new XapianEnquire($database);

        $qp = new XapianQueryParser();
        $stemmer = new XapianStem("english");
        $qp->set_stemmer($stemmer);
        $qp->set_database($database);
        $qp->set_stemming_strategy(XapianQueryParser::STEM_SOME);

        $qp->add_prefix( 'byline', 'B' );
        $qp->add_prefix( 'title', 'T' );

        $query = $qp->parse_query($query_string);
    /*    print "<pre>Parsed query is: {$query->get_description()}</pre>\n"; */

        $enquire->set_query($query);
        $matches = $enquire->get_mset($start, $num_per_page);

        $total = max( $matches->get_matches_estimated(), $start+$matches->size() );


        // Display the results.
        printf( "<p>Showing %d-%d of around %d:</p>\n",
            $start+1,
            min( $start+$num_per_page, $total),
            $total );

        print "<ul>\n";
        $i = $matches->begin();
        while (!$i->equals($matches->end())) {
            $n = $i->get_rank() + 1;
            $doc = $i->get_document();
            $data = $doc->get_data();

            $article_id = $doc->get_value( XAP_ARTICLE_ID );

            /* a bunch of values are stored in the xapian db for us, so
             * we don't have to look them up in the main db.
             */
            $art = array(
                'id'=>$article_id,
                'title'=> $doc->get_value( XAP_TITLE_ID ),
                'pubdate'=> $doc->get_value( XAP_PUBDATE_ID ),
                'srcorg' => $doc->get_value( XAP_SRCORG_ID ),
                'permalink' => $doc->get_value( XAP_PERMALINK_ID ),
                'journos' => DecodeJournoList( $doc->get_value( XAP_JOURNOS_ID ) ) );

            $headlinelink = "<a href=\"/article?id={$art['id']}\">{$art['title']}</a>\n";
            $postedfrag = PostedFragment($art);

            $journolinks = array();
            foreach( $art['journos'] as $j )
            {
                $journolinks[] = sprintf( "<a href=\"%s\">%s</a>", '/'.$j['ref'], $j['prettyname'] );
            }

            $journofrag = '';
            if( $journolinks )
                $journofrag = ' <small>(' . implode( ', ', $journolinks ) . ")</small>";

            //print "<li>{$i->get_percent()}% \"{$headlinelink}\"<br/>{$postedfrag}</li>\n";
            print "<li>\"{$headlinelink}\"<br/>{$postedfrag}{$journofrag}</li>\n";
            $i->next();
        }
        print "</ul>\n";

        if( $total >= $num_per_page || $start>0 )
        {
            EmitPageControl( $query_string, $start, $num_per_page, $total );
        }
    } catch (Exception $e) {
        print $e->getMessage() . "\n";

    }
}
?>

