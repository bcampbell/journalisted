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
<?php
    }


    function handleActions()
    {
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $this->handleSubmit();
            // back to main profile page now please
            $this->Redirect( "/{$this->journo['ref']}#tab-contact" );
        }
    }


    function display()
    {
?><h2>Contact Information</h2><?php

        $email = db_getRow( "SELECT * FROM journo_email WHERE journo_id=? AND approved=true AND srctype='' LIMIT 1", $this->journo['id'] );
        $phone = db_getRow( "SELECT * FROM journo_phone WHERE journo_id=? LIMIT 1", $this->journo['id'] );
        $address = db_getRow( "SELECT * FROM journo_address WHERE journo_id=? LIMIT 1", $this->journo['id'] );
        $twitter_id = journo_fetchTwitterID( $this->journo['id'] );

        $contact = array( 'email' => $email, 'phone'=>$phone, 'address'=>$address, 'twitter'=>$twitter_id );


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
        $twitter = $contact['twitter'];

?>
<p>This contact information is for your <em>public</em> profile.<br/>
Please do not enter any information wish to keep private.</p>

<form class="contact" method="POST" action="<?= $this->pagePath; ?>">


  <dl>
    <dt><label for="email">Email Address</label></dt>
    <dd><input type="text" size="60" name="email" id="email" value="<?= h($email['email']) ?>" /></dd>

    <dt><label for="email">Twitter name</label></dt>
    <dd><input type="text" size="60" name="twitter" id="twitter" value="<?= h($twitter) ?>" /></dd>

    <dt><label for="phone">Telephone</label></dt>
    <dd><input type="text" size="60" name="phone" id="phone" value="<?= h($phone['phone_number']); ?>" /></dd>

    <dt><label for="address">Postal Address</label></dt>
    <dd><textarea name="address" cols="80" rows="5" id="address"><?= h($address['address']); ?></textarea></dd>
  </dl>

  <input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
  <input type="hidden" name="action" value="submit" />
  <button class="submit" type="submit">Save changes</button> or
  <a class="cancel" href="/<?= $this->journo['ref'] ?>#tab-contact">cancel</a>

</form>

<?php

    }




    function handleSubmit()
    {
        $email = get_http_var('email');
        $phone = get_http_var('phone');
        $address = get_http_var('address');
        $twitter = get_http_var('twitter');

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

        // twitter
        db_do( "DELETE FROM journo_weblink WHERE journo_id=? AND kind='twitter'", $this->journo['id'] );
        if( $twitter ) {
            $twitter_url = 'http://twitter.com/' . $twitter;
            $twitter_desc = $this->journo['prettyname'] . ' on Twitter';
            db_do( "INSERT INTO journo_weblink (journo_id,url,description,approved,kind) VALUES (?,?,?,true,'twitter')",
                $this->journo['id'],
                $twitter_url,
                $twitter_desc );
        }

        db_commit();
        eventlog_Add( 'modify-contact', $this->journo['id'] );
    }


}




$page = new ContactPage();
$page->run();


