<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 9/5/2017
 * Time: 4:46 μμ
 */

class Slack {
    function __construct() {
    }

    function sendSlack($slack) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,  $slack['url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,"payload={\"text\": \"".$slack['msg']."\"}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        if(!curl_errno($ch)) {
            $info = curl_getinfo($ch);
        }
        curl_close ($ch);

        return $server_output;
    }
}