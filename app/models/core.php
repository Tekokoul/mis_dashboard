<?php

class coreModel
{

    protected $R;
    protected $DB;
    protected $S;
    protected $lang_id;
    protected $lang;
    function __construct($registry) {
        $this->R = $registry;
        $this->DB = $this->R->{$this->R->defaultDB};
        $this->S = $this->R->settings[$this->R->defaultDB];
        $this->lang_id = $this->R->url['language']['langid'];
        $this->lang = $this->R->url['language']['lang'];
    }

    function get_table_name($table_name, $mode = "C") {
        if ($mode == "C") {
            return $this->S['db_table_prefix'] . $table_name . $this->S['db_table_suffix'];
        }
        if ($mode == "L") {
            return $this->S['db_table_prefix'] . $table_name . $this->S['db_table_languages_suffix'] . $this->S['db_table_suffix'];
        }
    }

    function get_meta_name($table_name, $mode = "C") {
        $settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json") : [];
        return (isset($settings['meta']['name'])) ? $settings['meta']['name'] : $table_name;
    }

    function get_meta_actions($table_name, $mode = "C"){
        $settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json") : [];
        return (isset($settings['meta']['actions'])) ? $settings['meta']['actions'] : [];
    }

    function get_meta_filters($table_name, $mode = "C"){
        $settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json") : [];
        return (isset($settings['meta']['filters'])) ? $settings['meta']['filters'] : [];
    }

    function get_callbacks($table_name, $on ,$mode = "C"){
        $settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json") : [];
        $reply = [
            "before" => (isset($settings['meta']['callbacks']['before_'.$on])) ? $settings['meta']['callbacks']['before_'.$on] : [],
            "after" =>  (isset($settings['meta']['callbacks']['after_'.$on])) ? $settings['meta']['callbacks']['after_'.$on] : [],
        ];
        return $reply;
    }

    function get_includes($table_name, $on, $mode = "C"){
        $settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, $mode) . ".json") : [];
        return (isset($settings['meta']['includes'][$on])) ? $settings['meta']['includes'][$on] : [];
    }

    function has_languages_table($table_name) {
        $query = "SHOW TABLES LIKE '" . $this->get_table_name($table_name, "L") . "'";
        $result = $this->DB->MQ($query, "one");
        return is_set($result);
    }

    function get_table_fields($table_name) {
        $common_settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name) . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name) . ".json")['fields'] : [];
        $language_settings = (file_exists(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, "L") . ".json")) ? readJSONFile(_MODELS_SETTINGS_PATH . $this->get_table_name($table_name, "L") . ".json")['fields'] : [];
        $model = [];

        $query = "select
       ORDINAL_POSITION,
       TABLE_NAME,
       COLUMN_NAME,
       DATA_TYPE,
       COLUMN_COMMENT
from information_schema.columns 
where (table_schema='" . $this->S['db_database'] . "'
and (table_name='" . $this->get_table_name($table_name) . "') 
) order by ordinal_position";
        $fields = $this->DB->MQ($query, "all");
        if (is_set($fields)) {

            foreach ($fields as $field) {
                $model['common'][$field['COLUMN_NAME']] = [
                    "type" => $field['DATA_TYPE']
                ];
            }
            $model['common'] = array_merge_recursive($model['common'], $common_settings);
            foreach ($model['common'] as $field => $values) {
                if (isset($values['type'])&&(is_array($values['type']))) {
                    $model['common'][$field]['type'] = end($values['type']);
                }
            }

//            array_multisort(array_column($model['common'], "appear_in_form"), SORT_ASC, $model['common']);
        }
        $query = "select
       ORDINAL_POSITION,
       TABLE_NAME,
       COLUMN_NAME,
       DATA_TYPE,
       COLUMN_COMMENT
