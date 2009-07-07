<?php
// journo-email.php
// admin page for approving scraped journo bios
//
//

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../phplib/adm.php';

$action = get_http_var( 'action' );
$id = get_http_var( 'id' );


if( $action == 'ajax-hide' )
{
    header("Cache-Control: no-cache");

    db_do( "UPDATE journo_other_articles SET status='h' WHERE id=?", $id );
    db_commit();

    $p = new ApproverPage();
    $item = $p->fetchItem($id);
    $p->emitItem( $item );
    return;
}

if( $action == 'ajax-approve' )
{
    header("Cache-Control: no-cache");
    
    db_do( "UPDATE journo_other_articles SET status='a' WHERE id=?", $id );
    db_commit();

    $p = new ApproverPage();
    $item = $p->fetchItem($id);
    $p->emitItem( $item );
    return;
}

admPageHeader( "title", "ExtraHead" );


if( $action ) {
    printf( "<strong>$action</strong>\n" );
}

$p = new ApproverPage();
$p->run();

admPageFooter();

/********************************/

function EmitFilterForm( $filter )
{
	$f = array( 'all'=>'All addresses', 'approved'=>'Approved addresses only', 'unapproved'=>'Unapproved addresses only' );

?>
<form method="get" action="/adm/journo-email">
Show
<?= form_element_select( "filter", $f, $filter ); ?>
 <input type="submit" name="submit" value="Find" />
</form>
<?php

}

function ExtraHead()
{
?>
<script type="text/javascript" src="/jquery.js"></script>
<script type="text/JavaScript">
$(document).ready(function(){

    $("#item-list").click(function(e) {
        m = /action-(\w+)-(\d+)/i.exec( $(e.target).attr("id") );
        if( m ) {
            var action = m[1];
            var id = m[2];
            var url = '/adm/journo-otherarticles?id=' + id + '&action=ajax-' + action;
            $("#item-" + id).load( url );
            return false;
        }
    });
}); 
</script>
<?php
}



class ApproverPage
{
    public $filter = '';
    public $items = null;

    function __construct() {
        $this->filter = get_http_var( 'filter', '' );
    }

    function run() {
        $this->items = $this->fetchItems();
?>
<div id='item-list'>
<table>
<thead>
  <tr>
    <th>journo</th>
    <th>email</th>
    <th>approved?</th>
    <th>select</th>
  </tr>
</thead>
<tbody>
<?php foreach( $this->items as $item ) { ?>
  <tr id="item-<?php echo $item['otherarticle_id']; ?>">
    <?php $this->emitItem( $item ); ?>
  </tr>
<?php } ?>
</tbody>
</table>
</div>
<?php
    }

    function fetchItem($id) {
    	$sql = <<<EOT
SELECT o.id AS otherarticle_id, o.journo_id, o.url, o.title, o.pubdate, o.publication, o.status, j.ref AS journo_ref, j.prettyname as journo_prettyname
    FROM journo_other_articles o
    JOIN journo j ON o.journo_id=j.id
    WHERE o.id=?
EOT;
    	return db_getRow( $sql,$id );
    }

    function fetchItems() {
        $filter = '';
    	$whereclause = '';
    	if( $filter=='approved' )
    		$whereclause = "WHERE status='a'";
    	elseif( $filter=='unapproved' )
	    	$whereclause = "WHERE status<>'a'";

    	$sql = <<<EOT
SELECT o.id AS otherarticle_id, o.journo_id, o.url, o.title, o.pubdate, o.publication, o.status, j.ref AS journo_ref, j.prettyname as journo_prettyname
    FROM journo_other_articles o
    JOIN journo j ON o.journo_id=j.id
    $whereclause
	ORDER BY o.id
EOT;

    	return db_getAll( $sql );
    }


    function emitItem( &$item ) {
		/* links to journo page and journo admin page */

		$tr_class = ($item['status'] == 'a' ? "bio_approved":"bio_unapproved");
        $title = $item['title']=='' ? $item['url'] : $item['title'];
        $pretty_status = array( 'a' => 'a - approved', 'u' => 'u - unapproved', 'h'=>'h - hidden' );

?>
    <td>
       <a href="/<?echo $item['journo_ref']; ?>"><?php echo $item['journo_prettyname']; ?></a>
       <small>[<a href="/adm/<?echo $item['journo_ref']; ?>">admin</a>]</small>
    </td>
    <td>
        <a href="<?php echo $item['url']; ?>"><?php echo $title; ?></a>
        <small>
          <?php echo $item['publication']; ?>
          <?php echo $item['pubdate']; ?>
        </small>
    </td>
    <td class="<?php echo $tr_class;?>"><?php echo $pretty_status[ $item['status'] ]; ?></td>
    <td>
<?php if( $item['status'] == 'a' ) { ?>
        [<a href="" id="action-hide-<?php echo $item['otherarticle_id']; ?>" class="action-hide">hide</a>]
<!--        <a href="/adm/journo-otherarticles?action=hide&id=<?php echo $item['otherarticle_id'];?>">hide</a> -->
<?php } else { ?>
        [<a href="" id="action-approve-<?php echo $item['otherarticle_id']; ?>" class="action-approve">approve</a>]
<!--        <a href="/adm/journo-otherarticles?action=approve&id=<?php echo $item['otherarticle_id'];?>">approve</a> -->
<?php } ?>
    </td>
<?php

    }

}



