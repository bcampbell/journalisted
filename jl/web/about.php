<?php

error_reporting(E_ALL);

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

page_header( "About", array( 'menupage'=>'about' ) );

$contactemail = OPTION_TEAM_EMAIL;
$subject = "Suggestions for site";

?>
<div class="main">
<div class="head"></div>
<div class="body">
<h2>About Journalisted</h2>

<p>Journalisted is an independent, not-for-profit website built to make it easier for you, the public, to find out more about journalists and what they write about. It is run by the Media Standards Trust, a registered charity set up to foster high standards in news on behalf of the public, and funded by donations from charitable foundations.</p>

The site allows you to:
<ul>
<li>Search articles published on UK national newspaper websites and BBC News by journalist, news outlet, subject and key word</li>
<li>Read all articles by a particular journalist</li>
<li>Find out further information about a particular journalist – such as links to a personal website or wikipedia page and, in some cases, an email address</li>
<li>Compare a journalist’s articles with those of other journalists who write about similar subjects</li>
<li>Contextualise articles, by seeing blogs that have linked to it and comments people have left about it</li>
<li>Find similar articles to the one you’re reading</li>
<li>Set up alerts to tell you when your favourite journalists have written something new</li>
</ul>

<p>It is independent, non-commercial and non-partisan, and is intended to make the news media more transparent and accountable on behalf of the public.</p>
<p>Journalisted works by automatically searching UK national newspaper websites, BBC News and Sky News, and picking out journalists' bylines (see full list of news outlets covered). Articles are then indexed by journalist - but you can also search by news outlet and key words.</p>
<p>Because of the way the information is gathered, the site is not comprehensive, and will never do full justice to the output of the journalists we cover, for which we apologise. There will be some mistakes and omissions – but we aim to rectify these as soon as we hear of them.</p>
<p>Journalisted was first launched as a trial in November 2007. The current, upgraded version – which includes new features, such as suggesting similar articles and similar journalists – went live in June 2009.</p>

<p>If you’ve got any suggestions, please 
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'email us' );?>.</p>

<p>If you’d like to know more, take a look at our <a href="/faq">FAQs</a>, or
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'get in touch' );?>. </p>



<h3>Credits</h3>

<p>
The site was built by <a class="extlink" href="http://www.scumways.com">Ben Campbell</a> and designed by James Williamson.
Julian Todd and <a class="extlink" href="http://www.flourish.org">Francis Irving</a> have provided hosting and admin support.
<a class="extlink" href="http://mediastandardstrust.blogspot.com">Martin Moore</a> has led the initiative on behalf of the Media Standards Trust.
Gavin Freeguard and Ben Campbell administrate the site.
</p>
<p>Thanks to Gavin Buttimore, Tom Lynn, Ayesha Garrett, Gary Jones and Simon Roe, who have all contributed to development.</p>
<p>Thanks also to Tom Steinberg, Phil Gyford, Louise Crow, Tom Loosemore, Matt Cain and everyone else who offered invaluable ideas and advice.</p>
<p>Journalisted reuses some code from the wonderful <a class="extlink" href="http://mysociety.org">mySociety</a> websites.</p>
</div>
<div class="foot"></div>
</div>  <!-- end main -->

<div class="sidebar">

  <a class="donate" href="http://www.justgiving.com/mediastandardstrust">Donate</a>

  <div class="box">
    <div class="head"><h3>FAQs</h3></div>
    <div class="body">
      <ul>
        <li><a href="/faq/what-news-outlets-does-journalisted-cover">What news outlets does Journalisted cover?</a></li>
        <li><a href="/faq/why-doesnt-journalisted-cover-more-news-outlets">Why doesn’t Journalisted cover more news outlets?</a></li>
        <li><a href="/faq/how-does-journalisted-work">How does Journalisted work?</a></li>
        <li><a href="/faq/how-is-journalisted-funded">How is Journalisted funded?</a></li>
        <li><a href="/faq/why-are-some-articles-missing">Why are some articles missing?</a></li>
      </ul>
      <div class="box-action"><a href="/faq">See all FAQs</a></div>
      <div style="clear: both;"></div>
    </div>
    <div class="foot"></div>
  </div>
</div> <!-- end sidebar -->
<?php

page_footer();
?>
