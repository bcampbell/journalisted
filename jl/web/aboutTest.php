<?php

error_reporting(E_ALL);

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

page_header( "About", array( 'menupage'=>'about' ) );

?>
<h2>About Journa-list</h2>



<h3>Why journa-list?</h3>
<p>Have you ever read an article and thought, I'd really like to know more about the journalist who wrote this?
<p>Maybe you want to read previous things the journalist has written, or find out more about their experience in a particular subject area, or see if there is other information about their work on the web.
<p>Or perhaps you want to find out who else is writing about a particular subject - say 'Bluetongue' - and compare one journalist's articles with those written by other journalists from different publications.
<p>Or, you might want to build your own newsroom of journalists - science journalists for example - and have their articles emailed directly to you each morning.
<p>You can do all of this on journa-list.
<p>It's an attempt to make news a bit more transparent and journalists a bit more accountable - on behalf of the public.

<h3>What is journa-list?</h3>
<p>Journa-list is an independent, not-for-profit website that makes it easier for people to find out more about journalists and what they write about.
<p>It is the first UK website - after <a href="http://www.byliner.com">www.byliner.com</a> - to offer a free searchable database of UK national journalists published by one of 14 news outlets online (who write under a byline), with links to their current and previous articles, and some basic statistics about their work.
<p>It allows you to build up your own tailored list of journalists whose views you respect and trust. You can search through their back catalogue of articles, and link to other articles on the same topic by different journalists. And, if you want, you can be alerted each time any of them writes an article.
<p><b>It is certainly not comprehensive.</b> Due to the way information is gathered (see below) it is also bound to contain mistakes (though we do our best to correct them whenever we find them). And, it will never do full justice to the output of the journalists covered – for which we apologise.
<p>But in a world of growing information overload we hope it will be an additional way for the public to navigate news, compare different stories and build up a range of journalists that they read regularly and trust.

<a name="howcollected"><h3>How is this information collected?</h3></a>
<p>This information is collected automatically from the websites of <a href="#whichoutlets">various UK national news outlets</a>. Articles are indexed by journalist based on the byline to the article. Statistics are based on articles indexed to each journalist from the date the first article was recorded on this site. 
<p>We started indexing articles in the summer 2007. 

<a name="whichoutlets"><h3>Which news outlets are covered?</h3></a>
<p>The site currently covers:
<ul>
 <li>BBC News</li>
 <li>Sky News (Blogs only)</li>
 <li>The Daily Express</li>
 <li>The Daily Mail</li>
 <li>The Daily Telegraph</li>
 <li>The Guardian</li>
 <li>The Independent</li>
 <li>The Mirror</li>
 <li>The Observer</li>
 <li>The Sun</li>
 <li>The Sunday Mirror</li>
 <li>The Sunday Telegraph</li>
 <li>The Sunday Times</li>
 <li>The Times</li>
</ul>
</p>


<?php

	$contactemail = "team@" . OPTION_WEB_DOMAIN;
	$subject = "Adding Information / Correcting Information";

?>

<h3>Adding Information / Correcting Information</h3>
<p>If you spot information that is incorrect – or notice articles that are missing – please get in touch and
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'tell us' );?>.
<p>As explained above, due to the way in which information is collected there are bound to be errors, but as soon as we find them or are told about them, we will do our best to correct them.
<p>Once we have got through this first stage in the development of the site we will enable journalists to add information / material of their own.
<p>If you have suggestions on how to improve the site, please 
<?=SafeMailto( $contactemail . '?subject=' . $subject, 'let us know' );?>.


<?php

page_footer();
?>
