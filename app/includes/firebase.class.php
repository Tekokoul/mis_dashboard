<?php
require _ROOT_PATH.'vendor/autoload.php';

$fbFolder = _ROOT_PATH."vendor/kreait/firebase-php";
if(is_dir($fbFolder)){
    require $fbFolder.'/src/Firebase/Factory.php';;
    require $fbFolder.'/src/Firebase/Messaging/CloudMessage.php';
} else {
    JSON_reply(500,"Please run composer. Firebase for PHP is not installed.");
}


class Firebase{
    private $factory;

    function __construct($settings){
        $this->factory = (new Kreait\Firebase\Factory)->withServiceAccount($settings['credentials_file']);
    }

    function sendMessage($data){
        $messaging = $this->factory->createMessaging();

        $message = Kreait\Firebase\Messaging\CloudMessage::fromArray([
            'token' => $data['to'],
            'notification' => [
                'title' => $data['title'],
                'body' => $data['body'],
                'sound' => 'default',
                'badge' => '1',
            ],
            'data' => [
                'type' => $data['type'],
                'next' => $data['next']
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1
                    ]
                ]
            ]
        ]);
        $response = $messaging->send($message);
        return $response['name'];
    }
}