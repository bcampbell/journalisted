<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class WeblinksPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "weblinks";
        $this->pagePath = "/profile_weblinks";
        $this->pageTitle = "Weblinks";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();

        /* if submitting, hold fields as class-wide data, so we can add error messages etc... */
        $this->submitted = null;
        $this->badSubmit = false;
        if( get_http_var('action') == 'submit' ) {
            $this->submitted = $this->weblinksFromHTTPVars();
            /* check links, agument with error message if there is one */
            foreach( $this->submitted as &$w ) {
                $err = $this->checkWebLink( $w );
                if( !is_null( $err ) ) {
                    $this->badSubmit = true;
                    $w['err'] = $err;
                }
            }
        }
    }


    function extra_head()
    {
?>
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-util.js"></script>
<script type="text/javascript">

    $(document).ready( function() {

        function initEntry() {
            var e = $(this);
            e.find('.remove').click( function() { e.remove(); return false; } );
        }

        /* only display description field if site kind is other */
        function showHideDesc(sel) {
            if( sel.val() == '' /* "Other..." */ ) {
                sel.closest('dl').find('.desc').fadeIn();
            } else {
                sel.closest('dl').find('.desc').hide();
            }
        }
        $('.weblink dl select').each( function() {
            showHideDesc($(this));
        }).change( function() {
            showHideDesc($(this));
        });

        /* set up 'add' link - clone template to create a new entry */
        $('.weblink .template' ).hide();
        $('.weblink .add').click( function() {
            var c = $('.weblink .template').clone();
            jl.normalizeElement( c );
            c.removeClass('template');
            c.insertBefore( '.weblink .template' )
            c.each( initEntry );
            c.fadeIn();
            return false;
        });

        $('.weblink dl' ).each( initEntry );


/*        fancyForms( '.weblink', { plusLabel: 'Add another website' } ); */
    });
</script>
<?php
    }




    function handleActions()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            if( $this->badSubmit )
                return; // just go on to redisplay form with errors
            $this->handleSubmit();
        }
        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }

        if( $action == '' )
            return;

        // jump back to journo page
        $this->Redirect( "/{$this->journo['ref']}" );
    }



    function weblinksFromHTTPVars()
    {
        $urls = get_http_var( 'url' );
        $kinds = get_http_var( 'kind' );
        $descs = get_http_var( 'desc' );
        $ids = get_http_var( 'id' );

        $weblinks = array();
        while( !empty($urls) ) {
            $w = array(
                'url'=>array_shift($urls),
                'kind'=>array_shift($kinds),
                'description'=>array_shift($descs),
                'id'=>array_shift($ids) );

            // only use non-blank ones
            if( $w['url'] || ($w['kind']=='' && $w['description']!='' ) )
                $weblinks[] = $w;
        }

        return $weblinks;
    }


    function checkWebLink( &$w ) {
        if( $w['kind']=='' && $w['description']=='' ) {
            return 'Please enter a description';
        }

        if( $w['url']=='' ) {
            return 'Please enter a URL';
        }

        return null;    // all OK.
    }


    function display()
    {
        $weblinks = null;
        if( $this->badSubmit ) {
            $weblinks = $this->submitted;
        } else {
            $weblinks = db_getAll( "SELECT * FROM journo_weblink WHERE journo_id=? ORDER BY rank DESC", $this->journo['id'] );
        }

        $home_url = '';
        $twitter_name = '';



?>

<h2><?= $this->journo['prettyname'] ?> on the web</h2>
<form class="weblink" method="POST" action="<?= $this->pagePath; ?>">

<?php
        foreach( $weblinks as $w ) {
            $this->emitLinkFields( $w );
        }
        $this->emitLinkFields( null );
?>
  <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
  <input type="hidden" name="action" value="submit" />
  <div class="button-area">
    <a class="add" href="#">Add a site</a><br/>
    <button class="submit" type="submit">Save changes</button> or <a href="/<?= $this->journo['ref'] ?>">cancel</a>
  </div>
</form>
<p>Note: journa<i>listed</i> reserves the right to change or remove links</p>
<?php
    }


    function emitLinkFields( $w=NULL )
    {
        static $uniq = 0;

        $kinds = array(
            'blog'=>'My Blog',
            'homepage'=>'My Website',
            'profile'=>'Profile/Bio',
            'twitter'=>'Twitter',
            ''=>'Other...' );

        $is_template = false;
        if( is_null( $w ) ) {
            $is_template = true;
            $w = array('id'=>'', 'url'=>'','kind'=>'blog', 'description'=>'' );
        }
        $err = null;
        if( array_key_exists( 'err', $w ) )
            $err = $w['err'];

?>
<?php if($err) { ?>
 <span class="errhint"><?= h($err) ?></span>
<?php } ?>
 <dl class="<?= $is_template?'template':'' ?>">
  <dt><span class="faux-label">Site</span></dt>
  <dd>
    <select name="kind[]" id="kind_<?= $uniq ?>">
<?php foreach( $kinds as $k=>$ktxt ) { $sel=($k==$w['kind'])?' selected':''; ?>
      <option value="<?= $k ?>"<?=$sel?>><?= $ktxt ?></option>
<?php } ?>
    </select>
    <input type="text" size="60" id="url_<?= $uniq ?>"name="url[]" value="<?= h($w['url']) ?>" />
  </dd>
  <dt><label class="desc" for="desc_<?= $uniq; ?>">Description</label></dt>
  <dd>
    <input class="desc" type="text" size="60" id="desc_<?= $uniq ?>" name="desc[]" value="<?= h($w['description']) ?>" />
<?php if( $w['id'] ) { ?>
  </dd>
  <dd>
    <a class="remove" href="<?= $this->pagePath ?>?ref=<?= $this->journo['ref'] ?>&remove_id=<?= $w['id'] ?>">Remove</a>
<?php } else { ?>
    <a class="remove" href="#">Remove</a>
<?php } ?>
  </dd>

 </dl>
 <input type="hidden" name="id[]" value="<?= $w['id'] ?>" />
<?php
        ++$uniq;
    }


    function ajax()
    {
        return NULL;
    }



    function handleSubmit()
    {
        db_do( "DELETE FROM journo_weblink WHERE journo_id=?", $this->journo['id'] );
        $rankstep = 10;
        $rank = 100 + $rankstep*sizeof($this->submitted);
        foreach( $this->submitted as &$w ) {
            db_do( "INSERT INTO journo_weblink (journo_id,kind,url,description,approved,rank) VALUES (?,?,?,?,true,?)",
                $this->journo['id'],
                $w['kind'],
                $w['url'],
                $w['description'],
                $rank );
            $w['id'] = db_getOne( "SELECT lastval()" );
            $rank = $rank - $rankstep;
        }
        db_commit();
        eventlog_Add( 'modify-weblinks', $this->journo['id'] );
    }



    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_weblink WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
        eventlog_Add( 'remove-weblinks', $this->journo['id'] );
    }


}




$page = new WeblinksPage();
$page->run();


