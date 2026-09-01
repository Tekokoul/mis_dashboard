<?php
$order_info = $data['data']['order_info'];
if(isset($order_info['extra_info']['invoice'])&&($order_info['extra_info']['invoice']==1)){
    $invoice = true;
    $invoice_text = " - <span style='color: green'>ΤΙΜΟΛΟΓΙΟ</span>";
    $billing_address = $order_info['billing_address'];
    $shipping_address = $order_info['shipping_address'];
} else {
    $invoice = false;
    $invoice_text = "";
    $billing_address = $order_info['shipping_address'];
    $shipping_address = $order_info['shipping_address'];
}
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("orders/list");?>">Order</a> #<?=display($data['data']['order_reference']); ?> Details</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Edit mode</span></li>
            <li><span>Order reference: <?= display($data['data']['id']); ?></span></li>
        </ol>
    </div>
</header>
<form class="order-details action-buttons-fixed" method="post" action="<?=$this->L("orders/update");?>">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <input type="hidden" name="id" value="<?= display($data['data']['id']); ?>" >
    <div class="row mb-4">
        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 mb-4">

            <div class="card card-modern">
                <div class="card-header">
                    <h2 class="card-title">General</h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <?=createOrderStatus("order_status",$data['model']['common']['order_status'], $data['data']['order_status'])?>
                    </div>
                    <div class="form-row">
                        <?=createDateTime("addeddate",$data['model']['common']['addeddate'], $data['data']['addeddate']);?>
                    </div>
                    <div class="form-row">
                        <?=createDropDown("customer_id", $data['model']['common']['customer_id'], $data['data']['customer_id']);?>
                    </div>
                    <div class="form-row">
                        <?=createText("tracking_number", $data['model']['common']['tracking_number'], $data['data']['tracking_number']);?>
                    </div>
                    <div class="form-row">
                        <?=createTextArea("comments", $data['model']['common']['comments'], $data['data']['comments']);?>
                    </div>
                    <div class="form-row">
                        <?=createDropDown("email_status", $data['model']['common']['email_status'], $data['data']['email_status']);?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 mb-4">
            <div class="card card-modern">
                <div class="card-header">
                    <h1 class="card-title">Payment - <?=(($data['data']['payment_status']=="accepted")||($data['data']['payment_status']=="completed")) ? "<span style='color: green'>ΠΛΗΡΩΘΗΚΕ</span>" : "<span style='color: maroon'>ΔΕΝ ΠΛΗΡΩΘΗΚΕ</span>"?></h1>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-xl-auto">
                            <ul class="list list-unstyled list-item-bottom-space-0">
                                <li>Method: <b><?=display_from_db($data['data']['payment_method']??"");?></b></li>
                                <li>Date: <b><?=display_time($data['data']['payment_date']??"");?></b></li>
                                <li>Payment Order: <b><?=display_from_db($data['data']['payment_order']??"");?></b></li>
                                <li>Transaction ID: <b><?=display_from_db($data['data']['payment_transaction']??"");?></b></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-header">
                    <h1 class="card-title">Shipping Info <?= ($data['data']['shipping_method']=='pickup') ? "- <span style='color: green'>ΠΑΡΑΛΑΒΗ ΑΠΟ ΚΑΤΑΣΤΗΜΑ</span>" : ""?></h1>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-xl-auto">
                            <ul class="list list-unstyled list-item-bottom-space-0">
                                <li>Bell: <b><?=display_from_db($shipping_address['bell']??"");?></b></li>
                                <li>Floor: <b><?=display_from_db($shipping_address['floor']??"");?></b></li>
                                <li>Preferred Date: <b><?= display_from_db($order_info['extra_info']['delivery_date'] ?? "");?></b></li>
                                <li>Weight: <b><?=display_weight($order_info['extra_info']['weight_of_items']??"");?></b></li>
                                <li>Notes: <b><?=display_from_db($order_info['extra_info']['comment']??"");?></b></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 mb-4">
            <div class="card card-modern">
                <div class="card-header">
                    <h2 class="card-title">Addresses</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-12 me-xl-5 pe-xl-5 mb-4">
                            <h3 class="font-weight-bold text-color-dark text-4 line-height-1 mt-0 mb-3">SHIPPING</h3>
                            <ul class="list list-unstyled list-item-bottom-space-0">
                                <li><?=display_from_db($shipping_address['surname']??"")." ".display_from_db($shipping_address['firstname']??"");?></li>
                                <li><?=display_from_db($shipping_address['address']??"");?></li>
                                <li><?=display_from_db($shipping_address['city']??"");?></li>
                                <li><?=display_from_db($shipping_address['pobox']??"");?></li>
                                <li><?=display_from_db($shipping_address['country']??"");?></li>
                            </ul>
                            <strong class="d-block text-color-dark">Email address:</strong>
                            <a href="mailto:<?=$order_info['customer']['email'];?>"><?=$order_info['customer']['email'];?></a>
                            <strong class="d-block text-color-dark mt-3">Phone:</strong>
                            <a href="tel:<?=$shipping_address['telephone'];?>" class="text-color-dark"><?=$shipping_address['telephone'];?></a>
                        </div>
                        <div class="col-xl-12">
                            <h3 class="text-color-dark font-weight-bold text-4 line-height-1 mt-0 mb-3">BILLING<?=$invoice_text;?></h3>
                            <ul class="list list-unstyled list-item-bottom-space-0">
                                <?php
                                if($invoice){
                                    ?>
                                    <li><?=$billing_address['companyname'];?></li>
                                    <li>VAT: <?=$billing_address['vat'];?></li>
                                    <li><?=$billing_address['vatoffice'];?></li>
                                    <li><?=$billing_address['job'];?></li>
                                    <?php
                                }
                                ?>
                                <li><?=$billing_address['surname']." ".$billing_address['firstname'];?></li>
                                <li><?=$billing_address['address'];?></li>
                                <li><?=$billing_address['city'];?></li>
                                <li><?=$billing_address['pobox'];?></li>
                                <li><?=$billing_address['country'];?></li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>
    <div class="row">
        <div class="col">

            <div class="card card-modern">
                <div class="card-header">
                    <h2 class="card-title">Products</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-ecommerce-simple table-ecommerce-simple-border-bottom table-borderless table-striped mb-0" style="min-width: 380px;">
                            <thead>
                            <tr>
                                <th width="10%" class="ps-4">SKU</th>
                                <th width="40%">Name</th>
                                <th width="15%">Brand</th>
                                <th width="10%" class="text-end">Price</th>
                                <th width="10%" class="text-end">Qty</th>
                                <th width="5%" class="text-end">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($order_info['products'] as $product) {
                                if($product['id']!=""){
                                $link = "core/db_edit/site_store_products/".$product['id'];
                                $weight_ratio = isset($product['weight_ratio']) ? $product['weight_ratio'] : 0;
                                if ($weight_ratio == 0) {
                                    $view_price =(intval($product['quantity']) * $product['price']);
                                    $view_quantity =$product['quantity'];
                                } else {
                                    $price = $product['price'] / 1000 * $product['weight_ratio'];
                                    $view_price = round($product['quantity'] * $price, 2);
                                    if ($product['quantity'] * $product['weight_ratio'] / 1000 < 1) {
                                        $view_quantity = $product['quantity'] * $product['weight_ratio'] . ' gr';
                                    } else {
                                        $view_quantity = sprintf('%0.2f', ($product['quantity'] * $product['weight_ratio'] / 1000)) . ' kg';
                                    }
                                }
                            ?>
                            <tr>
                                <td class="ps-4"><a href="<?=$this->L($link);?>"><strong><?=$product[$_SESSION['user']['settings']['eshop_id']??"ean"]?></strong></a></td>
                                <td><a href="<?=$this->L($link);?>"><strong><?=$product['title']?></strong></a></td>
                                <td class=""><?=display_from_db($product['brand'] ?? "");?></td>
                                <?php
                                if($product['price']<$product['retail_price']){
                                    ?>
                                    <td class="text-end"><del><?=display_price_currency($product['retail_price']);?></del>&nbsp;<?=display_price_currency($product['price']);?></td>
                                        <?php
                                } else {
                                ?>
                                <td class="text-end"><?=display_price_currency($product['price']);?></td>
                                    <?php
                                }
                                    ?>
                                <td class="text-end"><?=$view_quantity;?></td>
                                <td class="text-end"><?=display_price_currency($view_price);?></td>
                            </tr>
                            <?php
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row justify-content-end flex-column flex-lg-row my-3">
                        <div class="col-auto me-5">
                            <h3 class="font-weight-bold text-color-dark text-4 mb-3">Products Subtotal</h3>
                            <span class="d-flex align-items-center">
                                <?=count($order_info['products']);?> Products
                                <i class="fas fa-chevron-right text-color-primary px-3"></i>
                                <b class="text-color-dark text-xxs"><?=display_price_currency($order_info['extra_info']['cost_of_items']);?></b>
                            </span>
                        </div>
                        <div class="col-auto me-5">
                            <h3 class="font-weight-bold text-color-dark text-4 mb-3">Shipping</h3>
                            <span class="d-flex align-items-center">
                                Shipping & Handling
                                <i class="fas fa-chevron-right text-color-primary px-3"></i>
                                <b class="text-color-dark text-xxs"><?=display_price_currency(($order_info['extra_info']['shipping_cost']+$order_info['extra_info']['handling_cost']));?></b>
                            </span>
                        </div>
                        <?php
                        if($order_info['extra_info']['promo_discount']>0){
                            ?>
                            <div class="col-auto me-5">
                                <h3 class="font-weight-bold text-color-dark text-4 mb-3">Discount</h3>
                                <span class="d-flex align-items-center">
                                <?=display($order_info['extra_info']['promo_code']);?>
                                <i class="fas fa-chevron-right text-color-primary px-3"></i>
                                <b class="text-color-dark text-xxs"><?=display_price_currency($order_info['extra_info']['promo_discount']);?></b>
                            </span>
                            </div>
                        <?php
                        }
                        ?>
                        <div class="col-auto">
                            <h3 class="font-weight-bold text-color-dark text-4 mb-3">Order Total</h3>
                            <span class="d-flex align-items-center justify-content-lg-end">
                                <strong class="text-color-dark text-5"><?=display_price_currency($order_info['extra_info']['payable_cost']);?></strong>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <div class="row">
        <div class="col">

            <div class="card card-modern">
                <div class="card-header">
                    <h2 class="card-title">Order Notes</h2>
                </div>
                <div class="card-body">
                    <div class="ecommerce-timeline mb-3">
                        <div class="ecommerce-timeline-items-wrapper">
                            <div class="ecommerce-timeline-item">
                                <p><b>Session Info</b><br>
                                    IP Address: <?=display_from_db($order_info['extra_info']['user_ip']??"");?><br>
                                    User System: <?=display_from_db($order_info['extra_info']['user_system']??"");?></p>
                            </div>
                            <?php
                            if(isset($data['data']['logs'])){
                                foreach ($data['data']['logs'] as $log_entry) {
                                ?>
                           <div class="ecommerce-timeline-item">
                               <small><?=display_time($log_entry['event_time']);?></small>
                               <p><b><?=$log_entry['title'];?></b><br><?=$log_entry['message'];?></p>
                            </div>
                                <?php
                                }
                            }
                            ?>
<!--                            <div class="ecommerce-timeline-item">-->
<!--                                <small>added on June 26, 2020 at 4:01 pm by admin - <a href="#" class="text-color-danger">Delete note</a></small>-->
<!--                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas hendrerit augue at leo viverra, aliquam egestas lectus laoreet. Donec vehicula vestibulum ipsum, tincidunt ultrices elit suscipit ac. Sed eget risus laoreet, varius nibh id, luctus ligula. Nulla facilisi</p>-->
<!--                            </div>-->
                        </div>
                    </div>
<!--                    <div class="form-row">-->
<!--                        <div class="form-group col pb-1 mb-3">-->
<!--                            <label>Add Note</label>-->
<!--                            <textarea class="form-control form-control-modern" name="orderAddNote" rows="6"></textarea>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                    <div class="form-row">-->
<!--                        <div class="form-group col">-->
<!--                            <a href="#" class="cancel-button btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3">Add Note</a>-->
<!--                        </div>-->
<!--                    </div>-->
                </div>
            </div>

        </div>
    </div>
    <div class="row action-buttons">
        <div class="col-12 col-md-auto">
            <?php
            if(isset($data['meta_actions'])){
                foreach ($data['meta_actions'] as $action) {
                    $show_action = false;

                    if (isset($action['condition'])) {
                        if (ce_compare_values($data['data'][$action['condition']['field']], $action['condition']['operator'], $action['condition']['value'])) {
                            $show_action = true;
                        }
                    } else {
                        $show_action = true;
                    }
                    if ($show_action) {
                        print '<a href="' . $this->model->dynamic_link($action['link'], $data['data']) . '" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" target="' . $action['target'] . '">
                <i class="bx ' . $action['icon'] . ' text-4 me-2"></i> ' . $action['title'] . '
            </a>';
                    }
                }
            }
            ?>

            <!--            <a href="#" class="delete-button btn btn-danger btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1">-->
            <!--                <i class="bx bx-trash text-4 me-2"></i> Delete Product-->
            <!--            </a>-->
        </div>

        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-save text-4 me-2"></i> Update
            </button>
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?=$_SERVER['HTTP_REFERER'];?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Back</a>
        </div>
    </div>
</form>
<!-- end: page -->