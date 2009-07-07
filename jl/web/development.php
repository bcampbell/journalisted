<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';

page_header( "Development" );

?>
<h2>Journa<i>listed</i> for Developers</h2>



<h3>RSS Feeds</h3>

<p>
There is an RSS feed provided for each journalist, containing the list of articles they have written.
</p>
<p>
Look out for the RSS icon:<img src="/images/rss.gif" />
</p>


<h3>hAtom Support</h3>

<p>We like <a href="http://microformats.org/wiki/hatom">hAtom</a> and try to use it wherever it makes sense to do so.</p>
<p>This includes search results and the lists of articles on journalist pages.</p>

<h3>APIs</h3>

<p>We provide some APIs you can use to pull information out of our database. Find out more on the <a href="/api">API Page</a>.</p>

<h3>Site Development</h3>

<p>
Journa<i>listed</i> itself is an open source project.
</p>

<p>
The code is licensed under the <a href="http://www.affero.org/oagpl.html">Affero General Public License</a> (<a href="http://www.affero.org/oagf.html">FAQs</a>)<br />
(Quick summary: it's the GPL v2 with provision to cover using the code
as a network service)
</p>

<p>
The main project page is <a href="http://code.google.com/p/journa-list/">here</a> (hosted at code.google.com).
</p>
<p>
There is a public development <a href="http://groups.google.com/group/jl-dev">mailing list</a>.
</p>
<p>
If you have technical questions, contact <a href="http://scumways.com">Ben Campbell</a>.
</p>


<?php

page_footer();

?>
