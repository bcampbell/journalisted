<?php

/* 
 *
 */

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



$article_id = get_http_var( 'id' );
$findtext = get_http_var( 'find' );
$ref = get_http_var( 'ref' );

page_header( 'Buzz' );
emit_blog_buzz();
emit_comment_buzz();
page_footer();


function emit_blog_buzz()
{

?>
<h3>Blog references</h3>
<table>
<tr><th>BlogRefs</th><th>Headline</th></tr>
<?php

$q = db_query( "SELECT count(*) as n, a.id, a.title FROM (article a INNER JOIN article_bloglink b ON a.id=b.article_id) GROUP BY a.id,a.title ORDER BY n DESC" );
while( $r=db_fetch_array($q) )
{
	$cnt = $r['n'];
	$headline = $r['title'];
	$id = $r['id'];
	$link = sprintf( "<a href=\"/article?id=%d\">%s</a>",$id,$headline);

	printf("<tr><td>%d</td><td>%s</td></tr>\n", $cnt, $link );


}

?>
</table>
<?php
}


function emit_comment_buzz()
{

?>
<h3>Sharing/comments</h3>
<table>
<tr><th>Comments</th><th>Headline</th></tr>
<?php

$q = db_query( "SELECT a.title, a.id, SUM( c.num_comments ) AS tot_comments, COUNT(*) AS num_sites FROM (article a INNER JOIN article_commentlink c ON a.id=c.article_id) GROUP BY a.title, a.id ORDER BY tot_comments DESC" );

while( $r=db_fetch_array($q) )
{
	$comments = $r['tot_comments'];
	$sites = $r['num_sites'];

	$headline = $r['title'];
	$id = $r['id'];
	$link = sprintf( "<a href=\"/article?id=%d\">%s</a>",$id,$headline);

	printf("<tr><td>%d (on %d sites)</td><td>%s</td></tr>\n", $comments, $sites, $link );

}

?>
</table>
<?php
}




