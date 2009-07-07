<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';

$P = person_if_signed_on();

page_header( "For Press Officers" );

?>
<div id="maincolumn">

    <h2>For Press Officers</h2>

      <p>Journa<i>listed</i> is a media monitoring tool which follows journalists.</p>

      <ul>
        <li>Get a free email alert every time your most important journalists write.<br/>
Create an account below and you will get an email alert every time your key journalists write an article. You can add as many journalists as you want.
      </ul>

      <p>Journa<i>listed</i> can help you:</p>
      <ul>
        <li>Monitor all the articles written by your most important journalists</li>
        <li>Track which other journalists are writing stories on the same topics</li>
        <li>Gain intelligence on the issues your journalists write about, how often and how much they write.</li>
      </ul>


<?php
if( $P == null ) {
    loginform_emit();
} else {
?>
      <strong><a href="/alert">Manage your alerts</a></strong>
<?php
}
?>


</div>

<div id="smallcolumn">
  <div class="box">
    <h3>FAQs</h3>
    <div class="box-content">
      <ul>

        <li><a href="/faq/what-news-outlets-does-journalisted-cover">What news outlets does Journalisted cover?</a></li>
        <li><a href="/faq/why-doesnt-journalisted-cover-more-news-outlets">Why doesn't Journalisted cover more news outlets?</a></li>
        <li><a href="/faq/how-does-journalisted-work">How does Journalisted work?</a></li>
        <li><a href="/faq/why-are-some-articles-missing">Why are some journalist's articles missing?</a></li>
        <li><a href="/faq/do-you-have-more-contact-information-for-journalists">Do you have more contact information for journalists?</a></li>
        <li><a href="/faq/how-is-journalisted-funded">How is Journalisted funded?</a></li>
      </ul>
      <div class="box-action"><a href="/faq">See all FAQs</a></div>
      <div style="clear: both;"></div>
    </div>
  </div>
</div>
<?php

page_footer();

?>
