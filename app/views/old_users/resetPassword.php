<!-- Content
============================================= -->
<section id="content" style="margin-top:50px; margin-bottom: 50px">

	<div class="content-wrap">
		<div class="container clearfix">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="/<?= $this->lang?>/">Αρχική</a></li>
				<li class="breadcrumb-item active" aria-current="page">Αλλαγή Κωδικού</li>
			</ol>
		</div>
		<div class="container clearfix">
			<h1>Αλλάξτε το κωδικό σας!</h1>
			<div class="col_two_third col_last nobottommargin">
				<p>Επαναφέρεται το κωδικό σας! Ένα email θα σας αποσταλή στο email σας για να αλλάξετε τον κωδικό σας!</p>
				<form id="reset-password" name="reset-password" class="nobottommargin" action="/<?=$this->lang;?>/users/recoverPassword" method="post">
					<div class="form-group">
						<input type="password" name="password" placeholder="Νέος Κωδικός" id="password-reset" class="form-control">
					</div>
					<div class="form-group">
						<input type="password" name="repassword" placeholder="Πληκτρολόγησε πάλι" class="form-control">
					</div>
					<div class="form-group" style="visibility: hidden">
						<input type="text" name="email" value="<?= $this->R->content['email'] ?>" class="form-control">
					</div>
					<button type="submit" id="reset-password-email-button" class="button primary-bg btn-block">Αλλαγή</button>
				</form>

			</div>

		</div>

	</div>

</section><!-- #content end -->