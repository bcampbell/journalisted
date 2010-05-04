<?php



/* param processing for search forms
 * (shared by site header, and also on various pages)
 *
 *  q: the query
 *  type: 'article','journo'
 *  start: offset of first result (default 0)
 *  num: max num of results to return (default 20)
 *
 * for article queries only:
 *  by: filter by author (adds a clause to 'q')
 *  q_original: query before without author clause etc...
 *  sort_order:
 */
function search_getParams()
{
	static $s=null;
	if( $s )
		return $s;
    
    $s = array();
    $art_q = get_http_var( 'a' );
    $journo_q = get_http_var( 'j' );
    if( $art_q ) {
        $s['type'] = 'article';
        $s['q'] = $art_q;
    } else if( $journo_q ) {
        $s['type'] = 'journo';
        $s['q'] = $journo_q;
    } else {
        // need this one to handle search forms with dropdown to select type of query
        $s['type'] = strtolower( get_http_var( 'type', 'journo' ) );
        $s['q'] = get_http_var( 'q', '' );
    }
    $s['num'] = (int)get_http_var('num', 20 );
    $s['start'] = (int)get_http_var('start', '0' );
    if( $s['type'] == 'article' ) {
        $s['sort_order'] = get_http_var( 'o', 'date' );
    }

    $s['by'] = get_http_var( 'by' );    // a journo ref
    $s['q_original'] = '';
    if( $s['by'] ) {
        $s['q_original'] = $s['q'];
        $s['q'] .= " author:" . $s['by'];
    }
    return $s;
}




function search_emit_onpage_form() {
    $s = search_getParams();
?>
  <div class="search">
    <form action="/search" method="get">
<!--        <label for="q">Search articles</label> -->
      <select name="type">
        <option value="journo"<?= ($s['type']=='journo')?' selected':'' ?>>Search journalists</option>
        <option value="article"<?= ($s['type']=='article')?' selected':'' ?>>Search articles</option>
      </select>
      <input type="text" value="<?= h($s['q']) ?>" id="q2" name="q" />
      <input type="submit" alt="search" value="Search" />
    </form>
  </div>
<?php
}



function search_buildURL( $s, $start=null )
{
    if( $start === null )
        $start = $s['start'];

    $url = '/search?';

    if( $s['type'] == 'journo' ) {
        $url .= 'j=' . urlencode( $s['q'] );
    } else {    /* 'article' */
        $url .= 'a=' . urlencode( $s['q'] );
        if( $s['sort_order'] !='date' )
            $url .= '&o=' . urlencode($sort_order);
    }

    if( $start>0 )
        $url .= '&start=' . $start;
    if( $s['num'] != 20 )
        $url .= '&num=' . $s['num'];
    return $url;
}



function search_emitPageControl( $s, $total )
{
    $total_pages = (int)(($total+($s['num']-1))/$s['num']);
    $current_page = (int)($s['start']/$s['num']);

    $max_pages = 10;
    $firstpage = max( 0, $current_page-$max_pages/2 );
    $lastpage = min( $total_pages-1, $firstpage+($max_pages-1) );

?>
<?php if( $total == 0 ) { ?>
<span>Page 0 of 0</span>
<?php } else { ?>
<span>Page <?= $current_page+1 ?> of <?= $total_pages ?></span>

<span class="page-links">
<?php
    if( $s['start']>0 && $total>0 )
    {
?>
  <a rel="prev" href="<?= h(search_buildURL( $s, max(0,$s['start']-$s['num']) ) ) ?>" >&laquo; Previous</a> |
<?php
    }

    if( $firstpage > 0 )
        print " &hellip; ";

    for( $page=$firstpage; $page<=$lastpage; ++$page )
    {
        if( $page == $current_page ) {
            printf("%s ", $page+1 );
        } else {
            printf( "<a href=\"%s\">%s</a> ",
                h( search_buildURL($s, $page*$s['num']) ),
                $page+1 );
        }
    }

    if( $lastpage+1 < $total_pages )
        print " &hellip; ";

    if( $s['start']+$s['num'] < $total )
    {
?>
  | <a rel="next" href="<?= h(search_buildURL($s, $s['start']+$s['num'] )) ?>">Next &raquo;</a>
<?php
    }
?>
</span>
<?php } ?>

<?php
}



?>
