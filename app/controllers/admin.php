<?php
class adminController extends protectedController{

    public function models(){
        $this->render();
    }

    public function configuration(){
        $config_file = fopen(_APP_PATH.DS."configuration".DS."settings.php", "r") or die("Unable to open configuration file");
        $data['content'] = trim(fread($config_file,filesize(_APP_PATH.DS."configuration".DS."settings.php")));
        fclose($config_file);
        $this->AddCSS("/vendor/codemirror/lib/codemirror.css");
        $this->AddCSS("/vendor/codemirror/theme/material-palenight.css");
        $this->AddJS("/vendor/codemirror/lib/codemirror.js");
        $this->AddJS("/vendor/codemirror/addon/selection/active-line.js");
        $this->AddJS("/vendor/codemirror/addon/edit/matchbrackets.js");
        $this->AddJS("/vendor/codemirror/mode/javascript/javascript.js");
        $this->AddJS("/vendor/codemirror/mode/xml/xml.js");
        $this->AddJS("/vendor/codemirror/mode/htmlmixed/htmlmixed.js");
        $this->AddJS("/vendor/codemirror/mode/css/css.js");
        $this->AddJS("/vendor/codemirror/mode/clike/clike.js");
        $this->AddJS("/vendor/codemirror/mode/php/php.js");
        $this->render($data);
    }

    public function website_configuration(){
        $config_file = fopen(_ROOT_PATH."../app".DS."configuration".DS."settings.php", "r") or die("Unable to open configuration file");
        $data['content'] = trim(fread($config_file,filesize(_APP_PATH.DS."configuration".DS."settings.php")));
        fclose($config_file);
        $this->AddCSS("/vendor/codemirror/lib/codemirror.css");
        $this->AddCSS("/vendor/codemirror/theme/material-palenight.css");
        $this->AddJS("/vendor/codemirror/lib/codemirror.js");
        $this->AddJS("/vendor/codemirror/addon/selection/active-line.js");
        $this->AddJS("/vendor/codemirror/addon/edit/matchbrackets.js");
        $this->AddJS("/vendor/codemirror/mode/javascript/javascript.js");
        $this->AddJS("/vendor/codemirror/mode/xml/xml.js");
        $this->AddJS("/vendor/codemirror/mode/htmlmixed/htmlmixed.js");
        $this->AddJS("/vendor/codemirror/mode/css/css.js");
        $this->AddJS("/vendor/codemirror/mode/clike/clike.js");
        $this->AddJS("/vendor/codemirror/mode/php/php.js");
        $this->render($data);
    }

    public function configuration_update(){
        $this->checkMethod("POST");
        $this->checkRequired(["content"], $this->query);
        // Rules and validation of the QueryString
        $rules = [
//            "content" => FILTER_SANITIZE_
        ];
        $validated = $this->query; //$this->sanitize($this->query, $rules);
        $config_file = fopen(_APP_PATH.DS."configuration".DS."settings.php", "w") or die(__FILE__." Unable to open configuration file");
        fwrite($config_file, trim($validated['content']));
        fclose($config_file);
        redirect($this->L("admin/configuration"));
    }

    public function website_configuration_update(){
        $this->checkMethod("POST");
        $this->checkRequired(["content"], $this->query);
        // Rules and validation of the QueryString
        $rules = [
//            "content" => FILTER_SANITIZE_
        ];
        $validated = $this->query;//$this->sanitize($this->query, $rules);
        $config_file = fopen(_ROOT_PATH."../app".DS."configuration".DS."settings.php", "w") or die(__FILE__." Unable to open configuration file");
        fwrite($config_file, trim($validated['content']));
        fclose($config_file);
        redirect($this->L("admin/website_configuration"));
    }

    public function configure_json(){
        $this->mapRoute("folder/model");
        $rules = [
            "folder" => FILTER_UNSAFE_RAW,
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        if(isset($validated['model'])){
            $data['filename'] = $validated['model'];
            $data['folder'] = $validated['folder'];
            $filename = _ROOT_PATH."db".DS.$validated['folder'].DS.$validated['model'].".json";
            if(file_exists($filename)){
                $config_file = fopen($filename, "r") or die("Unable to open configuration file");
                $data['content'] = trim(stream_get_contents($config_file));
                fclose($config_file);
            }
        }
        $data['models_settings'] = get_folder_contents(_MODELS_SETTINGS_PATH, "json");
        $data['reports'] = get_folder_contents(_REPORTS_PATH, "json");
        $data['json_models'] = get_folder_contents(_JSON_MODELS_PATH, "json", "table.*");
        $data['menus'] = get_folder_contents(_MENUS_PATH, "json");
        $this->AddCSS("/vendor/codemirror/lib/codemirror.css");
        $this->AddCSS("/vendor/codemirror/theme/material-palenight.css");
        $this->AddJS("/vendor/codemirror/lib/codemirror.js");
        $this->AddJS("/vendor/codemirror/addon/selection/active-line.js");
        $this->AddJS("/vendor/codemirror/addon/edit/matchbrackets.js");
        $this->AddJS("/vendor/codemirror/mode/javascript/javascript.js");
        $this->AddJS("/vendor/codemirror/mode/xml/xml.js");
        $this->AddJS("/vendor/codemirror/mode/htmlmixed/htmlmixed.js");
        $this->AddJS("/vendor/codemirror/mode/css/css.js");
        $this->AddJS("/vendor/codemirror/mode/clike/clike.js");
        $this->AddJS("/vendor/codemirror/mode/php/php.js");
        $this->AddCSS("/vendor/jstree/themes/default/style.css");
        $this->AddJS("/vendor/jstree/jstree.js");
        $this->render($data);
    }

    public function json_update(){
        $this->checkMethod("POST");
        $this->checkRequired(["model", "originalmodel", "folder", "content"], $this->query);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "originalmodel" => FILTER_UNSAFE_RAW,
            "folder" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);

        if(($validated['model']=="new")||($validated['model']=="")){
            $validated['model'] = uniqid();
        }
        if($validated['originalmodel']!=$validated['model']){
            unlink(_ROOT_PATH."db".DS.$validated['folder'].DS.$validated['originalmodel'].".json");
        }
        $filename = ends_with($validated['model'], ".json") ? substr($validated['model'],0,-5) : $validated['model'];

        $json_table = fopen(_ROOT_PATH."db".DS.$validated['folder'].DS.$filename.".json", "w") or die(__FILE__." Unable to open JSON file");
        fwrite($json_table, $this->query['content']);
        fclose($json_table);
        redirect($this->L("admin/configure_json/".$validated['folder']."/".$filename));
    }
}