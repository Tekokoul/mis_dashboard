<?php
$users = $this->R->users;
?>
<!-- Content
============================================= -->
<section id="content" style="margin-bottom: 50px">

	<div class="content-wrap">
		<div class="container clearfix">
            <div class="row before-content">
                <div id="breadcrumbs-style" class="global-margin-from-nav col-md-12">
                    <nav aria-label="breadcrumb" role="navigation" class="top-breadcrumb-style">
                        <ol class="breadcrumb-style">
                            <li class="breadcrumb-item">
                                <a href="/<?= $this->lang ?>"><?= $this->T('breadcrumb_homepage'); ?></a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="#"><?= $this->T('breadcrumb_register'); ?></a>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
			<h1>Ο λογαριασμός μου</h1>
			<div class="col_one_third nobottommargin">

				<div class="well well-lg" style="padding-bottom: 40px;">
					<h3><?= $users['languages'][$this->lang]['already_account']; ?></h3>
					<a style="float:left" href="/<?= $this->lang?>/users" class="button button-small button-rounded"><?= $users['languages'][$this->lang]['login_button']; ?></a>
				</div>

			</div>

			<div class="col_two_third col_last nobottommargin">
				<h3><?= $users['languages'][$this->lang]['not_have_account']; ?></h3>
				<p><?= $users['languages'][$this->lang]['not_have_account_info']; ?></p>

				<form id="signup-form" name="register-form" class="nobottommargin" action="/<?=$this->lang;?>/users/signup" method="post">


                    <div class="col_half">
                        <label for="register-form-firstname"><?= $users['languages'][$this->lang]['register_name']; ?></label>
                        <input type="text" id="register-form-firstname" name="firstname" value="" class="form-control" />
                    </div>
                    <div class="col_half col_last">
                        <label for="register-form-surname"><?= $users['languages'][$this->lang]['register_last_name']; ?></label>
                        <input type="text" id="register-form-surname" name="surname" value="" class="form-control" />
                    </div>

                    <div class="clear"></div>

					<div class="col_half">
						<label for="register-form-email">Email:</label>
						<input type="text" id="register-form-email" name="email" value="" class="form-control" />
					</div>

                    <div class="col_half col_last">
                        <label for="register-form-telephone"><?= $users['languages'][$this->lang]['register_phone']; ?></label>
                        <input type="text" id="register-form-telephone" name="telephone" value="" class="form-control" />
                    </div>

                    <div class="clear"></div>

                    <div class="col_half">
                        <label for="register-form-address"><?= $users['languages'][$this->lang]['register_address']; ?></label>
                        <input type="text" id="register-form-address" name="address" value="" class="form-control" />
                    </div>
                    <div class="col_half col_last">
                        <label for="register-form-pobox"><?= $users['languages'][$this->lang]['register_zip']; ?></label>
                        <input type="text" id="register-form-pobox" name="pobox" value="" class="form-control" />
                    </div>

                    <div class="clear"></div>

                    <div class="col_half">
                        <label for="register-form-city"><?= $users['languages'][$this->lang]['register_city']; ?></label>
                        <input type="text" id="register-form-city" name="city" value="" class="form-control" />
                    </div>
                    <div class="col_half col_last">
                        <label for="register-form-state"><?= $users['languages'][$this->lang]['register_area']; ?></label>
                        <input type="text" id="register-form-state" name="state" value="" class="form-control" />
                    </div>

                    <div class="col_half">
                        <label for="register-form-country"><?= $users['languages'][$this->lang]['register_country']; ?></label>
                        <select id="register-form-country" name="country"  class="form-control">
							<?php
							$country = "getCountries_" .$this->lang;
							foreach ($country() as $key => $count) {
							    if ($key == 'GR') {
									echo '<option value="'.$key.'" selected>'.$count.'</option>';
                                } else {
									echo '<option value="'.$key.'">'.$count.'</option>';
                                }
							}
							?>
                        </select>
                    </div>

                    <div class="col_half col_last">
                        <label for="register-form-mobilephone"><?= $users['languages'][$this->lang]['register_mobile']; ?></label>
                        <input type="text" id="register-form-mobilephone" name="mobilephone" value="" class="form-control" />
                    </div>

					<div class="col_half">
						<label for="register-form-password"><?= $users['languages'][$this->lang]['register_password']; ?></label>
						<input type="password" id="register-form-password" name="password" value="" class="form-control" />
					</div>

					<div class="col_half col_last">
						<label for="register-form-repassword"><?= $users['languages'][$this->lang]['register_rePassword']; ?></label>
						<input type="password" id="register-form-repassword" name="repassword" value="" class="form-control" />
					</div>

					<div class="col_full" style="display: none">
						<input type="text" id="register-form-step" name="step" value="<?= $this->R->step ?>" class="form-control" />
					</div>
					<div class="clear"></div>
					<div class="form-check">
						<input name="termsAccepted" class="form-check-input" type="checkbox" value="" id="defaultCheck2">
						<label class="form-check-label" for="defaultCheck2">
							<?= $users['languages'][$this->lang]['register_agree']; ?> <a target="_blank" href="/<?=$this->lang;?>/terms"><?= $users['languages'][$this->lang]['register_terms']; ?></a>
						</label>
					</div>
					<div class="clear"></div>
					<div class="col_full nobottommargin" style="margin-top:20px;">
						<button class="button button-3d nomargin" id="register-form-submit" name="register-form-submit" value="register"><?= $users['languages'][$this->lang]['register_button']; ?></button>
					</div>

				</form>

			</div>

		</div>

	</div>

</section><!-- #content end -->
<script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
        {
        "@type": "ListItem",
        "position": "1",
        "name": "<?= $this->T('breadcrumb_homepage'); ?>",
        "item": "<?=  $this->S['project_url'].'/' . $this->lang . '/' ?>"
    },         {
        "@type": "ListItem",
        "position": "2",
        "name": "<?= $this->T('breadcrumb_register'); ?>",
        "item": "<?= $this->S['project_url'] .'/' . $this->lang . '/register' ?>"
    }
        ]
    }
</script>