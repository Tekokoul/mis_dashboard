<?php
require_once _INCLUDES_PATH."api.class.php";

class remoteController extends protectedController {

    protected $unprotected = ["get_json", "put_json"];

    public function json_edit(){
        $this->checkMethod();
        $this->mapRoute("host/model");
        $this->checkRequired(["host","model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "host" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $this->checkHost($validated['host']);

        $api = new API();
        $response = $api->call(
            $this->S['remote'][$validated['host']]['url'].$this->L("remote/get_json/".$validated['model']),
            "GET",
            "Bearer " . $this->CE_encrypt($this->S['remote'][$validated['host']]["api_key"])
            );

        $answer = json_decode($response['data'], true);
        if($answer['code']==200){
            $data = $answer['data'];
            $data['remote_host'] = $validated['host'];
            $this->prepare_edit_mode();
            $this->render($data);
        } else {
//            $this->setAnswer(500, "Could not retrieve '".$validated['model']."' from remote server");
            debug($response);
        }
    }

    public function json_update(){
        $this->checkMethod("POST");

        $values = $this->R->url['query'];

        $table = $values['tablename'];
        $remote_host = $values['remote_host'];
        unset($values['tablename']);
        unset($values['remote_host']);

        $api = new API();
        $response = $api->call(
            $this->S['remote'][$remote_host]['url'].$this->L("remote/put_json/".$table),
            "POST",
            "Bearer " . $this->CE_encrypt($this->S['remote'][$remote_host]["api_key"]),
            json_encode($values)
        );
        $answer = json_decode($response['data'], true);
        if($answer['code']==200) {
            redirect($this->L("remote/json_edit/" . $remote_host . "/" . $table));
        } else {
            $this->setAnswer(500, "Could not store '".$table."' to the remote server");
        }
    }

    public function get_json(){
        $this->checkMethod();
        $this->checkAuthorization();
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $model_file_common = _JSON_MODELS_PATH."table.".$validated['model'].".json";
        $model_file_languages = _JSON_MODELS_PATH."table.".$validated['model']."_languages.json";
        $data_file = _JSON_MODELS_PATH."data.".$validated['model'].".json";

        $model = [];
        $found = false;
        if(file_exists($model_file_common)) {
            $model["common"] = readJSONFile($model_file_common);
            $found = true;
        }
        if(file_exists($model_file_languages)){
            foreach ($this->R->languages as $language=>$properties){
                $model["languages"][$language] = readJSONFile($model_file_languages);
            }
            $found = true;
        }
        if($found){
            $data['model_name'] = $validated['model'];
            $data['model'] = $model;
            $data['data'] = readJSONFile($data_file);

            $this->setAnswer(200, "Model '" . $validated['model'] . "' found", $data, "json");
        } else {
            $this->setAnswer(404, "Model '".$validated['model']."' not found");
        }
    }

    public function put_json(){
        $this->checkMethod("POST");
        $this->checkAuthorization();
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $data = json_decode($this->R->url['payload'], true);
//        $data = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        $json_table = fopen(_JSON_MODELS_PATH . "data." . $validated['model'] . ".json", "w") or die(__FILE__ . " Unable to open json_models/data." . $validated['model'] . ".json");
        fwrite($json_table, json_encode($data));
        fclose($json_table);
        $this->setAnswer(200, "Model '" . $validated['model'] . "' saved", [], "json");
    }

    protected function checkHost($host){
        if(!array_key_exists($host, $this->S['remote'])){
            $this->setAnswer(404, "Remote host '".$host."' not found.");
        }
    }
}