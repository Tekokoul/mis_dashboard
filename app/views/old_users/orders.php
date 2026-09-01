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
                        <span><?=$this->T("account_previous_orders_menu");?></span>
                    </div>

                    <div class="clear"></div>

                    <table class="table table-striped table-responsive">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th><?= $users['languages'][$this->lang]['order_number']; ?></th>
                            <th><?= $users['languages'][$this->lang]['order_status']; ?></th>
                            <th><?= $users['languages'][$this->lang]['order_payment']; ?></th>
                            <th><?= $users['languages'][$this->lang]['order_price']; ?></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						$i = 1;
						foreach ($this->R->content as $order) {
                            $orderNumber = $order['order_reference'];
							$status = '';
							if ($order['order_status'] == 1) {
								$status = $users['languages'][$this->lang]['order_status_pending'];
							} else if ($order['order_status'] == 2) {
								$status = $users['languages'][$this->lang]['order_status_pending'];
							} else if ($order['order_status'] == 3) {
								$status = $users['languages'][$this->lang]['order_status_reject'];
							} else if ($order['order_status'] == 4) {
								$status = $users['languages'][$this->lang]['order_status_success'];
							} else {
								$status = $users['languages'][$this->lang]['order_status_pending'];
							}

							if ($order['payment_method'] == 'cash_on_delivery') {
								$payment = $users['languages'][$this->lang]['order_payment_method_cach_on_delivery'];
							} else {
								$payment = $users['languages'][$this->lang]['order_payment_method_viva'];
							}

							$price = $order['total_amount'];
							echo "<tr>
                                    <td>$i</td>
                                    <td><a href='/$this->lang/users/order/$orderNumber'>$orderNumber</a></td>
                                    <td>$status</td>
                                    <td>$payment</td>
                                    <td>$price&euro;</td>
                                </tr>";
							$i++;
						}
						?>

                        </tbody>
                    </table>

                </div>

            </div>

        </div>

    </div>

</section><!-- #content end -->