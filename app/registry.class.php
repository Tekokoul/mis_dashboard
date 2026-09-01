<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 4/4/2017
 * Time: 2:43 μμ
 */
#[AllowDynamicProperties]
class Registry {
    public function __construct($settings) {
        $this->__set("settings", $settings);
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function connectDB($db_name, $settings) {
        $this->__set($db_name, new DB($settings));
        if($settings['default']) {
            $this->__set("defaultDB", $db_name);
        }
    }
}