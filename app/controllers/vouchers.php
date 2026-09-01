<?php
require_once _CONTROLLERS_PATH."core.php";
class vouchersController extends coreController{
    public function index(){
        $query = "select * from store_vouchers_tbl where listed=false and tracking_number!='' order by tracking_number_date desc";
        $data['data'] = $this->DB->MQ($query, "all");
        $this->render($data);
    }
    public function create(){
        $this->checkMethod("GET");
        $this->mapRoute("courier/order_reference");
        $this->checkRequired(["courier", "order_reference"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW,
            "order_reference" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $order_row = $this->DB->MQ("select * from site_store_orders_tbl where order_reference='".$validated['order_reference']."'", "one");
        if(!$this->S[$validated['courier']]['multiple_vouchers']){
            if((!is_null($order_row['tracking_number']))&&(strlen($order_row['tracking_number'])>0)){
                redirect($this->L("orders/edit/".$order_row["order_reference"]));
            }
        }
        $data['model_name'] = "store_vouchers";
        $data["model"] = $this->model->get_table_fields("store_vouchers");
        $order = json_decode($order_row['order_info'], true);
        $data['order_id'] =$order_row['id'];
        $data['courier'] = $validated['courier'];
        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $data = array_merge($data, $courier->prepare_form($order, $this->S));
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function create_update(){
        $this->checkMethod("POST");
        $this->checkRequired(["courier", "name", "address", "postal_code", "city", "telephone", "floor", "country", "email", "comment", "order_reference", "cod", "company"], $this->query);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW,
            "name" => FILTER_UNSAFE_RAW,
            "address" => FILTER_UNSAFE_RAW,
            "address_number" => FILTER_UNSAFE_RAW,
            "postal_code" => FILTER_UNSAFE_RAW,
            "city" => FILTER_UNSAFE_RAW,
            "telephone" => FILTER_UNSAFE_RAW,
            "floor" => FILTER_UNSAFE_RAW,
            "country" => FILTER_UNSAFE_RAW,
            "email" => FILTER_SANITIZE_EMAIL,
            "comment" => FILTER_UNSAFE_RAW,
            "order_reference" => FILTER_UNSAFE_RAW,
            "cod" => FILTER_UNSAFE_RAW,
            "company" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        $order = $this->DB->MQ("select * from site_store_orders_tbl where order_reference='".$validated['order_reference']."'", "one");
        if(!$this->S[$validated['courier']]['multiple_vouchers']){
            if((!is_null($order['tracking_number']))&&(strlen($order['tracking_number'])>0)){
                redirect($this->L("orders/edit/" . $validated['order_reference']));
            }
        }
        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $got_voucher = $courier->create_voucher($validated);
        //    $got_voucher = "2875623847";
        if($got_voucher!="Error"){
            $trnd = date("Y-m-d H:i:s");
            $query = "INSERT INTO store_orders_log_tbl ( `order_reference`, `event_time`, `event_trigger`, `title`, `message`) VALUES ( '".$validated['order_reference']."', '".date("Y-m-d H:i:s")."', 'vouchers', 'Voucher created', 'A voucher with number ".$got_voucher." was created')";
            $this->DB->MQ($query);
            if($this->S[$validated['courier']]['multiple_vouchers']){
                $query = "UPDATE `site_store_orders_tbl` SET `tracking_number` = 'Created' WHERE `order_reference` = '".$validated['order_reference']."';";
            } else {
                $query = "UPDATE `site_store_orders_tbl` SET `tracking_number` = '".$got_voucher."', `tracking_number_date` = '".$trnd."' WHERE `order_reference` = '".$validated['order_reference']."';";
            }
            $this->DB->MQ($query);
            $tr_data = $validated;
            $tr_data['tablename'] = "store_vouchers";
            $tr_data['courier_name'] = $validated['courier'];
            $tr_data['tracking_number'] = $got_voucher;
            $tr_data['tracking_number_date'] = $trnd;
            $executed = $this->model->add_data("store_vouchers", $tr_data);

            if($this->S[$validated['courier']]['send_email']){
                $order_details = json_from_db($order['order_info']);
                $recipients = [$order_details['customer']['email']];
                $fields['order_reference'] = $validated['order_reference'];
                $fields['tracking_number'] = $got_voucher;
                $this->_sendEmail("voucher", $recipients, $fields);
            }
        } else {
            $query = "INSERT INTO store_orders_log_tbl ( `order_reference`, `event_time`, `event_trigger`, `title`, `message`) VALUES ( '".$validated['order_reference']."', '".date("Y-m-d H:i:s")."', 'vouchers', 'Voucher error', '".$_SESSION['message']."')";
            $this->DB->MQ($query);
        }
        redirect($this->L("orders/edit/" . $validated['order_reference']));
    }

    public function delete(){
        $this->checkMethod("GET");
        $this->mapRoute("courier/voucher_id");
        $this->checkRequired(["courier", "voucher_id"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW,
            "voucher_id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $query = "select * from store_vouchers_tbl where tracking_number = '".$validated['voucher_id']."'";
        $tracking_row = $this->DB->MQ($query, "one");

        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $data = [
            "tracking_number" => $validated['voucher_id'],
            "country" => $tracking_row['country']
        ];
        if($courier->delete_voucher($data)) {
            $query = "update site_store_orders_tbl set tracking_number=null, tracking_number_date=null where order_reference='".$tracking_row['order_reference']."'";
            $this->DB->MQ($query);
            $query = "delete from store_vouchers_tbl where tracking_number = '".$validated['voucher_id']."'";
            $this->DB->MQ($query);
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['voucher_id']."</b>", [], "json");
        } else {
            $this->setAnswer(500, "Problem deleting entry", ["error" => $_SESSION['message']], "json");
        }
    }

    public function reset(){
        $this->checkMethod("GET");
        $this->mapRoute("courier/order_reference");
        $this->checkRequired(["courier", "order_reference"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW,
            "order_reference" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $query = "select * from store_vouchers_tbl where order_reference = '".$validated['order_reference']."'";
        $tracking_rows = $this->DB->MQ($query, "all");
        $courier = new $validated['courier']($this->S[$validated['courier']]);
        
        foreach ($tracking_rows as $tracking_row) {
            $data = [
                "tracking_number" => $tracking_row['tracking_number'],
                "country" => $tracking_row['country']
            ];
            $courier->delete_voucher($data);
            $query = "delete from store_vouchers_tbl where tracking_number = '".$tracking_row['tracking_number']."'";
            $this->DB->MQ($query);
            $query = "INSERT INTO store_orders_log_tbl ( `order_reference`, `event_time`, `event_trigger`, `title`, `message`) VALUES ( '".$validated['order_reference']."', '".date("Y-m-d H:i:s")."', 'vouchers', 'Voucher deleted', 'Thr voucher with number ".$tracking_row['tracking_number']." was deleted')";
        }
        
        $query = "update site_store_orders_tbl set tracking_number=null, tracking_number_date=null where order_reference='".$validated['order_reference']."'";
        $this->DB->MQ($query);

        redirect($this->L("orders/edit/" . $validated['order_reference']));       
    }

    public function print(){
        $this->checkMethod("GET");
        $this->mapRoute("courier/voucher_id");
        $this->checkRequired(["courier", "voucher_id"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW,
            "voucher_id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $query = "select * from store_vouchers_tbl where tracking_number = '".$validated['voucher_id']."'";
        $tracking_row = $this->DB->MQ($query, "one");

        $query = "update store_vouchers_tbl set printed=true, printed_date='".date("Y-m-d H:i:")."' where tracking_number='".$validated['voucher_id']."'";
        $this->DB->MQ($query);
        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $data = [
            "tracking_number" => $validated['voucher_id'],
            "country" => $tracking_row['country']
        ];
        $courier->print_voucher($data);
    }

    public function print_all(){
        $this->checkMethod("GET");
        $this->mapRoute("courier");
        $this->checkRequired(["courier"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $query = "select tracking_number from store_vouchers_tbl where listed=false and tracking_number!=''";
        $vouchers = $this->DB->MQ($query,"all");
        $tr_numbers = [];
        foreach ($vouchers as $voucher){
            $tr_numbers[] = $voucher['tracking_number'];
            $query = "update store_vouchers_tbl set printed=true, printed_date='".date("Y-m-d H:i:")."' where tracking_number='".$voucher['tracking_number']."'";
            $this->DB->MQ($query);
        }
        $courier->print_multiple_voucher($tr_numbers);
    }

    public function finalize_list(){
        $this->checkMethod("GET");
        $this->mapRoute("courier");
        $this->checkRequired(["courier"], $this->parts);
        $rules = [
            "courier" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $query = "select * from store_vouchers_tbl where printed=0 and tracking_number!=''";
        $unprinted = $this->DB->MQ($query, "all");
        if(count($unprinted)>0){
            redirect($this->L("vouchers"));
        }
        $courier = new $validated['courier']($this->S[$validated['courier']]);
        $list_id = $courier->issue_pickup_list(date("Y-m-d"));
        if(is_null($list_id)){
            redirect($this->L("vouchers"));
        }
        $query = "update store_vouchers_tbl set listed=1, listed_date='".date("Y-m-d H:i:s")."', listed_number='".$list_id."' where listed=false";
        $this->DB->MQ($query);
        $data['list_id'] = $list_id;
        $data['date'] = date("Y-m-d");
        $courier->print_pickup_list($data);
    }
}