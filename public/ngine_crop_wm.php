<?php 
/*
nGine Crop with Watermark v0.1
+caching added
*/


$file_name=$_GET['f'];
$crop_height=$_GET['h'];
$crop_width=$_GET['w'];
$wm_type=$_GET['t'];
$prefix="wm_".$wm_type."_";
$path_parts = pathinfo($file_name);
$file_type=$path_parts['extension'];
//$file_type= explode('.', $file_name); 
//$file_type = $file_type[count($file_type) -1];
//$file_type=strtolower($file_type);
switch ($wm_type){
    case "e":   $percentage = 100;
                $stamp = imagecreatefrompng('images/exclussive.png');
                $align = "right";
                break;
    case "a":   $percentage = 100;
                $stamp = imagecreatefrompng('images/alert.png');
                $align = "right";
                break;
    case "l":   $percentage = 100;
                $stamp = imagecreatefrompng('images/live.png');
                $align = "right";
                break;
    case "w":   $percentage = 10;
                $stamp = imagecreatefrompng('images/watermark.png');
                $align = "center";
                break;
}






$cachedir="cache/".$path_parts['dirname']."/";
if (!file_exists ($cachedir)) {mkdir($cachedir, 0755,true);}
$cachefile=$cachedir.$prefix.$crop_height."_".$crop_width."_".$path_parts['filename'].".jpg";


if( !file_exists($cachefile) )
{

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


$sx = imagesx($stamp);
$sy = imagesy($stamp);
$newwidth = $crop_width;
$newheight = round($crop_width/($sx/$sy));
//echo $newwidth." ".$newheight;


// Load
$thumb = imagecreatetruecolor($newwidth, $newheight);
$background = imagecolorallocate($thumb, 0, 0, 0);
ImageColorTransparent($thumb, $background); // make the new temp image all transparent
imagealphablending($thumb, false); // turn off the alpha blending to keep the alpha channel
//imageSaveAlpha($thumb, false);
// Resize
imagecopyresized($thumb, $stamp, 0, 0, 0, 0, $newwidth, $newheight, $sx, $sy);



if ($align=="center")
{imagecopymerge($cropped_image_gd, $thumb, imagesx($cropped_image_gd)/2 - $newwidth/2, imagesy($cropped_image_gd)/2 - $newheight/2, 0, 0, imagesx($thumb), imagesy($thumb), $percentage);}
if ($align=="right")
{imagecopymerge($cropped_image_gd, $thumb, imagesx($cropped_image_gd) - $newwidth, imagesy($cropped_image_gd) - $newheight, 0, 0, imagesx($thumb), imagesy($thumb), $percentage);}
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

?>