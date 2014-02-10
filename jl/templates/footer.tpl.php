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
	<li><a href="/about">About</a></li>
	<li><a href="/development">Development</a></li>
	<li><?= SafeMailto( $contactemail, 'Contact' );?></li>
	<li><a href="/faq">FAQs</a></li>
	<li><a href="/api">API</a></li>
	<li><a href="/faq/what-is-your-privacy-policy">Privacy</a></li>
</ul>
</div>
&copy; <a href="http://www.mediastandardstrust.org">Media Standards Trust</a> 2007&ndash;<script type="text/javascript">
<!--
var now = (new Date().getFullYear()).toString();
var theYear = now.substring(now.length, 2);
document.writeln(theYear,"");
// -->
</script> <br />


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

