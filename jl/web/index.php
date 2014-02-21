<?php

/*
 * index.php - the front page for journalisted.com
 *
 * Most of this page is generated offline and cached.
 * see ../bin/offline-page-build tool for details
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/cache.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../phplib/journo.php';
require_once '../phplib/article.php';



function extra_head()
{
?>
<script language="javascript" type="text/javascript">
    $(document).ready( function () {
        // equalHeight() by Rob Glazebrook
        function equalHeight(group) {
            var tallest = 0;
            group.each(function() {
                var thisHeight = $(this).height();
                if(thisHeight > tallest) {
                    tallest = thisHeight;
                }
            });
            group.height(tallest);
        }
        equalHeight( $(".recently-viewed .body,.recently-updated .body,.most-blogged .body") );
/*        equalHeight( $(".box.thisweek,.box.tobias") ); */
    });
</script>
<?php
}



function view()
{


    // setup for placeholder text in input forms
    $head_extra = <<<EOT
      <script type="text/javascript" language="JavaScript">
        window.onload=function() {
          activatePlaceholders();
        }
      </script>
EOT;


    page_header( "", array( 'menupage'=>'cover', 'head_extra_fn'=>"extra_head" ) );

    // get most-recently-updated journos from event log
    $recently_updated  = array();

    $sql = <<<EOT
SELECT j.ref AS journo_ref, j.prettyname as journo_prettyname, j.oneliner as journo_oneliner, min(now()-e.event_time) as when
    FROM event_log e LEFT JOIN journo j ON j.id=e.journo_id
    WHERE event_time>NOW()-interval '7 days' AND j.status='a'
    GROUP BY journo_ref, journo_prettyname, journo_oneliner
    ORDER BY min( now()-e.event_time) ASC
    LIMIT 10;
EOT;

    $events = db_getAll( $sql );
    foreach( $events as &$ev ) {
        $recently_updated[] = array( 'ref'=> $ev['journo_ref'], 'prettyname'=>$ev['journo_prettyname'], 'oneliner'=>$ev['journo_oneliner'] );
    }

    // invoke the template
    {
        include "../templates/frontpage.tpl.php";
    }

    page_footer();
}


view();

?>
