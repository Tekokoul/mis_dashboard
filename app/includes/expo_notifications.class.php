<?php
require _ROOT_PATH.'vendor/autoload.php';

use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;

class expo_notifications{
    function send_notification($data){
        $messages = [
            new ExpoMessage([
                'title' => $data['title'],
                'body' => $data['body'],
            ]),
        ];

        (new Expo)->send($messages)->to($data['tokens'])->push();
    }
}