<?php
if(defined("_WHITELABEL")&&(_WHITELABEL)){
    // The sign-in column sits on the page's light background - the green panel is
    // the OTHER column - so this needs the coloured mark. _WHITELABEL_LOGO_DARK is
    // the all-white PNG and was invisible here.
    $logo_src = defined("_WHITELABEL_LOGO_ON_LIGHT") ? _WHITELABEL_LOGO_ON_LIGHT : _WHITELABEL_LOGO_DARK;
    $logo = '<img src="'.$logo_src.'" class="logo-image" width="180" alt="'._PROJECT_NAME.'"/>';
    $copyright = '<p class="text-center text-muted mt-3 mb-3">'. _WHITELABEL_COPYRIGHT .'</p>';
    $bg = _WHITELABEL_BG;
} else {
    $logo = '<img src="/media/logo/africacdc_logo.png" class="logo-image" width="180" alt="'._PROJECT_NAME.'" />';
    $copyright = '<p class="text-center text-muted mt-3 mb-3">&copy; Copyright '. date("Y").' Africa CDC. An entity of the African Union.<br>'
               . _PROJECT_NAME .' ver. '. _PROJECT_VERSION .'</p>';
    $bg = "/media/logo/africacdc_login_bg.svg";
}
?>
<!doctype html>
<html class="fixed">
<head>
    <meta charset="UTF-8">
    <title><?= _PROJECT_NAME; ?></title>
    <meta name="author" content="<?= _AUTHOR ?>">
    <link rel="icon" href="<?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png"; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700,800|Shadows+Into+Light"
          rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.css"/>
    <link rel="stylesheet" href="/vendor/boxicons/css/boxicons.min.css"/>
    <link rel="stylesheet" href="/css/theme.css"/>
    <?php
    if(_WHITELABEL){
        $skin = _WHITELABEL_SKIN;
    } else {
        $skin = "skin_africacdc.css";
    }
    ?>
    <link rel="stylesheet" href="/css/<?=$skin;?>" />
    <link rel="stylesheet" href="/css/custom.css">
    <script src="/vendor/modernizr/modernizr.js"></script>
<style>
    .login-bg {
        background-image: url('<?=$bg;?>');
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        height: 100vh;
        width: 100%;
    }
</style>
</head>
<body>
<div class="row">
    <div class="col-lg-6 d-none d-sm-block d-md-none d-lg-block">
        <div class="row login-bg"></div>
    </div>
    <div class="col-lg-6 col-md-12">
        <div class="row">
            <section class="body-sign">
                <div class="center-sign">
                    <a href="<?= $this->L(""); ?>" class="logo float-left">
                        <?= $logo; ?>
                    </a>
                    <div class="panel card-sign">
                        <div class="card-title-sign mt-3 text-end">
                            <h2 class="title text-uppercase font-weight-bold m-0"><i class="bx bx-user-circle me-1 text-6 position-relative top-5"></i> Sign In</h2>
                        </div>
                        <div class="card-body">
                            <form action="<?= $this->L("users/login"); ?>" method="POST">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['token']; ?>">
                                <div class="form-group mb-3">
                                    <label>Username</label>
                                    <div class="input-group">
                                        <input name="username" type="text" class="form-control form-control-lg"/>
                                        <span class="input-group-text">
                                <i class="bx bx-user text-4"></i>
                            </span>
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <div class="clearfix">
                                        <label class="float-left">Password</label>
                                      <!--  <a href="<?= $this->L("recover-password"); ?>" class="float-end">Lost Password?</a>-->
                                    </div>
                                    <div class="input-group">
                                        <input name="password" type="password" class="form-control form-control-lg"/>
                                        <span class="input-group-text">
                                <i class="bx bx-lock text-4"></i>
                            </span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12 text-end pull-right">
                                        <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 font-weight-semibold line-height-1">Sign In</button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                    <?=$copyright;?>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="/vendor/jquery/jquery.js"></script>
<script src="/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="/vendor/popper/umd/popper.min.js"></script>
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/common/common.js"></script>
<script src="/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="/vendor/nanoscroller/nanoscroller.js"></script>
<script src="/js/theme.js"></script>
<script src="/js/custom.js"></script>
<script src="/js/theme.init.js"></script>
</body>
</html>