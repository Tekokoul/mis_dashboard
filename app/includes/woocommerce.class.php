<?php
class Woocommerce extends API{

    protected $settings;
    protected $file;
    function __construct($settings){
        $this->settings = $settings;
//        $this->authToken = $this->get_authorization();
        $this->file = _CACHE_PATH.'wc_responses.txt';
    }

    /**
     * List all categories or Retrieve a specific one
     * @id is the id of the category
     *
     * */
    function get_categories($id = null){
        $query = isset($id) ? "/".$id : "";
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/categories".$query,
            "GET",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret'])
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    /**
     * Create a new category
     * @category is the array of the category consisting of name and parent id
     *
     * */
    function create_category($category){
        $data = [
            "name" => $category['name'],
            "parent" => $category['parent']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/categories",
            "POST",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    /**
     * Update a category
     * @category is the array of the category consisting of id, name and parent id
     *
     * */
    function update_category($category){
        $data = [
            "name" => $category['name'],
            "parent" => $category['parent']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/categories/".$category['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    /**
     * Delete a category
     * @id is the id of the category
     *
     * */
    function delete_categories($id){
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/categories/".$id,
            "DELETE",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret'])
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    /**
     * List all products or Retrieve a specific one
     * @id is the id of the category
     * @sku is the sku of the product
     *
     * */
    function get_products($id = null, $sku = null){
        $query = isset($id) ? "/".$id : "";
        $query = isset($sku) ? "?sku=".$sku : $query;
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products".$query,
            "GET",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret'])
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    /**
     * Create a new simple product
     * @product is the array of the product consisting of name, price, description, short_description and categories
     *
     * */
    function create_simple_product($product){
        $data = [
            'name' => $product['name'],
            'type' => 'simple',
            'regular_price' => (string)$product['price'],
            'sku' => $product['sku'],
            'categories' => $product['categories']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products",
            "POST",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_simple_product($product){
        $data = [
            'name' => $product['name'],
            'type' => 'simple',
            'regular_price' => (string)$product['price'],
            'categories' => $product['categories']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/".$product['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function create_variable_product($product){
        $data = [
            'name' => $product['name'],
            'type' => 'variable',
            'sku' => $product['sku'],
            'categories' => $product['categories']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products",
            "POST",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_variable_product($product){
        $data = [
            'name' => $product['name'],
            'type' => 'variable',
            'categories' => $product['categories']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/".$product['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_product_quantity($product){
        $data = [
            'stock_status' => $product['stock_status'],
            "manage_stock" => true,
            "stock_quantity"=> $product['quantity'],
            "status" => $product['status']
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/".$product['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['api_key'].":".$this->settings['api_secret']),
            json_encode($data)
        );
//        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."UPDATE QUANTITY\t".json_to_db($response['data'])."\n", FILE_APPEND | LOCK_EX);
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_product_attribute($product){
        $data = [
            'type' => 'variable',
            'attributes' => [
                [
                    'id' => 2,
                    'position' => 0,
                    'visible' => false,
                    'variation' => true,
                    'options' => $product['options']
                ]
            ]
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/".$product['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function create_product_variation($product){
        $data = [
            "regular_price" => (string)$product['regular_price'],
            "sku" => $product['sku'],
            "manage_stock" => true,
            "stock_quantity"=> $product['quantity'],
            "attributes" => [
                [
                    'id' => $product['option_id'],
                    'option' =>$product['option_name']
                ]
            ]
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v2/products/".$product['product_id']."/variations",
            "POST",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
//        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."CREATE VARIATION\t".$response['data']."\n", FILE_APPEND | LOCK_EX);
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_product_variation($product){
        $data = [
            "regular_price" => (string)$product['regular_price'],
            "manage_stock" => true,
            "stock_quantity"=> $product['quantity']
        ];

//        print $this->settings['api_url']."wp-json/wc/v3/products/".$product['product_id']."/variations/".$product['variation_id']."\n".json_encode($data)."\n";

        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v3/products/".$product['product_id']."/variations/".$product['variation_id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
//        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."UPDATE VARIATION\t".$response['data']."\n", FILE_APPEND | LOCK_EX);
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function create_attribute_term($term){
        $data = [
            'name' => $term['name'],
        ];
        $response = $this->call(
            $this->settings['api_url']."/wp-json/wc/v3/products/attributes/".$term['id']."/terms",
            "POST",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_attribute_term($term){
        $data = [
            'name' => $term['name'],
        ];
        $response = $this->call(
            $this->settings['api_url']."/wp-json/wc/v3/products/attributes/".$term['id']."/terms/".$term['term_id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function update_product_bundle($product){
        $bundled_products = [];
        foreach ($product['bundled_items'] as $item){
            $temp = [
                "product_id" => $item['id'],
                "quantity_min" => $item['quantity'],
                "quantity_max" => $item['quantity'],
                "priced_individually" => false,
                "shipped_individually" => false,
                "single_product_visibility" => "hidden",
                "cart_visibility" => "hidden",
                "order_visibility" => "hidden",
                "optional" => false,
                "title" => $item['name']
            ];
            if($item['variable']){
                $temp["override_default_variation_attributes"] = true;
                $temp["default_variation_attributes"][] = $item['variable_info'];
            }
            if(isset($item['bundled_item_id'])){
                $temp['bundled_item_id'] = $item['bundled_item_id'];
            }
            $bundled_products[] = $temp;
        }
        $data = [
            "type" => "bundle",
            'regular_price' => (string)$product['price'],
            "bundled_items" => $bundled_products
        ];
        $response = $this->call(
            $this->settings['api_url']."wp-json/wc/v1/products/".$product['product_id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
//        file_put_contents($this->file, date("Y-m-d H:i:s")."\t"."UPDATE BUNDLE\t".$response['data']."\n", FILE_APPEND | LOCK_EX);
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data['bundled_items'];
        }
    }

    function update_order($order){
        $data = [
            'status' => $order['status']
        ];
        $response = $this->call(
            $this->settings['api_url']."/wp-json/wc/v3/orders/".$order['id'],
            "PUT",
            "Basic ".base64_encode($this->settings['key'].":".$this->settings['secret']),
            json_encode($data)
        );
        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }

    function add_tracking_number($order){
        $data = [
            "tracking_provider" => (($order['tracking_provider']=="ups") ? "UPS Global" : "DHL Germany"),
            "tracking_number" => $order['tracking_number']
        ];
        $response = $this->call(
            $this->settings['api_url'].'/wp-json/wc-shipment-tracking/v3/orders/'.$order['id'].'/shipment-trackings/',
            "POST",
            "Basic ".base64_encode($this->settings['api_key'].":".$this->settings['api_secret']),
            json_encode($data)
        );

        if ($response['error']) {
            echo "cURL Error #:" . $response['error'];
            return [];
        } else {
            $response_data = json_decode($response['data'], true);
            return $response_data;
        }
    }
}
