<?php
/* template for footer at the bottom of every page */
?>

<br clear="all" />

</div>  <!-- end content div -->


<div id="footer">
  <div class="nav">
<?php
    gatso_report_html();
    $contactemail = OPTION_TEAM_EMAIL;
?>
  <ul>
<li><a href="/development">Development</a></li>
<li><?= SafeMailto( $contactemail, 'Contact us' );?></li>
<li><a href="/faq">FAQs</a></li>
<li><a href="/api">API</a></li>
<li><a href="/faq/what-is-your-privacy-policy">Privacy Policy</a></li>
</ul>
</div>
&copy; 2007 <a href="http://www.mediastandardstrust.org">Media Standards Trust</a><br />


  </div>
</div>

<?php if( OPTION_JL_GOOGLE_ANALYTICS_ENABLE ) { ?>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
var pageTracker = _gat._getTracker("UA-10908611-1");
pageTracker._trackPageview();
} catch(err) {}</script>
<?php } ?>

</body>
</html>

