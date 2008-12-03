<?php

require_once '../conf/general';
require_once '../phplib/page.php';
/*require_once '../phplib/frontpage.php'; */
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

/* hmm... not happy about this absolute path here... */
include "xapian.php";

$DBPATH = OPTION_JL_XAPDB;

# IDs for extra values tied to articles in the xapian db
# NOTE: these need to be kept in sync with bin/indexer! (written in python)
define( 'XAP_ARTICLE_ID', 0 );
define( 'XAP_TITLE_ID', 1 );
define( 'XAP_PUBDATE_ID', 2 );
define( 'XAP_SRCORG_ID', 3 );
define( 'XAP_PERMALINK_ID', 4 );
define( 'XAP_JOURNOS_ID', 5 );

define( 'DEFAULT_NUM_PER_PAGE', 25 );


$query = get_http_var( 'q', '' );
$num_per_page = (int)get_http_var('num', DEFAULT_NUM_PER_PAGE );
$start = (int)get_http_var('start', '0' );
$sort_order = get_http_var( 'o', 'relevance' );

/* was a particular journo specified? (can be an id or ref) */
$journo = null;
$id_or_ref = get_http_var( 'j', null );
if( $id_or_ref )
{
    $sql = sprintf( "SELECT id,ref,prettyname FROM journo WHERE %s=?",
        (int)$id_or_ref ? "id" : "ref" );
    $journo = db_getRow( $sql, $id_or_ref );
}

page_header( "" );

?>
<div id="maincolumn">
<h2>Find similar articles</h2>
<?php



EmitQueryForm( $query, $sort_order, $num_per_page, $journo );
if( $query )
    DoQuery( $query, $sort_order, $start, $num_per_page, $journo );

?>
</div>
<?php

page_footer();




/*------------------------------------*/

function FetchJourno( $id_or_ref )
{
}




function EmitQueryForm( $query, $sort_order, $num_per_page, $journo=null )
{

?>
<form method="post" action="/searchtest">
<p>Paste text to match here:</p>
<textarea name="q" rows="40" cols="100"><?php echo htmlspecialchars($query); ?></textarea>
<?php
    if( $journo ) {
        printf( "<input type=\"hidden\" name=\"j\" value=\"%s\" />\n",
            $journo['ref'] );
    }

?>
<select name="o">
 <option <?php echo $sort_order=='relevance'?'selected ':''; ?>value="relevance">order by relevance</option>
 <option <?php echo $sort_order=='date'?'selected ':''; ?>value="date">order by date</option>
</select>
<br/>
<input type="submit" value="Find" />
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


function PageURL( $query, $sort_order, $start, $num_per_page )
{
    $url = sprintf( "/search?q=%s&start=%d&num=%d",
        urlencode($query), $start, $num_per_page );
    if( $sort_order!='date' )
        $url .= "&o={$sort_order}";
    return $url;
}

function EmitPageControl( $query, $sort_order, $start, $num_per_page, $total )
{
    $pagecnt = $total/$num_per_page;
    $currentpage = $start/$num_per_page;

    $n = 20;
    $firstpage = max( 0, $currentpage-$n/2 );
    $lastpage = min( $pagecnt, $firstpage+($n-1) );

    if( $start>0 && $total>0 )
    {
        printf( "<a href=\"%s\">%s</a> ",
            PageURL($query,
                $sort_order,
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
                PageURL($query, $sort_order, $page*$num_per_page, $num_per_page),
                $page+1 );
        }
    }

    if( $start+$num_per_page < $total )
    {
        printf( "<a href=\"%s\">%s</a> ",
            PageURL($query, $sort_order, $start+$num_per_page, $num_per_page),
            "Next" );
    }

    print "<br/>\n";


}



/* perform query, display results */
function DoQuery( $query_string, $sort_order, $start, $num_per_page, $journo=null )
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
        $qp->set_default_op( XapianQuery::OP_ELITE_SET );

        $start_time = getmicrotime();

/*
        $qp->add_prefix( 'byline', 'B' );
        $qp->add_prefix( 'title', 'T' );
        $qp->add_prefix( 'journo', 'J' );
*/
        $query = $qp->parse_query($query_string,0);

/*        print "<pre>Parsed query is: {$query->get_description()}</pre>\n"; */

        if( $sort_order == 'date' ) {
            $enquire->set_sort_by_value_then_relevance( XAP_PUBDATE_ID );
        }   /* (default is relevance) */

        printf("QUERY:<pre>\n%s\n</pre>\n", $query->get_description() );


        $enquire->set_query($query);
#        $enquire->set_cutoff( 50 );
        $matches = $enquire->get_mset($start, $num_per_page);

        $total = max( $matches->get_matches_estimated(), $start+$matches->size() );

        $finish_time = getmicrotime();
        $elapsed = $finish_time - $start_time;
        printf( "<p>query took %.5f seconds</p>\n", $elapsed );




        // Display the results.
        if( $total == 0 )
        {
            print( "<p>None found.</p>\n" );
            return;
        }
        printf( "<p>Showing %d-%d of around %d articles, %s:</p>\n",
            $start+1,
            min( $start+$num_per_page, $total),
            $total,
            $sort_order=='date'?'newest first':'most relevant first' );

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
            print "<li><strong>{$i->get_percent()}%</strong> \"{$headlinelink}\"<br/>{$postedfrag}{$journofrag}</li>\n";
            $i->next();
        }
        print "</ul>\n";

        if( $total >= $num_per_page || $start>0 )
        {
            EmitPageControl( $query_string, $sort_order, $start, $num_per_page, $total );
        }
    } catch (Exception $e) {
        print $e->getMessage() . "\n";

    }
}
?>

