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
						<span><?=$this->T("account_companies_menu");?></span>
						<a style="float:right" href="/<?= $this->lang?>/users/addCompany" class="button button-large button-rounded"><?= $users['languages'][$this->lang]['companies_add_company']; ?></a>
					</div>

					<div class="clear"></div>
					<div class="row clearfix">
						<div class="col-lg-12">
							<div class="accordion" id="accordionExample">
								<?php
								$collapse = 'show';
								$i = 0 ;
								foreach ($this->R->content as $company) {
									if ($i > 1) {
										$collapse = '';
									}
									echo '<div class="card">
                                    <div class="card-header" id="headingOne">
                                        <h2 class="mb-0">
                                            <a href="/'.$this->lang.'/users/company/'.$company['id'].'" class="btn btn-link">
                                                '.$company['company_name'].'
                                            </a>
                                            <a style="float: right" href="/'.$this->lang.'/users/deleteCompany?id='.$company['id'].'">X</a>
                                        </h2>
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

	</div>

</section><!-- #content end -->