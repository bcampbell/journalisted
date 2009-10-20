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
        // TODO: use compressed jquery.autocomplete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<?php
    }


    function displayMain()
    {
        $action = get_http_var( 'action' );
        if( $action=='upload_pic' ) {
            $this->handleUpload();
        }
        if( $action=='remove_pic' ) {
            $this->handleRemove();
        }

        $sql = <<<EOT
SELECT i.filename,i.width,i.height
    FROM ( image i INNER JOIN journo_picture jp ON jp.image_id=i.id )
    WHERE jp.journo_id=?
EOT;
        $imgs = db_getAll( $sql, $this->journo['id'] );

?>
<h2>Set a picture</h2>
<?php

        foreach( $imgs as $img ) {

?>
<img src="<?= imageUrl($img['filename']); ?>" />
<a class="remove" href="">Remove</a>
<br/>
<?php

        }

        if( sizeof($imgs) == 0 ) {

?>
<p>You have no picture set currently</p>
<?php

        }

?>
<form action="<?= $this->pagePath; ?>" method="post" enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file" />
<br />
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

                print "<p>image uploaded.</p>\n";
/*                print "<img src=\"/img/{$img['filename']}\" />\n";
                print "<p>{$img['width']}x{$img['height']}</p>\n"; */
            } else {
                print "<p>ERROR: failed to store image</p>\n";
            }

        } else {
            print "<p>ERROR: $errmsg</p>\n";
        }
    }


    function handleRemove() {
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


