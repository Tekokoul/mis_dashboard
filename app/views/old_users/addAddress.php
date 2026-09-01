<?php
$users = $this->R->users;
?>
<section id="content" style="margin-top:50px; margin-bottom: 50px">

	<div class="content-wrap">

		<div class="container clearfix">

			<div class="row clearfix">

				<div class="col-md-3 clearfix">

					<div class="list-group">
                        <a href="/<?php echo $this->lang ?>/users/profile" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_profile_menu");?> <i class="icon-user float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/orders" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_previous_orders_menu");?> <i class="icon-file-invoice float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/favourites" class="list-group-item list-group-item-action clearfix "><?=$this->T("account_favourites_menu");?> <i class="icon-heart float-right"></i></a>
                        <!--						<a href="/--><?php //echo $this->lang ?><!--/users/billing" class="list-group-item list-group-item-action clearfix">Στοιχεία Τιμολόγησης <i class="icon-credit-cards float-right"></i></a>-->
                        <a href="/<?php echo $this->lang ?>/users/address" class="list-group-item list-group-item-action clearfix active"><?=$this->T("account_addresses_menu");?> <i class="icon-home float-right"></i></a>
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
						<span><?= $users['languages'][$this->lang]['address_add_new']; ?></span>
					</div>

					<div class="clear"></div>
					<div class="row clearfix">
						<div class="col-lg-12">
							<form class="billing-info-create" class="nobottommargin" action="/<?=$this->lang?>/users/createBilling" method="post">
								<div class="col_half">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_name']; ?></label>
									<input type="text" id="register-form-surname" name="surname" value="" class="form-control" />
								</div>

								<div class="col_half col_last">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_last_name']; ?></label>
									<input type="text" id="register-form-firstname" name="firstname" value="" class="form-control" />
								</div>

								<div class="clear"></div>
								<div class="col_half">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_address']; ?></label>
									<input type="text" id="register-form-address" name="address" value="" class="form-control" />
								</div>

								<div class="col_half col_last">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_area']; ?></label>
									<input type="text" id="register-form-state" name="state" value="" class="form-control" />
								</div>

								<div class="clear"></div>
								<div class="col_half">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_city']; ?></label>
									<input type="text" id="register-form-city" name="city" value="" class="form-control" />
								</div>

								<div class="col_half col_last">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_zip']; ?></label>
									<input type="text" id="register-form-pobox" name="pobox" value="" class="form-control" />
								</div>

								<div class="clear"></div>
								<div class="col_half">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_country']; ?></label>
									<select id="register-form-country" name="country" class="form-control" style="width:100%;">
										<? $country = "getCountries_" .$this->lang;
										foreach ($country() as $key => $count) {
											echo '<option value="'.$key.'">'.$count.'</option>';
										} ?>
										</select>
								</div>

								<div class="col_half col_last">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_phone']; ?></label>
									<input type="text" id="register-form-telephone" name="telephone" value="" class="form-control" />
								</div>

								<div class="clear"></div>
								<div class="col_half">
									<label for="register-form-name"><?= $users['languages'][$this->lang]['register_mobile']; ?></label>
									<input type="text" id="register-form-mobilephone" name="mobilephone" value="" class="form-control" />
								</div>

								<div class="col_half col_last">
									<label for="register-form-floor"><?= $users['languages'][$this->lang]['address_roof']; ?></label>
									<input type="text" id="register-form-floor" name="floor" value="" class="form-control" />
								</div>

								<div class="clear"></div>
								<div class="col_half">
									<label for="register-form-bell"><?= $users['languages'][$this->lang]['address_bell']; ?></label>
									<input type="text" id="register-form-bell" name="bell" value="" class="form-control" />
								</div>
								<div class="clear"></div>
								<div class="col_half">
									<label class="mb-3"><?= $users['languages'][$this->lang]['address_is_shipping']; ?></label><br>
									<div class="btn-group btn-group-toggle" data-toggle="buttons">
										<label for="is-shipping" class="btn btn-outline-secondary px-3 t600 ls0 nott active">
											<input type="radio" name="is-shipping" id="is-shipping-yes" class="required valid" checked value="1"> <?= $users['languages'][$this->lang]['yes']; ?>
										</label>
										<label for="is-shipping" class="btn btn-outline-secondary px-3 t600 ls0 nott">
											<input type="radio" name="is-shipping" id="is-shipping-no" class="required valid" value="0"> <?= $users['languages'][$this->lang]['no']; ?>
										</label>
									</div>
								</div>
								<div class="col_half col_last">
									<label class="mb-3"><?= $users['languages'][$this->lang]['address_is_payment']; ?></label><br>
									<div class="btn-group btn-group-toggle" data-toggle="buttons">
										<label for="is-billing" class="btn btn-outline-secondary px-3 t600 ls0 nott active">
											<input type="radio" name="is-billing" id="is-billing-yes" class="required valid" checked value="1"> <?= $users['languages'][$this->lang]['yes']; ?>
										</label>
										<label for="is-billing" class="btn btn-outline-secondary px-3 t600 ls0 nott">
											<input type="radio" name="is-billing" id="is-billing-no" class="required valid" value="0"> <?= $users['languages'][$this->lang]['no']; ?>
										</label>
									</div>
								</div>
								<div class="clear"></div>
								<div class="col_full nobottommargin">
									<button class="button button-3d button-black nomargin" id="register-form-submit" name="register-form-submit" value="register"><?= $users['languages'][$this->lang]['address_save_button']; ?></button>
								</div>
							</form>

						</div>

					</div>

				</div>

			</div>

		</div>

	</div>

</section><!-- #content end -->