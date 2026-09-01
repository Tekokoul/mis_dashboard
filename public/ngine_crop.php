<?php
/*
nGine Crop v1.0
+caching added
*/


$file_name=$_GET['f'];
$crop_height=$_GET['h'];
$crop_width=$_GET['w'];
$path_parts = pathinfo($file_name);
$file_type=$path_parts['extension'];
//$file_type= explode('.', $file_name); 
//$file_type = $file_type[count($file_type) -1];
//$file_type=strtolower($file_type);

$cachedir="cache/".$path_parts['dirname']."/";
if (!file_exists ($cachedir)) {mkdir($cachedir, 0755,true);}
$cachefile=$cachedir.$crop_height."_".$crop_width."_".$path_parts['filename'].".jpg";


if($file_type == "svg") {
    header("Content-type: image/svg+xml");
    $fp = fopen($file_name, 'rb'); # stream the image directly from the cachefile
    fpassthru($fp);
    exit();
}

if( !file_exists($cachefile) ) {

$original_image_size = getimagesize($file_name);
$original_width = $original_image_size[0];
$original_height = $original_image_size[1];
if(($file_type=='jpg') ||($file_type=='jpeg')||($file_type=='JPG') ||($file_type=='JPEG'))
{
$original_image_gd = imagecreatefromjpeg($file_name);
}

if(($file_type=='gif')||($file_type=='GIF')) 
{ $original_image_gd = imagecreatefromgif($file_name);
}	

if(($file_type=='png')||($file_type=='PNG')) 
{
$original_image_gd = imagecreatefrompng($file_name);
}

$cropped_image_gd = imagecreatetruecolor($crop_width, $crop_height);
$wm = $original_width /$crop_width;
$hm = $original_height /$crop_height;
$h_height = $crop_height/2;
$w_height = $crop_width/2;

if($original_width > $original_height ) 
{
$adjusted_width =$original_width / $hm;
$half_width = $adjusted_width / 2;
$int_width = $half_width - $w_height;

imagecopyresampled($cropped_image_gd ,$original_image_gd ,-$int_width,0,0,0, $adjusted_width, $crop_height, $original_width , $original_height );
} 
elseif(($original_width < $original_height ) || ($original_width == $original_height ))
{
$adjusted_height = $original_height / $wm;
$half_height = $adjusted_height / 2;
$int_height = $half_height - $h_height;

imagecopyresampled($cropped_image_gd , $original_image_gd ,0,-$int_height,0,0, $crop_width, $adjusted_height, $original_width , $original_height );
} 
else {

imagecopyresampled($cropped_image_gd , $original_image_gd ,0,0,0,0, $crop_width, $crop_height, $original_width , $original_height );
}
    header ("Content-type: image/jpeg");
  //  imageinterlace($cropped_image_gd,0);
    imagejpeg($cropped_image_gd, $cachefile,80 ); # store the image to cachefile

    # don't output it like this:
    /* imagepng($im);*/
   
    imagedestroy($cropped_image_gd);
  }

  $fp = fopen($cachefile, 'rb'); # stream the image directly from the cachefile
  fpassthru($fp);
  exit;


//imagepng($cropped_image_gd);