<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/image.php';
require_once '../phplib/editprofilepage.php';
require_once '../phplib/eventlog.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';

define( 'THUMB_W', 128 );
define( 'THUMB_H', 128 );

class PhotoPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "photo";
        $this->pageTitle = "Photo";
        $this->pagePath = "/profile_photo";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );

        $this->uploadError = NULL;

        parent::__construct();

        // fetch the current photo, if any
        $sql = <<<EOT
SELECT p.id, p.image_id, p.is_thumbnail, i.width, i.height, i.filename, i.created
    FROM (journo_photo p INNER JOIN image i ON i.id=p.image_id )
    WHERE p.journo_id=?
    LIMIT 1
EOT;
        $this->photo = db_getRow( $sql, $this->journo['id'] );
        if( !is_null($this->photo) ) {
            $this->photo['is_thumbnail'] = ($this->photo['is_thumbnail']=='t') ? TRUE:FALSE;
        }
    }


    function extra_head()
    {
        if( is_null( $this->photo ) )
            return;

?>
<link rel="stylesheet" type="text/css" href="/imgareaselect-default.css" />
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.imgareaselect.pack.js"></script>


<script type="text/javascript">
  $(document).ready( function() {

    function preview(img, selection) {
      if (!selection.width || !selection.height)
          return;
    
      var scaleX = <?= THUMB_W ?> / selection.width;
      var scaleY = <?= THUMB_H ?> / selection.height;

      $('.croptool .preview img').css({
          width: Math.round(scaleX * <?= $this->photo['width'] ?>),
          height: Math.round(scaleY * <?= $this->photo['height'] ?>),
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

    $('.croptool form .field').hide();

    $('.croptool .fullsize img').imgAreaSelect({ aspectRatio: '1:1', handles: true,
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
        if( $action=='remove' ) {
            $this->replacePhoto( NULL );
        }
        if( $action=='set_thumbnail' ) {
            $this->handleSetThumbnail();
        }
        return TRUE;
    }


    function displayMain()
    {
?>
<h2>Your photo</h2>
<?php
        if( !is_null( $this->photo ) ) {
            if( $this->photo['is_thumbnail'] ) {
?>
<div class="photo">
  <img src="<?= image_url( $this->photo['filename'] ) ?>" alt="photo" width="<?= $this->photo['width'] ?>" height="<?= $this->photo['height']; ?>" />
</div>
<?php
            } else {
                $this->emitCroppingTool();
            }
?>
<a href="<?= $this->pagePath; ?>?ref=<?= $this->journo['ref'] ?>&action=remove">Remove</a>
<?php

        } else {
?>
<p>You have no photo set</p>
<?php
        }
       


?>
<?php if( is_null($this->photo) ) { ?>
<h3>Upload a photo</h3>
<?php } else { ?>
<h3>Upload a different photo</h3>
<?php } ?>

<form action="<?= $this->pagePath; ?>" method="post" enctype="multipart/form-data">

<div class="field">
<label for="file">Filename:</label>
<input type="file" name="file" id="file" />
<?php if( $this->uploadError ) { ?>
<span class="errhint"><?= $this->uploadError; ?></span>
<?php } ?>
</div>
<input type="hidden" name="ref" value="<?= $this->journo['ref']; ?>" />
<input type="hidden" name="action" value="upload_pic" />
<input type="submit" name="submit" value="Upload Photo" />
</form>

<?php

    }


    function handleUpload() {

        $file = $_FILES['file'];

        $err = $file['error'];
        switch( $err ) {
            case UPLOAD_ERR_OK:
                break;

            case UPLOAD_ERR_INI_SIZE:       // 1
                $this->uploadError = "That file was too big (maximum size is " . ini_get( 'upload_max_filesize' ) . ")";
                return;
            case UPLOAD_ERR_NO_FILE:        // 4
                $this->uploadError = "No file was uploaded";
                return;
            default:
                $this->uploadError = "Upload failed (code {$file['error']})";
                return;
        }

        if( image_fileExt( $file['type'] ) == NULL ) {
            $this->uploadError = 'Image must be jpeg, gif or png';
            return;
        }

        $inf = getimagesize( $file['tmp_name'] );
        if( $inf === FALSE ) {
            $this->uploadError = "can't determine image size";
            return;
        }

        $w = $inf[0];
        $h = $inf[1];
        $MAXW = 480;
        if( $w>$MAXW) {
            // scale it down to a reasonable width
            $scale = (float)$MAXW/(float)$w;

            $new_w = $w * $scale;
            $new_h = $h * $scale;

            $source = imagecreatefromstring(
                file_get_contents( $file['tmp_name'] ) );
            $new_image = imagecreatetruecolor( $new_w, $new_h );
            imagecopyresampled( $new_image, $source, 0,0, 0,0,
                $new_w, $new_h,
                $w, $h );
            $this->replacePhoto( $new_image, FALSE );
            return;
        }


        if( $w<=THUMB_W && $h<=THUMB_H ) {
            // It's small enough to use as a thumbnail straight off
            $this->replacePhoto( $file, TRUE );
            return;
        }

        // 
        $this->replacePhoto( $file, FALSE );
    }


    function emitCroppingTool( $thumbrect=null ) {
        // show the cropping tool

        $img = $this->photo;

        if( is_null($thumbrect) ) {
            $thumbrect = array( 'x1'=>'0', 'y1'=>'0', 'x2'=>$img['width'], 'y2'=>$img['height'] );
        }
?>
<div class="croptool">


<form method="post" action="<?= $this->pagePath; ?>">
 <h4>preview</h4>
 <div class="preview" style="padding:0px; width: <?= THUMB_W ?>px; height: <?= THUMB_H ?>px; overflow: hidden;" >
  <img src="<?= image_url($img['filename']); ?>" width="<?= THUMB_W ?>" height="<?= THUMB_H ?>" />
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

 <input type="submit" name="submit" value="Set" />

</form>

<div class="fullsize">
  <p>Select the area of the picture you want to use:</p>
 <img src="<?= image_url($img['filename']); ?>" width="<?=$img['width'] ?>" height="<?= $img['height'] ?>" />
</div>

</div>

<div style="clear: both;"></div>
<?php

    }


    function handleSetThumbnail()
    {
        $photo = $this->photo;
        if( is_null($photo) )
            return;

        $x1 = get_http_var('x1');
        $y1 = get_http_var('y1');
        $x2 = get_http_var('x2');
        $y2 = get_http_var('y2');

        $source = imagecreatefromstring(
            file_get_contents( image_path( $this->photo['filename'] ) ) );
        $thumb = imagecreatetruecolor( THUMB_W, THUMB_H );
        imagecopyresampled( $thumb, $source, 0, 0, $x1, $y1,
            THUMB_W, THUMB_H,
            $x2-$x1, $y2-$y1 );

        $this->replacePhoto( $thumb, TRUE );

        imagedestroy( $thumb );
        imagedestroy( $source );
    }


    // $p can be one of:
    // 1) uploaded file info
    // 2) a gd image
    // 3) null - just remove existing photo without adding a new one
    function replacePhoto( $p, $is_thumbnail=FALSE ) {

        $new_photo = null;

        if( $p ) {
            if( is_resource($p) && get_resource_type($p)=='gd' ) {
                $new_photo = image_storeGD( $p );
            } else {
                // assume it's an uploaded file
                $new_photo = image_storeUploaded( $p );
            }
            if( $new_photo ) {
                $new_photo['image_id'] = $new_photo['id'];
                $new_photo['is_thumbnail'] = $is_thumbnail;
                unset( $new_photo['id'] );
            }
        }

        if( $this->photo ) {
            // remove existing one from db
            db_do( "DELETE FROM journo_photo WHERE id=?", $this->photo['id'] );
            db_do( "DELETE FROM image WHERE id=?", $this->photo['image_id'] );
        }


        if( $new_photo ) {
            // put new one in db
            $new_photo['id'] = db_getOne( "select nextval('journo_photo_id_seq' )" );
            db_do( "INSERT INTO journo_photo (journo_id,image_id,is_thumbnail) VALUES (?,?,?)", $this->journo['id'], $new_photo['image_id'], $new_photo['is_thumbnail'] );

            db_commit();
        
            if( $this->photo ) {
                // db synced - can now zap the old file
                unlink( image_path( $this->photo['filename'] ) );
            }
        }

        // done.
        $this->photo = $new_photo;
    }


}


$page = new PhotoPage();
$page->run();


