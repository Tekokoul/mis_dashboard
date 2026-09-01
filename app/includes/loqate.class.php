<?php
class Locate extends API
{

    protected $settings;
    function __construct($settings){
        $this->settings = $settings;
    }

    function verify_address($address){
        $payload = [
            "Key" => $this->settings['api_key'],
            "Geocode" => false,
            "Addresses" => [
               [
                  "Address1" =>$address['address'],
                  "Address2" =>$address['city'],
                  "Country" =>$address['country'],
                  "PostalCode" =>$address['pobox']
               ]
            ]
        ];
        $response = $this->call(
            "https://api.addressy.com/Cleansing/International/Batch/v1.00/json4.ws",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8"
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            $verified_address = $response_data[0]['Matches'][0];
            if(($verified_address['AQI']=="A")||($verified_address['AQI']=="B")){
                return $verified_address;
            } else {
                return false;
            }
        }
    }
}