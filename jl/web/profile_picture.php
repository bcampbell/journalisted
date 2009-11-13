<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/image.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class PicturePage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "picture";
        $this->pageTitle = "Photo";
        $this->pagePath = "/profile_picture";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<?php
    }


    function handleActions()
    {
        $action = get_http_var( 'action' );
        if( $action=='upload_pic' ) {
            $this->handleUpload();
        }
        if( $action=='remove_pic' ) {
            $this->handleRemove();
        }
        return TRUE;
    }


    function displayMain()
    {
        $img = journo_getPicture( $this->journo['id'] );

?>
<h2><?= $img ? "Change your photo" : "Set a photo" ?></h2>

<form action="<?= $this->pagePath; ?>" method="post" enctype="multipart/form-data">

<?php if( $img ) { ?>
<p>Your current photo:</p>
<img src="<?= $img['url']; ?>" />
<br/>
<p>Upload a different one:</p>
<?php } else { ?>
<p>You have no photo set currently</p>
<p>Upload one:</p>
<?php } ?>

<label for="file">Filename:</label>
<input type="file" name="file" id="file" />
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<input type="hidden" name="action" value="upload_pic" />
<input type="submit" name="submit" value="Upload" />
</form>

<?php

    }



    function handleUpload() {
        $up = $_FILES['file'];
        $errmsg = $this->CheckUploadedImage( $up );
        if( is_null( $errmsg ) ) {
            $img = imageStoreUploaded( $up );
            if( $img ) {

                db_do( "INSERT INTO journo_picture (journo_id,image_id) VALUES (?,?)",
                    $this->journo['id'], $img['id'] );
                db_commit();

                $this->addInfo( "Picture set" );
/*                print "<p>image uploaded.</p>\n"; */
/*                print "<img src=\"/img/{$img['filename']}\" />\n";
                print "<p>{$img['width']}x{$img['height']}</p>\n"; */

                /* delete any other images */
                $others = db_getAll( "SELECT image_id FROM journo_picture WHERE journo_id=? AND image_id<>?", $this->journo['id'], $img['id'] );
                db_do( "DELETE FROM journo_picture WHERE journo_id=? AND image_id<>?", $this->journo['id'], $img['id'] );
                db_commit();
                foreach( $others as $other )
                    imageZap( $other['image_id'] );

            } else {
                $this->addError( "failed to store image" );
            }

        } else {
            $this->addError( $errmsg );
        }
    }


    function handleRemove() {
        /* should only be one image, but zap all, just in case */
        $image_ids = db_getAll( "SELECT image_id FROM journo_picture WHERE journo_id=?", $this->journo['id'] );
        db_do( "DELETE FROM journo_picture WHERE journo_id=?", $this->journo['id'] );
        db_commit();

        foreach( $image_ids as $image_id ) {
            imageZap( $image_id );
        }

        $this->addInfo( "Picture removed" );
    }




    function CheckUploadedImage( &$file ) {
        if( $file['error'] > 0 ) {
            return "Upload failed (code {$file['error']})";
        }

        if( imageFileExt( $file['type'] ) == NULL ) {
            return 'Image must be jpeg, gif or png';
        }

        $inf = getimagesize( $file['tmp_name'] );
        if( $inf === FALSE )
            return "can't determine image size";

        $w = $inf[0];
        $h = $inf[1];
        $MAXH = 400;
        $MAXW = 400;
        if( $w>$MAXW || $h>$MAXH) {
            return "image too large (max {$MAXW}x{$MAXH})";
        }

        /* if we get this far, image is acceptable! */
        return NULL;
    }








}


$page = new PicturePage();
$page->run();


