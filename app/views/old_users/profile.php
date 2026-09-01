<section id="content" style="margin-top:50px; margin-bottom: 50px">
    <div class="content-wrap">

        <div class="container clearfix">

            <div class="row clearfix">

                <div class="col-md-3 clearfix">

                    <div class="list-group">
                        <a href="/<?php echo $this->lang ?>/users/profile" class="list-group-item list-group-item-action clearfix active"><?=$this->T("account_profile_menu");?> <i class="icon-user float-right"></i></a>
                        <a href="/<?php echo $this->lang ?>/users/orders" class="list-group-item list-group-item-action clearfix"><?=$this->T("account_previous_orders_menu");?> <i class="icon-file-invoice float-right"></i></a>
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
                        <span><?=$this->T("account_profile_menu");?></span>
                    </div>

                    <div class="clear"></div>

                    <div class="row clearfix">

                        <div class="col-lg-12">
                            <form id="register-form" name="register-form" class="nobottommargin" action="/<?=$this->lang;?>/users/updateProfile" method="post">
                                <div class="col_half">
                                    <label for="register-form-surname"><?=$this->T("account_surname_field");?></label>
                                    <input type="text" id="register-form-surname" name="surname" value="<?= $_SESSION['user']['surname'] ?>" class="form-control" />
                                </div>

                                <div class="col_half col_last">
                                    <label for="register-form-firstname"><?=$this->T("account_name_field");?></label>
                                    <input type="text" id="register-form-firstname" name="firstname" value="<?= $_SESSION['user']['firstname'] ?>" class="form-control" />
                                </div>

                                <div class="clear"></div>
                                <div class="col_half">
                                    <label for="register-form-email"><?=$this->T("account_email_field");?></label>
                                    <input type="text" id="register-form-email" name="email" value="<?= $_SESSION['user']['email'] ?>" class="form-control" disabled />
                                </div>

<!--                                <div class="clear"></div>-->
<!--                                <h4>Εάν επιθυμείτε τιμολόγιο, συμπληρώστε τα παρακάτω στοιχεία.</h4>-->
<!--                                <div class="col_half">-->
<!--                                    <label for="register-form-company_name">Όνομα Εταιρείας:</label>-->
<!--                                    <input type="text" id="register-form-company_name" name="company_name" value="--><?//= $_SESSION['user']['company_name'] ?><!--" class="form-control" />-->
<!--                                </div>-->
<!---->
<!--                                <div class="col_half col_last">-->
<!--                                    <label for="register-form-vat">ΑΦΜ:</label>-->
<!--                                    <input type="text" id="register-form-vat" name="vat" value="--><?//= $_SESSION['user']['vat'] ?><!--" class="form-control" />-->
<!--                                </div>-->
<!---->
<!--                                <div class="clear"></div>-->
<!--                                <div class="col_half">-->
<!--                                    <label for="register-form-vatoffice">ΔΟΥ:</label>-->
<!--                                    <input type="text" id="register-form-vatoffice" name="vatoffice" value="--><?//= $_SESSION['user']['vatoffice'] ?><!--" class="form-control" />-->
<!--                                </div>-->
<!--                                <div class="col_half col_last">-->
<!--                                    <label for="register-form-job">Επαγγελμα:</label>-->
<!--                                    <input type="text" id="register-form-job" name="job" value="--><?//= $_SESSION['user']['job'] ?><!--" class="form-control" />-->
<!--                                </div>-->
<!--                                <div class="clear"></div>-->
<!--                                <div class="col_half">-->
<!--                                    <label for="register-form-telephone">Τηλέφωνο:</label>-->
<!--                                    <input type="text" id="register-form-telephone" name="telephone" value="--><?//= $_SESSION['user']['telephone'] ?><!--" class="form-control" />-->
<!--                                </div>-->
<!--                                <div class="col_half col_last">-->
<!--                                    <label for="register-form-address">Διεύθυνση:</label>-->
<!--                                    <input type="text" id="register-form-address" name="address" value="--><?//= $_SESSION['user']['address'] ?><!--" class="form-control" />-->
<!--                                </div>-->
                                <div class="col_half col_last" style="display: none">
                                    <label for="register-form-name">ID:</label>
                                    <input type="text" id="register-form-id" name="id" value="<?= $_SESSION['user']['id'] ?>" class="form-control" />
                                </div>

                                <div class="clear"></div>
                                <div class="col_full nobottommargin">
                                    <button class="button button-3d button-black nomargin" id="register-form-submit" name="register-form-submit" value="register"><?=$this->T("account_refresh_btn");?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</section><!-- #content end -->