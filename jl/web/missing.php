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
    if( 1==preg_match( '%(?P<day>[0-9]?[0-9])[-/](?P<month>[0-9]?[0-9])[-/](?P<year>[0-9]{4})%', $d, $m ) ) {
        $day = intval( $m['day'] );
        $month = intval( $m['month'] );
        $year = intval( $m['year'] );

        if( checkdate( $month, $day, $year ) ) {
            $dt = new DateTime();
            $dt->setDate( $year, $month, $day );
            return $dt;
        }
    }
   /* else {
        // just use DateTime parser
        $dt = date_create( $d );
        if( $dt )
            return $dt;
   }*/
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
    public $article = null; // array with id, title, pubdate, srcorg, journos

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

        // article already in DB?
        $art_id = article_find($this->url);
        if(is_null($art_id)) {
            // nope - try and scrape it
            list($ret,$txt) = scrape_ScrapeURL($this->url, $this->journo['ref']);
            if($ret != 0) {
                $this->errs['error_message'] = "Journa<i>listed</i> had problems reading this article";
                $this->state = 'scrape_failed';
                $this->_register_error();
                return;
            }
 
            $arts = scrape_ParseOutput($txt);
            if(sizeof($arts)<1) {
                $this->errs['error_message'] = "Journa<i>listed</i> had problems reading this article";
                $this->state = 'scrape_failed';
                $this->_register_error();
                return;
            }
            $art_id = $arts[0];
        }

        // if we get this far, $art_id will be set

        // fetch some basic details about the article
        $art = db_getRow("SELECT id,title,permalink,pubdate,srcorg FROM article WHERE id=?",$art_id);
        $sql = <<<EOT
            SELECT j.id,j.prettyname,j.ref
                FROM (journo j INNER JOIN journo_attr attr ON attr.journo_id=j.id)
                WHERE attr.article_id=?
EOT;
        $journos = db_getAll($sql, $art_id);

        $art['journos'] = $journos;
        $this->article = $art;

        // attributed to the expected journo?
        $got_expected_journo = FALSE;
        foreach($journos as $j) {
            if($j['id'] == $this->journo['id']) {
                $got_expected_journo = TRUE;
                break;
            }
        }
        if($got_expected_journo) {
            // all is well.
            $this->state = 'done';
            return;
        } else {
//            $this->errs['error_message'] = "Journa<i>listed</i> had trouble reading the byline";
            $this->state = 'journo_mismatch';
            $this->_register_error();
            return;
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
                $this->_emit_form();
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

    function _emit_form()
    {
?>
<form action="<?= $this->pagePath ?>" method=POST>
Please enter the URL of the article:<br/>
<dl>
  <dt><label for="url">URL</label></dt>
  <dd>
    <input type="text" name="url" id="url" class="url" value="<?= h($this->url); ?>" /><br/>
    <span class="explain">e.g. "http://www.thedailynews.com/articles/12345.html"</span></br/>
<?= $this->errhint('url') ?>
  </dd>
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
    <p>Thank you - the article '<a href="<?= article_url($this->article['id']); ?>"><?= h($this->article['title']) ?></a>' has been added to your page.
</p>
    </div>
<?php
    }

    function _emit_failed() {
?>
<?php if(array_key_exists('error_message',$this->errs)) { ?>
<p class="errormessage"> <?= $this->errs['error_message'] ?></p> </p>
<?php } ?>
<p>Sorry, the article couldn't be added immediately.<br/>
It has been queued for manual addition by the journa<i>listed</i> team.</p>
<?php
    }




    // log the fact that there was a problem with the article,
    // so a site admin person can check it out.
    function _register_error()
    {
        $reason = $this->state;
        assert($reason=='scrape_failed' || $reason=='journo_mismatch');

        $extra = '';    // could be extra context, in json fmt
        $art_id = is_null($this->article) ? null : $this->article['id'];
        $journo_id = is_null($this->journo) ? null : $this->journo['id'];

        $person = person_if_signed_on();
        $person_id = is_null($person) ? null : $person->id();

        // uh-oh. queue it up for admin attention
        db_do("DELETE FROM article_error WHERE url=?",$this->url);
        db_do("INSERT INTO article_error (url,reason_code,submitted_by,article_id,expected_journo) VALUES (?,?,?,?,?)",
            $this->url,
            $reason,
            $person_id,
            $art_id,
            $journo_id );
        db_commit();
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
        <li>It was published before journa<i>listed</i> started</li>
        <li>It was published in a 'registration required' area of the news outlet's website (behind a paywall)</li>
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

    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND ((journo_id=? AND permission='edit') OR permission='admin')",
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

