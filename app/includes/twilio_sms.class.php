<?php

require_once _INCLUDES_PATH."api.class.php";

class Twilio_SMS extends API {
    function __construct($settings){
        $this->settings = $settings;
    }

    function send_sms($data){
        $message_data = [
            "To" => $data['phone'],
            "MessagingServiceSid" => $this->settings['messaging_service_sid'],
            "Body" => $data['message']
        ];

        $response = $this->call(
            $this->settings['api_url']."2010-04-01/Accounts/".$this->settings['account_sid']."/Messages.json",
            "POST",
            "Basic ".base64_encode($this->settings["account_sid"].":".$this->settings["auth_token"]),
            http_build_query($message_data),
            "application/x-www-form-urlencoded"
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }
}