from information_schema.columns
where (table_schema='" . $this->S['db_database'] . "'
and (table_name='" . $this->get_table_name($table_name, "L") . "')
) order by ordinal_position";
        $fields = $this->DB->MQ($query, "all");
        if (is_set($fields)) {
            foreach ($this->R->languages as $language => $properties) {
                foreach ($fields as $field) {
                    $model['languages'][$language][$field['COLUMN_NAME']] = [
                        "type" => $field['DATA_TYPE']
                    ];
                }
                $model['languages'][$language] = array_merge_recursive($model['languages'][$language], $language_settings);

                foreach ($model['languages'][$language] as $field => $values) {
                    if (isset($values['type'])&&(is_array($values['type']))) {
                        $model['languages'][$language][$field]['type'] = end($values['type']);
                    }
                }
            }
        }
        return $model;
    }

    function get_list_data($model, $page, $items_per_page = 50, $search = "", $filterby=[]) {
//        $this->check_DRM($model['model_name']);
        $fields = $this->get_list_fields($model);
        $fields = ["id" => ["hidden" => true]] + $fields;
        if (array_key_exists("active", $model['model']['common'])) {

            $fields["active"] = [
                "type" => "dropdown",
                "appear_in_list" => 1000,
                "values_from" => "values_list",
                "values_list" => [
                    "1" => "Yes",
                    "0" => "No"
                ]
            ];
        }
        // start building query
        $query = "select `" . implode("`,`", array_keys($fields)) . "` from " . $this->get_table_name($model['model_name']);
        $count_query = "select count(*) as total from " . $this->get_table_name($model['model_name']);
        $query_clauses = "";
        $where_string = [];
        // include languages
        if (isset($model['model']['languages'])) {
            $query_clauses .= " inner join " . $this->get_table_name($model['model_name'], "L")
                . " on " . $this->get_table_name($model['model_name']) . ".id = " . $this->get_table_name($model['model_name'], "L") . ".article_id";
            $where_string[] = "AND language_id = " . $this->lang_id;
        }

        if ($search != "") {
            $all_fields = array_merge($model['model']['common'] ?? [], $model['model']['languages'][$this->lang] ?? []);
            $search_fields = $this->array_with_value("search_field", $all_fields);
            $search_clause = [];
            foreach ($search_fields as $search_field=>$values){
                $search_clause[] = "`".$search_field."` like '%".$search."%'";
            }
            $where_string[] = "AND (".implode(" OR ",$search_clause).")";
        }

        if(is_set($filterby)){
            foreach ($filterby as $filter){
                $where_string[] = $filter;
            }
        }

        if (count($where_string) > 0) {
            $query_clauses .= " where 1 " . implode(" ", $where_string);
        }

        // include the ordering query
        $order_fields = $this->array_with_value("order_field", $fields);
        if (count($order_fields)>0) {
            $query_clauses .= " order by ";
            $order_string = [];
            foreach ($order_fields as $order_field => $properties) {
                $order_string[] = $order_field . " " . $properties['order_field'];
            }
            $query_clauses .= implode(",", $order_string);
        }
        $result['count'] = $this->DB->MQ($count_query.$query_clauses, "one")['total'];

        // include the limits
        $query_limits = " limit " . $items_per_page . " offset " . (($page - 1) * $items_per_page);

        $result['data'] = $this->DB->MQ($query.$query_clauses.$query_limits, "all");
        $result['page'] = $page;
        $result['items'] = $items_per_page;
        return $result;
    }

    function get_all_data($model, $page, $items_per_page = 50, $language=null, $terms = []){
        $query = "select * from " . $this->get_table_name($model);
        $count_query = "select count(*) as total from " . $this->get_table_name($model);
        $query_clauses = "";
        $where_string = [];
        // include languages
        if(!is_null($language)){
            $query_clauses .= " inner join " . $this->get_table_name($model, "L")
                . " on " . $this->get_table_name($model) . ".id = " . $this->get_table_name($model, "L") . ".article_id";
            $where_string[] = "and language_id = " . $language;
        }

        $query_clauses .= " where 1 ".implode(" ", $where_string);

        $result['count'] = $this->DB->MQ($count_query.$query_clauses, "one")['total'];
        // include the limits

        $query_limits = " limit " . $items_per_page . " offset " . (($page - 1) * $items_per_page);

        $result['data'] = $this->DB->MQ($query.$query_clauses.$query_limits, "all");
        $result['page'] = $page;
        $result['items'] = $items_per_page;
        return $result;
    }

    function get_data($model, $id, $by_field = []) {
        if(!empty($by_field)){
            $query = "select * from " . $this->get_table_name($model) . " where ".$by_field['key']."='" . $by_field['value']."'";
        } else {
            $query = "select * from " . $this->get_table_name($model) . " where id=" . $id;
        }
        $data = $this->DB->MQ($query, "one");
        if ($this->has_languages_table($model)) {
            $query = "select * from " . $this->get_table_name($model, "L") . " where article_id=" . $id." order by language_id";
            $languages_result = $this->DB->MQ($query, "all");

            if (is_set($languages_result)) {
                $i = 0;
                foreach ($this->R->languages as $language => $properties) {
                    foreach ($languages_result as $result) {
                        if ($result['language_id'] == $properties['langid']) {
                            $languages_result[$language] = $result;
                            unset($languages_result[$i]);
                        }
                    }
                    $i++;
                }
            }
            $data['languages'] = $languages_result;
        }
        return $data;
    }

    function get_list_fields($model) {
        $fields = [];
        if (isset($model['model']['common'])) {
            $fields = $this->array_with_value("appear_in_list", $model['model']['common']);
            $fields = array_merge($fields, $this->array_with_value("fetch_in_list", $model['model']['common']));
        }
        if (isset($model['model']['languages'])) {
            $fields = array_merge($fields, $this->array_with_value("appear_in_list", end($model['model']['languages'])));
            $fields = array_merge($fields, $this->array_with_value("fetch_in_list", end($model['model']['languages'])));
        }
        if(count($fields)>0){
            uasort($fields, function ($item1, $item2) {
                if(isset($item1['appear_in_list'])&&isset($item2['appear_in_list'])){
                    return $item1['appear_in_list'] <=> $item2['appear_in_list'];
                } else {
                    return -1;
                }
            });
        }
        return $fields;
    }

    function array_with_value($word, $array) {
        $answer = [];
        if (isset($array)) {
            foreach ($array as $field => $properties) {
                if (array_key_exists($word, $properties)) {
                    $answer[$field] = $properties;
                }
            }
        }
        return $answer;
    }

    function add_data($table_name, $data) {
        $answer = [];
        $callbacks = $this->get_callbacks($table_name, "insert");
        $data = $this->prepare_data($data);
        $fields = $this->get_table_fields($table_name);
        $common_fields = $this->remaining_array($fields['common'], $this->array_with_value("no_update", $fields['common']));

        $this->run_callbacks($callbacks['before'], $data);
        $query = "INSERT INTO " . $this->get_table_name($table_name) . " (";
        $query_elements_1 = [];
        $query_elements_2 = [];
        foreach ($common_fields as $field=>$properties){
            $query_elements_1[] = "`".$field."`";

            if(isset($data[$field])&&$data[$field]!=""){
                if(is_array($data[$field])){
                    if((isset($properties['comma_separated']))&&$properties['comma_separated']) {
                        $data[$field] = implode(",", $data[$field]);
                    } else {
                        $data[$field] = $this->json_to_db($data[$field]);
                    }
                }
                if($properties['type']=="password"){
                    $query_elements_2[] = "'".$this->create_password($data[$field])."'";
                } else {
                    $to_add = (isset($properties['added_date'])) ? "'".date("Y-m-d H:i:s")."'" : "'".$data[$field]."'";
                    $query_elements_2[] = $to_add;
                }
            } else {
                $query_elements_2[] = "NULL";
            }
        }
        $query .= implode(",", $query_elements_1).") VALUES (".implode(",",$query_elements_2).")";
        $answer['common'] = $this->DB->MQ($query, 'last');
        $data['id'] = $answer['common'];

        if($this->has_languages_table($table_name)){
            foreach ($this->R->languages as $language=>$language_properties){
                $language_fields = $this->remaining_array($fields['languages'][$language], $this->array_with_value("no_update", $fields['languages'][$language]));
                $query = "INSERT INTO " . $this->get_table_name($data['tablename'], "L") . " (";
                $query_elements_1 = [];
                $query_elements_2 = [];
                foreach ($language_fields as $field=>$properties){
                    $query_elements_1[] = "`".$field."`";

                    if(isset($data['languages'][$language][$field])&&$data['languages'][$language][$field]!=""){
                        if(is_array($data['languages'][$language][$field])){
                            $data['languages'][$language][$field] = $this->json_to_db($data['languages'][$language][$field]);
                        }
                        $to_add = (isset($properties['added_date'])) ? "'".date("Y-m-d H:i:s")."'" : "'".$data['languages'][$language][$field]."'";
                        $query_elements_2[] = $to_add;
                    } else {
                        $to_add = ($field=="article_id") ? "'".$answer['common']."'" : "NULL";
                        $to_add = ($field=="language_id") ? "'".$language_properties['langid']."'" : $to_add;
                        $query_elements_2[] = $to_add;
                    }
                }
                $query .= implode(",", $query_elements_1).") VALUES (".implode(",",$query_elements_2).")";
                $answer[$language] = $this->DB->MQ($query);
            }
        }
        $this->run_callbacks($callbacks['after'], $data);
        return $answer;
    }

    function update_data($table_name, $id, $data) {
        $answer = [];
        $callbacks = $this->get_callbacks($table_name, "update");
        $data = $this->prepare_data($data);
        $fields = $this->get_table_fields($table_name);

        $common_fields = $this->remaining_array($fields['common'], $this->array_with_value("no_update", $fields['common']));

        $this->run_callbacks($callbacks['before'], $data);
        $query = "UPDATE " . $this->get_table_name($table_name) . " SET ";
        $query_elements = [];
        foreach ($common_fields as $field=>$properties){
            if(isset($data[$field])&&$data[$field]!=""){
                if(is_array($data[$field])){
                    if((isset($properties['comma_separated']))&&$properties['comma_separated']) {
                        $query_elements[] = "`".$field."` = '".implode(",", $data[$field])."'";
                    } else {
                        $query_elements[] = "`".$field."` = '".$this->json_to_db($data[$field])."'";
                    }
                } else {
                    if($properties['type']=="password"){
                        $query_elements[] = "`".$field."` = '".$this->create_password($data[$field])."'";
                    } else {
                        $query_elements[] = "`".$field."` = '".$data[$field]."'";
                    }
                }
            } else {
                $query_elements[] = "`".$field."` = NULL";
            }
        }
        $query .= implode(",", $query_elements)." where id='".$id."'";
        $answer['common'] = $this->DB->MQ($query);

        if($this->has_languages_table($table_name)){
            foreach ($this->R->languages as $language=>$language_properties){
                $language_fields = $this->remaining_array($fields['languages'][$language], $this->array_with_value("no_update", $fields['languages'][$language]));
                $query = "UPDATE " . $this->get_table_name($data['tablename'], "L") . " SET ";
                $query_elements = [];
                foreach ($language_fields as $field=>$properties){
                    if(isset($data['languages'][$language][$field])&&$data['languages'][$language][$field]!=""){
                        if(is_array($data['languages'][$language][$field])){
                            $query_elements[] = "`".$field."` = '".$this->json_to_db($data['languages'][$language][$field])."'";
                        } else {
                            $query_elements[] = "`".$field."` = '".$data['languages'][$language][$field]."'";
                        }

                    } else {
                        $query_elements[] = "`".$field."` = NULL";
                    }
                }
                $query .= implode(",", $query_elements)." where article_id='".$id."' and language_id='".$language_properties['langid']."'";
                $answer[$language] = $this->DB->MQ($query);
            }
        }
        $this->run_callbacks($callbacks['after'], $data);

        return $answer;
    }

    function delete_data($table_name, $id) {
        $answer = [];
        $callbacks = $this->get_callbacks($table_name, "delete");
        // LOGS
        $query = "SELECT * FROM ".$this->get_table_name($table_name)." where id='".$id."'";
        $log = $this->DB->MQ($query, "one");
        $query = "INSERT INTO `core_table_logs_tbl` ( `tablename`, `record`, `log_date`, `user`) VALUES ( '".$this->get_table_name($table_name)."', '".$this->json_to_db($log)."', '".date("Y-m-d H:i:s")."', '".$_SESSION['user']['username']."' );";
        $this->DB->MQ($query);
        // LOGS
        $this->run_callbacks($callbacks['before'], $log);

        $query = "DELETE FROM ".$this->get_table_name($table_name)." where id='".$id."'";
        $answer['common'] = $this->DB->MQ($query);
        if($this->has_languages_table($table_name)){
            // LOGS
            $query = "SELECT * FROM ".$this->get_table_name($table_name, "L")." where article_id='".$id."'";
            $logs = $this->DB->MQ($query, "all");
            foreach ($logs as $log){
                $query = "INSERT INTO `core_table_logs_tbl` ( `tablename`, `record`, `log_date`, `user`) VALUES ( '".$this->get_table_name($table_name, "L")."', '".$this->json_to_db($log)."', '".date("Y-m-d H:i:s")."', '".$_SESSION['user']['username']."');";
                $this->DB->MQ($query);
            }
            // LOGS
            $query = "DELETE FROM ".$this->get_table_name($table_name, "L")." where article_id='".$id."'";
            $answer['languages'] = $this->DB->MQ($query);
        }
        $this->run_callbacks($callbacks['after'], $log);

        return $answer;
    }

    function prepare_data($data){
        $answer = [];
        foreach ($data as $key => $value) {
            if($key=="_url"){
                unset($key);
            } else {
                if(!is_array($value)){
                    $answer[$key] = addslashes(trim($value));
                } else {
                    $answer[$key] = $this->prepare_data($value);
                }
            }
        }
        return $answer;
    }

    function run_callbacks($callbacks, $data){
        foreach ($callbacks as $callback){
            $running_class = new $callback['class']($this->R);
            $running_function = $callback['function'];
            $running_class->$running_function($data);
        }
    }

    function remaining_array($big_array, $remove_array){
        foreach ($remove_array as $key=>$value){
            unset($big_array[$key]);
        }
        return $big_array;
    }


    function dynamic_link($text, $row){
        preg_match_all('/%%(.*?)%%/', $text, $match);
        $dynamic_parameter = isset($match[1]) ? $match[1] : [];
        if(isset($dynamic_parameter)){
            foreach ($dynamic_parameter as $par){
                $text = str_replace("%%".$par."%%",$row[$par], $text);
            }
        }
        return $text;
    }


    function create_password($text){
        return md5(md5($text));
    }

    function json_to_db($json_array) {
        return addslashes(json_encode($json_array,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    function json_from_db($json_data){
        $json = preg_replace('/[[:cntrl:]]/', '',$json_data);
        return json_decode($json, true);
    }

    protected function check_DRM($data, $mode){
        if(_DRM_ACTIVE){
            $drm_settings = readJSONFile(_DRM_FILE);
            $group = $_SESSION['user']['group'];
            $model = $this->get_table_name($data);
            if(isset($drm_settings[$group])){
                $active_drm_full = $drm_settings[$group];
            } else {
                $active_drm_full = $drm_settings['default'];
            }
            if(isset($active_drm_full['models'][$model])){
                $active_drm_model = $active_drm_full['models'][$model];
            } else {
                $active_drm_model = $active_drm_full['models']['default'];
            }
            if(!$active_drm_model){
                return false;
            }
        }
    }
}