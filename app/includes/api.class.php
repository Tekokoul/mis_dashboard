<?php

class API {

    function __construct(){
        $this->message("");
    }

    function call($url, $method="GET", $auth="", $data="", $content_type = "application/json; charset=utf-8", $headers = []){
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array_merge([
                "Authorization: ".$auth,
                "Content-Type: ".$content_type
            ], $headers),
        ]);
        $response['data'] = curl_exec($curl);
        $response['error'] = curl_error($curl);

        curl_close($curl);
        return $response;
    }

    function message($data){
        $_SESSION['message'] = $data;
    }
}