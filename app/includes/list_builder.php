<?php
function display_list_element($field, $data, $active){
    global $registry;
    $language_suffix = $registry->settings[$registry->defaultDB]['db_table_languages_suffix'];
    $default_language_id = $registry->languages[_DEFAULT_LANGUAGE]['langid'];

    switch ($field['type']){
        case "datetime":
            $answer = date("d/m/Y @ H:i", strtotime($data));
            if(get_field_property("check_upload", $field)) {
                $currenttime = date("Y-m-d H:i:s");
                $up_or_down = ($currenttime < $data || !$active) ? " <span style='color:darkred;font-size:20px;'>▼</span> " : " <span style='color:green;font-size:20px;'>▲</span> ";
                $answer = $up_or_down.$answer;
            }
            break;
        case "date":
            $answer = date("d/m/Y", strtotime($data));
            break;
        case "dropdown":
            if($field['values_from']=="db"){
                $from_field = $field['link_from_field'] ?? "id";
                // A multiselect column holds "3,7,12". Resolving that as one
                // value made MySQL cast it to 3 and the column showed a single
                // name, hiding everyone else on the list.
                if (get_field_property("multiselect", $field) && strpos((string)$data, ",") !== false) {
                    $names = [];
                    foreach (explode(",", (string)$data) as $one) {
                        $one = trim($one);
                        if ($one === "") { continue; }
                        $names[] = display_list_element($field, $one, $active);
                    }
                    $answer = implode(", ", array_filter($names, function($n){ return $n !== "" && $n !== "None"; }));
                    if ($answer === "") { $answer = "None"; }
                    break;
                }
                $link_to_table_part = substr($field['link_to_table'], 0, strlen($field['link_to_table']) - 4);

                $has_languages = $registry->db_master->MQ("SHOW TABLES LIKE '" . $link_to_table_part . $language_suffix."_tbl'", "all");
                if (is_set($has_languages)) {
                    $query = "select " . $field['link_to_field'] . " from " . $link_to_table_part . "_tbl inner join " . $link_to_table_part . $language_suffix . "_tbl on " .
                        $link_to_table_part . "_tbl.id=" . $link_to_table_part . $language_suffix . "_tbl.article_id where ".$from_field."='" . $data . "'";
                } else {
                    $query = "select " . $field['link_to_field'] . " from " . $field['link_to_table'] . " where ".$from_field."='" . $data . "'";
                }
                $linked_result = $registry->db_master->MQ($query, "one");
                if (is_set($linked_result)) {
                    if(strpos($field['link_to_field'], ",")!==false){
                        $linked_fields = explode(",", $field['link_to_field']);
                    } else {
                        $linked_fields[]=$field['link_to_field'];
                    }
                    // Escaped: these come from user-editable rows, so an
                    // objective renamed to contain markup would otherwise run
                    // for every user who opens any list showing that column.
                    $answer = "";
                    foreach($linked_fields as $tempfield){
                        $answer .= display($linked_result[$tempfield] ?? "")." ";
                    }
                    $answer = trim($answer);
                } else {
                    $answer = "None";
                }
            }
            if($field['values_from']=="values_list") {
                $answer = display($field['values_list'][$data] ?? "");
            }
            if($field['values_from']=="file") {
                $linked_result = readJSONFile(_JSON_MODELS_PATH.$field['link_to_table']);
                $link_to_field = $field['link_to_field'];
                $link_from_field = $field['link_from_field'] ?? "id";
                $file_row = $linked_result[array_search($data, array_column($linked_result, $link_from_field))] ?? [];
                $answer = display($file_row[$link_to_field] ?? "");
            }
            break;
        case "order_status":
            $answer = '<span class="ecommerce-status '.display($field['values_list'][$data]['status'] ?? "").'">'.display($field['values_list'][$data]['title'] ?? "").'</span>';
            break;
        default:
            $answer = display($data);
            $value = $field['value'] ?? "";
            if(isset($field['value_position'])&&($field['value_position']=="before")){
                $answer= $value." ".$answer;
            } else {
                $answer= $answer." ".$value;
            }
            break;
    }
    return $answer;
}

