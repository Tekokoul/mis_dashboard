<?php
require_once _CONTROLLERS_PATH."core.php";

class constituenciesController extends coreController
{
    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;
        $validated['model'] = 'youspeak_constituencies';
        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);

        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        $filters[] = "AND ".$filter['key']."='".($this->query[$filter['key']] ?? "")."'";
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

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function add(){
        $this->checkMethod("GET");
        $validated['model'] = 'youspeak_constituencies';

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
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
            $id_part = ($this->update_redirect=="db_edit") ? "/".$executed['common'] : "";
            redirect($this->L("constituencies/edit".$id_part));
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

        $data['model_name'] = "youspeak_constituencies";
        $data["model"] = $this->model->get_table_fields("youspeak_constituencies");
        $data['meta_name'] = $this->model->get_meta_name("youspeak_constituencies");
        $data['meta_actions'] = $this->model->get_meta_actions("youspeak_constituencies");
        $data['data'] = $this->model->get_data("youspeak_constituencies", $validated['id']);
        $this->AddJS("/js/youspeak.js");
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

        $additional_tables = $this->query['additional_tables'];
        unset($this->query['additional_tables']);

        $previous = $this->DB->MQ("select * from ".$this->model->get_table_name($validated['tablename'])." where id=".$validated['id'], "one");
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $new_id = $validated['id'];
            $id_part = "edit/".$new_id;
            redirect($this->L("constituencies/".$id_part));
        }
    }

    public function ward(){
        $this->checkMethod("GET");
        $this->mapRoute("constituencies_id/id");
        $this->checkRequired(["constituencies_id","id"], $this->parts);
        $rules = [
            "constituencies_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_wards";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["constituencies_id"=>$validated['constituencies_id'], "user_id"=>$_SESSION['user']['id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function ward_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        if($validated['id']!=""){
            $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $this->query);
        }

        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            redirect($this->L("constituencies/edit/".$this->query['constituencies_id']));
        }
    }

    public function ward_delete(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_wards";
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function get_details(){
        $this->checkMethod("GET");
        $this->mapRoute("constituencies_id");
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "constituencies_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = 'youspeak_wards';
        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['model_name'] = $validated['model'];
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
//        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
        $data['fields'] = $this->model->get_list_fields($model);
        $data['constituencies_id'] = $validated['constituencies_id'];

        if($validated['constituencies_id']!=0) {
            $data['data'] = $this->DB->MQ("select * from ". $this->model->get_table_name($validated['model'])." where constituencies_id=". $validated['constituencies_id'], "all");
        }
        $this->prepare_edit_mode();
        $this->partial_render($data);
    }
}