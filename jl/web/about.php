<?php

error_reporting(E_ALL);

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

page_header( "About", array( 'menupage'=>'about' ) );

?>
<h2>About Journalisted</h2>


<h3>What is Journalisted?</h3>
<p>Journalisted is an independent, not-for-profit website built to make it easier for the public to find out more about journalists and what they write about.</p>
<p>It is non-commercial and non-partisan. It is intended to make the news media more transparent and accountable on behalf of the public (as opposed to journalists or PR). It is an initiative of the Media Standards Trust, a charitable body set up to find ways to foster high standards in news.</p>
<p>It has been funded - on a very limited budget - by a number of charitable foundations (including Esmee Fairbairn and Joseph Rowntree) and by individual Board members of the Media Standards Trust.</p>
<p>It is the first UK website - after <a href="http://www.byliner.com">www.byliner.com</a> - to offer a free searchable database of UK national journalists published by one of 14 news outlets online (who write under a byline), with links to their current and previous articles, and some basic statistics about their work.</p>
<p>It allows you to build up your own tailored list of journalists whose views you respect and trust. You can search through their back catalogue of articles (since October 2007), and link to other articles on the same topic by different journalists. And, if you want, you can be alerted each time any of them writes an article.</p>
<p><b>It is certainly not comprehensive</b>. Due to the way information is gathered (see below) it is also bound to contain mistakes (though we do our best to correct them whenever we find them). And, it will never do full justice to the output of the journalists covered – for which we apologise.</p>
<p>But in a world of growing information overload we hope it will be an additional way for the public to navigate news, compare different stories and build up a range of journalists that they read regularly and trust.</p>


<a name="howcollected"><h3>How is this information collected?</h3></a>
<p>All the information on our site is collected automatically from the websites of <a href="#whichoutlets">21 British news outlets</a>. Altogether, this means 14 news websites, since many daily papers share a website with their sister Sunday paper.</p>
<p>Articles are indexed by journalist, based on the byline to the article. Keywords and statistics are automatically generated, and the site searches for any blogs or social bookmarking sites linking to the article.</p>
<p>We started indexing articles in the summer of 2007. Statistics are based on articles indexed to each journalist from the date the first article was recorded on this site.</p>
<p><b>Journa<i>listed</i> is certainly not comprehensive</b>. Due to the way information is gathered it is also bound to contain mistakes (though we do our best to correct them whenever we find them). And, it will never do full justice to the output of the journalists covered – for which we apologise.</p>
<p>But in a world of growing information overload we hope it will be an additional way for the public to navigate news, compare different stories and build up a range of journalists that they read regularly and trust.</p>


<a name="whichoutlets"><h3>Which news outlets are covered?</h3></a>
<p>The site currently covers 21 UK news outlets across 14 different websites:
<ul>
 <li>BBC News</li>
 <li>Financial Times</li>
 <li>Scotland on Sunday</li>
 <li>Sky News</li>
 <li>Express.co.uk - Daily Express and Sunday Express</li>
 <li>Mail Online - Daily Mail and Mail on Sunday</li>
 <li>Telegraph.co.uk - Daily Telegraph and Sunday Telegraph</li>
 <li>Guardian.co.uk - The Guardian and The Observer</li>
 <li>The Herald</li>
 <li>Independent.co.uk - The Independent and The Independent on Sunday</li>
 <li>Mirror.co.uk - Daily Mirror and Sunday Mirror</li>
 <li>The Scotsman</li>
 <li>The Sun</li>
 <li>Times Online - The Times and The Sunday Times</li>
</ul>
</p>


<?php

	$contactemail = OPTION_TEAM_EMAIL;
	$subject = "Suggestions for site";

?>

<h3>Adding Information / Correcting Information</h3>
<p>If you spot information that is incorrect – or notice articles that are missing – please get in touch and
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'tell us' );?>.
<p>As explained above, due to the way in which information is collected there are bound to be errors, but as soon as we find them or are told about them, we will do our best to correct them.
<p>Once we have got through this first stage in the development of the site we will enable journalists to add information / material of their own.
<p>If you have suggestions on how to improve the site, please 
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'let us know' );?>.

<h3>What next?</h3>
<p>We believe the site has enormous potential and we very much want to develop it further.</p>
We would very much like:
<ul>
<li>To give journalists the opportunity to add further information about themselves, links to more of their articles, and to register their interests.</li>
<li>To increase the number of publications from which we source articles – particularly regional newspapers, weekly magazines and the trade press.</li>
<li>To offer more information to users about each article – who is blogging about it, who is sharing it (via del.icio.us, digg, reddit etc.), who is commenting on it.</li>
<li>To be able to allow people to compare articles to press releases and other marketing material.</li>
</ul>
<p>To do any of this, however, we need support. If you would like to support us please do 
<?=SafeMailto( $contactemail . '?subject=journalisted', 'get in touch' );?>.</p>

<h3>Credits</h3>
<p>
<a href="http://www.scumways.com">Ben Campbell</a> built the site from his
rural idyll in North Wales, with help after launch from Gavin Buttimore
and Tom Lynn.
Ayesha Garrett, Gary Jones, and James Williamson designed it. Simon Roe
translated the design to the website. Tom Steinberg, Phil Gyford, Louise Crow,
Tom Loosemore, Matt Cain and others have offered invaluable ideas and advice
along the way. Julian Todd and
<a href="http://www.flourish.org">Francis Irving</a> generously provided the
hosting and admin support.
<a href="http://mediastandardstrust.blogspot.com">Martin Moore</a> has led
the initiative on behalf of the Media Standards Trust, with assistance from Gavin Freeguard.
</p>

<?php

page_footer();
?>
