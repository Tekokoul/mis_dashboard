<?php
function chooseElement($name, $field, $data=[]){
    $html = "";
    if($field['type']=="varchar") {
        $html .= createText($name, $field, $data);
    }
    if($field['type']=="int") {
        $html .= createText($name, $field, $data);
    }
    if($field['type']=="double") {
        $html .= createText($name, $field, $data);
    }
    if($field['type']=="float") {
        $html .= createText($name, $field, $data);
    }
    if(($field['type']=="mediumtext")||($field['type']=="text")) {
        $html .= createTextArea($name , $field, $data);
    }
    if(($field['type']=="dropdown")||($field['type']=="multiselect")) {
        $html .= createDropDown($name, $field, $data);
    }
    if($field['type']=="increment") {
        $html .= createIncrement($name, $field, $data);
    }
    if($field['type']=="date") {
        $html .= createDate($name, $field, $data);
    }
    if($field['type']=="datetime") {
        $html .= createDateTime($name, $field, $data);
    }
    if($field['type']=="image") {
        $html .= createImage($name, $field, $data);
    }
    if($field['type']=="imagelist") {
        $html .= createImageList($name, $field, $data);
    }
    if($field['type']=="file") {
        $html .= createFile($name, $field, $data);
    }
    if($field['type']=="list") {
        $html .= createList($name, $field, $data);
    }
    if($field['type']=="json") {
        $html .= createJSON($name, $field, $data);
    }
    if($field['type']=="order_status") {
        $html .= createOrderStatus($name, $field, $data);
    }
    if($field['type']=="caption") {
        $html .= createCaption($name, $field, $data);
    }
    if($field['type']=="password") {
        $html .= createPassword($name, $field, $data);
    }
    return $html;
}

function createLabel($name, $field){
    if(isset($field['title'])) {
        $label = $field['title'];
    } else {
        $label = ucfirst($name);
    }
    $value = $field['value'] ?? "";
    $label= $label." ".$value;
    return $label;
}

function createText($name, $field, $data) {
    if(get_field_property("hidden", $field)) {
        $html = '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.htmlspecialchars($data).'">';
    } else {
        $disabled = isset($field['disabled']) ? "disabled" : "";
        $required = isset($field['required']) ? "required" : "";
        $required_star = isset($field['required']) ? "<span class='text-danger'>*</span>" : "";

        $label = createLabel($name, $field);
        $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">' . $label . $required_star.'</label><div class="col-lg-8">';
        $html .= '<input type="text" class="form-control form-control-modern" id="' . $name . '" name="' . $name . '" value="' . display($data) . '" '.$disabled.' '.$required.'>';
        $html .= '</div></div>';
    }
    return $html;
}

function createTextArea($name, $field, $data) {
    $disabled = isset($field['disabled']) ? "disabled" : "";
    $required = isset($field['required']) ? "required" : "";
    $required_star = isset($field['required']) ? "<span class='text-danger'>*</span>" : "";

    if(get_field_property("hidden", $field)) {
        $html = '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.htmlspecialchars($data).'">';
    } else {
        $label = createLabel($name, $field);
        if(get_field_property("no_editor",$field)){
            $extra_classes = "" ;
            $rows = 5;
        } else {
            $extra_classes = "mceEditor";
            $rows = 10;
        }

        $html = '<div class="form-group row pb-3"><label class="col-lg-3 control-label text-lg-end pt-2" for="textareaDefault">'.$label.$required_star.'</label><div class="col-lg-8">';
        $html .= '<textarea cols="80" rows="'.$rows.'" name="'.$name.'" id="' . $name . '" class="form-control '.$extra_classes.'" '.$disabled.' '.$required.'>'.display($data).'</textarea>';
        $html .= '</div></div>';
    }
    return $html;
}

