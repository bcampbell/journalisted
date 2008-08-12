<?php

//phpinfo();

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/xap.php';
//require_once '../phplib/misc.php';
//require_once '../phplib/gatso.php';
//require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/person.php';


/* get journo identifier (eg 'fred-bloggs') */

$paper_id = get_http_var( 'id', null );

#page_header( $title, $pageparams );
page_header( "Custom Papers" );

$P = person_if_signed_on(true); /* Don't renew any login cookie. */

$paper = null;
if( $paper_id )
{
    $paper = db_getRow( "SELECT * FROM custompaper WHERE id=?", $paper_id );
}

if( $paper )
{
    emit_paper( &$paper );
?>
<br />
<br />
<a href="/custompaper">back to custom newspaper index</a>
<?php
}
else
{
    emit_public_paper_list();
?>
<a href="/custompaper_edit">Edit your custom newspapers</a>
<?php
}

page_footer();


function emit_public_paper_list()
{
    global $P;

?>
<h2>Index of Custom Newspapers</h2>
<ul>
<?php
    $person_id = $P->id();
    if($P)
        $papers = db_getAll( "SELECT * FROM custompaper WHERE is_public=true OR owner=?", $P->id() );
    else
        $papers = db_getAll( "SELECT * FROM custompaper WHERE is_public=true" );

    foreach( $papers as $p )
    {
        $name = $p['name'];
        $desc = $p['description'];
        $paper_id = $p['id'];
        $link = "/custompaper?id={$paper_id}";

//        $edit = '';
//        if( $p['owner'] == $person_id )
//            $edit = " [<a href=\"custompaper_edit?id={$paper_id}\">edit</a>]\n";
        $access = $p['is_public'] == 't' ? "":" (private)";

        print "<li><a href=\"{$link}\">{$name}</a>{$access} - <em>{$desc}</em></li>\n";
    }
?>
</ul>
<?php

}



function PostedFragment( &$r )
{
    $orgs = get_org_names();
    $org = $orgs[ $r['srcorg'] ];
    $pubdate = pretty_date(strtotime($r['pubdate']));

    return "<cite class=\"posted\"><a href=\"{$r['permalink']}\">{$pubdate}, <em>{$org}</em></a></cite>";
}


function emit_paper( &$paper )
{
    $paper_id = $paper['id'];

    print "<h2>{$paper['name']}</h2>\n";
    print "<p><em>{$paper['description']}</em></p>\n";
    $criteria = db_getAll( "SELECT * FROM custompaper_criteria_text WHERE paper_id=?", $paper_id );

    $queries = array();
    foreach( $criteria as $c )
    {
        $queries[] = $c['query'];
    }


    print "<small>(articles matching \n";
    foreach( $queries as $q )
        print "\"<code>$q</code>\" ";
    print " over the last 24 hours)</small>\n";

    $end_dt = new DateTime( 'now', new DateTimeZone('UTC') );
 
    $start_dt = new DateTime( 'now', new DateTimeZone('UTC') );
    $start_dt->modify( "-24 days" );
    #$start_dt->modify( "-24 hours" );
#     print $d->format( DATE_RFC3339 );

    $arts = xap_DoQueries( $queries,
        $start_dt->format( DATE_RFC3339 ),
        $end_dt->format( DATE_RFC3339 ) );

    print "<hr>\n";

    if( $arts )
    {

        foreach( $arts as $art )
        {
            $jl_link = "/article?id={$art['id']}";
            $posted = PostedFragment( $art );

            print "<div>\n";
            print " <h3><a href=\"{$art['permalink']}\">{$art['title']}</a></h3>\n";
            print " {$art['description']}<br/>\n";
            print " $posted (<a href=\"$jl_link\">journalisted page</a>)<br/>\n";
            print "</div>\n";
        }
    }
    else
    {
        print "<p>No articles</p>\n";
    }
}

?>
