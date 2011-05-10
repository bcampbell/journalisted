<?php

//phpinfo();

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/article.php';
require_once '../phplib/journo.php';
require_once "../phplib/journo_rdf.php";
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$ref = strtolower( get_http_var( 'ref' ) );
$journo = db_getRow( "SELECT * FROM journo WHERE ref=?", $ref );
if(!$journo)
{
    spew_404( $ref );
    exit(1);
}

// is logged in, and with edit rights to this journo?
$can_edit_page = FALSE;

$P = null;
if( get_http_var( 'login' ) ) {
    /* force a login (so we can send edit links out to journos
       without explaining that they have to log in before the 'edit'
       buttons appear) */
    $r = array(
        'reason_web' => "Edit your journalisted profile",
        'reason_email' => "Edit your journalisted profile",
        'reason_email_subject' => "Edit your journalisted profile"
        );
    $P = person_signon($r);
} else {
    $P = person_if_signed_on();
}

if( !is_null($P) )
{
    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND ((journo_id=? AND permission='edit') OR permission='admin')",
        $P->id(), $journo['id'] ) ) {
        $can_edit_page = TRUE;
    }
}


// if journo is not active, only allow viewing if logged-in user can edit page

if( $journo['status'] == 'i' ) {
    // activate journo if they've met the requirements
    if( journo_checkActivation( $journo['id'] ) ) {
        $journo['status'] = 'a';
    }
}


if( $journo['status'] != 'a' && !$can_edit_page ) {
    spew_404( $ref );
    exit(1);
}


$pageparams = array(
    'rss'=>array( 'Recent Articles'=>journoRSS( $journo ) ),
    'head_extra_fn'=>'extra_head',
    'pingbacks'=>TRUE,
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

// UGH!
if( $journo['status'] == 'a' ) {
    db_do( "DELETE FROM recently_viewed WHERE journo_id=?", $journo['id'] );
    db_do( "INSERT INTO recently_viewed (journo_id) VALUES (?)", $journo['id'] );
    db_commit();
}


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

// fields that we've recently added, which might not be in cached versions
if( !array_key_exists('fake',$data ) ) {
    $data['fake'] = False;
}

// some stuff we don't cache:
$data['can_edit_page'] = $can_edit_page;

// recent editing changes (from the eventlog) - would be fine to cache this list, but we'd
// need to make sure the page template only displayed events which were less than than a day or so old...
$data['recent_changes'] = journo_fetchRecentEvents( $journo['id'] );

// add some derived fields to the monthly_stats data
if( !$data[ 'quick_n_nasty' ] && array_key_exists( 'monthly_stats', $data ) ) {
    foreach( $data['monthly_stats'] as $yearmonth=>&$row ) {
        $dt = new DateTime( "{$yearmonth}-01" );
        // javascript timestamp
        $jsts = (int)($dt->format('U')) * 1000;

        // Dogey assumption that all months have 31 days
        // (php5.2 has crappy date fns)
        // xapian range is string-based anyway, so we'll be fine.
        $range = "{$yearmonth}-01..{$yearmonth}-31";
        $row['search_url'] = "/search?a=" . urlencode( $range ) . "&by=". $journo['ref'];
    }
    unset( $row );
}



/* all set - invoke a template to render the page! */
$fmt = get_http_var( 'fmt','html' );
if( $fmt == 'text' ) {
    /* plaintext version */
    extract( $data );

    header( "Content-Type: text/plain" );
    include "../templates/journo_text.tpl.php";
} else if( $fmt == 'rdfxml' ) {
    header( "Content-Type: application/rdf+xml" );
    journo_emitRDFXML( $data );
} else if( $fmt == 'n3' ) {
    header( "Content-Type: text/plain" );   // text/n3
    journo_emitN3( $data );
} else {
    /* normal html version */
    $title = $journo['prettyname'];
    page_header( $title, $pageparams );
    {
        extract( $data );
        if( $journo['ref'] == 'tobias-grubbe' ) {
            include "../templates/tobias.tpl.php";
        } else {
            include "../templates/journo.tpl.php";
        }
    }
    page_footer();
}




/*
 * HELPERS
 */


function extra_head()
{
    global $journo;
    global $data;

?>
<link rel="alternate" type="application/rdf+xml" href="/data/journo/<?= $journo['ref'] ?>" />


<!-- <script language="javascript" type="text/javascript" src='http://getfirebug.com/releases/lite/1.2/firebug-lite-compressed.js'></script> -->
<script language="javascript" type="text/javascript" src="/js/raphael-min.js"></script>
<script language="javascript" type="text/javascript" src="/js/jquery.ba-hashchange.min.js"></script>
<?php if($data['twitter_id']) { ?>
<script language="javascript" type="text/javascript" src="/js/jquery.tweet.js"></script>
<?php } ?>
<script language="javascript" type="text/javascript">
    $(document).ready(
    function () {
        var tabs = $('.tabs li');
        function setTab( tabname ) {
            if( tabname=='' ) {
                /* default to first tab */
                tabname = tabs.find('a').attr('href');
            }
            tabs.each( function() {
                tabid = $('a',this).attr('href');
                if( tabid == tabname ) {
                    $(tabid).show();
                    $(this).addClass( 'current' );
                } else {
                    $(tabid).hide();
                    $(this).removeClass( 'current' );
                }
            } );
        }
        $(window).bind( 'hashchange', function() {
            var hash = location.hash;
            setTab( hash );
        } );
        $(window).trigger( 'hashchange' );

        /* */

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

<?php if($data['twitter_id']) { ?>
        $("#tweets").tweet({
            username: "<?= $data['twitter_id'] ?>",
            join_text: "auto",
            avatar_size: 32,
            count: 3,
/*
            auto_join_text_default: "said,",
            auto_join_text_ed: "",
            auto_join_text_ing: "was",
            auto_join_text_reply: "replied to",
            auto_join_text_url: "was checking out",
*/
//            template: '{text}{time}<div style="clear:both;"></div>',  //"{avatar}{time}{join}{text}",
            template: '{time}:<br/>{text}',  //"{avatar}{time}{join}{text}",
            loading_text: "loading tweets..."
        });

//        $("#twitter_profile").bind("load", function(){
        //
            var proto = ('https:' == document.location.protocol ? 'https:' : 'http:');
            var foo_url = proto+'//api.twitter.com/1/users/show.json?screen_name=<?= $data['twitter_id'] ?>&callback=?';
            //alert(foo_url);
            $.getJSON(foo_url, function(data){
                var args = data;
                var profile = '<img src="{profile_image_url}" />';
                profile += '<span class="screen_name">@<a href="http://www.twitter.com/<?= $data['twitter_id']; ?>">{screen_name}</a></span><br/>';
                profile += '<span><span class="count">{statuses_count}</span><span class="stat">Tweets</span></span>';
                profile += '<span><span class="count">{friends_count}</span><span class="stat">Following</span></span>';
                profile += '<span><span class="count">{followers_count}</span><span class="stat">Followers</span></span>';
                profile += '<div style="clear:both;"></div>';
                profile = profile.replace(/\{([_a-z]+)\}/g, function (m, n) { return args[n]; });

               $("#twitter_profile").append(profile);
            });
//        });


<?php } ?>


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





