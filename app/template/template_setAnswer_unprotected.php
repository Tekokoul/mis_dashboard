<?php
$icon = ($code>=400) ? "fa-exclamation-circle" :"fa-info-circle";
?>
<!doctype html>
<html lang="en" class="fixed">
<head>
    <meta charset="UTF-8">
    <title><?=_PROJECT_NAME;?></title>
    <meta name="author" content="<?=_AUTHOR?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="<?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png"; ?>">
    <link rel="stylesheet" href="/vendor/fonts/open-sans.css">
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.css" />
    <link rel="stylesheet" href="/vendor/animate/animate.compat.css">
    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
    <link rel="stylesheet" href="/vendor/boxicons/css/boxicons.min.css" />
    <link rel="stylesheet" href="/vendor/magnific-popup/magnific-popup.css" />
    <link rel="stylesheet" href="/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />
    <link rel="stylesheet" href="/css/theme.css" />
    <?php $skin = (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_SKIN : "skin_africacdc.css"; ?>
    <link rel="stylesheet" href="/css/<?=$skin;?>" />
    <link rel="stylesheet" href="/css/custom.css">
    <script src="/vendor/modernizr/modernizr.js"></script>
</head>
<body>
<!-- start: page -->
<section class="body-error error-outside">
    <div class="center-error">

        <div class="row">
            <div class="col-md-8">
                <div class="main-error mb-3">
                    <h2 class="error-code text-dark text-center font-weight-semibold m-0"><?=$code;?> <i class="fas <?=$icon;?>"></i></h2>
                    <p class="error-explanation text-center"><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <h4 class="text">Here are some useful links</h4>
                <ul class="nav nav-list flex-column primary">
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="fas fa-caret-right text-dark"></i> Try to login</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
<script src="/vendor/jquery/jquery.js"></script>
<script src="/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="/vendor/popper/umd/popper.min.js"></script>
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
<script src="/vendor/common/common.js"></script>
<script src="/vendor/nanoscroller/nanoscroller.js"></script>
<script src="/vendor/magnific-popup/jquery.magnific-popup.js"></script>
<script src="/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="/js/theme.js"></script>
<script src="/js/custom.js"></script>
<script src="/js/theme.init.js"></script>

</body>
</html>
