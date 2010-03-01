<?php

/* functions for handling uploaded images */


function image_fileExt( $filetype ) {
    if( $filetype == 'image/gif' )
        return 'gif';
    elseif( $filetype == 'image/jpeg' || $filetype=='image/pjpeg' )
        return 'jpeg';
    elseif( $filetype=='image/png' )
        return 'png';
    else
        return NULL;
}




/* copy an uploaded image into the uploads dir, and create a db entry for it
 * returns array with new image entry.
 * db_commit() not called - this is left up to the caller.
 */
function image_storeUploaded( &$up ) {
    $ext = image_fileExt( $up['type'] );
    if( is_null($ext) )
        return NULL;

    $inf = getimagesize( $up['tmp_name'] );
    if( $inf === FALSE )
        return NULL;
    $w = $inf[0];
    $h = $inf[1];

    $image_id = db_getOne( "select nextval('image_id_seq' )" );
    $filename = "{$image_id}.{$ext}";

    if( !move_uploaded_file( $up['tmp_name'], OPTION_JL_IMG_UPLOAD . '/' . $filename ) ) {
        return NULL;
    }

    db_do( "INSERT INTO image (id,filename,width,height) VALUES (?,?,?,?)",
        $image_id, $filename, $w, $h );

    return array(
        'id' => $image_id,
        'width' => $w,
        'height' => $h,
        'filename' => $filename
        );
}



function image_storeGD( $im, $ext='jpeg' )
{
    $image_id = db_getOne( "select nextval('image_id_seq' )" );
    $filename = "{$image_id}.{$ext}";

    $success = FALSE;
    if( $ext == 'jpeg' ) {
        $success = imagejpeg( $im, OPTION_JL_IMG_UPLOAD . '/' . $filename );
    }

    if( !$success )
        return NULL;

    $w = imagesx($im);
    $h = imagesy($im);
    db_do( "INSERT INTO image (id,filename,width,height) VALUES (?,?,?,?)",
        $image_id, $filename, $w, $h );

    return array(
        'id' => $image_id,
        'width' => $w,
        'height' => $h,
        'filename' => $filename
        );
}




//
function image_path( $filename ) {
    return OPTION_JL_IMG_UPLOAD . '/' . $filename;
}


function image_url( $filename ) {
    return "/upload/" . $filename;
}

// delete an uploaded image. commits the db change before deleting image file.
function image_zap( $image_id )
{
    $filename = db_getOne( "SELECT filename FROM image WHERE id=?", $image_id );
    db_do( "DELETE FROM image WHERE id=?", $image_id );
    db_commit();

    unlink( image_path( $filename )  );
}


?>
