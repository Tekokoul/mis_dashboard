<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 2/3/2017
 * Time: 11:58 πμ
 */

require "configuration/settings.php";
require "includes/library.php";

require_once "db.class.php";
require_once "registry.class.php";
require_once "vanillaController.class.php";
require_once "protectedController.class.php";
require_once "router.class.php";

session_start();
define("_CURRENT_COMMIT", get_current_git_commit());

spl_autoload_register(function($class) {
    $parts = explode("\\", $class);
    if(file_exists(_INCLUDES_PATH.strtolower(end($parts)).".class.php")){
        require _INCLUDES_PATH.strtolower(end($parts)).".class.php";
    }
});

$registry = new Registry($settings);
$registry->connectDB("db_master", $settings['db_master']);

if($registry->settings["mail"]['enable_email']){
    $registry->settings["mail"] = array_merge($registry->settings["mail"],readJSONFile(_JSON_MODELS_PATH."data.email.json"));
}

require _ROOT_PATH.'vendor/autoload.php';

$router = new Router($registry);