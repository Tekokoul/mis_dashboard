<?php

class wc24{
    protected $R;
    protected $S;
    protected $DB;
    public function __construct(Registry $registry)
    {
        $this->R = $registry;
        $this->S = $this->R->settings;
        $this->DB = $this->R->{$this->R->defaultDB};
    }

    public function handle_webhook($data){
        $query = "select id from store_channels_tbl where handle='".$data['system']."'";
        $system_id = $this->DB->MQ($query, "one")['id'];

        $customer = [
            "id" => 0,
            "surname" => $data['billing']['last_name'],
            "firstname" => $data['billing']['first_name'],
            "email" => $data['billing']['email'],
            "newsletter" => 0
        ];
        $shipping_address = [
            "surname" => $data['shipping']['last_name'],
            "firstname" => $data['shipping']['first_name'],
            "address" => $data['shipping']['address_1']." ".$data['shipping']['address_2'],
            "city" => $data['shipping']['city'],
            "pobox" => $data['shipping']['postcode'],
            "country" => $data['shipping']['country'],
            "telephone" => $data['shipping']['phone'] == "" ? $data['billing']['phone'] : $data['shipping']['phone'],
            "floor" => "",
            "bell" => "",
            "extra_info" => "",
            "company" => $data['shipping']['company']??""
        ];
        $billing_address = [
            "surname" => $data['billing']['last_name'],
            "firstname" => $data['billing']['first_name'],
            "address" => $data['billing']['address_1']." ".$data['billing']['address_2'],
            "city" => $data['billing']['city'],
            "pobox" => $data['billing']['postcode'],
            "country" => $data['billing']['country'],
            "telephone" => $data['billing']['phone'],
            "floor" => "",
            "bell" => "",
            "extra_info" => "",
            "company" => $data['billing']['company']??""
        ];
        $total_price = 0;
        $weight = 0;
        $total_items = 0;
        $products = [];

        if(in_array($data['shipping']['country'], explode(',', $this->S['tokada']['dhl_countries']))) {
            $shipping_method = "dhl";
        } else {
            $shipping_method = "ups";
        }

        $handling_cost = 0;
        $promo_code = "";
        $promo_discount = 0;
        foreach ($data['fee_lines'] as $fee_line) {
            if((double)$fee_line['total']>0){
                $handling_cost = (double)$fee_line['total'];
                $promo_code = "";
                $promo_discount = (double)0;
            } else {
                $handling_cost = (double)0;
                $promo_code = $fee_line['name'];
                $promo_discount = abs((double)$fee_line['total']);
            }
        }

        foreach ($data['line_items'] as $line_item){
            $total_price += number_format($line_item['price'] * $line_item['quantity'],2);
            $weight += ($line_item['quantity']*0);
            $total_items = $total_items + $line_item['quantity'];

            $name_parts = explode(" - ", $line_item['name']);

            $product = [
                "id" => $line_item['product_id'],
                "sku" => $line_item['sku'],
                "title" => $line_item['name'],
                "ean" => $line_item['sku'],
                "weight" => 0,
                "price" => $line_item['price'],
                "retail_price" => $line_item['price'],
                "quantity" => $line_item['quantity'],
                "brand" => $name_parts[0],
                "weight_ratio" => 0
            ];

            $products[] = $product;
        }

        $order = [
            "reference" => $data['system']."-".$data['id'],
            "customer" => $customer,
            "shipping_address" => $shipping_address,
            "billing_address" => $billing_address,
            "products" => $products,
            "extra_info" => [
                "num_of_items" => $total_items,
                "cost_of_items" => $total_price,
                "shipping_cost" => 0,
                "handling_cost" => $handling_cost,
                "promo_discount" => $promo_discount,
                "payable_cost" => (double)($total_price+$handling_cost-$promo_discount),
                "promo_code" => $promo_code,
                "delivery_date" => '0000-00-00',
                "comment" => $data['customer_note'],
                "promo_percentage" => 0,
                "weight_of_items" => $weight,
                "invoice" => 0,
                "currency" => "EUR",
                "language_id" => 1,
                "language" => "en",
                "payment_method" => $data['payment_method'],
                "shipping_method" => $shipping_method,
                "user_system" => $_SERVER['HTTP_USER_AGENT'],
                "user_ip" => $_SERVER['REMOTE_ADDR']
            ]
        ];

        $order_query = "INSERT INTO `site_store_orders_tbl`
(`order_reference`,
`customer_id`,
`customer_name`,
`shipping_address`,
`billing_address`,
`product_amount`,
`shipping_amount`,
`extras_amount`,
`discount_amount`,
`total_amount`,
`order_info`,
`payment_method`,
`shipping_method`,
`payment_currency`,
`payment_status`,
`addeddate`,
`order_status`,
`email_status`,
 `channel`)
VALUES ('"
            .$order['reference']."', 0 ,'".$customer['firstname']." ".$customer['surname']."',0,0,'".$total_price."',0,'".$handling_cost."','".$promo_discount."','"
            .$order['extra_info']['payable_cost']."','".json_to_db($order)."','".$order['extra_info']['payment_method']."','".$order['extra_info']['shipping_method']."','".$order['extra_info']['currency']."','initialized','".date("Y-m-d H:i:s")."',2,0,".$system_id.")";

        $add_order = $this->DB->MQ($order_query, "last");
        return (isset($add_order));
    }

    function woo_update($data){
        $WC = new Woocommerce($this->S['woo_hosts']['r24']);
        $wc_ids = json_from_db(stripslashes($data['wc_ids'])) ?? [];
        $status = $data['active'] ? "publish" : "draft" ;
        if(isset($wc_ids['r24'])){
            $qty = $data['quantity']+$data['quantity_2'];
            $product = [
                "id" => $wc_ids['r24'],
                "stock_status" => (( $qty>0 ) ? "instock" : "outofstock"),
                "quantity" => $qty,
                "status" => $status
            ];
            $WC->update_product_quantity($product);
        }
    }

    function on_create_voucher($voucher){
        $reference = explode("-", $voucher['order_reference']);
        $query = "select * from site_store_orders_tbl where order_reference='".$voucher['order_reference']."'";
        $order = $this->DB->MQ($query, "one");
        $data = [
            "id" => $reference[1],
            "tracking_provider" =>$order['shipping_method'],
            "tracking_number" => $voucher['tracking_number']
        ];
        
        $WC = new Woocommerce($this->S['woo_hosts'][$reference[0]]);
        $WC->add_tracking_number($data);
    }
}