function filter_DropDown($name, $field, $data = []) {
    global $registry;
    $language_suffix = $registry->settings[$registry->defaultDB]['db_table_languages_suffix'];
    $default_language_id = $registry->languages[_DEFAULT_LANGUAGE]['langid'];
    $disabled = isset($field['disabled']) ? "disabled" : "";

    $label = createLabel($name, $field);
    $html = '<div class="col-12 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0"><div class="d-flex align-items-lg-center flex-column flex-lg-row"><label class="ws-nowrap me-3 mb-0">'.$label.'</label>';
    if($field['values_from']=="db"){
        $link_to_table = $field['link_to_table'];
        $link_to_field = $field['link_to_field'];
        $display_to_field = $field['display_to_field'] ?? "";

        $link_from_field = $field['link_from_field'] ?? "id";


        $where_clause = $field['where_clause'] ?? "";
        $limit = (isset($field['limit'])) ? " limit ".$field['limit'] : "";
        $order_by = $field['order_by'] ?? $link_to_field;
//        if(isset($data)){
//            $where_clause .= " or ".$link_to_table.".id=".$data;
//            $limit ++;
//        }
        $html .= '<select class="form-control select-style-1 filter-by" name="'.$name.'" id="'.$name.'" '.$disabled.' data-afcdc-autosubmit="1">';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='%'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">All</option>";
        }
        $link_to_table_part = substr($link_to_table, 0, strlen($link_to_table) - 4);
        $result = $registry->db_master->MQ("SHOW TABLES LIKE '" . $link_to_table_part . $language_suffix . "_tbl'", "all");

        if(is_set($result)) {
            $query = "select * from " . $link_to_table_part . "_tbl inner join " . $link_to_table_part  . $language_suffix . "_tbl on " .
                $link_to_table_part . "_tbl.id=" . $link_to_table_part  . $language_suffix . "_tbl.article_id where language_id=" . $default_language_id ." ". $where_clause." "
                . " order by " .$order_by . $limit;
            $linkedresult = $registry->db_master->MQ($query, "all");
        } else {
            $query = "select * from " . $link_to_table . " where 1 ". $where_clause." order by " . $order_by . $limit;
            $linkedresult = $registry->db_master->MQ($query, "all");
        }
        $linked_fields = [];
        if(strpos($link_to_field, ",")!==false){
            $linked_fields = explode(",", $link_to_field);
        } else {
            $linked_fields[]=$link_to_field;
        }
        // Filter options are built from database rows: escaped like a table
        // cell, so a name containing </select><img onerror> cannot break out
        // of the control on the list pages.
        foreach($linkedresult as $linkedrow) {
            $html .= "<option value='" . display($linkedrow[$link_from_field]) . "' ";
            if($display_to_field!=""){
                $html .= ">";
                $values_array = [];
                foreach($linked_fields as $tempfield){
                    $values_array[] = $linkedrow[$tempfield];
                }
                $html .= display(vsprintf($display_to_field, $values_array));
            } else {
                if (is_array($data)) {
                    $html .= in_array($linkedrow[$link_from_field], $data) ? "selected" : "";
                } else {
                    $html .= ($linkedrow[$link_from_field] == $data) ? "selected" : "";
                }

                $html .= ">";
                foreach ($linked_fields as $tempfield) {
                    $html .= display($linkedrow[$tempfield]) . " ";
                }
            }
            $html .= "</option>";
        }
        $html .= '</select>';
    }
    if($field['values_from']=="file"){
        $linkedresult = readJSONFile(_JSON_MODELS_PATH.$field['link_to_table']);
        $link_to_table = $field['link_to_table'];
        $link_to_field = $field['link_to_field'];
        $link_from_field = $field['link_from_field'] ?? "id";
        $select2 = isset($field['select2']) ? $field['select2'] : true;
        $selectElement = 'data-plugin-selectTwo';
        if (!$select2) {
            $selectElement = '';
        }

        $html .= '<select '.$selectElement.' class="form-control populate" name="'.$name.'" id="'.$name.'" '.$disabled.'>';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='0'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">None</option>";
        }

        $linked_fields = [];
        if(strpos($link_to_field, ",")!==false){
            $linked_fields = explode(",", $link_to_field);
        } else {
            $linked_fields[]=$link_to_field;
        }

        foreach($linkedresult as $linkedrow) {
            $html .= "<option value='" . display($linkedrow[$link_from_field]) . "' ";
            $html .= ($linkedrow[$link_from_field] == $data) ? "selected" : "";
            $html .= ">" ;
            foreach($linked_fields as $tempfield){
                $html .= display($linkedrow[$tempfield])." ";
            }
            $html .= "</option>";
        }
        $html .= '</select>';
    }
    if($field['values_from']=="json"){
        $option_field = $field['option_field'];
        $select2 = isset($field['select2']) ? $field['select2'] : true;
        $multiselect = isset($field['multiselect']) ? $field['multiselect'] : false;
        $selectElement = 'data-plugin-selectTwo';
        if (!$select2) {
            $selectElement = '';
        }
        if ($multiselect) {
            $selectElement = 'multiple="multiple" '.$selectElement;
            $name = $name.'[]';
            $data = json_from_db($data);
        }

        $html .= '<select class="form-control select-style-1 filter-by" name="'.$name.'" id="'.$name.'" '.$disabled.' data-afcdc-autosubmit="1">';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='0'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">None</option>";
        }

        $json_values = json_from_db($field["values"]);
        foreach ($json_values as $json_value) {
            $html .= "<option value='" . display($json_value[$option_field]) . "' ";
            if (is_array($data)) {
                $html .= in_array($json_value[$option_field], $data) ? "selected" : "";
            } else {
                $html .= ($json_value[$option_field] == $data) ? "selected" : "";
            }
            $html .= ">".display($json_value[$option_field])."</option>";
        }
        $html .= '</select>';
    }
    if($field['values_from']=="values_list"){
        $html .= '<select class="form-control select-style-1 filter-by" name="'.$name.'" id="'.$name.'" '.$disabled.' data-afcdc-autosubmit="1">';
        foreach ($field[$field['values_from']] as $key => $value){
            $html .= '<option value="'.display($key).'"';
            if($key==$data){
                $html .= ' selected ';
            }
            $html .= '>'.display($value).'</option>';
        }
        $html .= '</select>';
    }
    $html .= '</div></div>';
    return $html;
}