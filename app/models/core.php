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
        // The model name arrives from the URL or the form (core/db_list/<model>,
        // tablename=<model>) and becomes an SQL identifier, so it is confined
        // to identifier characters before it goes anywhere near a query.
        // "pm_projects_tbl WHERE id=1 AND SLEEP(3)-- " used to be accepted.
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string)$table_name)) {
            db_error("model name", "refused model name: " . substr((string)$table_name, 0, 80));
        }
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

    /**
     * ORDER BY clause that reads a text column as "major.minor" numbers:
     * the part before the first dot, then the part after it (digits only,
     * so "1.10 PRG" -> 1, 10), then the raw text as the final tiebreaker.
     * Non-numeric values cast to 0 and fall back to plain text order.
     */
    public static function natural_order_sql($column, $dir = "asc") {
        $dir = (strtolower($dir) == "desc") ? "desc" : "asc";
        $c = "`" . preg_replace('/[^A-Za-z0-9_]/', '', $column) . "`";
        return "CAST(SUBSTRING_INDEX(" . $c . ",'.',1) AS UNSIGNED) " . $dir
             . ", CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(" . $c . ",'.0.0'),'.',2),'.',-1) AS UNSIGNED) " . $dir
             . ", CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(" . $c . ",'.0.0'),'.',3),'.',-1) AS UNSIGNED) " . $dir
             . ", " . $c . " " . $dir;
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

        // Bound values for this query, in placeholder order: the search term
        // first (it appears earlier in the WHERE), then any filter values.
        $params = [];
        // The search term is raw user input (FILTER_UNSAFE_RAW in every caller),
        // so it is bound, never interpolated. The field NAMES come from the
        // model settings, not from the request, so they stay as identifiers.
        if ($search != "") {
            $all_fields = array_merge($model['model']['common'] ?? [], $model['model']['languages'][$this->lang] ?? []);
            $search_fields = $this->array_with_value("search_field", $all_fields);
            $search_clause = [];
            foreach ($search_fields as $search_field=>$values){
                $search_clause[] = "`".$search_field."` like ?";
                $params[] = "%".$search."%";
            }
            if (count($search_clause) > 0) {
                $where_string[] = "AND (".implode(" OR ",$search_clause).")";
            }
        }

        // A filter is either a bound pair ['sql' => "... = ?", 'value' => x] or,
        // for callers not yet converted, a literal SQL string. The pair form is
        // the safe one: filter VALUES come from the query string.
        if(is_set($filterby)){
            foreach ($filterby as $filter){
                if (is_array($filter) && isset($filter['sql'])) {
                    $where_string[] = $filter['sql'];
                    if (array_key_exists('value', $filter)) {
                        $params[] = $filter['value'];
                    }
                } else {
                    $where_string[] = $filter;
                }
            }
        }

        if (count($where_string) > 0) {
            $query_clauses .= " where 1 " . implode(" ", $where_string);
        }

        // include the ordering query. The model settings name the sort column
        // (db/models_settings/*.json, "order_field"); the content lists sort by
        // WBS code or id so they read in document order. A model with no
        // order_field falls back to the primary key: without an ORDER BY,
        // InnoDB returns physical order, which changes after any rewrite.
        $order_fields = $this->array_with_value("order_field", $fields);
        if (count($order_fields)>0) {
            $query_clauses .= " order by ";
            $order_string = [];
            foreach ($order_fields as $order_field => $properties) {
                $dir = (strtolower($properties['order_field']) == "desc") ? "desc" : "asc";
                // "order_natural": a varchar that holds a WBS-style number
                // ("1.0", "10.0", "1.10 PRG") sorts by its numeric parts, so
                // 2.0 follows 1.0 instead of 15.0, and 1.10 follows 1.9.
                if (!empty($properties['order_natural'])) {
                    $order_string[] = self::natural_order_sql($order_field, $dir);
                } else {
                    $order_string[] = "`" . $order_field . "` " . $dir;
                }
            }
            $query_clauses .= implode(",", $order_string);
        } else {
            $query_clauses .= " order by `id` asc";
        }
        $result['count'] = $this->DB->MQ($count_query.$query_clauses, "one", $params)['total'];

        // include the limits. Cast rather than bind: MySQL will not accept a
        // placeholder in LIMIT/OFFSET while emulation is off, and both values
        // are integers we control.
        $query_limits = " limit " . (int)$items_per_page . " offset " . (((int)$page - 1) * (int)$items_per_page);

        $result['data'] = $this->DB->MQ($query.$query_clauses.$query_limits, "all", $params);
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
            // key is caller-supplied (code, not request); the VALUE is bound.
            $query = "select * from " . $this->get_table_name($model) . " where `".$by_field['key']."` = ?";
            $by_params = [$by_field['value']];
        } else {
            $query = "select * from " . $this->get_table_name($model) . " where id = ?";
            $by_params = [$id];
        }
        $data = $this->DB->MQ($query, "one", $by_params);
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

        // Numeric columns take what a person typed ("10,000 USD") as a number;
        // a value with no digits becomes empty and the column default applies.
        foreach ($common_fields as $field => $properties) {
            $t = strtolower((string)($properties['type'] ?? ''));
            if (isset($data[$field]) && !is_array($data[$field]) && in_array($t, ['double','float','decimal','int','tinyint','smallint','bigint','integer'], true)) {
                $data[$field] = normalise_number($data[$field], in_array($t, ['int','tinyint','smallint','bigint','integer'], true));
            }
        }

        $this->run_callbacks($callbacks['before'], $data);
        $query = "INSERT INTO " . $this->get_table_name($table_name) . " (";
        $query_elements_1 = [];
        $query_elements_2 = [];
        $values = [];
        foreach ($common_fields as $field=>$properties){
            $query_elements_1[] = "`".$field."`";

            if(isset($data[$field])&&$data[$field]!==""){
                if(is_array($data[$field])){
                    if((isset($properties['comma_separated']))&&$properties['comma_separated']) {
                        $data[$field] = implode(",", $data[$field]);
                    } else {
                        $data[$field] = $this->json_to_db($data[$field]);
                    }
                }
                // Values are bound, never interpolated. Column names come from
                // the live schema (information_schema) and the model settings,
                // not from the request, so they remain identifiers.
                $query_elements_2[] = "?";
                if($properties['type']=="password"){
                    $values[] = $this->create_password($data[$field]);
                } else {
                    $values[] = (isset($properties['added_date'])) ? date("Y-m-d H:i:s") : $data[$field];
                }
            } else {
                // The column's own default: NULL where allowed, else what the
                // schema says (pm_objectives_tbl.position is NOT NULL DEFAULT 0,
                // and an explicit NULL made every new objective fail).
                $query_elements_2[] = "DEFAULT";
            }
        }
        $query .= implode(",", $query_elements_1).") VALUES (".implode(",",$query_elements_2).")";
        $answer['common'] = $this->DB->MQ($query, 'last', $values);
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

        // Numeric columns take what a person typed ("10,000 USD") as a number;
        // a value with no digits becomes empty and the column default applies.
        foreach ($common_fields as $field => $properties) {
            $t = strtolower((string)($properties['type'] ?? ''));
            if (isset($data[$field]) && !is_array($data[$field]) && in_array($t, ['double','float','decimal','int','tinyint','smallint','bigint','integer'], true)) {
                $data[$field] = normalise_number($data[$field], in_array($t, ['int','tinyint','smallint','bigint','integer'], true));
            }
        }

        $this->run_callbacks($callbacks['before'], $data);
        $query = "UPDATE " . $this->get_table_name($table_name) . " SET ";
        $query_elements = [];
        $values = [];
        foreach ($common_fields as $field=>$properties){
            // A column the request did not mention is left exactly as it is.
            // Every column the form omitted used to be set to NULL, so a
            // one-field edit - or a forged POST with only `id` - blanked
            // username, group and active and locked the account out. A
            // checkbox is the one control that is absent when cleared, so it
            // keeps the old behaviour and becomes NULL.
            if (!array_key_exists($field, $data) && ($properties['type'] ?? '') !== "checkbox") {
                continue;
            }
            if(isset($data[$field])&&$data[$field]!==""){
                // Values bound; column names are schema-derived identifiers.
                $query_elements[] = "`".$field."` = ?";
                if(is_array($data[$field])){
                    if((isset($properties['comma_separated']))&&$properties['comma_separated']) {
                        $values[] = implode(",", $data[$field]);
                    } else {
                        $values[] = $this->json_to_db($data[$field]);
                    }
                } else {
                    if($properties['type']=="password"){
                        $values[] = $this->create_password($data[$field]);
                    } else {
                        $values[] = $data[$field];
                    }
                }
            } else {
                // A password input never renders with the current value in it,
                // so an edit that changes anything else submits password="".
                // Writing NULL here wiped the hash and locked the account out
                // permanently - the "admin accounts stopped working" bug. An
                // empty password means "leave it as it is".
                if (($properties['type'] ?? '') == "password") {
                    continue;
                }
                $query_elements[] = "`".$field."` = DEFAULT";
            }
        }
        if (count($query_elements) === 0) {
            $answer['common'] = true;   // nothing to change
        } else {
            $query .= implode(",", $query_elements)." where id = ?";
            $values[] = $id;
            $answer['common'] = $this->DB->MQ($query, false, $values);
        }

        if($this->has_languages_table($table_name)){
            foreach ($this->R->languages as $language=>$language_properties){
                $language_fields = $this->remaining_array($fields['languages'][$language], $this->array_with_value("no_update", $fields['languages'][$language]));
                $query = "UPDATE " . $this->get_table_name($data['tablename'], "L") . " SET ";
                $query_elements = [];
                $lang_values = [];
                foreach ($language_fields as $field=>$properties){
                    if(isset($data['languages'][$language][$field])&&$data['languages'][$language][$field]!=""){
                        if(is_array($data['languages'][$language][$field])){
                            $query_elements[] = "`".$field."` = ?";
                            $lang_values[] = $this->json_to_db($data['languages'][$language][$field]);
                        } else {
                            $query_elements[] = "`".$field."` = ?";
                            $lang_values[] = $data['languages'][$language][$field];
                        }

                    } else {
                        $query_elements[] = "`".$field."` = NULL";
                    }
                }
                $query .= implode(",", $query_elements)." where article_id = ? and language_id = ?";
                $lang_values[] = $id;
                $lang_values[] = $language_properties['langid'];
                $answer[$language] = $this->DB->MQ($query, false, $lang_values);
            }
        }
        $this->run_callbacks($callbacks['after'], $data);

        return $answer;
    }

    function delete_data($table_name, $id) {
        $answer = [];
        $callbacks = $this->get_callbacks($table_name, "delete");
        // LOGS
        $query = "SELECT * FROM ".$this->get_table_name($table_name)." where id = ?";
        $log = $this->DB->MQ($query, "one", [$id]);
        // The whole deleted row goes into the audit log as JSON, so this value
        // is arbitrary content and must be bound, not quoted into the string.
        $query = "INSERT INTO `core_table_logs_tbl` (`tablename`, `record`, `log_date`, `user`) VALUES (?, ?, ?, ?)";
        $this->DB->MQ($query, false, [
            $this->get_table_name($table_name),
            $this->json_to_db($log),
            date("Y-m-d H:i:s"),
            $_SESSION['user']['username'] ?? '',
        ]);
        // LOGS
        $this->run_callbacks($callbacks['before'], $log);

        $query = "DELETE FROM ".$this->get_table_name($table_name)." where id = ?";
        $answer['common'] = $this->DB->MQ($query, false, [$id]);
        if($this->has_languages_table($table_name)){
            // LOGS
            $query = "SELECT * FROM ".$this->get_table_name($table_name, "L")." where article_id = ?";
            $logs = $this->DB->MQ($query, "all", [$id]);
            foreach ($logs as $log){
                $query = "INSERT INTO `core_table_logs_tbl` (`tablename`, `record`, `log_date`, `user`) VALUES (?, ?, ?, ?)";
                $this->DB->MQ($query, false, [
                    $this->get_table_name($table_name, "L"),
                    $this->json_to_db($log),
                    date("Y-m-d H:i:s"),
                    $_SESSION['user']['username'] ?? '',
                ]);
            }
            // LOGS
            $query = "DELETE FROM ".$this->get_table_name($table_name, "L")." where article_id = ?";
            $answer['languages'] = $this->DB->MQ($query, false, [$id]);
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
                    // No addslashes here any more. add_data/update_data bind
                    // their values, and PDO escapes a bound value itself - so
                    // pre-escaping would store a literal backslash instead of
                    // the character the user typed.
                    $answer[$key] = trim((string)$value);
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


    // password_hash: salted, slow, Argon2id where PHP has it. Until 2 Sep 2026
    // this was md5(md5($text)), unsalted; those hashes are still accepted by
    // users::login once and rewritten with this on the spot.
    function create_password($text){
        return password_hash((string)$text, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    // True when $text matches $stored - either a password_hash() value or a
    // legacy md5(md5()) one. Sets $needs_rehash when the stored form should
    // be replaced with create_password($text).
    function check_password($text, $stored, &$needs_rehash = false){
        $stored = (string)$stored;
        $needs_rehash = false;
        if ($stored === "") {
            return false;
        }
        if ($stored[0] === '$') {
            $ok = password_verify((string)$text, $stored);
            $needs_rehash = $ok && password_needs_rehash($stored, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
            return $ok;
        }
        $ok = hash_equals($stored, md5(md5((string)$text)));
        $needs_rehash = $ok;
        return $ok;
    }

    function json_to_db($json_array) {
        // Not addslashes()d: the result is written as a bound value.
        return json_encode($json_array, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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