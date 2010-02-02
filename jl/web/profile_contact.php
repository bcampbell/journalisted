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


    function handleActions()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
        }
        return TRUE;
    }


    function display()
    {
?><h2>Contact Information</h2><?php

        $email = db_getRow( "SELECT * FROM journo_email WHERE journo_id=? AND approved=true AND srctype='' LIMIT 1", $this->journo['id'] );
        $phone = db_getRow( "SELECT * FROM journo_phone WHERE journo_id=? LIMIT 1", $this->journo['id'] );
        $address = db_getRow( "SELECT * FROM journo_address WHERE journo_id=? LIMIT 1", $this->journo['id'] );

        $contact = array( 'email' => $email, 'phone'=>$phone, 'address'=>$address );


        $this->showForm( $contact );
    }



    function showForm( $contact )
    {
        /* set up defaults for anything missing */
        if( is_null( $contact['email'] ) )
            $contact['email'] = array( 'email'=>'' );
        if( is_null( $contact['phone'] ) )
            $contact['phone'] = array( 'phone_number'=>'' );
        if( is_null( $contact['address'] ) )
            $contact['address'] = array( 'address'=>'' );

        $email = $contact['email'];
        $address = $contact['address'];
        $phone = $contact['phone'];

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

        // address
        db_do( "DELETE FROM journo_address WHERE journo_id=?", $this->journo['id'] );
        if( $address ) {
            db_do( "INSERT INTO journo_address (journo_id,address) VALUES (?,?)",
                $this->journo['id'],
                $address );
        }

        // phone
        db_do( "DELETE FROM journo_phone WHERE journo_id=?", $this->journo['id'] );
        if( $phone ) {
            db_do( "INSERT INTO journo_phone (journo_id,phone_number) VALUES (?,?)",
                $this->journo['id'],
                $phone );
        }

        // email
        db_do( "DELETE FROM journo_email WHERE journo_id=? AND srctype=''", $this->journo['id'] );
        if( $email ) {
            db_do( "INSERT INTO journo_email (journo_id,email,srctype,srcurl,approved) VALUES (?,?,?,?,?)",
                $this->journo['id'],
                $email,
                '',     // srctype
                '',     // srcurl
                TRUE );
        }

        db_commit();
        eventlog_Add( 'modify-contact', $this->journo['id'] );
    }


}




$page = new ContactPage();
$page->run();


