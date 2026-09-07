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
                    // "abbr,name" renders as the code, then the name, in
                    // two spans, so the column can keep the code visible and
                    // let the (repeated, long) name truncate or hide.
                    if (count($linked_fields) >= 2 && trim($linked_fields[0]) === 'abbr' && trim((string)($linked_result[$linked_fields[0]] ?? '')) !== '') {
                        $rest = [];
                        foreach (array_slice($linked_fields, 1) as $tempfield) { $rest[] = display($linked_result[$tempfield] ?? ""); }
                        $answer = '<span class="afcdc-code">' . display($linked_result[$linked_fields[0]]) . '</span> '
                                . '<span class="afcdc-parent">' . trim(implode(" ", $rest)) . '</span>';
                    } else {
                        $answer = "";
                        foreach($linked_fields as $tempfield){
                            $answer .= display($linked_result[$tempfield] ?? "")." ";
                        }
                        $answer = trim($answer);
                    }
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

    // The same filter vocabulary as the overview page: a small label above
    // the control, in one wrapping row, lit green while it is narrowing.
    // A filter may name a parent filter ("narrow_by") and the column on ITS
    // linked table that carries the parent id ("parent_field"): the options
    // then carry data-parent and custom.js hides the ones that do not belong
    // to the chosen parent, and clears this box when the parent changes.
    $is_active = !is_array($data) && (string)$data !== '' && (string)$data !== '%';
    $parent_of = (string)($field['narrow_by'] ?? '');
    $parent_col = (string)($field['parent_field'] ?? '');
    $parent_via = (isset($field['parent_via']) && is_array($field['parent_via'])) ? $field['parent_via'] : null;
    $html = '<label class="afcdc-filter' . ($is_active ? ' is-active' : '') . '"><span>' . display($field['title'] ?? ucfirst($name)) . '</span>';
    $select_attrs = ' data-afcdc-autosubmit="1"' . ($parent_of !== '' ? ' data-afcdc-narrow-by="' . display($parent_of) . '"' : '');
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
        $html .= '<select class="form-select form-select-sm filter-by" name="'.$name.'" id="'.$name.'" '.$disabled.$select_attrs.'>';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='%'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">" . display($field['all_label'] ?? 'All') . "</option>";
        }
        $link_to_table_part = substr($link_to_table, 0, strlen($link_to_table) - 4);
        $result = $registry->db_master->MQ("SHOW TABLES LIKE '" . $link_to_table_part . $language_suffix . "_tbl'", "all");
        // When the parent id lives one table further up (a programme's goal
        // is its objective's goal), "parent_via" maps through that table:
        //   {"table": "pm_objectives_tbl", "key": "objective_id", "parent_field": "pillar_id"}
        $parent_map = null;
        if ($parent_via && preg_match('/^[A-Za-z0-9_]{1,64}\z/', (string)($parent_via['table'] ?? ''))
            && preg_match('/^[A-Za-z0-9_]{1,64}\z/', (string)($parent_via['parent_field'] ?? ''))) {
            $parent_map = [];
            foreach ((array)$registry->db_master->MQ("select `id`, `" . $parent_via['parent_field'] . "` as p from `" . $parent_via['table'] . "`", "all") as $pr) {
                $parent_map[(string)$pr['id']] = (string)$pr['p'];
            }
        }

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
            $parent_value = null;
            if ($parent_map !== null) {
                $parent_value = $parent_map[(string)($linkedrow[$parent_via['key'] ?? ''] ?? '')] ?? '';
            } elseif ($parent_col !== '' && array_key_exists($parent_col, $linkedrow)) {
                $parent_value = (string)$linkedrow[$parent_col];
            }
            if ($parent_value !== null) { $html .= "data-parent='" . display($parent_value) . "' "; }
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
    if($field['values_from']=="values_list"){
        // A fixed list (e.g. Active: Yes/No). "%" means All; the value is
        // compared as a string so "0" (No) is not mistaken for "no choice".
        $current = is_array($data) ? '' : (string)$data;
        $html .= '<select class="form-select form-select-sm filter-by" name="'.$name.'" id="'.$name.'" '.$disabled.$select_attrs.'>';
        // "All" is there when the filter asks for it (add_zero_value) or names
        // it (all_label): without it the box showed its first choice as
        // selected while filtering nothing.
        if(isset($field['add_zero_value']) || isset($field['all_label'])) {
            $html .= "<option value='%'".(($current === '' || $current === '%') ? ' selected' : '').">".display($field['all_label'] ?? 'All')."</option>";
        }
        foreach ((array)($field['values_list'] ?? []) as $value => $label) {
            $html .= "<option value='".display($value)."'".(($current !== '' && $current !== '%' && $current === (string)$value) ? ' selected' : '').">".display($label)."</option>";
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
    $html .= '</label>';
    return $html;
}

/**
 * Class and title for a list cell, so the views can stay one line each:
 * the parent column keeps its code and truncates its name, the name column
 * stops at two lines, and the utility columns can be hidden on a phone.
 * The title carries the full text, which a click on the row also reaches.
 */
function list_cell_attrs($field, array $properties, $cell) {
    $classes = ['afcdc-col-' . preg_replace('/[^a-z0-9_]/i', '', (string)$field)];
    // Only a cell that actually rendered a code truncates: a goal shown by
    // name alone, or a list of people, must keep wrapping.
    if (strpos((string)$cell, 'class="afcdc-code"') !== false && empty($properties['multiselect'])) {
        $classes[] = 'afcdc-cell-parent';
    } elseif ($field === 'name') {
        $classes[] = 'afcdc-cell-name';
    }
    // The title (hover text) only where something can be cut off.
    $plain = trim(html_entity_decode(strip_tags((string)$cell), ENT_QUOTES, 'UTF-8'));
    $truncates = in_array('afcdc-cell-parent', $classes, true) || in_array('afcdc-cell-name', $classes, true);
    $title = ($truncates && $plain !== '' && $plain !== 'None') ? ' title="' . display($plain) . '"' : '';
    return ' class="' . implode(' ', $classes) . '"' . $title;
}

/**
 * The search box, in the same shape as the filters beside it: a small label
 * above a control of the same height. It sits inside the filter row, so on
 * a narrow screen it wraps under the first filter instead of being pushed
 * alone to the far right in a differently styled pill.
 */
function list_search_box($value) {
    $value = (string)$value;
    $html  = '<label class="afcdc-filter afcdc-filter--search' . ($value !== '' ? ' is-active' : '') . '"><span>Search</span>';
    $html .= '<div class="input-group input-group-sm afcdc-search">';
    $html .= '<input type="search" class="search-term form-control form-control-sm" name="search-term" id="search-term"'
           . ' placeholder="Name, code or programme" value="' . display($value) . '" autocomplete="off">';
    $html .= '<button class="btn btn-light border" type="submit" aria-label="Search"><i class="bx bx-search" aria-hidden="true"></i></button>';
    $html .= '</div></label>';
    return $html;
}

/** "Clear", shown only while a filter or a search is narrowing the list. */
function list_clear_link($href, array $filter_data, $search) {
    $narrowing = array_filter($filter_data, function ($v) { return $v !== '' && $v !== '%'; });
    if (!$narrowing && (string)$search === '') { return ''; }
    return '<a class="btn btn-sm btn-light border afcdc-filters__clear" href="' . $href . '">Clear</a>';
}

