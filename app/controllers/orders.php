<?php
require_once _CONTROLLERS_PATH."core.php";
class ordersController extends coreController{

    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $page = $validated['page'] ?? 1;
        $items_per_page = (isset($_SESSION['user']['settings']['table_rows'])) ? $_SESSION['user']['settings']['table_rows'] : _PAGINATION;

        $table_name = "site_store_orders";

        $model['model_name'] = $table_name;
        $model["model"] = $this->model->get_table_fields($table_name);
        $data['meta_name'] = $this->model->get_meta_name($table_name);
        $data['meta_actions'] = $this->model->get_meta_actions($table_name);
        $data['meta_filters'] = $this->model->get_meta_filters($table_name);

        $data['model_name'] = $table_name;
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

    public function edit(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $table_name = "site_store_orders";

        $data['model_name'] = $table_name;
        $data["model"] = $this->model->get_table_fields($table_name);
        $data['meta_name'] = $this->model->get_meta_name($table_name);
        $data['meta_actions'] = $this->model->get_meta_actions($table_name);
        $data['data'] = $this->model->get_data($table_name, 0, ["key"=>"order_reference", "value"=>$validated['id']]);
        $data['data']['order_info'] = $this->model->json_from_db($data['data']['order_info']);
        $query = "SELECT * FROM store_orders_log_tbl WHERE order_reference = '".$validated['id']."'";
        $data['data']['logs'] = $this->DB->MQ($query, "all");
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function update(){
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
            $order = $this->model->get_data($validated['tablename'],$validated['id']);
            redirect($this->L("orders/edit/".$order['order_reference']));
        }
    }

    public function remove(){

    }

    public function cancel(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
    }

    public function order_is_ready(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $table_name = "site_store_orders";

        $order = $this->model->get_data($table_name, $validated['id']);
        if(is_set($order)){
            $order_info = json_from_db($order['order_info']);
            $recipients = [$order_info['customer']['email']];
            $fields['order_reference'] = $order['order_reference'];

            $this->_sendEmail("order_is_ready", $recipients, $fields);
        }
        redirect($this->L("orders/edit/".$order['order_reference']));
    }

}