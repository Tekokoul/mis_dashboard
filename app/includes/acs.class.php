<?php
class ACS extends API
{

    protected $settings;
    protected $file;
    function __construct($settings) {
        $this->settings = $settings;
        $this->file = _CACHE_PATH.'acs_db.txt';
    }

    function prepare_form($order, $settings = []){
        $data['data'] = [
            "name" => grstrtoupper($order['customer']['surname']." ".$order['customer']['firstname']),
            "address" => grstrtoupper($order['shipping_address']['address']),
            "postal_code" => $order['shipping_address']['pobox'],
            "city" => grstrtoupper($order['shipping_address']['city']),
            "telephone" => $order['shipping_address']['telephone'],
            "floor" => grstrtoupper($order['shipping_address']['floor']),
            "country" => grstrtoupper($order['shipping_address']['country']),
            "email" => $order['customer']['email'],
            "comment" => grstrtoupper("ΚΟΥΔΟΥΝΙ: ".display_from_db($order['shipping_address']['bell'])." | ΣΧΟΛΙΑ: ".display_from_db($order['extra_info']['comment'])),
            "order_reference" => $order['reference'],
            "cod" => ($order['extra_info']['payment_method']=="cash_on_delivery") ? $order['extra_info']['payable_cost'] : null
        ];
        return $data;
    }
    function create_voucher($data){

        if($data['cod']!=""){
            $cod_amount = $data['cod'];
            $cod_payment_way = 0;
            $acs_delivery_products = "COD";
        } else {
            $cod_amount = null;
            $cod_payment_way = null;
            $acs_delivery_products = null;
        }

        $payload = [
            "ACSAlias" => "ACS_Create_Voucher",
            "ACSInputParameters" => [
                "Company_ID" => $this->settings['Company_ID'],
                "Company_Password" => $this->settings['Company_Password'],
                "User_ID" => $this->settings['User_ID'],
                "User_Password" => $this->settings['User_Password'],
                "Pickup_Date" => date("Y-m-d"),
                "Sender" => $this->settings['Sender'],
                "Recipient_Name" => $data['name'],
                "Recipient_Address" => $data['address'],
                "Recipient_Address_Number" => null,
                "Recipient_Zipcode" => $data['postal_code'],
                "Recipient_Region" => $data['city'],
                "Recipient_Phone" => $data['telephone'],
                "Recipient_Cell_Phone" => null,
                "Recipient_Floor" => $data['floor'],
                "Recipient_Company_Name" => null,
                "Recipient_Country" => $data['country'],
                "Acs_Station_Destination" => null,
                "Acs_Station_Branch_Destination" => 0,
                "Billing_Code" => $this->settings["Billing_Code_".$data['country']],
                "Charge_Type" => 2,
                "Cost_Center_Code" => null,
                "Item_Quantity" => 1,
                "Weight" => 1,
                "Dimension_X_In_Cm" => null,
                "Dimension_Y_in_Cm" => null,
                "Dimension_Z_in_Cm" => null,
                "Cod_Ammount" => (double)$cod_amount,
                "Cod_Payment_Way" => $cod_payment_way,
                "Acs_Delivery_Products" => $acs_delivery_products,
                "Insurance_Ammount" => null,
                "Delivery_Notes" => $data['comment'],
                "Appointment_Until_Time" => null,
                "Recipient_Email" => $data['email'],
                "Reference_Key1" => $data['order_reference'],
                "Reference_Key2" => null,
                "With_Return_Voucher" => null,
                "Content_Type_ID" => null,
                "Language" => null
            ]
        ];

        $response = $this->call(
            "https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8",
            ["AcsApiKey: ". $this->settings['AcsApiKey']]
        );

//        debug($response);
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            $voucher = $response_data['ACSOutputResponce']['ACSValueOutput'][0]['Voucher_No'];
            return $voucher;
        }
    }

    function print_voucher($data){
        $payload = [
            "ACSAlias" => "ACS_Print_Voucher",
            "ACSInputParameters" => [
                "Company_ID" => $this->settings['Company_ID'],
                "Company_Password" => $this->settings['Company_Password'],
                "User_ID" => $this->settings['User_ID'],
                "User_Password" => $this->settings['User_Password'],
                "Voucher_No" => (string)$data['tracking_number'],
 		        "Print_Type" => 2,
 		        "Start_Position" => 1
            ]
        ];

        $response = $this->call(
            "https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8",
            ["AcsApiKey: ". $this->settings['AcsApiKey']]
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            $pdf = $response_data['ACSOutputResponce']['ACSValueOutput'][0]['ACSObjectOutput'];
            header('Content-type: application/pdf');
            header('Content-Disposition: inline; filename="'.$data.'.pdf"');
            print base64_decode($pdf);
        }
    }

    function print_multiple_voucher($data){
        $response = $this->call(
            "https://acs-eud2.acscourier.net/Eshops/GetVoucher.aspx?MainID=".$this->settings['Company_ID']."&MainPass=".$this->settings['Company_Password']."&UserID=".$this->settings['User_ID']."&UserPass=".$this->settings['User_Password']."&voucherno=".implode("|", $data)."&PrintType=2&StartFromNumber=1"
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            header('Content-type: application/pdf');
            header('Content-Disposition: inline; filename="vouchers.pdf"');
            print $response['data'];
        }
    }

    function delete_voucher($data){
        $payload = [
            "ACSAlias" => "ACS_Delete_Voucher",
            "ACSInputParameters" => [
                "Company_ID" => $this->settings['Company_ID'],
                "Company_Password" => $this->settings['Company_Password'],
                "User_ID" => $this->settings['User_ID'],
                "User_Password" => $this->settings['User_Password'],
                "Voucher_No" => (string)$data['tracking_number'],
                "Language"=> null
            ]
        ];

        $response = $this->call(
            "https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8",
            ["AcsApiKey: ". $this->settings['AcsApiKey']]
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            if(is_null($response_data['ACSOutputResponce']['ACSValueOutput'][0]['Error_Message'])){
                return true;
            } else {
                $this->message($response_data['ACSOutputResponce']['ACSValueOutput'][0]['Error_Message']);
                return false;
            }
        }
    }

    function issue_pickup_list($data){
        $payload = [
            "ACSAlias" => "ACS_Issue_Pickup_List",
            "ACSInputParameters" => [
                "Company_ID" => $this->settings['Company_ID'],
                "Company_Password" => $this->settings['Company_Password'],
                "User_ID" => $this->settings['User_ID'],
                "User_Password" => $this->settings['User_Password'],
                "Pickup_Date" => display_time($data, "Y-m-d"),
                "Language"=> null
            ]
        ];

        $response = $this->call(
            "https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8",
            ["AcsApiKey: ". $this->settings['AcsApiKey']]
        );


        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            if(!is_null($response_data['ACSOutputResponce']['ACSValueOutput'][0]['PickupList_No'])){
                return $response_data['ACSOutputResponce']['ACSValueOutput'][0]['PickupList_No'];
            } else {
                $this->message($response_data['ACSOutputResponce']['ACSValueOutput'][0]['Error_Message']);
                return false;
            }
        }
    }

    function print_pickup_list($data){
        $payload = [
            "ACSAlias" => "ACS_Print_Pickup_List",
            "ACSInputParameters" => [
                "Company_ID" => $this->settings['Company_ID'],
                "Company_Password" => $this->settings['Company_Password'],
                "User_ID" => $this->settings['User_ID'],
                "User_Password" => $this->settings['User_Password'],
                "Language" => "GR",
                "Mass_Number" => $data['list_id'],
                "Pickup_Date" => $data['date']
            ]
        ];

        $response = $this->call(
            "https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest",
            "POST",
            "",
            json_encode($payload),
            "application/json; charset=utf-8",
            ["AcsApiKey: ". $this->settings['AcsApiKey']]
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
        } else {
            $response_data = json_decode($response['data'], true);
            $pdf = $response_data['ACSOutputResponce']['ACSValueOutput'][0]['ACSObjectOutput']['PDFData'];
            header('Content-type: application/pdf');
            header('Content-Disposition: inline; filename="'.$data['list_id'].'.pdf"');
            print base64_decode($pdf);
        }
    }
}