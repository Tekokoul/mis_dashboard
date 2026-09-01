<?php

class webhooksController extends vanillaController{
    public function incoming(){
        $this->checkMethod("POST");
        $this->mapRoute("system");
        $this->checkRequired(["system"], $this->parts);
        $rules = [
            "system" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $unprocessed_data = $this->payload;

        $parts = explode('--', $validated['system']);
        if(count($parts)>1){
            $handler = $this->S;
            foreach ($parts as $part) {
                $handler = $handler[$part];
            }
            $validated['system'] = end($parts);
        } else {
            $handler = $this->S[$validated['system']];
        }

        if($handler['webhook_keep_logs']){
            file_put_contents(_CACHE_PATH."webhook_".$validated['system'].".txt", $unprocessed_data."\n", FILE_APPEND | LOCK_EX);
        }

        $secret = $handler['webhook_secret'];
        $hmac_header = $_SERVER[$handler['webhook_key']];

        if (isset($hmac_header)){
            $verified = $this->verify_webhook($unprocessed_data, $hmac_header, $secret);
            if($verified){
        //    if(1){
                $data=json_decode($unprocessed_data,1);
                $data['system'] = $validated['system'];
                $api_class = _PROJECT_HELPER_CLASS;
        // The per-tenant helper class is optional and absent in this build.
        if ($api_class === "" || !class_exists($api_class)) { return; }
                $projectClass = new $api_class($this->R);
                $result = $projectClass->handle_webhook($data);
                if($result){
                    $this->setAnswer(200, 'Data Is handled successfully', [], "json");
                } else {
                    $this->setAnswer(400, 'An error occurred', [], "json");
                }
            } else {
                $this->setAnswer(401, "Wrong checksum", [], "json");
            }
        } else {
            $this->setAnswer(417, "No checksum found", [], "json");
        }
    }


    function verify_webhook($data, $hmac_header, $secret) {
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, $secret, true));
        return hash_equals($hmac_header, $calculated_hmac);
    }
}
