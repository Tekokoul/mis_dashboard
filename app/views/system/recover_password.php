<!doctype html>
<html lang="en" class="fixed">
<head>
    <meta charset="UTF-8">
    <title><?=_PROJECT_NAME;?></title>
    <meta name="author" content="<?=_AUTHOR?>">
    <link rel="icon" href="<?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png"; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.css" />
    <link rel="stylesheet" href="/vendor/animate/animate.compat.css">
    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
    <link rel="stylesheet" href="/vendor/boxicons/css/boxicons.min.css" />
    <link rel="stylesheet" href="/css/theme.css" />
    <?php $skin = (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_SKIN : "skin_africacdc.css"; ?>
    <link rel="stylesheet" href="/css/<?=$skin;?>" />
    <link rel="stylesheet" href="/css/custom.css">
    <script src="/vendor/modernizr/modernizr.js"></script>

</head>
<body>
<div class="row">
    <div class="col-lg-6 d-none d-sm-block d-md-none d-lg-block">
        <div class="row login-bg"></div>
    </div>
    <div class="col-lg-6">
        <div class="row">
<section class="body-sign">
    <div class="center-sign">
        <a href="<?=$this->L("");?>" class="logo float-left">
            <img src="<?= (defined("_WHITELABEL_LOGO_ON_LIGHT")) ? _WHITELABEL_LOGO_ON_LIGHT : "/media/logo/africacdc_logo.png"; ?>" width="180" alt="<?= _PROJECT_NAME; ?>" />
        </a>

        <div class="panel card-sign">
            <div class="card-title-sign mt-3 text-end">
                <h2 class="title text-uppercase font-weight-bold m-0"><i class="bx bx-user-circle me-1 text-6 position-relative top-5"></i> Recover Password</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-light">
                    <p class="m-0">Enter your e-mail below, and we will send you reset instructions!</p>
                </div>

                <form>
                    <div class="form-group mb-0">
                        <div class="input-group">
                            <input name="username" type="email" placeholder="E-mail" class="form-control form-control-lg" />
                            <button class="btn btn-primary btn-lg" type="submit">Reset!</button>
                        </div>
                    </div>

                    <p class="text-center mt-3">Remembered? <a href="<?=$this->L("");?>">Sign In!</a></p>
                </form>
            </div>
        </div>

        <p class="text-center text-muted mt-3 mb-3"><?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_COPYRIGHT : '&copy; Copyright '.date("Y").' Africa CDC. An entity of the African Union.'; ?>
            <br><?= htmlspecialchars(_PROJECT_NAME, ENT_QUOTES, 'UTF-8'); ?> ver. <?= _PROJECT_VERSION; ?></p>    </div>
</section>
        </div>
    </div>
</div>
<script src="/vendor/jquery/jquery.js"></script>
<script src="/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="/vendor/popper/umd/popper.min.js"></script>
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/common/common.js"></script>
<script src="/vendor/nanoscroller/nanoscroller.js"></script>
<script src="/vendor/magnific-popup/jquery.magnific-popup.js"></script>
<script src="/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="/js/theme.js"></script>
<script src="/js/custom.js"></script>
<script src="/js/theme.init.js"></script>
</body>
</html>