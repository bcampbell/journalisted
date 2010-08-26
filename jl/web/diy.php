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

<h2>Create your own profile</h2>
<p>And join over 20,000 UK professional
journalists on journalisted</p>

<ul>
<li>Build your own journalisted profile</li>
<li>Give easy, professional, url friendly, access to your work</li>
<li>Link to your blog, website, twitter profile, facebook profile</li>
<li>Have your work properly indexed on search engines, and automatically published in linked data format</li>
<li>Raise your profile amongst prospective employers</li>
<li>Be as transparent as possible to your audience</li>
</ul>

<p>
All you need to do is click here...
</p>

<a class="donate" href="/profile">Create journalisted profile</a>


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
