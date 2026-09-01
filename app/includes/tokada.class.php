<?php
class Tokada extends API
{

    protected $settings;
    protected $file;
    function __construct($settings) {
        $this->settings = $settings;
        $this->file = _CACHE_PATH.'tokada_db.txt';
    }

    function get_errors($data) {
        $errors = [];
        if (is_array($data)) {
            foreach ($data as $error) {
                $errors[] = $error['code'] . " - " . $error['message'];
            }
        }
        return implode("<br>", $errors);
    }

    function prepare_form($order, $settings){
        // require_once _INCLUDES_PATH."loqate.class.php";
        // $lct = new Locate($settings['loqate']);
        // $address = $lct->verify_address([
        //     "address" => $order['shipping_address']['address'],
        //     "city" => $order['shipping_address']['city'],
        //     "pobox" => $order['shipping_address']['pobox'],
        //     "country" => $order['shipping_address']['country']
        // ]);
        // if($address!==false){
        //     $displayed_address = [
        //         "address" => grstrtoupper($address['ThoroughfareName']),
        //         "address_number" => $address['PremiseNumber'],
        //         "postal_code" => $address['PostalCode'],
        //         "city" => grstrtoupper($address['Locality']),
        //         "country" => grstrtoupper($address['ISO3166-2'])
        //     ];
        // } else {
            $displayed_address = [
                "address" => grstrtoupper($order['shipping_address']['address']),
                "address_number" => "",
                "postal_code" => $order['shipping_address']['pobox'],
                "city" => grstrtoupper($order['shipping_address']['city']),
                "country" => grstrtoupper($order['shipping_address']['country'])
            ];
        // }
        $data['data'] = [
            "name" => grstrtoupper($order['customer']['surname']." ".$order['customer']['firstname']),
            "company" => grstrtoupper($order['shipping_address']['company']),
            "address" => grstrtoupper($displayed_address['address']),
            "address_number" => grstrtoupper($displayed_address['address_number']),
            "postal_code" => grstrtoupper($displayed_address['postal_code']),
            "city" => grstrtoupper($displayed_address['city']),
            "telephone" => $order['shipping_address']['telephone']??$order['billing_address']['telephone'],
            "floor" => grstrtoupper($order['shipping_address']['floor']),
            "country" => grstrtoupper($displayed_address['country']),
            "email" => $order['customer']['email'],
            "comment" => grstrtoupper("COMMENT: ".display_from_db($order['extra_info']['comment'])),
            "order_reference" => $order['reference'],
            "cod" => (($order['extra_info']['payment_method']=="cash_on_delivery")||($order['extra_info']['payment_method']=="cod")) ? $order['extra_info']['payable_cost'] : null
        ];
        return $data;
    }

    function create_voucher($data){
        if(in_array($data['country'], explode(',', $this->settings['dhl_countries']))){
            $url = "https://services.tokada.de/api/DHLShipping/CreateLabel";
        } else {
            $url = "https://services.tokada.de/api/UpsShipping/CreateLabel";
        }

        $payload = [
            "Shipper_ID" => $this->settings['sender_id'],
            "Database_ID" => -1,
            "Portal" => "website",
            "Receiver_FirstName" => explode(" ", $data['name'])[0],
            "Receiver_LastName" => explode(" ", $data['name'])[1],
            "Receiver_Company" => ($data['company']!="") ? $data['company'] : $data['name'],
            "Receiver_Street" => $data['address'],
            "Receiver_StreetNumber" => $data['address_number'],
            "Receiver_Zip" => $data['postal_code'],
            "Receiver_City" => $data['city'],
            "Receiver_CountryCode" => $data['country'],
            "Receiver_EMail" => $data['email'],
            "Receiver_Telephone" => $data['telephone'],
            "COD_Amount" => $data['cod'],
            "COD_Note1" => $data['order_reference'],
            "COD_Note2" => "",
            "Weight" => "1",
            "DaysToHandover" => "1",
            "OrderNo" => $data['order_reference'],
            "InvoiceNo" => $data['order_reference'],
            "JSVersion" => 95
        ];

        $response = $this->call(
            $url,
            "POST",
            "",
            json_encode($payload)
        );

        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."NEW VOUCHER\t".json_encode($response)."\n", FILE_APPEND | LOCK_EX);

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            $voucher = $response_data['d']['Value'];
            if ($voucher == "Error") {
                $errors = json_from_db($response_data['d']['Error']);
                $this->message($this->get_errors($errors['response']['errors']));
            }
            return $voucher;
        }
    }

    function print_voucher($data){
        if(in_array($data['country'], explode(',', $this->settings['dhl_countries']))){
            $url = "https://services.tokada.de/api/DHLShipping/ReprintLabel";
        } else {
            $url = "https://services.tokada.de/api/UpsShipping/ReprintLabel";
        }

        $payload = [
            "Shipper_ID" => $this->settings['sender_id'],
            "TrackingNo" => $data['tracking_number'],
            "JSVersion" => 95
        ];

        $response = $this->call(
            $url,
            "POST",
            "",
            json_encode($payload)
        );

        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."REPRINT VOUCHER\t".json_encode($response)."\n", FILE_APPEND | LOCK_EX);

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return false;
        } else {
            $response_data = json_decode($response['data'], true);
            $voucher = $response_data['d']['Value'];
            return $voucher;
        }
    }

    function delete_voucher($data){
        if(in_array($data['country'], explode(',', $this->settings['dhl_countries']))){
            $url = "https://services.tokada.de/api/DHLShipping/CancelLabel";
        } else {
            $url = "https://services.tokada.de/api/UpsShipping/CancelLabel";
        }

        $payload = [
            "Shipper_ID" => $this->settings['sender_id'],
            "TrackingNo" => $data['tracking_number'],
            "JSVersion" => 95
        ];

        $response = $this->call(
            $url,
            "POST",
            "",
            json_encode($payload)
        );

        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."CANCEL VOUCHER\t".json_encode($response)."\n", FILE_APPEND | LOCK_EX);

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return false;
        } else {
            $response_data = json_decode($response['data'], true);
            $voucher = $response_data['d']['Value'];
            if($voucher=="Error"){
                $errors = json_from_db($response_data['d']['Error']);
                $this->message($this->get_errors($errors['response']['errors']));
            }
            return $voucher;
        }
    }
}