<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';



$journo = NULL;
$j = strtolower( get_http_var( 'j' ) );   /* eg 'fred-bloggs' (ref), or '1234' (id) */
if( $j ) {
    $field = is_numeric( $j ) ? 'id':'ref';
    $journo = db_getRow( "SELECT * FROM journo WHERE {$field}=? AND status='a'", $j );
}


$p = array(
    'name' => get_http_var('name'),
    'articles' => get_http_var('articles',"http://" ),
    'web' => get_http_var('web',"http://" ),
    'other' => get_http_var('other',"http://" ),
    'email' => get_http_var('email'),
    'hideemail' => get_http_var('hideemail'),
    'submit' => get_http_var('submit')
);


page_header( "For Journalists" );

$errs = array();
if( $p['submit'] ) {
    $errs = Validate( $p );
}

?>
<div id="maincolumn">
<?php

if( $p['submit'] && !$errs ) {
    Process( $p );
} else {
    if( is_null($journo) ) {
?>
  <h2>For Journalists</h2>
  <p>Add more information to your page.</p>
  <p>If you appear on Journa<i>listed</i>, and find we have missed one or more of your articles, or you would like to link to a personal site, information on books you have written etc., let us know here (apart from your name, none of this information is compulsory):</p>
<?php
    } else {
?>
  <h2>Add more information for <?php echo $journo['prettyname']; ?></h2>
  <p>
  If an article is missing you can send us the link (url) here and we 
  will add it. You can also add links to other biographical information.
  </p>
  <p>
  If you would like to send us links to articles not automatically covered 
  by Journa<i>listed</i>, you can do that <a href="/missing?j=<?php echo $journo['ref'];?>">here</a>.
  </p>
  <p>
  If there is something wrong on the page (e.g. if it has mixed up two 
  journalists of the same name) please <?php echo SafeMailto( OPTION_TEAM_EMAIL, 'contact us' );?> so we can correct it.
  </p>
  <p>
  None of this information is compulsory. All information will be reviewed before being published.
  </p>
<?php
    }
    ShowForm( $p, $errs );
}

?>
</div>  <!-- end maincolumn -->

<div id="smallcolumn">
  <div class="box">
    <h3>FAQs</h3>
    <div class="box-content">
      <ul>
        <li><a href="/faq/what-news-outlets-does-journalisted-cover">What news outlets does Journalisted cover?</a></li>
        <li><a href="/faq/why-doesnt-journalisted-cover-more-news-outlets">Why doesn’t Journalisted cover more news outlets?</a></li>
        <li><a href="/faq/how-does-journalisted-work">How does Journalisted work?</a></li>
        <li><a href="/faq/why-are-some-articles-missing">Why are some articles missing?</a></li>
        <li><a href="/faq/how-do-i-add-more-articles-or-information-to-a-journalists-page">How do I add more articles or information to my page?</a></li>
        <li><a href="/faq/how-is-journalisted-funded">How is Journalisted funded?</a></li>
      </ul>
      <div class="box-action"><a href="/faq">See all FAQs</a></div>
      <div style="clear: both;"></div>
    </div>
  </div>
</div>
<?php

page_footer();




function ShowForm( &$p, &$errs=array() )
{
    global $journo;
?>
      <form id="forjournosform" method="post" action="/forjournos" >
        <p>
<?php if( is_null($journo) ) { ?>
<?php if(array_key_exists('name',$errs)) { ?><span class="errhint"><?php echo $errs['name'];?></span><br/><?php } ?>
        <label for="name">You are:</label>
        <input type="text" name="name" id="name" size="25" value="<?php echo htmlspecialchars( $p['name'] ); ?>" />
        </p>
<?php } else { ?>
        <input type="hidden" name="j" value="<?php echo $journo['ref']; ?>" />
<?php } ?>

<?php if(array_key_exists('general',$errs)) { ?><p class="errhint"><?php echo $errs['general'];?></p><?php } ?>
        <p>
        <label for="articles">Add article links (one per line):</label><br />
        <textarea name="articles" id="articles" rows="5" cols="60"><?php echo htmlspecialchars( $p['articles'] ); ?></textarea>
        </p>

        <p>
        <label for="web">Add links to personal website/blog<br/>(or profile on facebook/myspace/linkedin/twitter/whatever):</label><br />
        <textarea name="web" id="web" rows="2" cols="60"><?php echo htmlspecialchars( $p['web'] ); ?></textarea>
        </p>

        <p>
        <label for="other">Add links to more biographical information<br />(eg. books authored, journalism prizes):</label><br />
        <textarea name="other" id="other" rows="3" cols="60"><?php echo htmlspecialchars( $p['other'] ); ?></textarea>
        </p>

        <p>
<?php if(is_null($journo)) {?>
        <label for="email">Your email address:</label><br />
        <input type="text" name="email" id="email" size="25" value="<?php echo htmlspecialchars( $p['email'] ); ?>" />
        <br/>
        <input type="checkbox" name="hideemail" id="hideemail" <?php echo $p['hideemail'] ? "checked" : ""; ?> />
        <label for="hideemail">Check this box if you do not want this to appear on your page</label></br/>
<?php } else { /*?>
        <label for="email">Email address for <?php echo $journo['prettyname']; ?></label><br />
<?php */ } ?>
        </p>

        <input type="submit" name="submit" value="Submit" />
      </form>

<?php

}

function Validate( &$p ) {
    global $journo;
    $errs = array();

    if( is_null( $journo ) && !$p['name'] )
        $errs['name'] = "Please enter your name";

    if( ($p['articles']=='' || $p['articles']=='http://' ) &&
        ($p['web']=='' || $p['web']=='http://' ) &&
        ($p['other']=='' || $p['other'] =='http://' ) &&
        $p['email']=='') {
        $errs['general'] = "Please enter the information you'd like to submit";
    }
    return $errs;
}



function Process( &$p ) {
    global $journo;

    $msg = '';

    $msg .= "Missing information submitted\n\n";
    if( is_null($journo) ) {
        $msg .= "Name: " . $p['name'] . "\n\n";
    } else {
        $msg .= "Name: " . $journo['prettyname'] . "\n\n";
        $msg .= "      " . OPTION_BASE_URL . "/" . $journo['ref'] . "\n\n";
    }

    foreach( array('articles','web','other','email') as $f ) {
        if($p[$f] == '' || $p[$f] == 'http://' )
            continue;

        $msg .= $f . "\n";
        $msg .= "----------\n";
        $msg .= $p[$f] . "\n";
        if($f=='email' && $p['hideemail'] )
            $msg .= " => Don't show this email on page\n";
        $msg .= "----------\n\n";
    }

    if( is_null($journo) ) {
        $subject = sprintf( "[info submitted for %s]", $p['name'] );
    } else {
        $subject = sprintf( "[info submitted: %s]", $journo['ref'] );
    }

    $to_email = OPTION_TEAM_EMAIL;
    $from_name = "Journalisted";
    $from_email = OPTION_TEAM_EMAIL;

    /* SEND IT! */
    if( jl_send_text_email( $to_email, $from_name, $from_email, $subject, $msg ) )
    {

?>
<p>Thanks for letting us know!</p>
<p>The information will be considered for submission. If it doesn't appear within
7 days, please feel free to email us and ask why.</p>
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

?>
