<?php
foreach($this->CSS as $CSSfile) {
    print '<link rel="stylesheet" href="'. $CSSfile.'?v='._CURRENT_COMMIT.'">';
}

include $viewPath;

foreach($this->JS as $JSfile) {
    print '<script src="'. $JSfile.'?v='._CURRENT_COMMIT.'"></script>';
}

$jsfile = "/js/page_".$this->R->url['controller']."_".$this->R->url['action'].".js";
if(file_exists(_PUBLIC_PATH.$jsfile)){
    print '<script src="'.$jsfile.'?v='._CURRENT_COMMIT.'"></script>';
}