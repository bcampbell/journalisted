<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';

$page_params = array();


// We want to be able to direct users to particular answers.
// part of solution is to use fragments (eg "#why-is-the-sky-blue") to
// jump to the right place on the page. But sometimes it's not obvious which
// answer you're being directed at, expecially if it's nearer the bottom of
// the page - the browser won't scroll down that far.
//
// So we pass in the question id as a parameter (q), and apply a
// different style to the question. It'd be nice if we could access a fragment
// from here, but it has to be considered a client-side only thing (some
// browsers send fragments, some strip 'em).
//
// Then we use some javascript to jump to the correct location.
// this has the nasty side effect of adding to an already long url, but hey.
// I think it's the best we can do.
//
// eg the javascript turns:
//   http://journalisted.com/faq/what-are-column-inches
// into
//   http://journalisted.com/faq/what-are-column-inches#what-are-column-inches
//

$focus = strtolower( get_http_var('q') );
if( $focus ) {
    // on load, jump to the focus anchor.
    $page_params['head_extra'] = <<<EOT
<script type="text/javascript" language="JavaScript">
addLoadEvent(function() {
    window.location.hash="$focus";
});
</script>
EOT;

}

page_header( "FAQs", $page_params );


// wrap questions with faq_begin()/faq_end() to sort out highlighting, id generation...

function faq_begin( $title ) {
    global $focus;

    // derive a suitable id from the title
    $id = trim( strtolower($title) );
    $id = preg_replace( '/<.*?>/', '', $id );   // kill html tags
    $id = preg_replace( '/[^-\w\s]/', '', $id );
    $id = preg_replace( '/[\s]+/', '-', $id );

    $cls = 'faq';
    if( $id==$focus ) {
        $cls = 'faq focused';
    }
?>

<div id="<?php echo $id; ?>" class="<?php echo $cls; ?>">
<h3 ><?php echo $title; ?></h3>

<?php
}


function faq_end() {

?>
</div>

<?php

}


?>
<h2>Frequently Asked Questions</h2>

<?php faq_begin("Who runs Journa<i>listed</i>?"); ?>
<p><a class="extlink" href="http://www.mediastandardstrust.org">The Media Standards Trust</a>, a registered charity set up to foster high standards in news on behalf of the public. The website, and the Trust, are independent, non-partisan and non-profit</p>
<?php faq_end(); ?>

<?php faq_begin("How is Journa<i>listed</i> funded?"); ?>
<p>Through grants from Foundations and donations from organisations and individuals. Grant makers include: the Esmee Fairbairn Foundation, the Gatsby Foundation, the Nuffield Foundation and the Joseph Rowntree Charitable Trust</p>
<?php faq_end(); ?>

<?php faq_begin("How does Journa<i>listed</i> work?"); ?>
<p>All the information on Journa<i>listed</i> is collected automatically from the websites of 21 British news outlets (altogether, this means 14 news websites, since many daily papers share a website with their sister Sunday paper). Articles are indexed by journalist, based on the byline to the article. Keywords and statistics are automatically generated, and the site searches for any blogs or social bookmarking sites linking to each article</p>
<?php faq_end(); ?>

<?php faq_begin("What news outlets does Journa<i>listed</i> cover?"); ?>
<p>Journa<i>listed</i> currently covers 21 UK news outlets across 14 different websites:
<ul>
  <li>BBC News Online</li>
  <li>Financial Times</li>
  <li>Express.co.uk - Daily Express and Sunday Express</li>
  <li>Mail Online - Daily Mail and Mail on Sunday</li>
  <li>Telegraph.co.uk - Daily Telegraph and Sunday Telegraph</li>
  <li>Guardian.co.uk - The Guardian and The Observer</li>
  <li>The Herald</li>
  <li>Independent.co.uk - The Independent and The Independent on Sunday</li>
  <li>Mirror.co.uk - Daily Mirror and Sunday Mirror</li>
  <li>The Scotsman</li>
  <li>Scotland on Sunday</li>
  <li>The Sun</li>
  <li>Times Online - The Times and The Sunday Times</li>
  <li>News of the World</li>
</ul>
<?php faq_end(); ?>

