<!-- Content
============================================= -->
<section id="content" style="margin-top:50px; margin-bottom: 50px">

	<div class="content-wrap">
		<div class="container clearfix">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="/<?= $this->lang?>/">Αρχική</a></li>
				<li class="breadcrumb-item active" aria-current="page">Forgot Password</li>
			</ol>
		</div>
		<div class="container clearfix">
			<h1>Ξεχάσατε το κωδικό σας?</h1>

			<div class="col_one_third nobottommargin">

				<div class="well well-lg nobottommargin">
					<h3>Δέν έχετε λογαριασμό? Εγγραφείτε τώρα.</h3>
					<a style="float:left" href="/<?= $this->lang?>/users/register" class="button button-small button-rounded">Εγγραφή</a>
				</div>

			</div>

			<div class="col_two_third col_last nobottommargin">
				<p>Επαναφέρεται το κωδικό σας! Ένα email θα σας αποσταλή στο email σας για να αλλάξετε τον κωδικό σας!</p>
				<form id="reset-password-email" name="reset-password-email" class="nobottommargin" action="/<?=$this->lang;?>/users/recoverPassword" method="post">
					<div class="col_half">
						<label for="login-form-username">Email:</label>
						<input type="text" id="login-form-username" name="email" value="" class="form-control" />
					</div>
					<div class="col_full nobottommargin">
						<button class="button button-3d nomargin" id="reset-password-email-button" name="login-form-submit" value="login">Αλλαγή</button>
					</div>

				</form>

			</div>

		</div>

	</div>

</section><!-- #content end -->