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
                        <a href="/<?php echo $this->lang ?>/users/address" class="list-group-item list-group-item-action clearfix "><?=$this->T("account_addresses_menu");?> <i class="icon-home float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/companies" class="list-group-item list-group-item-action clearfix active"><?=$this->T("account_companies_menu");?> <i class="icon-building float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/password" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_change_password_menu");?> <i class="icon-key float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/logout" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_logout_menu");?> <i class="icon-line2-logout float-right"></i></a>
					</div>
				</div>

				<div class="w-100 line d-block d-md-none"></div>

				<div class="col-md-9">
					<img src="/images/icons/avatar.jpg" class="alignleft img-circle img-thumbnail notopmargin nobottommargin" alt="Avatar" style="max-width: 84px;">

					<div class="heading-block noborder">
						<h3><?= $_SESSION['user']['surname'] ?> <?= $_SESSION['user']['firstname'] ?></h3>
						<span>Στοιχεία Εταιρείας</span>
					</div>

					<div class="clear"></div>
					<div class="row clearfix">

						<div class="col-lg-12">
							<?php
							$collapse = 'show';
							$i = 0 ;
							foreach ($this->R->content as $company) {
								//if ($i >= 1) {
								$collapse = '';
								//}
								echo '<div class="card">
                                    <div class="card-header" id="heading'.$i.'">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse'.$i.'" aria-expanded="true" aria-controls="collapse'.$i.'">
                                                '.$company['company_name'].'
                                            </button>
                                        </h2>
                                    </div>

                                    <div id="collapse'.$i.'" class="'.$collapse.'" aria-labelledby="heading'.$i.'" data-parent="#accordionExample">
                                        <div class="card-body">
                                            <form class="company-info" name="register-form-'.$i.'" class="nobottommargin" action="/'.$this->lang.'/users/updateCompany" method="post">
                                                <div class="col_half">
                                                    <label for="register-form-company_name">'.$users['languages'][$this->lang]['companies_name'].'</label>
                                                    <input type="text" id="register-form-company_name" name="company_name" value="'.$company['company_name'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                     <label for="register-form-job">'.$users['languages'][$this->lang]['companies_occupation'].'</label>
                                                    <input type="text" id="register-form-job" name="job" value="'.$company['job'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-vat">'.$users['languages'][$this->lang]['companies_vat'].'</label>
                                                    <input type="text" id="register-form-vat" name="vat" value="'.$company['vat'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-vat_office">'.$users['languages'][$this->lang]['companies_vat_office'].'</label>
                                                    <input type="text" id="register-form-vat_office" name="vat_office" value="'.$company['vat_office'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['companies_address'].'</label>
                                                    <input type="text" id="register-form-address" name="address" value="'.$company['address'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['companies_phone'].'</label>
                                                    <input type="text" id="register-form-telephone" name="telephone" value="'.$company['telephone'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['companies_country'].'</label>
                                                    <select id="register-form-country" name="country" class="form-control" style="width:100%;">';

                                                        $country = "getAllCountries_" .$this->lang;
                                                        foreach ($country() as $key => $count) {
                                                            if ($company["country"] == $key) {
                                                                echo '<option value="'.$key.'" selected>'.$count.'</option>';
                                                            } else {
                                                                echo '<option value="'.$key.'">'.$count.'</option>';
                                                            }
                                                        }
                                $checked = $company['default'] == '1' ? 'checked' : '';
								echo '</select>
                                                </div>
                
                                                <div class="col_half col_last">
                                                   <input name="default" class="form-check-input" type="checkbox" '.$checked.' value="" id="defaultCheck2">
                                                    <label class="form-check-label" for="defaultCheck2">
                                                        '.$users['languages'][$this->lang]['companies_default'].'</a>
                                                    </label>
                                                </div>
                
                                                <div class="clear"></div>
                                                               
                                                <div class="col_half col_last" style="display:none;">
                                                    <label for="register-form-name">id:</label>
                                                    <input type="text" id="register-form-name" name="id" value="'.$company['id'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_full nobottommargin">
                                                <button class="button button-3d button-black nomargin billing-form-submit" for="register-form-'.$i.'" id="register-form-submit-'.$i.'">'.$users['languages'][$this->lang]['companies_save_data'].'</button>
                                            </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>';
								$i++;
							}
							?>

						</div>

					</div>

				</div>

			</div>

		</div>

	</div>

</section><!-- #content end -->