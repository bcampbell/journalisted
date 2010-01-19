<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
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
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
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

        $contact = journo_getContactDetails( $this->journo['id'] );
        $this->showForm( $contact );
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



    function showForm( $contact )
    {
        /* set up defaults for anything missing */
        if( is_null( $contact['email'] ) )
            $contact['email'] = array( 'email'=>'', 'show_public'=>TRUE );
        if( is_null( $contact['phone'] ) )
            $contact['phone'] = array( 'phone_number'=>'', 'show_public'=>TRUE );
        if( is_null( $contact['address'] ) )
            $contact['address'] = array( 'address'=>'', 'show_public'=>TRUE );

        $email = $contact['email'];
        $address = $contact['address'];
        $phone = $contact['phone'];
        $show_public = $email['show_public'] && $phone['show_public'] && $address['show_public'];

?>

<form class="contact" method="POST" action="<?= $this->pagePath; ?>">

 <div class="field">
  <label for="email">Email Address</label>
  <input type="text" size="60" name="email" id="email" value="<?= h($email['email']) ?>" />
 </div>

 <div class="field">
  <label for="phone">Phone</label>
  <input type="text" size="60" name="phone" id="phone" value="<?= h($phone['phone_number']); ?>" />
 </div>

 <div class="field">
  <label for="address">Postal Address</label>
  <textarea name="address" cols="80" rows="5" id="address"><?= h($address['address']); ?></textarea>
 </div>

 <fieldset class="field">
   <span class="faux-label"></span>
   <input type="checkbox" id="show_public" name="show_public" value="yes" <?= $show_public?'checked ':''?>/>
   <label for="show_public">Allow this information to be public?</label>
 </div>

 <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
 <input type="hidden" name="action" value="submit" />
 <button class="submit" type="submit">Save</button>
</form>
<?php

    }




    function handleSubmit()
    {
        $email = get_http_var('email');
        $phone = get_http_var('phone');
        $address = get_http_var('address');
        $show_public = get_http_var('show_public' ) == 'yes' ? TRUE:FALSE;



        // address
        db_do( "DELETE FROM journo_address WHERE journo_id=? AND srctype=''", $this->journo['id'] );
        if( $address ) {
            db_do( "INSERT INTO journo_address (journo_id,address,srctype,show_public) VALUES (?,?,?,?)",
                $this->journo['id'],
                $address,
                '',     // srctype
                $show_public );
        }

        // phone
        db_do( "DELETE FROM journo_phone WHERE journo_id=? AND srctype=''", $this->journo['id'] );
        if( $phone ) {
            db_do( "INSERT INTO journo_phone (journo_id,phone_number,srctype,show_public) VALUES (?,?,?,?)",
                $this->journo['id'],
                $phone,
                '',     // srctype
                $show_public );
        }

        // email
        db_do( "DELETE FROM journo_email WHERE journo_id=? AND srctype=''", $this->journo['id'] );
        if( $email ) {
            db_do( "INSERT INTO journo_email (journo_id,email,srctype,srcurl,approved) VALUES (?,?,?,?,?)",
                $this->journo['id'],
                $email,
                '',     // srctype
                '',     // srcurl
                $show_public );
        }

        db_commit();
        eventlog_Add( 'modify-contact', $this->journo['id'] );
    }



}




$page = new ContactPage();
$page->run();


