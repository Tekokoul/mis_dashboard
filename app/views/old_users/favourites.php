<section id="content" style="margin-top:50px; margin-bottom: 50px">

	<div class="content-wrap">

		<div class="container clearfix">

			<div class="row clearfix">

				<div class="col-md-3 clearfix">

					<div class="list-group">
						<a href="/<?php echo $this->lang ?>/users/profile" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_profile_menu");?> <i class="icon-user float-right"></i></a>
						<a href="/<?php echo $this->lang ?>/users/orders" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_previous_orders_menu");?> <i class="icon-file-invoice float-right"></i></a>
						<a href="/<?php echo $this->lang ?>/users/favourites" class="list-group-item list-group-item-action clearfix active"><?=$this->T("account_favourites_menu");?> <i class="icon-heart float-right"></i></a>
<!--						<a href="/--><?php //echo $this->lang ?><!--/users/billing" class="list-group-item list-group-item-action clearfix">Στοιχεία Τιμολόγησης <i class="icon-credit-cards float-right"></i></a>-->
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
						<span><?=$this->T("account_favourites_menu");?></span>
					</div>

					<div class="clear"></div>
					<div class="row clearfix">
						<div class="col-lg-12">
							<div id="shop" class="shop product-6 grid-container clearfix" data-layout="fitRows">
								<?php
								foreach ($this->R->products as $product) {
									$images = json_decode($product['image']);
									$path="media/products/";
									$filename = $path. '/Image-Not-Available.png';
									if (count($images) > 0) {
										foreach ($images as $file) {
											$filename = $path.$file;
											break;
										}
									}
									$product_link = "/".$this->lang."/product/".$product['id']."/".sluggify($product['title']);
//                                    if ($filename == 'images/loader1.gif') {
//                                        continue;
//                                    }

									echo "<div class='col-md-2 col-sm-6 product clearfix'>
							<div class='product-image'>
								<a href='".$product_link."'><img class='lazy' data-src='".displayimage_resize(200, $filename)."' src='/images/preloader.gif' alt='".$product['title']."'></a>";
								if ($product['active']==0 || $product['quantity'] == 0) {
									echo "<div style='font-size: 10px;' class='sale-flash out-of-stock'>Out of stock</div>";
								}
								echo "<div class='product-overlay'>";
									echo "<a style='width: 50%' href='#' class='add-to-cart' data-sku='".$product['sku']."'><i class='icon-shopping-cart' ></i></a>";
									if ($product['active'] == 1 && $product['weight_ratio'] == 0) {
										echo "<div class='quantity category-quantity'>
                                        <input type='button' value='-' class='minus'>
                                        <input type='text' step='1' min='1' name='quantity' value='1' title='Qty' class='qty' size='4'>
                                        <input type='button' value='+' class='plus'>
                                    </div>";
										echo "</div>
							</div>";
										echo "<div class='product-desc center'>";
										if ($product['active'] == 1 && $product['quantity'] != 0) {
											echo "<div class='product-price'><ins>".$product['price']."&euro;</ins></div>";
										}

								echo "<div class='product-title'><h3><a href='".$product_link."'>".$product['title']."</a></h3></div>
							</div>";

									} else if ($product['active'] == 1 && $product['weight_ratio'] > 0) {
										$priceWeight = $product['price'] / 1000 * $product['weight_ratio'];
										$value = '
                                <select data-price="'.$priceWeight.'" data-ratio="'.$product['weight_ratio'].'" class="product-select favorites-product-select">';
										$i = 1;
										for ($x = $product['weight_ratio']; $x <= $product['quantity'] * 1000; $x+=$product['weight_ratio']) {
											if (((double) $x / 1000) < 1) {
												$visible = $x . 'gr';
											} else {
												$visible = sprintf('%0.2f', ($x / 1000)) . 'kg';
											}
											$value .= "<option data-sku='".$product['sku']."' value='".$i."'>" .$visible. "</option>";
											$i++;
										}
										$value .="</select>";
										echo $value;
										echo "</div>
                                    </div>
                                    ";

									echo "<div class='product-desc center'>";
										if ($product['active'] == 1 && $product['quantity'] != 0) {
											echo "<div class='product-price'><ins class='product-price-ins'>" . $priceWeight . "&euro;</ins></div>";
										}
                                   echo "<div class='product-title'><h3><a href='".$product_link."'>".$product['title']."</a></h3></div>
							</div>";
                                    } else {
										echo "<div class='quantity category-quantity'>
                                        <input type='button' value='-' class='minus'>
                                        <input type='text' step='1' min='1' name='quantity' value='1' title='Qty' class='qty' size='4'>
                                        <input type='button' value='+' class='plus'>
                                    </div>";
										echo "</div>
							</div>";
										echo "<div class='product-desc center'>";
										if ($product['active'] == 1 && $product['quantity'] != 0) {
											echo "<div class='product-price'><ins>".$product['price']."&euro;</ins></div>";
										}

										echo "<div class='product-title'><h3><a href='".$product_link."'>".$product['title']."</a></h3></div>
							</div>";
                                    }

									echo "</div>";
								}	?>
							</div><!-- #shop end -->

						</div>

					</div>

				</div>

			</div>

		</div>

	</div>

</section><!-- #content end -->