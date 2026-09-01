<?php
class toolsController extends protectedController{
    public function export_keys(){
        $files = get_folder_contents(_JSON_MODELS_PATH, "json", "data.*");
        $data = [];
        foreach ($files as $file){
            $temp_data = readJSONFile($file);
            $common_keys = count($temp_data)-1;
            $language_keys = (isset($temp_data['languages']['en'])) ? count($temp_data['languages']['en']) : 0;
            $data[basename($file)] = $common_keys+$language_keys;
        }
        $this->render($data);
    }

    public function export_data_xls(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $model_data = readJSONFile(_JSON_MODELS_PATH.$validated['model']);
        $common_data  = $model_data;
        unset($common_data['languages']);
        $language_data = (isset($model_data['languages']))? $model_data['languages'] : [];
        $list_of_languages = array_keys($language_data);

        $data = [];

        foreach ($common_data as $key=>$value){
            $datum = [];
            $datum['key'] = $key;
            foreach ($list_of_languages as $lang){
                $datum[$lang] = $value;
            }
            $data[] = $datum;
        }

        foreach ($language_data['en'] as $key=>$value) {
            $datum = [];
            $datum['key'] = $key;
            foreach ($list_of_languages as $lang){
                $datum[$lang] = $language_data[$lang][$key];
            }
            $data[] = $datum;
        }

        $fileName = basename($validated['model'], ".json") ."-". date('YmdHis') . ".xlsx";
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Content-Type: application/vnd.ms-excel");

        $flag = false;
        foreach($data as $row) {
            if(!$flag) {
                // display column names as first row
                echo implode("\t", array_keys($row)) . "\n";
                $flag = true;
            }
            // filter data
            array_walk($row, 'filterData');
            echo implode("\t", array_values($row)) . "\n";
        }
        header( "refresh:5;url=".$this->L("tools/export_keys") );
    }
}