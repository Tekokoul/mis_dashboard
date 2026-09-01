<?php
require_once _CONTROLLERS_PATH."core.php";

class pollsController extends coreController{
    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "folders" => FILTER_UNSAFE_RAW,
            "filter-by" => FILTER_SANITIZE_NUMBER_INT,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);

        $validated['model'] = 'youspeak_polls';

        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
//        $data['meta_filters'][] = [
//            "key" => "idConstituency",
//            "title" => "Constituency",
//            "type" => "dropdown",
//            "values_from" => "db",
//            "link_to_table" => "youspeak_constituencies_tbl",
//            "link_to_field" => "name",
//            "order_by" => "`name` asc",
//            "where_clause" => "and active=1",
//            "add_zero_value" => true
//        ];
        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        if($filter['key']=='idIssues_1'){
                            $filters[] = "AND (idIssues_1='".($this->query[$filter['key']] ?? "")."' OR idIssues_2='".($this->query[$filter['key']] ?? "")."')";
                        } else {
                            $filters[] = "AND ".$filter['key']."='".($this->query[$filter['key']] ?? "")."'";
                        }
                    }
                }
                $data['filter_data'][$filter['key']] = $this->query[$filter['key']] ?? "";
            }
        }
        if(is_set($data['fields'])){
//            $results = ;
            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??"", $filters));
        } else {
            $data['data'] = [];
        }
        if($_SESSION['user']['constituencies']!=""){
            $query = "select * from youspeak_constituencies_tbl where id in (".$_SESSION['user']['constituencies'].") order by name";
        } else {
            $query = "select * from youspeak_constituencies_tbl order by name";
        }

        $data['available_constituencies'] = $this->DB->MQ($query, "all");

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function add(){
        $validated['model'] = 'youspeak_polls';

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $this->AddJS("/js/youspeak_polls.js");
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function add_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);


        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            $new_id = $executed['common'];
            $id_part = "edit/".$new_id;
            redirect($this->L("polls/".$id_part));
        } else {
            $this->setAnswer(500, "Problem adding the entry.");
        }
    }

    public function edit(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $validated['model'] = 'youspeak_polls';

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = $this->model->get_data($validated['model'], $validated['id']);
        $this->AddJS("/js/youspeak_polls.js");
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function edit_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $redirect_part = ($this->update_redirect=="db_edit") ? "edit/".$validated['id'] : "list";
            redirect($this->L("polls/".$redirect_part));
        }
    }

    public function get_details(){
        $this->checkMethod("GET");
        $this->mapRoute("polls_id");
        $this->checkRequired(["polls_id"], $this->parts);
        $rules = [
            "polls_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        
        $validated = $this->sanitize($this->parts, $rules);

        $data['polls_id'] = $validated['polls_id'];

        $validated['model'] = "youspeak_polls_pros";

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['pros']['model_name'] = $validated['model'];
        $data['pros']['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['pros']['meta_actions'] = $this->model->get_meta_actions($validated['model']);
//        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
        $data['pros']['fields'] = $this->model->get_list_fields($model);
        $data['pros']['page'] = 1;
        $data['pros']['items'] = 0;
        
        if($validated['polls_id']!=0) {
            $data['pros']['data'] = $this->DB->MQ("select * from ". $this->model->get_table_name($validated['model'])." where polls_id=". $validated['polls_id'], "all");
            $data['pros']['items'] = count($data['pros']['data']);
        }

        $validated['model'] = "youspeak_polls_cons";

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['cons']['model_name'] = $validated['model'];
        $data['cons']['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['cons']['meta_actions'] = $this->model->get_meta_actions($validated['model']);
//        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
        $data['cons']['fields'] = $this->model->get_list_fields($model);
        $data['cons']['page'] = 1;
        $data['cons']['items'] = 0;

        if($validated['polls_id']!=0) {
            $data['cons']['data'] = $this->DB->MQ("select * from ". $this->model->get_table_name($validated['model'])." where polls_id=". $validated['polls_id'], "all");
            $data['cons']['items'] = count($data['cons']['data']);
        }

        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function pros(){
        $this->checkMethod("GET");
        $this->mapRoute("polls_id/id");
        $this->checkRequired(["id", "polls_id"], $this->parts);
        $rules = [
            "id" => FILTER_UNSAFE_RAW,
            "polls_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_polls_pros";
        
        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["polls_id"=>$validated['polls_id'], "user_id"=>$_SESSION['user']['id']];

        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function pros_update(){
        $this->checkMethod("POST");
        $rules = [
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        $validated['tablename'] = "youspeak_polls_pros";
        if($validated['id']!=""){
            $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $this->query);
        }

        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            redirect($this->L("polls/edit/".$this->query['polls_id']));
        }
    }

    public function event_delete(){
        $this->checkMethod("GET");
        $this->mapRoute("type/id");
        $this->checkRequired(["type", "id"], $this->parts);
        $rules = [
            "type" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        switch ($validated['type']){
            case "pros":
                $validated['model'] = "youspeak_polls_pros";
                break;
            case "cons":
                $validated['model'] = "youspeak_polls_cons";
                break;
        }
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function cons(){
        $this->checkMethod("GET");
        $this->mapRoute("polls_id/id");
        $this->checkRequired(["id", "polls_id"], $this->parts);
        $rules = [
            "id" => FILTER_UNSAFE_RAW,
            "polls_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_polls_cons";
        
        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["polls_id"=>$validated['polls_id'], "user_id"=>$_SESSION['user']['id']];

        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function cons_update(){
        $this->checkMethod("POST");
        $rules = [
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        $validated['tablename'] = "youspeak_polls_cons";
        if($validated['id']!=""){
            $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $this->query);
        }

        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            redirect($this->L("polls/edit/".$this->query['polls_id']));
        }
    }

}