<?php

/* 
 * Page to show information about why we might be missing articles for
 * a journo and to submit links.
*/

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/scrapeutils.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



$state = strtolower( get_http_var('state','initial' ) );
$ref = strtolower( get_http_var( 'j' ) );   /* eg 'fred-bloggs' */
$journo = NULL;
if( $ref )
    $journo = db_getRow( "SELECT id,ref,prettyname,lastname,firstname FROM journo WHERE ref=? AND status='a'", $ref );

if( $journo )
    $title = "Tell us about a missing article for " . $journo['prettyname'];
else
    $title = "Tell us about a missing article";

page_header($title);

?>
<div id="maincolumn">
  <div class="box">
    <h2><?php echo $title; ?></h2>
    <div class="box-content">
<?php

switch( $state )
{
    case 'initial':
        $basic = new BasicForm( $journo );
        $basic->Emit();
        break;
    case 'submit_basic': // url only
        // check url.
        $basic = new BasicForm( $journo );
        $errs = $basic->Validate();
        if( !$errs ) {
            // params look OK. is it an article we handle?
            $url = $basic->URL();
            $srcid = scrape_CalcSrcID( $url );
            if( $srcid ) {

                // url is scrapable
                // TODO: 
                // - Scrape it.
                // - show results.
                // send email only if required.
                Article_Process( $journo, $url );

                EmitDoneBlurb();

            } else {
                // url can't be scraped
                $extended = new ExtendedForm( $journo );
                $extended->Emit();
            }
        } else {
            $basic->Emit( $errs );
        }

        break;
    case 'submit_extended': // url, title, pubdate, publication
        $extended = new ExtendedForm( $journo );
        $errs = $extended->Validate();
        if( !$errs ) {

            $extended->SendToDB();         

?>
<p>Thanks for letting us know!</p>
<?php
            EmitDoneBlurb();
        } else {
            $extended->Emit( $errs );
        }
        break;
}
?>
    </div>
  </div>
</div> <!-- end maincolumn -->
<div id="smallcolumn">
  <div class="box">
    <h3>Why might an article be missing?</h3>
    <div class="box-content">
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
</div>  <!-- end smallcolumn -->
<?php

page_footer();



function EmitDoneBlurb()
{
    global $journo;
?>
<p>Go back to <a href="/<?php echo $journo['ref']; ?>"><?php echo $journo['prettyname'];?>'s page</a>
(or tell us about another missing article)</p>
<?php

    $basic = new BasicForm( $journo );
    $basic->BlankOutParams();
    $basic->Emit();
}



function Article_Process( $journo, $url )
{
    $msg = '';
    if( $journo )
        $subject = sprintf( "[missing article for %s]", $journo['prettyname'] );
    else
        $subject = '[missing article]';

    $to_email = OPTION_TEAM_EMAIL;
    $from_name = "Journalisted";
    $from_email = OPTION_TEAM_EMAIL;


    if( $journo )
    {
        /* provide a nice easy link to journos page */

        $journo_url = OPTION_BASE_URL . "/" . $journo['ref'];
        $journo_admin_url = OPTION_BASE_URL . "/adm/journo?journo_id=" . $journo['id'];

        $msg .= sprintf( "Missing article URL submitted for %s:\n", $journo['prettyname'] );
        $msg .= "$url\n\n";

        $msg .= $journo['prettyname'] . "'s pages:\n";
        $msg .= "$journo_url\n";
        $msg .= "$journo_admin_url\n";
        $msg .= "\n";
    }
    else
    {
        $msg .= "Missing article URL submitted:\n$url\n";
    }


    /* SEND IT! */
    if( jl_send_text_email( $to_email, $from_name, $from_email, $subject, $msg ) )
    {

?>
<div id="forminfo">
<p>The Journalisted team has been notified of the missing article.</p>
<p>Thanks for letting us know!</p>
</div>
<?php

    }
    else
    {

?>
<div id="errors">
<p>Uh-oh... there was an unknown problem when notifying
The Journalisted team...</p>
</div>
<?php

    }
}




// Basic form handling submission of articles  - URL only.
class BasicForm
{
    public $params = array();
    public $journo = NULL;

    function __construct( $journo )
    {
        $this->journo = $journo;
        $this->params['url'] = get_http_var( 'url' );
    }


    // blank out all the fields in the form so it can be reused
    function BlankOutParams()
    {
        $this->params['url'] = '';
    }

    function URL()
        { return $this->params['url']; }


