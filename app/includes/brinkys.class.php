<?php

class Brinkys
{
    public function __construct(Registry $registry)
    {
        $this->R = $registry;
        $this->S = $this->R->settings;
        $this->DB = $this->R->{$this->R->defaultDB};
    }

    function update_price_rates($data){
        $query = "";
        $excluded_query = "";

        if(!is_array($data['scope_id'])){
            $data['scope_id']= explode(",", $data['scope_id']);
        }

        if(($data['active']==1)&&($data['start_date']<=date("Y-m-d"))&&($data['end_date']>=date("Y-m-d"))){
            switch ($data['scope']){
                case 1:
                    $cat_match = [];
                    foreach ($data['scope_id'] as $sid){
                        $cat_match[] = "FIND_IN_SET(".$sid.", categories) > 0";
                    }
                    $query = "update site_store_products_tbl set price=((100-".$data['rate'].")*retail_price/100) where (".implode(" OR ", $cat_match).") and active=1 and quantity>0";
                    break;
                case 2:
                    $brand_match = [];
                    foreach ($data['scope_id'] as $sid){
                        $brand_match[] = "brand='".$sid."'";
                    }
                    $query = "update site_store_products_tbl set price=((100-".$data['rate'].")*retail_price/100) where (".implode(" OR ", $brand_match).") and active=1 and quantity>0";
                    break;
                case 3:
                    $query = "update site_store_products_tbl set price=((100-".$data['rate'].")*retail_price/100) where id in (".implode(",", $data['scope_id']).") and active=1 and quantity>0";
                    break;
            }
            if(isset($data['excluded_categories'])){
                foreach ($data['excluded_categories'] as $sid){
                    $cat_exc[] = "FIND_IN_SET(".$sid.", categories) = 0";
                    $excluded_query = " AND (".implode(" AND ", $cat_exc).")";
                }
            }
            if(isset($data['excluded_products'])){
                $excluded_query = " AND (id not in (".implode(",", $data['excluded_products'])."))";
            }
        } else {
            switch ($data['scope']){
                case 1:
                    $cat_match = [];
                    foreach ($data['scope_id'] as $sid){
                        $cat_match[] = "FIND_IN_SET(".$sid.", categories) > 0";
                    }
                    $query = "update site_store_products_tbl set price=retail_price where ".implode(" OR ", $cat_match)." and active=1 and quantity>0";
                    break;
                case 2:
                    $brand_match = [];
                    foreach ($data['scope_id'] as $sid){
                        $brand_match[] = "brand='".$sid."'";
                    }
                    $query = "update site_store_products_tbl set price=retail_price where ".implode(" OR ", $brand_match)." and active=1 and quantity>0";
                    break;
                case 3:
                    $query = "update site_store_products_tbl set price=retail_price where id in (".implode(",", $data['scope_id']).") and active=1 and quantity>0";
                    break;
            }
        }
        $this->DB->MQ($query.$excluded_query);
        return true;
    }

    function send_tracking_email($data){
        $query = "select * from site_store_orders_tbl where id=".$data['id'];
        $order = $this->DB->MQ($query, "one");

        if((!is_null($order['tracking_number']))&&($order['tracking_email_status']!=1)){
            $order_details = json_from_db($order['order_info']);
            $recipients = $order_details['customer']['email'];
            $fields['order_reference'] = $order_details['reference'];
            $fields['tracking_number'] = $order['tracking_number'];
            $answer = $this->_sendEmail("voucher", $recipients, $fields);
            if($answer['sent']){
                $query = "update site_store_orders_tbl set tracking_email_status=1 where id=".$data['id'];
                $this->DB->MQ($query);
            }
        }
    }

    protected function _sendEmail($email_template, $recipients, $fields, $attachment = ""){
        $email_texts = readJSONFile(_JSON_MODELS_PATH."data.email.json", _DEFAULT_LANGUAGE);
        $mailService = new Mail($this->S['mail']);
        $email['subject'] = $email_texts[$email_template.'_title'];
        $email['from'] = $email_texts['from_email'];
        $email['from_name'] = $email_texts['from_name'];
        $email['recipients'] = $recipients;
        $email['bcc_email'] = $email_texts['bcc_email'];
        $email['parameters']['preview_text'] = $email_texts[$email_template.'_preview_text'];
        $email['parameters']['text'] = $email_texts[$email_template.'_text'];
        $email['parameters']['footer'] = "<img width='150' src='"._PROJECT_URL."/".$email_texts['logo']."'>";
        //parameters from now on are per case------------------
        foreach ($fields as $field => $value){
            $email['parameters'][$field] =  $value;
        }
        if($attachment!=""){
            $email['attachment'] = $attachment;
        }
//        debug($email);
        return $mailService->sendMail($email);
    }
}