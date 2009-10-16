<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class AwardsPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "awards";
        $this->pagePath = "/profile_awards";
        $this->pageTitle = "Awards";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocompete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<? /*
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
*/
?>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.award' );
    });
</script>
<?php
    }




    function displayMain()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }

        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }
?><h2>Have you won any awards?</h2><?php
        $awards = db_getAll( "SELECT * FROM journo_awards WHERE journo_id=?", $this->journo['id'] );
        foreach( $awards as $a ) {
            $this->showForm( $a );
        }
        $this->showForm( NULL );

    }

    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'remove_link_html'=>$this->genRemoveLink($entry_id),
            );
            print json_encode( $result );
        }
    }

    function showForm( $award )
    {
        static $uniq=0;
        ++$uniq;
        $is_template = is_null( $award );
        if( $is_template )
            $award = array( 'award'=>'' );


?>
<form class="award<?= $is_template?' template':''; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="award_<?= $uniq; ?>">Award</label></th>
  <td><input type="text" size="60" name="award" id="award_<?= $uniq; ?>" value="<?= h($award['award']); ?>" /></td>
 </tr>
</table>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<button name="action" value="submit">Submit</button>
<button class="cancel" type="reset">Cancel</button>
<?php if( !$is_template ) { ?>
<input type="hidden" name="id" value="<?= $award['id']; ?>" />
<?= $this->genRemoveLink($award['id']); ?>
<?php } ?>
</form>

<?php

    }


    function handleSubmit()
    {
        $fieldnames = array( 'award' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        $this->genericStoreItem( "journo_awards", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_awards WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }
}



$page = new AwardsPage();
$page->run();