    // Display the form itself, with (optionally) error info
    function Emit( $errs=array() )
    {
	    $errhtml='';

        $p = &$this->params;

?>
<p>Please enter the details of the article you want to tell us about:</p>

<form method="post" action="/missing">

<input type="hidden" name="j" value="<?php echo $this->journo['ref']; ?>" />
<input type="hidden" name="state" value="submit_basic" />

<p>
<?php if(array_key_exists('url',$errs)) { ?><span class="errhint"><?php echo $errs['url'];?></span><?php } ?>
<label for="url">Article URL ( <code>http://</code>... )</label>
<input type="text" name="url" id="url" size="80" value="<?php echo $p['url']; ?>" />
</p>

<p class="submit">
<input type="submit" name="submit" value="Submit" />
</p>
</form>
<?php
    }


    /* check the form params - returns an array containing any bad params (with error messages) */
    function Validate()
    {
	    $errs = array();
    	if( !$this->params['url'] )
	    	$errs['url'] = "Please enter the URL of the article";
    	return $errs;
    }
}




// form for handling submission of articles from publications we don't otherwise cover
// which means we need to ask for title, pubdate, publication as well as url.
class ExtendedForm
{
    public $params = array();
    public $journo = NULL;

    function __construct( $journo )
    {
        $this->journo = $journo;
        $this->params['url'] = get_http_var( 'url' );
        $this->params['title'] = get_http_var( 'title' );
        $this->params['pubdate'] = get_http_var( 'pubdate' );
        $this->params['publication'] = get_http_var( 'publication' );
    }


    // blank out all the fields in the form so it can be reused
    function BlankOutParams()
    {
        $this->params['url'] = '';
        $this->params['title'] = '';
        $this->params['pubdate'] = '';
        $this->params['publication'] = '';
    }


    // Display the form itself, with (optionally) error info
    function Emit( $errs=array() )
    {
	    $errhtml='';

        $p = &$this->params;

?>
<p>It looks like this article is from an outlet we don't yet cover, so we'll need a few more details:</p>

<form class="missingarticle" method="post" action="/missing">

<input type="hidden" name="j" value="<?php echo $this->journo['ref']; ?>" />
<input type="hidden" name="state" value="submit_extended" />

<p>
<?php if(array_key_exists('url',$errs)) { ?><span class="errhint"><?php echo $errs['url'];?></span><?php } ?>
<label for="url">Article URL (required) <small>( <code>http://</code>... )</small></label>
<input type="text" name="url" id="url" size="80" value="<?php echo $p['url']; ?>" />
</p>

<p>
<?php if(array_key_exists('title',$errs)) { ?><span class="errhint"><?php echo $errs['title'];?></span><?php } ?>
<label for="title">Article title (required)</label>
<input type="text" name="title" id="title" size="80" value="<?php echo $p['title']; ?>" />
</p>

<p>
<?php if(array_key_exists('pubdate',$errs)) { ?><span class="errhint"><?php echo $errs['pubdate'];?></span><?php } ?>
<label for="pubdate">Date published (required) <small>(dd-mm-yyyy)</small></label>
<input type="text" name="pubdate" id="pubdate" size="20" value="<?php echo $p['pubdate']; ?>" />
</p>

<p>
<?php if(array_key_exists('publication',$errs)) { ?><span class="errhint"><?php echo $errs['publication'];?></span><?php } ?>
<label for="publication">Name of publication</label>
<input type="text" name="publication" id="publication" size="40" value="<?php echo $p['publication']; ?>" />
</p>


<input type="submit" name="submit" value="Submit" />
</form>
<?php
    }


    /* check the form params - returns an array containing any bad params (with error messages) */
    function Validate()
    {
	    $errs = array();
    	if( !$this->params['url'] )
	    	$errs['url'] = "Please enter the URL of the article";
    	if( !$this->params['title'] )
	    	$errs['title'] = "Please enter the title of the article";
    	if( $this->params['pubdate'] )
        {

            $dt = strtotime( $this->params['pubdate'] );
            if( !$dt )
	    	    $errs['pubdate'] = 'Please enter the date in the form: YYYY-MM-DD';
        } else {
	    	$errs['pubdate'] = "Please supply a date";
        }

    	return $errs;
    }

    // Process the action for this form: add the article details to the DB.
    // Assumes params have already been validated.
    function SendToDB()
    {
        $sql = <<<EOT
INSERT INTO journo_other_articles ( journo_id, url, title, pubdate, publication, status )
    VALUES ( ?,?,?,?,?,'u' )
EOT;

        $j = &$this->journo;
        $p = &$this->params;

        db_do( $sql, $j['id'], $p['url'], $p['title'], $p['pubdate'], $p['publication'] );
        db_do( "DELETE FROM htmlcache WHERE name=?",
	        'j' . $j['id'] );

        db_commit();
    }

}

?>

