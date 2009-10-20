<?php

/* functions for handling uploaded images */


function imageFileExt( $filetype ) {
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
function imageStoreUploaded( &$up ) {
    $ext = imageFileExt( $up['type'] );
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




function imageURL( $filename ) {
    return "/img/" . $filename;
}


function imageZap( $image_id )
{
    $filename = db_getOne( "SELECT filename FROM image WHERE id=?", $image_id );


}



?>
