<?php

/* helper functions for journo profile data */


require_once '../conf/general';
//require_once 'misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/person.php';
require_once '../phplib/page.php';
require_once '../../phplib/utility.php';


// base class for profile-editing pages
class EditProfilePage
{

    public $pageName = '';
    public $P = null;   // currently logged-on user
    public $journo = null;
    public $pageTitle = '';
    public $pageParams = array();

    function __construct()
    {
        $this->P = person_if_signed_on();
        $ref = get_http_var( 'ref' );
        $this->journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
    }


    function display()
    {
        page_header( $this->pageTitle, $this->pageParams );

        // check we're logged in
        if( is_null($this->P) ) {
            print( "<p>Sorry, you need to be logged in to edit your profile.</p>\n" );
            page_footer();
            return;
        }

        // make sure we've specified a journo
        if( is_null( $this->journo ) ) {
            print( "<p>No journalist specified.</p>\n" );
            page_footer();
            return;
        }

        // is this person allowed to edit this journo?
        if( !db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
            $this->P->id(), $this->journo['id'] ) ) {
            print( "<p>Not allowed.</p>\n" );
            page_footer();
            return;
        }

        // show the navbar for profile editing
?>
<h2>Welcome, <em>Phil Notebook</em></h2>

<div class="pipeline">
 <span class="<?=$this->pageName=='admired'?'active':'';?>">1. <a href="/profile_admired?ref=<?=$this->journo['ref'];?>">Journalists you admire</a></span>
 <span class="<?=$this->pageName=='profile'?'active':'';?>">2. <a href="">Add to your profile</a></span>
 <span class="<?=$this->pageName=='missing'?'active':'';?>">3. <a href="">Tell us anything we've missed/got wrong</a></span>
</div>
<?php

        $this->displayMain();
        page_footer();
    }

    // derived pages override this.
    function displayMain()
    {
    }

}


?>
