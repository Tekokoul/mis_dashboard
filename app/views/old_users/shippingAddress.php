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
                        <span><?= $users['languages'][$this->lang]['address_shipping_title']; ?></span>
                    </div>

                    <div class="clear"></div>
                    <div class="row clearfix">

                        <div class="col-lg-12">
							<?php
							$collapse = 'show';
							$i = 0 ;
							foreach ($this->R->content as $address) {
								//if ($i >= 1) {
								$collapse = '';
								//}
								echo '<div class="card">
                                    <div class="card-header" id="heading'.$i.'">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse'.$i.'" aria-expanded="true" aria-controls="collapse'.$i.'">
                                                '.$address['address'].' '. $address['pobox'] .'
                                            </button>
                                        </h2>
                                    </div>

                                    <div id="collapse'.$i.'" class="'.$collapse.'" aria-labelledby="heading'.$i.'" data-parent="#accordionExample">
                                        <div class="card-body">
                                            <form class="billing-info" name="register-form" class="nobottommargin" action="/'.$this->lang.'/users/updateAddress" method="post">
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_last_name'].'</label>
                                                    <input type="text" id="register-form-surname" name="surname" value="'.$address['surname'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_name'].'</label>
                                                    <input type="text" id="register-form-firstname" name="firstname" value="'.$address['firstname'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_address'].'</label>
                                                    <input type="text" id="register-form-address" name="address" value="'.$address['address'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_area'].'</label>
                                                    <input type="text" id="register-form-state" name="state" value="'.$address['state'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_city'].'</label>
                                                    <input type="text" id="register-form-city" name="city" value="'.$address['city'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_zip'].'</label>
                                                    <input type="text" id="register-form-pobox" name="pobox" value="'.$address['pobox'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_country'].'</label>
                                                    <select id="register-form-country" name="country" class="form-control" style="width:100%;">';

								$country = "getCountries_" .$this->lang;
								foreach ($country() as $key => $count) {
									if ($address['country'] == $key) {
										echo '<option value="'.$key.'" selected>'.$count.'</option>';
									} else {
										echo '<option value="'.$key.'">'.$count.'</option>';
									}
								}

								echo '</select>
                                                </div>
                
                                                <div class="col_half col_last">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_phone'].'</label>
                                                    <input type="text" id="register-form-telephone" name="telephone" value="'.$address['telephone'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-name">'.$users['languages'][$this->lang]['register_mobile'].'</label>
                                                    <input type="text" id="register-form-mobilephone" name="mobilephone" value="'.$address['mobilephone'].'" class="form-control" />
                                                </div>
                                                
                                                <div class="col_half col_last">
                                                    <label for="register-form-floor">'.$users['languages'][$this->lang]['address_roof'].'</label>
                                                    <input type="text" id="register-form-floor" name="floor" value="'.$address['floor'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_half">
                                                    <label for="register-form-bell">'.$users['languages'][$this->lang]['address_bell'].'</label>
                                                    <input type="text" id="register-form-bell" name="bell" value="'.$address['bell'].'" class="form-control" />
                                                </div>
                
                                                <div class="col_half col_last" style="display:none;">
                                                    <label for="register-form-name">id:</label>
                                                    <input type="text" id="register-form-name" name="id" value="'.$address['id'].'" class="form-control" />
                                                </div>
                
                                                <div class="clear"></div>
                                                <div class="col_full nobottommargin">
                                                <button class="button button-3d button-black nomargin" id="register-form-submit" name="register-form-submit" value="register">'.$users['languages'][$this->lang]['address_save_data'].'</button>
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