function createDropDown($name, $field, $data) {
    global $registry;
    $language_suffix = $registry->settings[$registry->defaultDB]['db_table_languages_suffix'];
    $default_language_id = $registry->languages[_DEFAULT_LANGUAGE]['langid'];
    $disabled = isset($field['disabled']) ? "disabled" : "";
    $required = isset($field['required']) ? "required" : "";
    $required_star = isset($field['required']) ? "<span class='text-danger'>*</span>" : "";
    $select2 = isset($field['select2']) ? $field['select2'] : false;
    if ((!$select2)&&($field['values_from']=="values_list")) {
        $selectElement = '';
    } else {
        $selectElement = 'data-plugin-selectTwo';
    }
    $multiselect = isset($field['multiselect']) ? $field['multiselect'] : false;
    if ($multiselect) {
        $selectElement = 'multiple="multiple" ' . $selectElement;
        $name = $name.'[]';
        if(isset($field['comma_separated'])&&$field['comma_separated']){
            $data = explode(",",$data);
        } else {
            $data = json_from_db($data);
        }
    }
    $label = createLabel($name, $field);
    $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">'.$label.$required_star.'</label><div class="col-lg-8">';
    if($field['values_from']=="db"){
        $link_to_table = $field['link_to_table'];
        $link_to_field = $field['link_to_field'];
        $display_to_field = $field['display_to_field'] ?? "";
        $selectElement .= ' data-plugin-selectTwo ';

        $link_from_field = $field['link_from_field'] ?? "id";

        $where_clause = $field['where_clause'] ?? "";
        $limit = (isset($field['limit'])) ? " limit ".$field['limit'] : "";
        $order_by = $field['order_by'] ?? $link_to_field;
//        if(isset($data)){
//            $where_clause .= " or ".$link_to_table.".id=".$data;
//            $limit ++;
//        }
        $html .= '<select '.$selectElement.' class="form-control populate" name="'.$name.'" id="'.md5($name).'" '.$disabled.' '.$required.'>';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='0'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">None</option>";
        }
        if(isset($field['add_all_value'])) {
            $html .= "<option value='%'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">All</option>";
            $html .= "<option value='0'>None</option>";
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
        foreach($linkedresult as $linkedrow) {
            $html .= "<option value='" . $linkedrow[$link_from_field] . "'";
            if (is_array($data)) {
                $html .= in_array($linkedrow[$link_from_field], $data) ? " selected>" : ">";
            } else {
                $html .= ($linkedrow[$link_from_field] == $data) ? " selected>" : ">";
            }
            if($display_to_field!=""){
                $values_array = [];
                foreach($linked_fields as $tempfield){
                    $values_array[] = $linkedrow[$tempfield];
                }
                $html .= vsprintf($display_to_field, $values_array);
            } else {
                foreach ($linked_fields as $tempfield) {
                    $html .= $linkedrow[$tempfield] . " ";
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
        $selectElement = 'data-plugin-selectTwo';
        if (!$select2) {
            $selectElement = '';
        }

        $html .= '<select '.$selectElement.' class="form-control populate" name="'.$name.'" id="'.md5($name).'" '.$disabled.' '.$required.'>';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='0'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">None</option>";
        }
        if(isset($field['add_all_value'])) {
            $html .= "<option value='%'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">All</option>";
        }
        $linked_fields = [];
        if(strpos($link_to_field, ",")!==false){
            $linked_fields = explode(",", $link_to_field);
        } else {
            $linked_fields[]=$link_to_field;
        }

        foreach($linkedresult as $linkedrow) {
            $html .= "<option value='" . $linkedrow[$link_from_field] . "' ";
            $html .= ($linkedrow[$link_from_field] == $data) ? "selected" : "";
            $html .= ">" ;
            foreach($linked_fields as $tempfield){
                $html .= $linkedrow[$tempfield]." ";
            }
            $html .= "</option>";
        }
        $html .= '</select>';
    }
    if($field['values_from']=="json"){
        $option_field = $field['option_field'];
        $selectElement = 'data-plugin-selectTwo';
        if (!$select2) {
            $selectElement = '';
        }
        if ($multiselect) {
            $selectElement = 'multiple="multiple" '.$selectElement;
            $name = $name.'[]';
            $data = json_from_db($data);
        }

        $html .= '<select '.$selectElement.' class="form-control populate" name="'.$name.'" id="'.md5($name).'" '.$disabled.' '.$required.'>';
        if(isset($field['add_zero_value'])) {
            $html .= "<option value='0'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">None</option>";
        }
        if(isset($field['add_all_value'])) {
            $html .= "<option value='%'";
            $html .= ($data==0) ? ' selected ' : '';
            $html .= ">All</option>";
        }
        $json_values = json_from_db($field["values"]);
        foreach ($json_values as $json_value) {
            $html .= "<option value='" . $json_value[$option_field] . "' ";
            if (is_array($data)) {
                $html .= in_array($json_value[$option_field], $data) ? "selected" : "";
            } else {
                $html .= ($json_value[$option_field] == $data) ? "selected" : "";
            }
            $html .= ">".$json_value[$option_field]."</option>";
        }
        $html .= '</select>';
    }
    if($field['values_from']=="values_list"){
        $html .= '<select '.$selectElement.' class="form-control form-control-modern" name="'.$name.'" id="'.md5($name).'" '.$disabled.' '.$required.'>';
        foreach ($field[$field['values_from']] as $key => $value){
            $html .= '<option value="'.$key.'"';
            if($key==$data){
                $html .= ' selected ';
            }
            $html .= '>'.$value.'</option>';
        }
        $html .= '</select>';
    }
    if($field['values_from']=="dynamic"){
        $html .= '<select '.$selectElement.' class="form-control populate" name="'.$name.'" id="'.md5($name).'" '.$disabled.' '.$required.'></select>
<script>
var dynamic_field_data = '.json_encode($data).';
</script>';

    }
    $html .= '</div></div>';
    return $html;
}

function createIncrement($name, $field, $data) {
    global $registry;
    $language_suffix = $registry->settings[$registry->defaultDB]['db_table_languages_suffix'];
    $default_language_id = $registry->languages[_DEFAULT_LANGUAGE]['langid'];
    $disabled = isset($field['disabled']) ? "disabled" : "";
    $value = $field['value'] ?? "";

    $label = createLabel($name, $field);
    $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">'.$label.'</label><div class="col-lg-8">';

    $html .= '<select class="form-control form-control-modern" name="'.$name.'" '.$disabled.'>';
    for($i=$field['start']; $i<=$field['end']; $i=$i+$field['increment']){
        $html .= '<option value="'.$i.'"';
        if($i==$data){
            $html .= ' selected ';
        }
        $html .= '>';
        if(isset($field['value_position'])&&($field['value_position']=="before")){
            $html .= $value." ".$i;
        } else {
            $html .= $i." ".$value;
        }

        $html .= '</option>';
    }
    $html .= '</select>';

    $html .= '</div></div>';
    return $html;
}

function createDate($name, $field, $data){
    $disabled = isset($field['disabled']) ? "disabled" : "";
    $label = createLabel($name, $field);
    $actual_data = ($data!="") ? $data : date("Y-m-d");
    $html = '<div class="form-group row pb-3"><label class="col-lg-3 control-label text-lg-end pt-2">'.$label.'</label><div class="col-lg-8"><div class="input-group">';
    $html .= "<input type='text' data-plugin-datepicker data-plugin-options='{\"format\": \"yyyy-mm-dd\", \"orientation\":\"bottom\"}' class='form-control' id ='" . $name . "' name ='" . $name . "' value='" . $actual_data . "' ".$disabled."/>";
    $html .= '<span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div></div></div>';
    return $html;
}

function createDateTime($name, $field, $data){
    $actual_data = ($data!="") ? $data : date("Y-m-d H:i:s");
    if(get_field_property("hidden", $field)) {
        $html = '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.htmlspecialchars($actual_data).'">';
    } else {
        $disabled = isset($field['disabled']) ? "disabled" : "";
        $label = createLabel($name, $field);
        $html = '<div class="form-group row pb-3"><label class="col-lg-3 control-label text-lg-end pt-2">'.$label.'</label><div class="col-lg-8"><div class="input-group">';
        $html .= "<input type='text' data-plugin-datetimepicker class='form-control' id ='" . $name . "' name ='" . $name . "' value='" . $actual_data . "' ".$disabled."/>";
        $html .= '<span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div></div></div>';
    }
    return $html;
}

function createImage($name, $field, $data) {
    $label = createLabel($name, $field);
    $html = '<div class="form-group row pb-3"><label class="col-lg-3 control-label text-lg-end pt-2">'.$label.'</label><div class="col-lg-5"><div class="input-group">';
    $html .= '<input class="form-control" data-plugin-rfm data-parameters="type=1&popup=1&relative_url=1&field_id=' . sluggify($name) . '" type="text" name="' . $name . '" id="' . sluggify($name) . '" value = "'.$data.'">';
    $html .= '<span class="input-group-text"><i class="fas fa-image"></i></span></div></div><div class="col-lg-3"><div id="thumb_'.sluggify($name).'">';
    $img_html = ($data!="") ? '<img src="'._PROJECT_URL.'/ngine_resize.php?w=200&f='.$data.'" class="img-fluid" style="width:200px;">' : '<span style="line-height: 3em">No image selected</span>';
    $html .= $img_html;
    $html .= '</div></div></div>';
    return $html;
}

function createFile($name, $field, $data) {
    $label = createLabel($name, $field);
    $html = '<div class="form-group row pb-3"><label class="col-lg-3 control-label text-lg-end pt-2">'.$label.'</label><div class="col-lg-8"><div class="input-group">';
    $html .= '<input class="form-control" data-plugin-rfm data-parameters="type=2&popup=1&relative_url=1&field_id=' . sluggify($name) . '" type="text" name="' . $name . '" id="' . sluggify($name) . '" value = "'.$data.'">';
    $html .= '<span class="input-group-text"><i class="fas fa-file"></i></span></div></div></div>';
    return $html;
}

function createListRow($name, $field, $data, $number){
    $html = '<tr id="element_'.$number.'" class="element_row"><td>';
    $html .= '<section class="card"><header class="card-header card-padding-10"><div class="card-actions">
<a href="javascript:void(0);" class="deleteElement" data-id="'.$number.'"><i class="fa fa-times"></i></a></div></header><div class="card-body">';
    foreach ($field['elements'] as $list_field => $value) {
        $prepopulated = $data[$list_field] ?? "";
        $html .= chooseElement($name . "[".$number."][" . $list_field . "]", $value, $prepopulated);
    }
    $html .= '</div></section>';
    $html .= '</td></tr>';
    return $html;
}

function createImageListRow($name, $field, $data, $number){

    $html = '<tr id="element_'.$number.'" class="element_row"><td>';
    $html .= '<section class="card"><header class="card-header card-padding-10"><div class="card-actions">
<a href="javascript:void(0);" class="deleteElement" data-id="'.$number.'"><i class="fa fa-times"></i></a></div></header><div class="card-body">';
    foreach ($field as $list_field => $value) {
        $prepopulated = $data ?? "";
        $html .= createImage($name . "[]", ['title' => $value], $prepopulated);
    }
    $html .= '</div></section>';
    $html .= '</td></tr>';
    return $html;
}

function createImageList($name, $field, $data) {
    if(!is_array($data)){
        $data = json_from_db($data);
    }
    $label = createLabel($name, $field);
    $html = "";
    $node_nr = 0;
    $html .= '<div class="col-lg-12"><section class="card"><header class="card-header"><div class="card-actions">
<a href="javascript:void(0);" id="addElement" data-id="'.$node_nr.'"><i class="fa fa-plus"></i></a></div><h2 class="card-title">'.$label.'</h2></header>
        <div class="card-body"><table class="table table-responsive-md table-striped mb-0" id="elementstable"><tbody>';
    if(is_set($data)){
        foreach ($data as $node) {
            $html .= createImageListRow($name, ['type' => 'image'], $node, $node_nr);
            $node_nr++;
        }
    } else {
        $html .= createImageListRow($name, ['type' => 'image'], $data, 0);
    }
    $html .= '</tbody></table></div></section></div>';
    return $html;
}

function createList($name, $field, $data) {
    if(!is_array($data)){
        $data = json_from_db($data);
    }
    $label = createLabel($name, $field);
    $html = "";
    $node_nr = 0;
    $html .= '<div class="col-lg-12"><section class="card"><header class="card-header"><div class="card-actions">
<a href="javascript:void(0);" id="addElement" data-id="'.$node_nr.'"><i class="fa fa-plus"></i></a></div><h2 class="card-title">'.$label.'</h2></header>
        <div class="card-body"><table class="table table-responsive-md table-striped mb-0" id="elementstable"><tbody>';
    if(is_set($data)){
        foreach ($data as $node) {
            $html .= createListRow($name, $field, $node, $node_nr);
            $node_nr++;
        }
    } else {
        $html .= createListRow($name, $field, $data, 0);
    }
    $html .= '</tbody></table></div></section></div>';
    return $html;
}

function createJSON($name, $field, $data) {
    if(!is_array($data)){
        $data = json_from_db($data);
    }
    $label = createLabel($name, $field);
    $html = "";
    $node_nr = 0;
    $html .= '<div class="col-lg-12"><section class="card"><header class="card-header"><div class="card-actions">
<a href="javascript:void(0);" id="addElement" data-id="'.$node_nr.'"><i class="fa fa-plus"></i></a></div><h2 class="card-title">'.$label.'</h2></header>
        <div class="card-body"><table class="table table-responsive-md table-striped mb-0" id="elementstable"><tbody>';
    $keys = array_keys($field['elements']);
    foreach ($keys as $key){
        if(is_set($data[$key])){
            foreach ($data[$key] as $node) {
                $html .= createListRow($name.'['.$key.']', $field['elements'][$key], $node, $node_nr);
                $node_nr++;
            }
        } else {
            $html .= createListRow($name, $field, $data, 0);
        }
    }

    $html .= '</tbody></table></div></section></div>';
    return $html;
}

function createOrderStatus($name, $field, $data){
    $label = createLabel($name, $field);
    $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">'.$label.'</label><div class="col-lg-8">';
    $html .= '<select class="form-control form-control-modern" name="'.$name.'">';
    foreach ($field[$field['values_from']] as $key => $value){
        $html .= '<option data-status="'.$value['status'].'" value="'.$key.'"';
        if($key==$data){
            $html .= ' selected ';
        }
        $html .= '>'.$value['title'].'</option>';
    }
    $html .= '</select>';
    $html .= '</div></div>';
    return $html;
}

function createCaption($name, $field, $data) {
    if(get_field_property("hidden", $field)) {
        $html = '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.htmlspecialchars($data).'">';
    } else {
        $label = createLabel($name, $field);
        $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">' . $label . '</label><div class="col-lg-8">';
        $html .= '<p class="form-control-static mb-0">'.$data.'</p>';
        $html .= '</div></div>';
    }
    return $html;
}

function createPassword($name, $field, $data) {

    $disabled = isset($field['disabled']) ? "disabled" : "";
    $required = isset($field['required']) ? "required" : "";
    $required_star = isset($field['required']) ? "<span class='text-danger'>*</span>" : "";

    $label = 'New password';
    $html = '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">' . $label . $required_star.'</label><div class="col-lg-8">';
    $html .= '<input type="password" class="form-control form-control-modern" id="pw_' . $name . '_1" name="' . $name . '" value="" '.$disabled.' '.$required.'>';
    $html .= '</div></div>';

    $label = 'Repeat password';
    $html .= '<div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">' . $label . $required_star.'</label><div class="col-lg-8">';
    $html .= '<input type="password" class="form-control form-control-modern" id="pw_' . $name . '_2" name="' . $name . '" value="" '.$disabled.' '.$required.'>';
    $html .= '</div></div>';
    return $html;
}