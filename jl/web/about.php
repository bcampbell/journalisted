<?php


require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';

page_header( "About", array( 'menupage'=>'about' ) );

?>
<h2>About Journa-list</h2>



<h3>Why journa-list?</h3>
<p>Have you ever read an article and thought, I'd really like to know more about the journalist who wrote this?</p>
<p>Maybe you want to read previous things the journalist has written, or find out more about their experience in a particular subject area, or get in contact with them.</p>
<p>Or perhaps you want to find out who else is writing about a particular subject - say 'Bluetongue' - and compare one journalist's articles with those written by other journalists from different publications.</p>
<p>Or, you might want to build your own newsroom of journalists - science journalists for example - and have all their articles emailed directly to you each morning.</p>
<p>You can do all of this on journa-list.</p>
<p>It's our attempt to make news a bit more transparent and journalists a bit more accountable - on behalf of the public.</p>

<h3>What is journa-list?</h3>
<p>Journa-list is an independent, not-for-profit website that makes it easy for people to find out more about journalists and what they write about.</p>

<p>It is the first UK website - after <a href="http://www.byliner.com">www.byliner.com</a> - to offer a free, fully searchable database of UK national journalists (who write under a byline), with links to their current and previous articles, and some basic statistics about their work.</p>

<p>It allows you to build up your own tailored list of journalists whose views you respect and trust. You can search through their back catalogue of articles, and link to other articles on the same topic by different journalists. And, if you want, you can be alerted each time any of them writes an article.</p>
<p>Right now there is no website that pulls together the work of individual journalists and aggregates it to make it easy to search and link. If you search on the internet for a journalist the chances are you’ll find one of three things: a link to an article of theirs on a news website, a brief bio on the news website (or, if well known, on Wikipedia), or their own website / blog (very rare in the UK).</p>


<a name="howcollected"><h3>How is this information collected?</h3></a>

<p>This information is collected automatically from the websites of
<a href="#whichoutlets">various UK national news outlets</a>. Articles are indexed by
journalist based on the byline to the article. Statistics are based
on articles indexed to each journalist from the date the first article
was recorded on this site.
</p>
<p>
We started indexing articles in May 2007.
</p>

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

page_footer();
?>
