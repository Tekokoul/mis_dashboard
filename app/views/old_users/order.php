<?php
$users = $this->R->users;
?>
<section id="content"  style="margin-top:50px; margin-bottom: 50px">

    <div class="content-wrap">

        <div class="container clearfix">

            <div class="row clearfix">

                <div class="col-md-3 clearfix">

                    <div class="list-group">
                        <a href="/<?php echo $this->lang ?>/users/profile" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_profile_menu");?> <i class="icon-user float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/orders" class="list-group-item list-group-item-action clearfix active"><?=$this->T("account_previous_orders_menu");?> <i class="icon-file-invoice float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/favourites" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_favourites_menu");?> <i class="icon-heart float-right"></i></a>
                        <!--                        <a href="/--><?php //echo $this->lang ?><!--/users/billing" class="list-group-item list-group-item-action clearfix">Στοιχεία Τιμολόγησης <i class="icon-credit-cards float-right"></i></a>-->
                        <a href="/<?php echo $this->lang ?>/users/address" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_addresses_menu");?> <i class="icon-home float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/companies" class="list-group-item list-group-item-action clearfix "><?=$this->T("account_companies_menu");?> <i class="icon-building float-right"></i></a>

                        <a href="/<?php echo $this->lang ?>/users/password" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_change_password_menu");?> <i class="icon-key float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/logout" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_logout_menu");?> <i class="icon-line2-logout float-right"></i></a>
                    </div>
                </div>

                <div class="w-100 line d-block d-md-none"></div>

                <div class="col-md-9">
                    <img src="/images/icons/avatar.jpg" class="alignleft img-circle img-thumbnail notopmargin nobottommargin" alt="Avatar" style="max-width: 84px;">

                    <div class="heading-block noborder">
                        <h3><?= $_SESSION['user']['surname'] ?> <?= $_SESSION['user']['firstname'] ?></h3>
                        <span><?=$this->T("account_previous_orders_menu");?> - <?= $this->R->content['order']['order_reference'] ?></span>
                    </div>

                    <div class="clear"></div>

                    <div class="row clearfix">
                        <div class="col-lg-12">
                            <h4 style="text-align: center; margin-top: 30px;"><?= $users['languages'][$this->lang]['order_basket']; ?></h4>
                            <div class="table-responsive">
                                <table class="table cart">
                                    <thead>
                                    <tr>
                                        <th class="cart-product-thumbnail">&nbsp</th>
                                        <th class="cart-product-name"><?= $users['languages'][$this->lang]['order_product_name']; ?></th>
                                        <th class="cart-product-quantity"><?= $users['languages'][$this->lang]['order_product_capacity']; ?></th>
                                        <th class="cart-product-quantity"><?= $users['languages'][$this->lang]['order_product_price']; ?></th>
                                        <th class="cart-product-subtotal"><?= $users['languages'][$this->lang]['order_product_sum']; ?></th>
                                        <th class="cart-product-subtotal"><?= $users['languages'][$this->lang]['order_product_price_now']; ?></th>
                                        <th class="cart-product-subtotal"><?= $users['languages'][$this->lang]['order_product_add']; ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>

									<?php
									$price = 0;

									foreach ($this->R->content['orderProducts'] as $cart) {
										$value = '';
										if ($cart['active'] == 1 && $cart['quantity'] > 0 && $cart['weight_ratio'] == 0) {
											$value ="<td><div class='product-overlay'><a style='width: 50%' href='#'";
											$value .= $cart['quantity'] == 0 ? 'disabled' : '';
											$value .= "class='add-to-cart' data-sku='".$cart['sku']."'><i class='icon-shopping-cart' ></i></a>";
											$value .= "<div class='quantity category-quantity'>
                                            <input type='button' value='-' class='minus'";
											$value .= $cart['quantity'] == 0 ? 'disabled' : '';
											$value .= ">
                                            <input type='text' step='1' min='1' max='".$cart['quantity']."' name='quantity' value='";
											$value .= $cart['quantity'] == 0 ? '0' : '1';
											$value .="' title='Qty' class='qty' size='4'";
											$value .= $cart['quantity'] == 0 ? 'disabled' : '';
											$value .= ">
                                            <input type='button' value='+' class='plus'";
											$value .= $cart['quantity'] == 0 ? 'disabled' : '';
											$value .= "></div>
                                                </div>
                                                </td>";
											$price += number_format($cart['price'] * $cart['qtyCart'],2);
										}

										if ($cart['active'] == 1 && $cart['quantity'] > 0 && $cart['weight_ratio'] > 0) {
											$priceWeight = $cart['price'] / 1000 * $cart['weight_ratio'];
											$price += number_format($priceWeight * $cart['qtyCart'],2);
											$value .="<td><div class='product-overlay'><a style='width: 50%' href='#'";
											$value .= $cart['quantity'] == 0 ? 'disabled' : '';
											$value .= "class='add-to-cart' data-sku='".$cart['sku']."'><i class='icon-shopping-cart' ></i></a>";
									$value .= '
                                <select data-price="'.$cart['price'].'" data-ratio="'.$cart['weight_ratio'].'" class="product-select">';
                                    $i = 1;
                                    for ($x = $cart['weight_ratio']; $x <= $cart['quantity'] * 1000; $x+=$cart['weight_ratio']) {
										if (((double) $x / 1000) < 1) {
											$visible = $x . 'gr';
										} else {
											$visible = sprintf('%0.2f', ($x / 1000)) . 'kg';
										}
                                        $value .= "<option data-sku='".$cart['sku']."' value='".$i."'>" .$visible. "</option>";
                                    $i++;
                                    }
                                    $value .="</select>";
											$value .= "</div></td>";
                                        }

										if ($cart['active']==0 || $cart['quantity'] == 0) {
											$value .= "<td><div style='font-size: 10px;' class='out-of-stock'>Out of stock</div></td>";
										}


										$product_link = "/".$this->lang."/product/".$cart['id']."/".sluggify($cart['title']);
										echo '<tr class="cart_item">
								<td class="cart-product-thumbnail">
									<a href="'.$product_link.'"><img width="64" height="64" src="'.$cart['image'].'" alt="'.$cart['title'].'"></a>
								</td>

								<td class="cart-product-name">
									<a href="'.$product_link.'">'.$cart['title'].'</a>
								</td>';
									if ($cart['quantity'] >= 0 && $cart['weight_ratio'] == 0) {
										echo '<td class="cart-product-quantity">
									<div class="quantity clearfix">
										1x' . $cart['qtyCart'] . '
									</div>
								</td>';
										echo '<td class="cart-product-quantity">
									<div class="quantity clearfix">
										' . $cart['price'] . '
									</div>
								</td>';

										echo '<td class="">
									<span class="amount">' . $cart['price'] * $cart['qtyCart'] . '&euro;</span>
								</td>';

										echo '<td class="">
									<span class="amount">' . $cart['salePrice'] . '&euro;</span>
								</td>';
									} else {
										echo '<td class="cart-product-quantity">
									<div class="quantity clearfix">
										' . $cart['qtyCart'] * $cart['weight_ratio']. 'gr
									</div>
								</td>';
										echo '<td class="cart-product-quantity">
									<div class="quantity clearfix">
										' . number_format($priceWeight,2) . '
									</div>
								</td>';

										echo '<td class="">
									<span class="amount">' . number_format($priceWeight * $cart['qtyCart'],2). '&euro;</span>
								</td>';

										echo '<td class="">
									<span class="amount">' . number_format($cart['salePrice'] / 1000 * $cart['weight_ratio'],2) . '&euro;</span>
								</td>';
                                    }
								echo $value;
							echo '
								</tr>';

									}
									?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="clear"></div>
                            <p style="margin-top: 30px"><?= $users['languages'][$this->lang]['order_again']; ?></p>
                            <a style="float:right" href="/<?= $this->lang?>/users/reorder/<?= $this->R->content['order']['order_reference'] ?>" class="button button-large button-rounded"><?= $users['languages'][$this->lang]['order_again_button']; ?></a>
                            <div class="clear"></div>
                            <h4 style="text-align: center; margin-top: 30px;"><?= $users['languages'][$this->lang]['specific_order_status']; ?></h4>
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th><?= $users['languages'][$this->lang]['order_status']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['order_payment']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['order_shipping']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['order_shipping_number']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['order_date']; ?></th>
                                </tr>
                                </thead>
                                <tbody>

								<?php
								    $status = '';
                                    $orserStatus = $this->R->content['order']['order_status'];
                                    if ($orserStatus == 1) {
                                        $status = $users['languages'][$this->lang]['order_status_pending'];
                                    } else if ($orserStatus == 2) {
                                        $status = $users['languages'][$this->lang]['order_status_pending'];
                                    } else if ($orserStatus == 3) {
                                        $status = $users['languages'][$this->lang]['order_status_reject'];
                                    } else if ($orserStatus == 4) {
                                        $status = $users['languages'][$this->lang]['order_status_success'];
                                    } else {
                                        $status = $users['languages'][$this->lang]['order_status_pending'];
                                    }
								?>

                                <tr>
                                    <td><?= $status; ?></td>
                                    <td><?= $this->R->content['order']['payment_method'] == "cash_on_delivery" ? $users['languages'][$this->lang]['order_payment_method_cach_on_delivery'] : $users['languages'][$this->lang]['order_payment_method_viva']; ?></td>
                                    <td><?= $this->R->content['order']['shipping_method']?></td>
                                    <td><?= $this->R->content['order']['tracking_number']?></td>
                                    <td><?= $this->R->content['order']['addeddate']?></td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="clear"></div>

                            <h4 style="text-align: center; margin-top: 30px;"><?= $users['languages'][$this->lang]['order_price']; ?></h4>
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th><?= $users['languages'][$this->lang]['specific_order_price']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_cost']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_extra_charges']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_discount_price']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_total_price']; ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td><?= $this->R->content['order']['product_amount']?></td>
                                    <td><?= $this->R->content['order']['shipping_amount']?></td>
                                    <td><?= $this->R->content['order']['extras_amount']?></td>
                                    <td><?= $this->R->content['order']['discount_amount']?></td>
                                    <td><?= $this->R->content['order']['total_amount']?></td>
                                </tr>
                                </tbody>
                            </table>

                            <div class="clear"></div>
                            <h4 style="text-align: center; margin-top: 30px;"><?= $users['languages'][$this->lang]['specific_order_shipping_details']; ?></h4>
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_details_name']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_details_address']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_details_phone']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_details_roof_bell']; ?></th>
                                    <th><?= $users['languages'][$this->lang]['specific_order_shipping_details_date']; ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td><?= $this->R->content['shipping']['surname'] .' '. $this->R->content['shipping']['firstname']?></td>
                                    <td><?= $this->R->content['shipping']['address']. ' '. $this->R->content['shipping']['country'] . ' '. $this->R->content['shipping']['pobox']?></td>
                                    <td><?= $this->R->content['shipping']['telephone']?></td>
                                    <td><?= $this->R->content['shipping']['floor']?> - <?= $this->R->content['shipping']['bell']?></td>
                                    <td><?= $this->R->content['delivery_date']?></td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="clear"></div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

</section><!-- #content end -->