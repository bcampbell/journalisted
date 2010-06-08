<?php

require_once '../conf/general';
require_once '../../phplib/db.php';

require_once 'xapian.php';



# IDs for extra values tied to articles in the xapian db
# NOTE: these need to be kept in sync with bin/indexer! (written in python)
define( 'XAP_PUBDATETIME_ID', 0 );  // "YYYYMMDDHHMMSS"
define( 'XAP_PUBDATE_ID', 1 );      // "YYYYMMDD"


class XapSearch {

    // estimated total result count (after calling run())
    public $total_results=null;

    private $r = null;

    function XapSearch() {
        $this->database = new XapianDatabase( OPTION_JL_XAPDB );

        // Start an enquire session.
        $this->enquire = new XapianEnquire($this->database);

        $this->qp = new XapianQueryParser();
        $this->stemmer = new XapianStem("english");
        $this->qp->set_stemmer($this->stemmer);
        $this->qp->set_database($this->database);
        $this->qp->set_stemming_strategy(XapianQueryParser::STEM_SOME);
        $this->qp->set_default_op( XapianQuery::OP_AND );

        /* allow date ranges in queries
         * dates follow this format: "YYYYMMDD"
         */
        $this->r = new XapianDateValueRangeProcessor( XAP_PUBDATE_ID );
        $this->qp->add_valuerangeprocessor( $this->r );

        $this->qp->add_prefix( 'author', 'A' );
        $this->qp->add_prefix( 'title', 'T' );
        $this->qp->add_prefix( 'journo', 'J' );
        $this->qp->add_prefix( 'srcorg', 'O' );
    }




    function set_query( $query_strings, $journo_id=null )
    {
        /* parse and combine queries */
        $query = null;
        if( !is_array( $query_strings ) )
            $query_strings = array( $query_strings );

        foreach( $query_strings as $q )
        {
            $newquery = $this->qp->parse_query( $q );
            if( $query )
                $query = new XapianQuery( XapianQuery::OP_OR, $query, $newquery );
            else
                $query = $newquery;
        }

        /* restrict search to the single journo? */
        if( $journo_id ) {
            $jfilt = new XapianQuery( "J{$journo_id}" );
            $query = new XapianQuery( XapianQuery::OP_AND, $jfilt, $query );
        }

#        $datequery = new XapianQuery( XapianQuery::OP_VALUE_RANGE, XAP_PUBDATE_ID, $start_time, $end_time );
#        $this->query = new XapianQuery( XapianQuery::OP_AND, $query, $datequery );


    //        print "<pre>Parsed query is: {$query->get_description()}</pre>\n"; 

    //        if( $sort_order == 'date' ) {
    //            $enquire->set_sort_by_value_then_relevance( XAP_PUBDATE_ID );
    //        }   /* (default is relevance) */

        $this->enquire->set_query($query);
    }


    // $offset - offset of first result to return
    // $limit - maximum number of results to return
    // sort_order  -  'date' or 'relevance'
    function run( $offset, $limit, $sort_order ) {
        if( $sort_order == 'date' ) {
            // XAP_PUBDATETIME_ID format is "YYYYMMDDHHMMSS"
            $this->enquire->set_sort_by_value_then_relevance( XAP_PUBDATETIME_ID );
        }   /* (default is relevance) */


        $checkatleast = max( $limit, 1000 );
        $matches = $this->enquire->get_mset($offset, $limit, $checkatleast );

        $this->total_results = max( $matches->get_matches_estimated(), $offset+$matches->size() );

        $results = array();

        $i = $matches->begin();
        while (!$i->equals($matches->end())) {
            $n = $i->get_rank() + 1;
            $doc = $i->get_document();

            /* the things we want to be able to display in search results are
             * stored in the document data, serialised using json
             * (and with short, cryptic names :-)
             */

            /* (use DateTime::createFromFormat instead of preg_match() if we upgrade to php 5.3) */
            preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $doc->get_value(XAP_PUBDATETIME_ID), $m);
#            $pubdate = new DateTime();
#            $pubdate->setDate( $m[1], $m[2], $m[3] );   // YYYY MM DD
#            $pubdate->setTime( $m[4], $m[5], $m[6] );   // HH MM SS

            $pubdate = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";

            $doc_data = $doc->get_data();
            $d = json_decode( $doc_data, TRUE );
            $art = array(
                'id'=>$d['i'],
                'title'=>$d['t'],
                'srcorg'=>$d['o'],
                'permalink'=>$d['l'],
                'description'=>$d['d'],
                'pubdate'=>$pubdate,
                'journos'=>array(),
            );

            foreach( $d['j'] as $j ) {
                $art['journos'][] = array(
                    'id'=>$j['i'],
                    'ref'=>$j['r'],
                    'prettyname'=>$j['n'] );
            }

            $results[] = $art;

            // images should be optional.
//            $art['images'] = db_getAll( "SELECT url,caption,credit FROM article_image WHERE article_id=?", $article_id );

            $i->next();
        }
        return $results;
    }
}


?>
