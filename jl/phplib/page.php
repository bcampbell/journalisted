<?php

require_once '../conf/general';
require_once '../../phplib/person.php';
require_once 'gatso.php';
require_once 'misc.php';

function page_header( $title, $params=array() )
{
    header( 'Content-Type: text/html; charset=utf-8' );

    if( $title )
        $title .= ' - ' . OPTION_WEB_DOMAIN;
    else
        $title = OPTION_WEB_DOMAIN;

    $P = person_if_signed_on(true); /* Don't renew any login cookie. */

    $datestring = date( 'l d.m.Y' );

    $mnpage = array_key_exists('menupage', $params) ? $params['menupage'] : '';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <title><?=$title ?></title>
  <style type="text/css" media="all">@import "/style.css";</style>
  <meta name="Content-Type" content="text/html; charset=UTF-8" />
<?php

    print "<!-- menupage: '$mnpage' -->\n";
    if (array_key_exists('rss', $params))
    {
        foreach ($params['rss'] as $rss_title => $rss_url)
        {
            printf( "  <link rel=\"alternate\" type=\"application/rss+xml\" title=\"%s\" href=\"%s\" />\n", $rss_title, $rss_url );
        }
    }

    $js = array( "/jl.js" );
    if (array_key_exists('js_extra', $params))
    {
        //print_r( $params['js_extra'] );
        $js = array_merge( $js, $params['js_extra'] );
    }

    foreach( $js as $s ) {
//      print $s;
        printf("  <script type=\"text/javascript\" src=\"%s\"></script>\n",$s);
    }

    if (array_key_exists('head_extra', $params)) {
        print $params['head_extra'];
    }
/*
    <script type="text/javascript" language="JavaScript">
      window.onload=function() {
        activatePlaceholders();
      }
    </script>
*/
?>
    <script type="text/javascript" language="JavaScript">
        addLoadEvent( activatePlaceholders );
    </script>

</head>

<body>

  <div id="head">
    <div class="inner">
      <h1><a href="/"><span></span>Journalisted</a></h1>

      <div id="tagline">&#8230;read all about them!</div>
<!--      <div id="today"><?php echo date( 'l, d.m.Y' ); ?></div>
      <div id="mst"><a href="http://www.mediastandardstrust.org">Media Standards Trust</a></div> -->
      <div id="menu">
        <ul>
          <li class="cover<?php echo $mnpage=='cover' ? ' active' :''; ?>"> <a href="/">Home</a> </li>
          <li class="all<?php echo $mnpage=='all' ? ' active' :''; ?>"> <a href="/list">Journalists A-Z</a> </li>
          <li class="subject<?php echo $mnpage=='subject' ? ' active' :''; ?>"> <a href="/tags">Subject Index</a> </li>
          <li class="my<?php echo $mnpage=='my' ? ' active' :''; ?>"> <a href="/alert">Alerts</a> </li>
          <li class="about<?php echo $mnpage=='about' ? ' active' :''; ?>"> <a href="/about">About</a> </li>
<!--
          <li class="donate<?php echo $mnpage=='donate' ? ' active' :''; ?>"> <a href="/donate">Donate</a> </li>
        </ul>
-->
      </div>
      <form action="/search" method="get" id="headsearch">
<!--        <label for="q">Search articles</label> -->
        <input type="text" value="" title="search articles" id="q" name="q" class="text" placeholder="search articles"/>
      <input type="submit" alt="find" value="Find" />
   </form>
<div style="clear:both;"></div>
    </div>
  </div>

<div id="dateline">
<?php

    if( $P )
    {
        if ($P->name_or_blank())
            $name = $P->name;
        else
            $name = $P->email;

?>
<span id="hellouser">
    Hello, <?php echo $name; ?> [<a href="/logout">log out</a>]<br/>
</span>
<?php

    } else {

?>
      <span id="today"><?php echo date( 'l d F Y' ); ?></span>
<?php

    }

?>
      <span id="mst"><a href="http://www.mediastandardstrust.org">Media Standards Trust</a></span>
<div style="clear:both;"></div>
</div>

<div id="content" class="home">
<?php
}


function page_footer( $params=array() )
{

?>
<br clear="all" />
</div>
<div id="footer">
<div class="inner">
<?php

    gatso_report_html();

    $contactemail = OPTION_TEAM_EMAIL;
?>
<a href="/development">Development</a> |
<?php echo SafeMailto( $contactemail, 'Contact us' );?> | <a href="/faq">FAQs</a> | <a href="/api">API</a> | <a href="/faq/what-is-your-privacy-policy">Privacy Policy</a>
<br />
&copy; 2007 <a href="http://www.mediastandardstrust.org">Media Standards Trust</a><br />

<?php
    if( OPTION_JL_PIWIK_ENABLE )
    {
?>
<!-- Piwik -->
<a href="http://piwik.org" title="Web 2.0 analytics" onclick="window.open(this.href);return(false);">
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://mststats.dyndns.org/" : "http://mststats.dyndns.org/");
document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
<!--
piwik_action_name = '';
piwik_idsite = 1;
piwik_url = pkBaseURL + "piwik.php";
piwik_log(piwik_action_name, piwik_idsite, piwik_url);
//-->
</script><object>
<noscript><p>Web 2.0 analytics <img src="http://mststats.dyndns.org/piwik.php" style="border:0" alt="piwik"/></p>
</noscript></object></a>
<!-- /Piwik --> 

<?php

    }
?>


</div>
</div>
</body>
</html>
<?php

//  debug_comment_timestamp();

}

?>
