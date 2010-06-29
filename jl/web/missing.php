<?php

/* 
 * Page to show information about why we might be missing articles for
 * a journo and to submit links.
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/journo.php';
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



// returns a datetime obj, or NULL
function parse_date( $d ) {
    // DateTime parser treats 01/25/2010 as mm/dd/yyyy,
    // but 01-25-2010 as dd/mm/yyyy. sigh.
    // Test for this case and handle it ourselves.
    $m = array();
    if( 1==preg_match( '%(?P<day>[0-9]?[1-9])[-/](?P<month>[0-9]?[1-9])[-/](?P<year>[0-9]{2,4})%', $d, $m ) ) {
        $day = intval( $m['day'] );
        $month = intval( $m['month'] );
        $year = intval( $m['year'] );

        if( checkdate( $month, $day, $year ) ) {
            $dt = new DateTime();
            $dt->setDate( $year, $month, $day );
            return $dt;
        }
    } else {
        // just use DateTime parser
        $dt = date_create( $d );
        if( $dt )
            return $dt;
    }
    return null;
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





// class to handle submitting an article
class ArticleSubmitter
{
    public $journo = null;

    public $url = '';
    public $prev_url = '';
    public $title = '';
    public $pubdate = ''; // as input by a user (ie a string in "dd/mm/yyyy" format)
    public $pubdate_dt = null; // as converted into a DateTime obj (null if $pubdate is bad)
    public $publication = '';

    public $state = 'initial';
    public $errs = array();
    public $pagePath = '/missing';

    public $finished = FALSE;

    public $guessed_flag = FALSE;    // have any fields been guessed?

    // if $blank is set, then don't read out from http vars at all
    function __construct( $journo, $blank=FALSE )
    {
        $this->journo = $journo;

        if( !$blank ) {
            $this->url = get_http_var( 'url','' );
            if( $this->url ) {
                $this->url = clean_url( $this->url );
            }

            $this->title = get_http_var( 'title','' );
            $this->pubdate = get_http_var( 'pubdate','' );
            $dt = parse_date( $this->pubdate );
            if( $dt )
                $this->pubdate_dt = $dt;

            $this->publication = get_http_var( 'publication','' );

            // so we can detect if url is changed
            $this->prev_url = get_http_var( 'prev_url', '' );
        }

        if( $blank || (!$this->url && !$this->prev_url) ) {
            $this->state = 'initial';
            return;
        }

        $msg = is_sane_article_url( $this->url );
        if( !is_null( $msg ) ) {
            $this->errs['url'] = $msg;
            $this->state = 'bad_url';
            return;
        }

        $srcid = scrape_CalcSrcID( $this->url );
        if( $srcid ) {
            // we should be able to scrape it
            $r = scrape_ScrapeArticle( $this->url );
            if( $r['status'] == 'fail' ) {
                $this->_queue_missing( 'failed to scrape' );
                $this->errs['error_message'] = "Journa<i>listed</i> had problems reading this article";
                $this->state = 'scrape_failed';
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
                if( $j['id'] == $this->journo['id'] ) {
                    $got_expected_journo = TRUE;
                    break;
                }
            }

            if( $got_expected_journo ) {
                $this->state = 'done';
                return;
            } else {
                $this->_queue_missing( "scraped, but didn't get expected journo" );
                $this->errs['error_message'] = "Journa<i>listed</i> had trouble reading the byline";
                $this->state = 'journo_mismatch';
                return;
            }
        } else {
            // it's an article we don't cover - need to collect title,pubdate,publication...

            // check for dupes
            if( db_getOne( "SELECT id FROM journo_other_articles WHERE url=? AND journo_id=? AND status='a'", $this->url, $this->journo['id'] ) ) {
                $this->errs['url'] = "This article has already been added";
                $this->state = 'bad_url';
                return;
            }

            if( $this->url != $this->prev_url ) {
                // url is new or has changed - try and autofill
                // fields by scraping the page
                $this->_guess_details();
                $this->state = 'details_required';
                return;
            } else {
                // submitting again - see if we can store 'em
                if( $this->_check_details() ) {
                    $this->_add_other_article();
                    $this->state = 'done';
                    return;
                } else {
                    $this->state = 'details_required';
                    return;
                }
            }
        }
    }

    // have we reached the end of processing for this article?
    // We still might be display an info or error message in emit(), but the
    // actual work is now complete - emit() won't output a form.
    function is_finished()
    {
        switch( $this->state ) {
            case 'journo_mismatch':
            case 'scrape_failed':
            case 'done':
                return TRUE;
            default:
                return FALSE;
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
?>
<form action="<?= $this->pagePath ?>" method=POST>
<?php if( $show_extra ) { ?>
We need some details about this article.<br/>
<?php if( $this->guessed_flag ) { ?>
We've made some guesses - <em>please check and correct any mistakes</em><br/>
<?php } ?>
<?php } ?>
<?php if( $this->state=='initial' or $this->state=='bad_url' ) { ?>
Please enter the URL of the article:<br/>
<?php } ?>
<dl>
  <dt><label for="url">URL</label></dt>
  <dd>
    <input type="text" name="url" id="url" class="url" value="<?= h($this->url); ?>" /><br/>
    <span class="explain">e.g. "http://www.thedailynews.com/articles/12345.html"</span></br/>
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
    <span class="explain">(dd/mm/yyyy)</span>
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
<input type="hidden" name="j" value="<?= $this->journo['ref']; ?>" />
<input type="submit" value="Submit" />
</form>
<?php
    }


    function _emit_finished() {
?>
    <div class="infomessage">
    <p>Thank you - the article '<em><?= h($this->title) ?></em>' has been added</p>
    </div>
<?php
    }

    function _emit_failed() {
?>
<p class="errormessage"> <?= $this->errs['error_message'] ?></p> </p>
<p>Sorry, the article couldn't be added immediately.<br/>
It has been flagged for manual approval by the journa<i>listed</i> team.</p>
<?php
    }


    function _check_details() {

        // date is required
        if( $this->pubdate == '' ) {
            $this->errs['pubdate'] = "Please enter the date the article was published";
        } else {
            if( is_null( $this->pubdate_dt ) ) {
                $this->errs['pubdate'] = "Please enter a valid date";
            }
        }

        // title is required
        if( $this->title == '' ) {
            $this->errs['title'] = "Please enter the title of the article";
        }

        return( $this->errs ? FALSE:TRUE );
    }


    // add the article to the other_articles table
    function _add_other_article()
    {
        $sql = <<<EOT
INSERT INTO journo_other_articles ( journo_id, url, title, pubdate, publication, status )
VALUES ( ?,?,?,?,?,? )
EOT;
        $art = array(
            'journo_id'=>$this->journo['id'],
            'url'=>$this->url,
            'title'=>$this->title,
            'publication'=>$this->publication,
            'status'=>canEditJourno( $this->journo['id'] ) ? 'a':'u',
            'pubdate_iso' => $this->pubdate_dt->format(DateTime::ISO8601) );
        db_do( $sql,
            $art['journo_id'],
            $art['url'],
            $art['title'],
            $art['pubdate_iso'],
            $art['publication'],
            $art['status'] );
        $art['id'] = db_getOne( "SELECT lastval()" );
        db_commit();
        eventlog_Add( 'submit-otherarticle', $this->journo['id'], $art );
    }


    function _queue_missing( $reason )
    {
        // uh-oh. queue it up for admin attention
        db_do( "INSERT INTO missing_articles (journo_id,url,reason) VALUES (?,?,?)",
            $this->journo['id'],
            $this->url,
            $reason
        );
        db_commit();
    }

    function _guess_details()
    {
        // try and guess some basic details by scraping the page:
        $cmd = OPTION_JL_FSROOT . "/bin/generic-scrape \"{$this->url}\"";
        $cmd = escapeshellcmd( $cmd ) . ' 2>&1';
        $out = `$cmd`;
        $guessed = json_decode( $out, TRUE );
        $g = $guessed[0];
        if( !is_null( $guessed ) && $g['status'] == 'ok' ) {
            // fill in any empty fields with out guesses
            $this->title = $g['title'];
            $this->pubdate = $g['pubdate'];
            $dt = parse_date( $this->pubdate );
            if( $dt ) {
                $this->pubdate_dt = $dt;
            }
            $this->publication = $g['publication'];

            if( $g['title'] || $g['pubdate'] || $g['publication'] )
                $this->guessed_flag = TRUE;

            // TODO: should verify journo name appears on page...
            // (can't do much with that information at this stage,
            // except maybe flag it up as requiring admin attention...)
        }
    }
}







$item = new ArticleSubmitter( $_journo );

if( canEditJourno( $_journo['id'] ) ) {
    $title = "Add articles you have written";
} else {
    $title = "Submit missing articles for " . $_journo['prettyname'];
}

$newly_active = FALSE;
if( $_journo['status'] == 'i' ) {
    $newly_active = journo_checkActivation( $_journo['id'] );
}

page_header($title);

?>
<div class="main">
<h2><?= $title ?></h2>
<?php
if( $newly_active ) {
?>
<div class="infomessage">
<strong>Profile activated</strong>
</div>
<?php
}


if( $item->is_finished() ) {
    // it's been submitted.
    // display the info message above the title...
?>
<?php $item->emit(); ?>
<p>Would you like to add another?</p>
<?php
    // ... and a new, blank form underneath
    $blank = new ArticleSubmitter( $_journo, TRUE );
    $blank->emit();
} else {
    // still going - show the form under the title
?>
<?php $item->emit(); ?>
<?php
}
?>
<a href="/<?= $_journo['ref'] ?>">Go to profile page</a>

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
    if( strpos( trim($url), ' ' ) !== False ) {
        return "URLs should not contain spaces";
    }

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
//    if( strpos( $host, ' ' ) !== False ) {
//        return "Please enter a valid url";
//    }

    // make sure we've got at least a non-blank path (or a non-blank query)
    if( ($path=='' || $path=='/') && $query=='' ) {
        return "Please enter the FULL url of this article";
    }

    return null;
}

