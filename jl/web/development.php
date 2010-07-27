<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../../phplib/db.php';

page_header( "Development" );

?>
<div class="main">
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

<h3>RDF/Linked data</h3>

<p>Some basic data is provided in RDF, and we plan to expose a lot more.</p>
<p>For journalists, the URL scheme is:</p>
<dl>
<dt><code>http://journalisted.com/id/journo/fred-bloggs</code></dt>
<dd>URI represents the <em>actual</em> Fred Bloggs (a non-information resource).<br/>
Obviously, since the technology to deliver people via HTTP isn't
quite there yet, this will redirect to a URL which will instead
deliver information _about_ Fred Bloggs instead. This will be either the HTML page
or RDF data, depending on the results of <a href="http://www4.wiwiss.fu-berlin.de/bizer/pub/LinkedDataTutorial/#ExampleHTTP">content negotiation</a>.</dd>
<dt><code>http://journalisted.com/fred-bloggs</code></dt>
<dd>The normal, human-readble HTML page of information about Fred Bloggs</dd>
<dt><code>http://journalisted.com/data/journo/fred-bloggs</code></dt>
<dd>Information about Fred Bloggs in RDF (currently in RDF XML but other formats
will also be supported)</dd>
</dl>

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
The source code is at <a href="http://github.com/bcampbell/journalisted">http://github.com/bcampbell/journalisted</a>.
</p>
<p>
There is a public development <a href="http://groups.google.com/group/jl-dev">mailing list</a>.
</p>
<p>
If you have technical questions, contact <a href="http://scumways.com">Ben Campbell</a>.
</p>

</div> <!-- end main -->
<?php

page_footer();

?>
