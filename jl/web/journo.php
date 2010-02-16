<?php

//phpinfo();

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );
$journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
if(!$journo)
{
    spew_404( $ref );
    exit(1);
}

// is logged in, and with edit rights to this journo?
$can_edit_page = FALSE;
$P = person_if_signed_on();
if( !is_null($P) )
{
    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo['id'] ) ) {
        $can_edit_page = TRUE;
    }
}


$pageparams = array(
    'rss'=>array( 'Recent Articles'=>journoRSS( $journo ) ),
    'head_extra_fn'=>'extra_head',
);



if( get_http_var( 'allarticles' ) == 'yes' ) {
    $title = "All articles by " . $journo['prettyname'];
    page_header( $title, $pageparams );

?>
<div class="main">
<?php    journo_emitAllArticles( $journo ); ?>
</div>
<?php

    page_footer();
    exit;
}

$title = $journo['prettyname'];
page_header( $title, $pageparams );

// just use journo id to index cache... other pages won't clash.
$cacheid = 'json_' . $journo['id'];
$data = null;

if( strtolower( get_http_var('full') == 'yes' ) ) {
    /* force a full page rebuild (slow) */
    $data = journo_collectData( $journo );
    $json = json_encode($data);

    db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
	db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)", $cacheid, $json );
    db_do( "UPDATE journo SET modified=false WHERE id=?", $journo['id'] );
	db_commit();
} else {

    /* look for cached data to build the page with */
    $cached_json = db_getOne( "SELECT content FROM htmlcache WHERE name=?", $cacheid );

    if( is_null( $cached_json ) ) {
        /* uh-oh... page is missing from the cache...  generate a quick n nasty version right now! */
        $data = journo_collectData( $journo, true );
        $json_quick = json_encode($data);

        /* mark journo as needing their page sorted out! */
        db_do( "UPDATE journo SET modified=true WHERE id=?", $journo['id'] );
        /* save the quick-n-nasty data */
        db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)", $cacheid, $json_quick );
        db_commit();
    } else {
        /* there is cached data - yay! */
        $data = json_decode( $cached_json,true );

        if( $can_edit_page && $journo['modified'] == 't' ) {
            /* journo is logged in and the page is out of date...
             * update the cached data with some fresh quick-n-nasty data
             * (which covers most of what a journo might be editing via their profile page, say)
             */

            $old_quick_n_nasty = $data[ 'quick_n_nasty' ];
            $newdata = journo_collectData( $journo, true );
            $data = array_merge( $data, $newdata );
            /* if there was non-quick-n-nasty data there, this makes sure it'll still be used in the template */
            $data['quick_n_nasty'] = $old_quick_n_nasty;

            /* store it in the cache for other users to enjoy too :-) */
            $updated_json = json_encode($data);
            db_do( "DELETE FROM htmlcache WHERE name=?", $cacheid );
            db_do( "INSERT INTO htmlcache (name,content) VALUES(?,?)", $cacheid, $updated_json );
            db_commit();
        }

    }
}


// some stuff we don't cache:
$data['can_edit_page'] = $can_edit_page;
// recent editing changes (from the eventlog) - would be fine to cache this list, but we'd
// need to make sure the page template only displayed events which were less than than a day or so old...
$data['recent_changes'] = journo_fetchRecentEvents( $journo['id'] );

/* all set - invoke the template to render the page! */
{
    extract( $data );
    include "../templates/journo.tpl.php";
}

page_footer();



/*
 * HELPERS
 */


function extra_head()
{


    $tab = get_http_var( 'tab', 'work' );
?>
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<!-- <link type="text/css" rel="stylesheet" href="/profile.css" /> -->

<script type="text/javascript">
$(document).ready(
    function() {
<?php if( $tab=='bio' ) { ?>
        $('#tab-work').hide();
        $('#tab-contact').hide();
<?php } elseif( $tab=='contact' ) { ?>
        $('#tab-work').hide();
        $('#tab-bio').hide();
<?php } else /*work*/ { ?>
        $('#tab-bio').hide();
        $('#tab-contact').hide();
<?php } ?>
        $(".tabs a[href='#tab-work']").click( function() {
            $('.tabs li').removeClass('current');
            $(this).closest('li').addClass('current');
            $('#tab-bio').hide();
            $('#tab-contact').hide();
            $('#tab-work').show();
            return false;
        });
        $(".tabs a[href='#tab-bio']").click( function() {
            $('.tabs li').removeClass('current');
            $(this).closest('li').addClass('current');
            $('#tab-work').hide();
            $('#tab-contact').hide();
            $('#tab-bio').show();
            return false;
        });
        $(".tabs a[href='#tab-contact']").click( function() {
            $('.tabs li').removeClass('current');
            $(this).closest('li').addClass('current');
            $('#tab-work').hide();
            $('#tab-bio').hide();
            $('#tab-contact').show();
            return false;
        });

        var searchLabel = $('.journo-profile .search form label').remove().text();
        $('#findarticles').addClass('placeholder').val(searchLabel).focus(function() {
            if (this.value == searchLabel) {
                $(this).removeClass('placeholder').val('');
            };
        }).blur(function() {
            if (this.value == '') {
                $(this).addClass('placeholder').val(searchLabel);
            };
        });
        $('.journo-profile .search form').submit(function() {
            if ($('#findarticles').val() == searchLabel) {
                $('#findarticles').val('');
            }
        });

});
</script>
<?php

}


// show a fancy 404 page with suggested matching journos
function spew_404( $ref )
{
    header("HTTP/1.0 404 Not Found");

    $query = preg_replace( '/[^a-z]+/', ' ', trim($ref) );

    $title = "Couldn't find \"" . h(ucwords($query)) . "\"";
    page_header( $title );



    $journos = journo_FuzzyFind( $query );

?>
<h2><?= $title ?></h2>
<?php if( $query ) { ?>
<p>Did you perhaps mean one of these?</p>

<ul>
<?php   foreach( $journos as $j ) { ?>
  <li><?= journo_link($j); ?></li>
<?php   } ?>
</ul>
<?php } ?>

<form method="get" action="/search">
 <input type="hidden" name="type" value="journo" />
 <input type="text" size="40" name="q" value="<?= h($query) ?>" />
 <input type="submit" name="action" value="Search for Journalist" />
</form>

<?php


    page_footer();
}





