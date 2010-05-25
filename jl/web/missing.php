<?php

/* 
 * Page to show information about why we might be missing articles for
 * a journo and to submit links.
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../../phplib/utility.php';


function extra_head()
{
?>
<script language="javascript" type="text/javascript">
    $(document).ready( function () {
    });
</script>
<?php
}

function run_json( $cmd )
{
    $cmd = escapeshellcmd( $cmd ) . ' 2>&1';
    $out = `$cmd`;

    return json_decode( $out, TRUE );
}

function clean_url( $url )
{
    $bits = crack_url( $url );
    // force http (but allow https too)
    if( strtolower( $bits['scheme'] ) != 'https' ) {
        $bits['scheme'] = 'http';
    }
    return glue_url($bits);
}


$P = person_if_signed_on(); /* (ugly hack to force login processing here, which might involve outputing http headers for cookies) */

$ref = strtolower( get_http_var( 'j' ) );   /* eg 'fred-bloggs' */
$_journo = NULL;
if( $ref ) {
    $_journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname,status FROM journo WHERE ref=?", $ref );

    if( $_journo && $_journo['status'] != 'a' ) {
        // only users with edit permissions can continue if journo is hidden/inactive
        if( !canEditJourno( $_journo['id'] ) ) {
            $_journo = NULL;
        }
    }
}


if( !$_journo ) {
    page_header('');
?>
<p>No journalist specified</p>
<?php
    page_footer();
    return;
}






class ItemSubmitter
{
    public $url = null;
    public $title = null;
    public $pubdate = null;
    public $publication = null;

    public $state = 'initial';
    public $errs = array();
    public $pagePath = '/missing';

    public $finished = FALSE;

    function __construct()
    {
        global $_journo;

        $this->url = get_http_var( 'url' );
        if( $this->url ) {
            $this->url = clean_url( $this->url );
        }

        $this->title = get_http_var( 'title' );
        $this->pubdate = get_http_var( 'pubdate' );
        $this->publication = get_http_var( 'publication' );
        $this->action = get_http_var( 'action' );

        // so we can detect if url is changed
        $this->prev_url = get_http_var( 'prev_url' );

        if( !$this->url && !$this->prev_url ) {
            $this->state = 'initial';
            return;
        }

        $msg = is_sane_article_url( $this->url );
        if( !is_null( $msg ) ) {
            $state = 'bad_url';
            $this->errs['url'] = $msg;
            return;
        }

        $srcid = scrape_CalcSrcID( $this->url );
        if( $srcid ) {
            // we should be able to scrape it
            $r = scrape_ScrapeArticle( $this->url );
            if( $r['status'] == 'fail' ) {
                // uh-oh. queue it up for admin attention
                db_do( "INSERT INTO missing_articles (journo_id,url) VALUES (?,?)",
                    $_journo['id'], $this->url );
                db_commit();
                $this->state = 'scrape_failed';
                $this->errs['error_message'] = "Journa<i>listed</i> had problems reading this article";
                return;
            }

            // fetch the details of the articles
            $art = db_getRow( "SELECT title,pubdate FROM article WHERE id=?", $r['article']['id'] );

            $this->title = $art['title'];
            $this->pubdate = $art['pubdate'];

            // result was 'new' or 'already_had'
            // make sure it's attributed to _this_ journo.
            $got_expected_journo = FALSE;
            foreach( $r['article']['journos'] as $j ) {
                if( $j['id'] == $_journo['id'] ) {
                    $got_expected_journo = TRUE;
                    break;
                }
            }

            if( $got_expected_journo ) {
                $this->state = 'done';
            } else {
                $this->state = 'journo_mismatch';
                $this->errs['error_message'] = "Journa<i>listed</i> had trouble reading the byline";
            }
        } else {
            // it's an article we don't cover - need to collect title,pubdate,publication...

            if( $this->url != $this->prev_url ) {
                // got a new url.
                // try and guess some basic details by scraping the page:
                $cmd = OPTION_JL_FSROOT . "/bin/generic-scrape \"{$this->url}\"";
                $cmd = escapeshellcmd( $cmd ) . ' 2>&1';
                $out = `$cmd`;
                $guessed = json_decode( $out, TRUE );
                if( !is_null( $guessed ) && $guessed[0]['status'] == 'ok' ) {
                    // fill in any empty fields with out guesses
                    if( !$this->title )
                        $this->title = $guessed[0]['title'];
                    if( !$this->publication )
                        $this->publication = $guessed[0]['publication'];
                    // TODO: should verify journo name appears on page...
                }
                $this->state = 'details_required';
            } else {
                // submitting again - see if we can store 'em
                if( $this->_check_details() ) {
                    // it's OK! store it etc...
                    $this->state = 'done';
                } else {
                    $this->state = 'details_required';
                }
            }
        }
    }



    function emit()
    {
/*
?>
<pre>
<?= $this->state ?>
</pre>
<?php
*/
        switch( $this->state ) {
            case 'details_required':
                $this->_emit_form( TRUE );
                break;
            case 'journo_mismatch':
            case 'scrape_failed':
                $this->_emit_failed();
                break;
            case 'done':
                $this->_emit_finished();
                break;
            case 'initial':
            case 'bad_url':
            default:
                $this->_emit_form(FALSE);
                break;
        }
    }

