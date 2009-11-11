<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class ContactPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "contact";
        $this->pagePath = "/profile_contact";
        $this->pageTitle = "Contact";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<? /*
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
*/
?>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.contact' );
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
?><h2>Contact Information</h2><?php

        $contacts = array();
        $email = db_getOne( "SELECT email FROM journo_email WHERE journo_id=?", $this->journo['id'] );
        if( $email )
            $contacts[] = array( 'email'=>$email );

        foreach( $contacts as &$contact ) {
            $this->showForm( 'edit', $contact);
        }

        if( !$contacts ) {
            /* show a ready-to-go creation form */
            $this->showForm( 'creator', null );
        }

        /* template form for adding new ones */
        $this->showForm( 'template', null );
    }


    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'editlinks_html'=>$this->genEditLinks($entry_id),
            );
            print json_encode( $result );
        }
    }



    function showForm( $formtype, $contact )
    {
        static $uniq=0;
        ++$uniq;
        if( is_null( $contact ) )
            $contact = array( 'email'=>'', 'phone'=>'', 'postal'=>'' );
 
        $formclasses = 'contact';
        if( $formtype == 'template' )
            $formclasses .= " template";
        if( $formtype == 'creator' )
            $formclasses .= " creator";

?>

<form class="<?= $formclasses; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="email_<?= $uniq; ?>">Email:</label></th>
  <td><input type="text" size="60" name="email" id="email_<?= $uniq; ?>" value="<?= h($contact['email']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="phone_<?= $uniq; ?>">Phone:</label></th>
  <td><input type="text" size="60" name="phone" id="phone_<?= $uniq; ?>" value="<?= h($contact['phone']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="postal_<?= $uniq; ?>">Postal:</label></th>
  <td><textarea cols="80" rows="5" name="postal" id="postal_<?= $uniq; ?>"><?= h($contact['postal']); ?></textarea></td>
 </tr>
</table>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save</button>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $contact['id']; ?>" />
<?= $this->genEditLinks($contact['id']); ?>
<?php } ?>
</form>
<?php

    }




    function handleSubmit()
    {
/*        $fieldnames = array( 'title', 'publisher', 'year_published' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        $this->genericStoreItem( "journo_books", $fieldnames, $item );
        return $item['id'];
*/
    }

    function handleRemove() {
/*        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_books WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
*/
    }


}




$page = new ContactPage();
$page->run();