<?php faq_begin("Why doesn’t Journa<i>listed</i> cover more news outlets?"); ?>
<p>We would certainly like to cover more outlets, but to do so we would need more money (more technical costs, more administration costs). So until we get a little more funding, we have to stick with what we’ve got.</p>
<p>Please consider making a <a href="/donate">donation</a>.</p>
<?php faq_end(); ?>

<?php faq_begin("What time period does Journa<i>listed</i> cover?"); ?>
<p>Journa<i>listed</i> covers most articles published by 21 news outlets online since early 2008. In some cases, where people have sent us links to earlier articles, it goes back further</p>
<?php faq_end(); ?>

<?php faq_begin("What is your privacy policy?"); ?>
<p>Our privacy policy is very simple:
<ol>
<li>We guarantee we will not sell or distribute any personal information you share with us (unless you're a journalist giving us information to add to your page)</li>
<li>Other than email alerts, we will not send you more than one unsolicited email a year and that will only be to update you about changes to the site</li>
<li>We will gladly show you the personal data we store about you in order to run the website.</li>
</ol>


</p>
<?php faq_end(); ?>

<?php faq_begin("Do you cover articles from other news outlets?"); ?>
<p>Yes. Though Journa<i>listed</i> only captures articles from 21 UK news outlets, you can send us links to articles published on other websites. These will appear in a separate box on the journalist’s page. However, they are not used in any other analysis performed by Journa<i>listed</i>.
<?php faq_end(); ?>

<?php faq_begin("Can I add links to other information about a journalist (e.g. blog, official website)?"); ?>
<p>Yes. You can send us links to other information about a journalist (blog, official website, publisher’s profile etc.) via the journalist’s page.</p>
<?php faq_end(); ?>

<?php faq_begin("How does Journa<i>listed</i> work out what articles are similar?"); ?>
<p>Article similarity is judged using an automated system.
The algorithm works by trying to identify which terms within an
article seem most significant. A search is then made for other
articles which also contain some or all of those terms.
</p>
<?php faq_end(); ?>

<?php faq_begin("How does Journa<i>listed</i> work out what journalists write similar stuff?" ); ?>

<p>The algorithm follows the same lines at the one used to determine
similar articles and goes roughly like this:</p>
<ol>
<li>Take a number of the journalists most recent articles</li>
<li>Identify the significant terms within those articles</li>
<li>Search for other articles containing those terms</li>
<li>Rank the journalists who wrote those articles according to the closeness and quantity of the matches</li>
</ol>
<?php faq_end(); ?>

<?php faq_begin("What are column inches?"); ?>
<p>The "column inches" measurements on journalist pages are just a rough
estimate of the size of articles a journalist writes. Journa<i>listed</i> (arbitarily)
takes a column inch to be 30 words.</p>
<p>You might also find the <a class="extlink" href="http://en.wikipedia.org/wiki/Column_inch">wikipedia explanation of column inches</a> useful.</p>

<?php faq_end(); ?>

<?php faq_begin("Why are some articles missing?"); ?>
<p>An article may not appear on a journalist’s page if:
<ul>
  <li>it was not published in one of the news outlets we cover</li>
  <li>it was not bylined</li>
  <li>the byline was mis-spelt in the original publication</li>
  <li>the byline was contained within the text of the article so our system could not find it</li>
  <li>it was published before we started collecting articles</li>
  <li>it was published in a 'registration required' area of the news outlet's website.</li>
</ul>
<p>To add a missing articles, go to a journalist’s page and click on ‘Article(s) missing? If you notice an article is missing, click here’</p>
<?php faq_end(); ?>

<?php faq_begin("How do I add more articles or information to a journalists page?"); ?>
<p>To add more articles, go to a journalist’s page and click on ‘Article(s) missing? If you notice an article is missing, click here’</p>
<?php faq_end(); ?>

<?php faq_begin("Do you have more contact information for journalists?"); ?>
<p>All the contact information we have is on each journalist’s page. Some pages will also have a link to their personal website on the top right hand side. We do not have any other contact information for journalists</p>
<?php faq_end(); ?>

<?php faq_begin("Can I send out a press release via your site to journalists?"); ?>
<p>No. The site is meant to make it easier for the public to navigate the news – not as a PR tool</p>
<?php faq_end(); ?>

<?php

page_footer();
?>
