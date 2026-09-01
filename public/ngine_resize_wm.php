<?php
/*
nGine Resize with Watermark v0.1
+caching added
*/

$file_name=$_GET['f'];
$crop_width=$_GET['w'];
$wm_type=$_GET['t'];
$prefix="wm_".$wm_type."_";
$path_parts = pathinfo($file_name);
$file_type=$path_parts['extension'];
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
$cachefile=$cachedir.$prefix.$crop_width."_".$path_parts['filename'].".jpg";


//if( !file_exists($cachefile) )
{

class SimpleImage {
   
   var $image;
   var $image_type;
 
   function load($filename) {
      $image_info = getimagesize($filename);
      $this->image_type = $image_info[2];
      if( $this->image_type == IMAGETYPE_JPEG ) {
         $this->image = imagecreatefromjpeg($filename);
      } elseif( $this->image_type == IMAGETYPE_GIF ) {
         $this->image = imagecreatefromgif($filename);
      } elseif( $this->image_type == IMAGETYPE_PNG ) {
         $this->image = imagecreatefrompng($filename);
      }
   }
   function save($filename, $image_type=IMAGETYPE_JPEG, $compression=80, $permissions=null) {
      if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image,$filename,$compression);
      } elseif( $image_type == IMAGETYPE_GIF ) {
         imagegif($this->image,$filename);         
      } elseif( $image_type == IMAGETYPE_PNG ) {
         imagepng($this->image,$filename);
      }   
      if( $permissions != null) {
         chmod($filename,$permissions);
      }
   }
   function output($image_type=IMAGETYPE_JPEG,$file) {
      /*if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image);
      } elseif( $image_type == IMAGETYPE_GIF ) {
         imagegif($this->image);         
      } elseif( $image_type == IMAGETYPE_PNG ) {
         imagepng($this->image);
      } */
 
      imagejpeg($this->image, $file,80 );
   }
   function getWidth() {
      return imagesx($this->image);
   }
   function getHeight() {
      return imagesy($this->image);
   }
   function resizeToHeight($height) {
      $ratio = $height / $this->getHeight();
      $width = $this->getWidth() * $ratio;
      $this->resize($width,$height);
   }
   function resizeToWidth($width) {
      $ratio = $width / $this->getWidth();
      $height = $this->getheight() * $ratio;
      $this->resize($width,$height);
   }
   function scale($scale) {
      $width = $this->getWidth() * $scale/100;
      $height = $this->getheight() * $scale/100; 
      $this->resize($width,$height);
   }
   function resize($width,$height) {
      $new_image = imagecreatetruecolor($width, $height);
      imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
      $this->image = $new_image;   
   }      
}


   $image = new SimpleImage();
   $image->load($file_name);
   $image->resizeToWidth($crop_width);
   $image->output(IMAGETYPE_JPEG,$cachefile);
  }

$im = imagecreatefromjpeg($cachefile);


$sx = imagesx($stamp);
$sy = imagesy($stamp);
$newwidth = imagesx($im);
$newheight = round(imagesx($im)/($sx/$sy));;
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
{imagecopymerge($im, $thumb, imagesx($im)/2 - $newwidth/2, imagesy($im)/2 - $newheight/2, 0, 0, imagesx($thumb), imagesy($thumb), $percentage);}
if ($align=="right")
{imagecopymerge($im, $thumb, imagesx($im) - $newwidth, imagesy($im) - $newheight, 0, 0, imagesx($thumb), imagesy($thumb), $percentage);}

//imagecopymerge($im, $stamp, imagesx($im)/2 - $sx/2, imagesy($im)/2 - $sy/2, 0, 0, imagesx($stamp), imagesy($stamp), $percentage);
imagejpeg($im, $cachefile);
imagedestroy($im);

header ("Content-type: image/jpeg"); 
  $fp = fopen($cachefile, 'rb'); # stream the image directly from the cachefile
  fpassthru($fp);
  exit;
?>