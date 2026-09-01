<?php
/*
nGine Resize v1.0
+caching added
*/

$file_name = $_GET['f'];
$crop_width = $_GET['w'];
$path_parts = pathinfo($file_name);
$file_type = $path_parts['extension'];

$cachedir = "cache/" . $path_parts['dirname'] . "/";
if (!file_exists($cachedir)) {
    mkdir($cachedir, 0755, true);
}
$cachefile = $cachedir . $crop_width . "_" . $path_parts['filename'] . ".jpg";

if($file_type == "svg") {
    header("Content-type: image/svg+xml");
    $fp = fopen($file_name, 'rb'); # stream the image directly from the cachefile
    fpassthru($fp);
    exit();
}

if (!file_exists($cachefile)) {

    class SimpleImage {

        var $image;
        var $image_type;

        function load($filename){
            $image_info = getimagesize($filename);
            $this->image_type = $image_info[2];
            if ($this->image_type == IMAGETYPE_JPEG) {
                $this->image = imagecreatefromjpeg($filename);
            } elseif ($this->image_type == IMAGETYPE_GIF) {
                $this->image = imagecreatefromgif($filename);
            } elseif ($this->image_type == IMAGETYPE_PNG) {
                $this->image = imagecreatefrompng($filename);
            }
        }

        function save($filename, $image_type = IMAGETYPE_JPEG, $compression = 75, $permissions = null){
            if ($image_type == IMAGETYPE_JPEG) {
                imagejpeg($this->image, $filename, $compression);
            } elseif ($image_type == IMAGETYPE_GIF) {
                imagegif($this->image, $filename);
            } elseif ($image_type == IMAGETYPE_PNG) {
                imagepng($this->image, $filename);
            }
            if ($permissions != null) {
                chmod($filename, $permissions);
            }
        }

        function output($image_type = IMAGETYPE_JPEG, $file){
            /*if( $image_type == IMAGETYPE_JPEG ) {
               imagejpeg($this->image);
            } elseif( $image_type == IMAGETYPE_GIF ) {
               imagegif($this->image);
            } elseif( $image_type == IMAGETYPE_PNG ) {
               imagepng($this->image);
            } */

            imagejpeg($this->image, $file, 80);
        }

        function getWidth()
        {
            return imagesx($this->image);
        }

        function getHeight()
        {
            return imagesy($this->image);
        }

        function resizeToHeight($height)
        {
            $ratio = $height / $this->getHeight();
            $width = $this->getWidth() * $ratio;
            $this->resize($width, $height);
        }

        function resizeToWidth($width)
        {
            $ratio = $width / $this->getWidth();
            $height = $this->getheight() * $ratio;
            $this->resize($width, $height);
        }

        function scale($scale)
        {
            $width = $this->getWidth() * $scale / 100;
            $height = $this->getheight() * $scale / 100;
            $this->resize($width, $height);
        }

        function resize($width, $height)
        {
            $new_image = imagecreatetruecolor($width, $height);
            $backgroundColor = imagecolorallocate($new_image, 255, 255, 255);
            imagefill($new_image, 0, 0, $backgroundColor);
            imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
            $this->image = $new_image;
        }
    }
    $image = new SimpleImage();
    $image->load($file_name);
    $image->resizeToWidth($crop_width);
    $image->output(IMAGETYPE_JPEG, $cachefile);

}
header("Content-type: image/jpeg");
$fp = fopen($cachefile, 'rb'); # stream the image directly from the cachefile
fpassthru($fp);
exit;