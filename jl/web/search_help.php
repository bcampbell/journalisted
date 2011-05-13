<?php

error_reporting(E_ALL);

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

page_header( "Search Help" );


?>
<div class="main">
<div class="head"></div>
<div class="body">

<h2>Using journa<i>listed</i> for advanced searches</h2>



<p>
Firstly, with all these searches make sure to select 'articles' from the drop-down box next to the search box on the homepage.
</p>
<p> Let's with start some examples...</p>

<div class="example-search">
articles containing "iraq" and "war":
<span class="query"><a href="/search?type=article&q=iraq+war">iraq war</a></span>
</div>


<div class="example-search">
articles containing the exact phrase "iraq war":
<span class="query"><a href="/search?type=article&q=%22iraq+war%22">"iraq war"</a></span>
</div>

<p>Did you know that you can mix special search terms into your usual searches on journa<i>listed</i>?<br/>

<div class="example-search">
articles about the G20 protests by Paul Lewis:
<span class="query"><a href="http://journalisted.com/search?type=article&q=G20+author%3Apaul-lewis">G20 author:paul-lewis</a></span>
</div>

<div class="example-search">
articles containing "citrus" but not "lemon":
<span class="query"><a href="/search?type=article&q=citrus+-lemon">citrus -lemon</a></span>
</div>


<div class="example-search">
anything about David Cameron in the Times or Telegraph:
<span class="query"><a href="/search?type=article&q=(srcorg%3A8+OR+srcorg%3A7)+David+Cameron">(srcorg:8 OR srcorg:7) David Cameron</a></span>
</div>

<div class="example-search">
articles written by Gillian Tett for the FT:
<span class="query"><a href="/search?type=article&q=author%3Agillian-tett+srcorg%3A18">author:gillian-tett srcorg:18</a></span>
(see <a href="#publication-search">Publication Search</a> for numbers of other publications).
</div>

<div class="example-search">
to check out the Guardian's coverage on phone hacking:
<span class="query"><a href="/search?type=article&q=phone+hacking+srcorg%3A4">phone hacking srcorg:4</a></span>
</div>

<div class="example-search">
phone hacking articles mentioning Rebekah Brooks:
<span class="query"><a href="/search?type=article&q=Rebekah+Brooks+title%3A(phone+hacking)">Rebekah Brooks title:(phone hacking)</a></span>
Note the use of parentheses to make sure the title includes both "phone" and "hacking".
</div>

<div class="example-search">
the Independent's coverage of Libya and Gaddafi for the first 4 months of 2011:
<span class="query"><a href="/search?type=article&q=libya+gaddafi+srcorg%3A1+20110101..20110501">libya gaddafi srcorg:1 20110101..20110501</a></span>
</div>

<div class="example-search">
What have the previous two UK Prime Ministers been up during the first year of the
coalition government?
<span class="query"><a href="/search?type=article&q=(%22tony+blair%22+OR+%22gordon+brown%22)+2010-05-12..2011-05-12">("tony blair" OR "gordon brown") 2010-05-12..2011-05-12</a></span>
Note the use of parentheses - without them, the date range would only apply to the "Gordon Brown" term!
</div>


<h3>Phrase searching</h3>

<p>Surround a phrase with double quotes ("") to match articles containing that exact phrase. Hyphenated words are treated as phrases.</p>
<div class="example-search"><span class="query"><a href="/search?type=article&q=%22phone+hacking%22">"phone hacking"</a></span>
Matches "phone hacking" and "phone-hacking", but not "phone hack" or "phone hackery".
</div>


<h3>Grouping</h3>
<p>Use parentheses to group terms to avoid confusion, especially if you are using <tt>OR</tt>.</p>
<div class="example-search">
For example:
<span class="query"><a href="/search?type=article&q=%22tony+blair%22+OR+%22gordon+brown%22+2010-05-12..2011-05-12">"tony blair" OR "gordon brown" 2010-05-12..2011-05-12</a></span>
is interpreted as:
<span class="query"><a href="/search?type=article&q=%22tony+blair%22+OR+(%22gordon+brown%22+2010-05-12..2011-05-12)">"tony blair" OR ("gordon brown" 2010-05-12..2011-05-12)</a></span>
that is, recent articles about Gordon Brown and _any_ article about Tony Blair...
which is probably not what you wanted.
</div>


<h3>Headline search</h3>

<p>
Prefix a term with <tt>title:</tt> to match headlines.
</p>

<div class="example-search">
For example, to search for all articles with "grapefruit" in the headline:
<span class="query"><a href="/search?type=article&q=title%3Agrapefruit">title:grapefruit</a></span></div>

<h3>Journalist search</h3>

<p>To search for articles written by a specific journalist, use an <tt>author:</tt> prefix.</p>

<div class="example-search">for example:<span class="query"><a href="">author:fred-bloggs</a></span></div>

<p>
The journalist identifier is usually of the form <tt>firstname-lastname</tt>,
but there are exceptions. For example, if there were two journalists called
Fred Bloggs, their identifiers might be <tt>fred-bloggs-1</tt> and
<tt>fred-bloggs-2</tt> to distinguish them.
</p>
<p>
You can discover the identifier for a given journalist by going to their
profile page and looking at URL in your web browser's address bar eg:
<tt>http://journalisted.com/fred-bloggs</tt>
</p>

<h3 id="publication-search">Publication search</h3>
<p>
The <tt>srcorg:</tt> prefix lets you restrict a search to a given
publication. The catch is that you need to know the publication ID, which
is a rather meaningless number. For now, here's a list of the IDs for
the UK publications we cover:
</p>

<table>
<tr><td>1</td><td>The Independent</td></tr>
<tr><td>2</td><td>MailOnline</td></tr>
<tr><td>3</td><td>The Daily Express</td></tr>
<tr><td>4</td><td>The Guardian</td></tr>
<tr><td>5</td><td>The Mirror</td></tr>
<tr><td>6</td><td>The Sun</td></tr>
<tr><td>7</td><td>The Daily Telegraph</td></tr>
<tr><td>8</td><td>The Times</td></tr>
<tr><td>9</td><td>The Sunday Times</td></tr>
<tr><td>10</td><td>BBC News</td></tr>
<tr><td>11</td><td>The Observer</td></tr>
<tr><td>12</td><td>The Sunday Mirror</td></tr>
<tr><td>13</td><td>The Sunday Telegraph</td></tr>
<tr><td>14</td><td>Sky News</td></tr>
<tr><td>15</td><td>The Scotsman</td></tr>
<tr><td>16</td><td>Scotland on Sunday</td></tr>
<tr><td>18</td><td>Financial Times</td></tr>
<tr><td>19</td><td>The Herald</td></tr>
<tr><td>20</td><td>News of the World</td></tr>
</table>

<div class="example-search">
For example, to look for articles published in The Mirror:
<span class="query"><a href="/search?type=article&q=srcorg%3A5">srcorg:5</a></span>
</div>


<h3>Date ranges</h3>

<p>Searches can be restricted to articles published within a range of dates,
using.
<tt>yyyy-mm-dd..yyyy-mm-dd</tt>. Or <tt>yyyymmdd..yyyymmdd</tt>. The two forms
are equivalent.
</p>

<div class="example-search">
For example, to search for articles published during July 2010: 
<span class="query"><a href="/search?type=article&q=afghanistan+2010-07-01..2010-07-31">afghanistan 2010-07-01..2010-07-31</a></span>
or 
<span class="query"><a href="/search?type=article&q=afghanistan+20100701..20100731">afghanistan 20100701..20100731</a></span>
</div>


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
