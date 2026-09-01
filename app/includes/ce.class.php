<?php
require_once _ROOT_PATH.'vendor/autoload.php';
class ce{

    function __construct(){
        global $registry;
        $this->R = $registry;
        $this->DB = $this->R->{$this->R->defaultDB};
    }

    function create_articles_folder($data){
        mkdir(_MEDIA_PATH."articles".DS.$data['id'], 0755, true);
    }

    function create_products_folder($data){
        mkdir(_MEDIA_PATH."products".DS.$data['id'], 0755, true);
    }
}