<?php
include "../app/configuration/settings.php";
$filename=$_GET['f'];
$height=$_GET['h'];
$width=$_GET['w'];
if(isset($_GET['t'])){
    $watermark = "media/website/watermark.png";
    $addprefix = "wm_";
} else {
    $watermark = "";
    $addprefix = "";
}

$path_parts = pathinfo($filename);

$file_type = ".".$path_parts['extension'];
$prefix = $addprefix.$height . "_" . $width . "_";
$new_filename = $prefix . $path_parts['filename'] . $file_type;

$cachedir=_CACHE_PATH.$path_parts['dirname'].DS;
if (!file_exists($cachedir)) {
    mkdir($cachedir, 0755, true);
}

$cache_actual_file = $cachedir.$new_filename;


function resize_crop_image($max_width, $max_height, $source_file, $cachefile=NULL, $watermark="" ){
    $imgsize = getimagesize($source_file);
    $width = $imgsize[0];
    $height = $imgsize[1];
    $mime = $imgsize['mime'];


    switch($mime){
        case 'image/gif':
            $image_create = "imagecreatefromgif";
            $image = "imagegif";
            break;

        case 'image/png':
            $image_create = "imagecreatefrompng";
            $image = "imagepng";
            $quality = 9;
            break;

        case 'image/jpeg':
            $image_create = "imagecreatefromjpeg";
            $image = "imagejpeg";
            $quality = 90;
            break;

        default:
            return false;
            break;
    }

    $dst_img = imagecreatetruecolor($max_width, $max_height);
    $src_img = $image_create($source_file);


    $width_new = $height * $max_width / $max_height;
    $height_new = $width * $max_height / $max_width;


    //if the new width is greater than the actual width of the image, then the height is too large and the rest cut off, or vice versa
    if($width_new > $width){
        //cut point by height
        $h_point = (($height - $height_new) / 2);
        //copy image
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, $h_point, $max_width, $max_height, $width, $height_new);
    }else{
        //cut point by width
        $w_point = (($width - $width_new) / 2);
        imagecopyresampled($dst_img, $src_img, 0, 0, $w_point, 0, $max_width, $max_height, $width_new, $height);
    }
    imagealphablending($dst_img, true);
    imagesavealpha($dst_img, true);
    if($watermark!="") {
        $stamp = imagecreatefrompng($watermark);
        $sx = imagesx($stamp);
        $sy = imagesy($stamp);
        $newwidth = $max_width;
        $newheight = round($max_width/($sx/$sy));
        imagecopyresized($dst_img, $stamp, imagesx($dst_img) - $newwidth, imagesy($dst_img) - $newheight, 0, 0, $newwidth, $newheight, $sx, $sy);
    }
    if(!file_exists($cachefile)){
        $image($dst_img, $cachefile, $quality);
    }
    header ("Content-type: ".$mime);
    $image($dst_img, NULL, $quality);

    if($dst_img)imagedestroy($dst_img);
    if($src_img)imagedestroy($src_img);
    exit();
}

resize_crop_image($width, $height, $filename, $cache_actual_file, $watermark);