<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/image.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class PhotoPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "photo";
        $this->pageTitle = "Photo";
        $this->pagePath = "/profile_photo";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
?>
<link rel="stylesheet" type="text/css" href="/imgareaselect-default.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.imgareaselect.pack.js"></script>


<script type="text/javascript">
    $(document).ready( function() {

function preview(img, selection) {
    if (!selection.width || !selection.height)
        return;
    
    var scaleX = 100 / selection.width;
    var scaleY = 100 / selection.height;

    $('#preview img').css({
        width: Math.round(scaleX * 640),
        height: Math.round(scaleY * 480),
        marginLeft: -Math.round(scaleX * selection.x1),
        marginTop: -Math.round(scaleY * selection.y1)
    });

    $('#x1').val(selection.x1);
    $('#y1').val(selection.y1);
    $('#x2').val(selection.x2);
    $('#y2').val(selection.y2);
    $('#w').val(selection.width);
    $('#h').val(selection.height);    
}


        $('#fullsize img').imgAreaSelect({ aspectRatio: '1:1', handles: true,
            fadeSpeed: 200, onSelectChange: preview });
    } );
</script>

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
        if( $action=='set_thumbnail' ) {
            $this->handleSetThumbnail();
        }
        return TRUE;
    }


    function displayMain()
    {
        $thumb = null;
        $fullsize = null;
        $jpics = db_getRow( "SELECT * FROM journo_photo WHERE journo_id=?", $this->journo['id'] );
        if( $jpics ) {
            if( !is_null( $jpics['thumb_id'] ) )
                $thumb = db_getRow( "SELECT * FROM image WHERE id=?", $jpics['thumb_id'] );
            if( !is_null( $jpics['fullsize_id'] ) )
                $fullsize = db_getRow( "SELECT * FROM image WHERE id=?", $jpics['fullsize_id'] );

            if( is_null($thumb) && is_null($fullsize) )
                $jpics = null;  /* shouldn't get here, but hey. */
        }


        if( $fullsize ) {
            $this->emitCroppingTool( $fullsize );
        }


?>
<h2>Upload a photo</h2>

<form action="<?= $this->pagePath; ?>" method="post" enctype="multipart/form-data">

<div class="field">
<label for="file">Filename:</label>
<input type="file" name="file" id="file" />
</div>
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<input type="hidden" name="action" value="upload_pic" />
<input type="submit" name="submit" value="Upload Photo" />
</form>

<?php

    }



    function handleUpload() {
        $up = $_FILES['file'];
        $errmsg = $this->CheckUploadedImage( $up );
        if( is_null( $errmsg ) ) {
            $img = imageStoreUploaded( $up );
            if( $img ) {
                db_do( "DELETE FROM journo_photo WHERE journo_id=?", $this->journo['id'] );
                /* TODO: zap actual images! */

                db_do( "INSERT INTO journo_photo (journo_id,fullsize_id) VALUES (?,?)", $this->journo['id'], $img['id'] );
                db_commit();
            } else {
                $this->addError( "failed to store image" );
            }
        } else {
            $this->addError( $errmsg );
        }
    }


    function handleRemove() {
        /* should only be one image, but zap all, just in case */
/*
        $image_ids = db_getAll( "SELECT image_id FROM journo_picture WHERE journo_id=?", $this->journo['id'] );
        db_do( "DELETE FROM journo_picture WHERE journo_id=?", $this->journo['id'] );
        db_commit();

        foreach( $image_ids as $image_id ) {
            imageZap( $image_id );
        }
*/
        $this->addInfo( "Photo removed" );
    }


    function fetchCurrent() {
        $cur = db_getRow( "SELECT * FROM journo_photo WHERE journo_id=?", $this->journo['id'] );
        if( $cur ) {
            $cur['fullsize'] = null;
            $cur['thumb'] = null;
            if( !is_null( $cur['thumb_id'] ) )
                $cur['thumb'] = db_getRow( "SELECT * FROM image WHERE id=?", $cur['thumb_id'] );
            if( !is_null( $cur['fullsize_id'] ) )
                $cur['fullsize'] = db_getRow( "SELECT * FROM image WHERE id=?", $cur['fullsize_id'] );
        }

        return $cur;
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
        $MAXH = 1000;
        $MAXW = 1000;
        if( $w>$MAXW || $h>$MAXH) {
            return "image too large (max {$MAXW}x{$MAXH})";
        }

        /* if we get this far, image is acceptable! */
        return NULL;
    }


    function emitCroppingTool( $fullsize, $thumbrect=null ) {
        // show the cropping tool

        if( is_null($thumbrect) ) {
            $thumbrect = array( 'x1'=>'0', 'y1'=>'0', 'x2'=>$fullsize['width'], 'y2'=>$fullsize['height'] );
        }
?>

<div id="fullsize">
 <img src="<?= imageUrl($fullsize['filename']); ?>" width="<?=$fullsize['width'] ?>" height="<?= $fullsize['height'] ?>" />
</div>

<form method="post" action="<?= $this->pagePath; ?>">
 <div id="preview" style="padding:0px; width: 100px; height: 100px; overflow: hidden;" >
  <img src="<?= imageUrl($fullsize['filename']); ?>" width="100" height="100" />
 </div>

 <div class="field">
  <label for="x1">x1</label>
  <input type="text" id="x1" name="x1" value="<?= $thumbrect['x1']; ?>" />
 </div>

 <div class="field">
  <label for="y1">y1</label>
  <input type="text" id="y1" name="y1" value="<?= $thumbrect['y1']; ?>" />
 </div>

 <div class="field">
  <label for="x2">x2</label>
  <input type="text" id="x2" name="x2" value="<?= $thumbrect['x2']; ?>" />
 </div>

 <div class="field">
  <label for="y2">y2</label>
  <input type="text" id="y2" name="y2" value="<?= $thumbrect['y2']; ?>" />
 </div>

 <input type="hidden" name="ref" value="<?= $this->journo['ref'] ?>" />
 <input type="hidden" name="action" value="set_thumbnail" />

 <input type="submit" name="submit" value="Set Thumbnail" />

</form>

<?php

    }


    function handleSetThumbnail()
    {
        $cur = fetchCurrent();
        if( is_null($cur) || is_null($cur['fullsize']) )
            return;

        $x1 = get_http_var('x1');
        $y1 = get_http_var('y1');
        $x2 = get_http_var('x2');
        $y2 = get_http_var('y2');

/*
        $thumb_width = 100;
        $thumn_heihgt = 100;

        $source = imagecreatefromstring(
            file_get_contents( imagePath( $cur['fullsize']['filename'] ) ) );
        $thumb = imagecreatetruecolor($thumb_width, $thumb_height);
        imagecopyresampled( $thumb, $source, 0, 0, $x1, $y1,
            $thumb_width, $thumb_height,
            $x2-$x1, $y2-y1 );
       


 
        imagedestroy($source);
*/        


    }



}


$page = new PhotoPage();
$page->run();