    function errhint( $field ) {
        if( array_key_exists( $field, $this->errs ) ) {
            return '<span class="errhint">' . $this->errs[$field] . '</span>';
        } else {
            return '';
        }
    }

    function _emit_form( $show_extra )
    {
        global $_journo;
?>
<form action="<?= $this->pagePath ?>" method=POST>
<dl>
  <dt><label for="url">URL</label></dt>
  <dd>
    <input type="text" name="url" id="url" class="url" value="<?= h($this->url); ?>" />
<?= $this->errhint('url') ?>
  </dd>
<?php if( $show_extra ) { ?>

  <dt><label for="title">article title</label></dt>
  <dd>
    <input type="text" name="title" id="title" class="headline" value="<?= h($this->title); ?>" />
<?= $this->errhint('title') ?>
  </dd>

  <dt><label for="pubdate">date of publication</label></dt>
  <dd>
    <input type="text" name="pubdate" id="pubdate" class="date" value="<?= h($this->pubdate); ?>" />
<?= $this->errhint('pubdate') ?>
  </dd>

  <dt><label for="publication">publication</label></dt>
  <dd>
    <input type="text" name="publication" id="publication" value="<?= h($this->publication); ?>" />
<?= $this->errhint('publication') ?>
  </dd>

<?php } ?>
</dl>

<input type="hidden" name="prev_url" value="<?= h($this->url); ?>" />
<input type="hidden" name="j" value="<?= $_journo['ref']; ?>" />
<input type="hidden" name="action" value="<?= $show_extra ? "submit_extra":"submit"?>" />
<input type="submit" value="Submit" />
</form>
<?php
    }


    function _emit_finished() {
?>
    <div class="infomessage">
    <p>Thank you - the article has been added to your profile</p>
    </div>
<?php
    }

    function _emit_failed() {
?>
<p class="errormessage"> <?= $this->errs['error_message'] ?></p> </p>
<p>Sorry, the article couldn't be added immediately.</p>
<p>It has been flagged for manual approval by the journa<i>listed</i> team.</p>
<?php
    }


    function _check_details() {

        // date is required
        if( $this->pubdate == '' ) {
            $this->errs['pubdate'] = "Please enter the date the article was published";
        } else {
            $dt = strtotime( $this->pubdate );
            if( !$dt ) {
                $this->errs['pubdate'] = "Please enter a valid date";
            }
        }

        // title is required
        if( $this->title == '' ) {
            $this->errs['title'] = "Please enter the title of the article";
        }

        return( $this->errs ? FALSE:TRUE );
    }
}










$item = new ItemSubmitter();
$title = "Submit missing articles for " . $_journo['prettyname'];

page_header($title);

?>
<div class="main">

<h2><?= $title ?></h2>

<?php
$item->emit();

?>
<a href="/<?= $_journo['ref'] ?>">Go back to your profile page</a>

</div> <!-- end main -->

<div class="sidebar">
  <div class="box">
    <div class="head">
    <h3>Why might an article be missing?</h3>
    </div>
    <div class="body">
      <p>An article may not appear on a journalist's page if:</p>
      <ul>
        <li>It was not published in one of the <a href="/about#whichoutlets">news outlets</a> we cover (but that's OK - tell us anyway so we can list it)</li>
        <li>It was not bylined</li>
        <li>The byline was mis-spelt in the original publication</li>
        <li>The byline was contained within the text of the article so our system could not find it</li>
        <li>It was published before we started</li>
        <li>It was published in a 'registration required' area of the news outlet's website</li>
      </ul>
    </div>
  </div>
</div>  <!-- end sidebar -->
<?php

page_footer();




// return true if user is logged in and has access to this journo
function canEditJourno( $journo_id )
{
    $P = person_if_signed_on();

    if( is_null( $P ) )
        return FALSE;

    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo_id ) ) {
        return TRUE;
    } else {
        return FALSE;
    }
}




//
function is_sane_article_url( $url )
{
    $bits = crack_url( $url );
    if( $bits === FALSE )
        return "Please enter the full url of this article";
    // default to http://
    if( $bits['scheme'] == '' ) {
        $bits['scheme'] = 'http';
    }

    $host = trim( $bits['host'] );
    $scheme = trim( strtolower( $bits['scheme'] ) );
    $path = trim( $bits['path'] );
    $query = trim( $bits['query'] );

    if( $host == '' ) {
        return "Please enter the full url of this article";
    }

    // no ftp: or internal file: links please!
    if( $scheme != 'http' && $scheme != 'https' ) {
        return "Sorry, \"{$scheme}://\" urls are not supported";
    }

    // hostnames probably shouldn't have spaces in them...
    // (proably user entering  a headline... sigh...)
    if( strpos( $host, ' ' ) !== False ) {
        return "Please enter a valid url";
    }

    // make sure we've got at least a non-blank path (or a non-blank query)
    if( ($path=='' || $path=='/') && $query=='' ) {
        return "Please enter the FULL url of this article";
    }

    return null;
}

