<?php

//phpinfo();

require_once '../conf/general';
//require_once '../phplib/page.php';
//require_once '../phplib/misc.php';
//require_once '../phplib/gatso.php';
//require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
//require_once '../../phplib/utility.php';


/* hmm... not happy about this absolute path here... */
#include "/usr/share/php5/xapian.php";
require_once 'xapian.php';

$DBPATH = OPTION_JL_XAPDB;


# IDs for extra values tied to articles in the xapian db
# NOTE: these need to be kept in sync with bin/indexer! (written in python)
define( 'XAP_ARTICLE_ID', 0 );
define( 'XAP_TITLE_ID', 1 );
define( 'XAP_PUBDATE_ID', 2 );
define( 'XAP_SRCORG_ID', 3 );
define( 'XAP_PERMALINK_ID', 4 );
define( 'XAP_JOURNOS_ID', 5 );



function xap_DoQueries( &$query_strings, $start_time, $end_time )
{
    global $DBPATH;

    if( !$query_strings)
        return array();

    try {
        $database = new XapianDatabase( $DBPATH );

        // Start an enquire session.
        $enquire = new XapianEnquire($database);

        $qp = new XapianQueryParser();
        $stemmer = new XapianStem("english");
        $qp->set_stemmer($stemmer);
        $qp->set_database($database);
        $qp->set_stemming_strategy(XapianQueryParser::STEM_SOME);
        $qp->set_default_op( XapianQuery::OP_AND );

        /* allow date ranges in queries, but just using string compares, so be careful! */
#        $r = new XapianStringValueRangeProcessor( XAP_PUBDATE_ID );
#        $qp->add_valuerangeprocessor( $r );

        $qp->add_prefix( 'byline', 'B' );
        $qp->add_prefix( 'title', 'T' );
        $qp->add_prefix( 'journo', 'J' );

        /* parse and combine queries */

        $query = null;
        foreach( $query_strings as $q )
        {
            $newquery = $qp->parse_query( $q );
            if( $query )
                $query = new XapianQuery( XapianQuery::OP_OR, $query, $newquery );
            else
                $query = $newquery;
        }

        $datequery = new XapianQuery( XapianQuery::OP_VALUE_RANGE, XAP_PUBDATE_ID, $start_time, $end_time );
        $query = new XapianQuery( XapianQuery::OP_AND, $query, $datequery );


//        print "<pre>Parsed query is: {$query->get_description()}</pre>\n"; 

//        if( $sort_order == 'date' ) {
//            $enquire->set_sort_by_value_then_relevance( XAP_PUBDATE_ID );
//        }   /* (default is relevance) */


        $start = 0;
        $num_per_page = 200;

        $enquire->set_query($query);
        $matches = $enquire->get_mset($start, $num_per_page);
/*
        $total = max( $matches->get_matches_estimated(), $start+$matches->size() );


    printf("<pre>Got %d</pre>\n", $total ); */
        $results = array();

        $i = $matches->begin();
        while (!$i->equals($matches->end())) {
            $n = $i->get_rank() + 1;
            $doc = $i->get_document();
//            $data = $doc->get_data();

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
#                'journos' => DecodeJournoList( $doc->get_value( XAP_JOURNOS_ID ) ),
            );

            //TODO: KILL KILL KILL
            // UGLY UGLY HACK.
            $art['description'] = db_getOne( "SELECT description FROM article WHERE id=?", $article_id );

            $results[] = $art;
            $i->next();
        }
        return $results;

    } catch (Exception $e) {
        print $e->getMessage() . "\n";

    }

    return null;
}
?>
