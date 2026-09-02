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
    if(defined("_WHITELABEL") && _WHITELABEL){
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
        min-height: 100vh;
        width: 100%;
        /* The panel carries content now, not just a backdrop. */
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 3.5rem 4rem;
        margin: 0;
    }
    .login-brand img { width: 190px; height: auto; }
    .login-quotes { max-width: 34rem; }
    .login-quotes .kicker {
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: rgba(255,255,255,.72);
        margin-bottom: 1rem;
    }
    .login-quotes .rule {
        width: 3.5rem; height: 3px;
        background: #B4A269;            /* AU gold, as an accent rule only */
        margin-bottom: 1.5rem;
    }
    .login-quote {
        display: none;
        color: #FFFFFF;                  /* 8.67:1+ on the deep-green field */
        font-size: 1.75rem;
        font-weight: 300;
        line-height: 1.35;
        margin: 0 0 .75rem;
    }
    .login-quote.is-active { display: block; }
    .login-quote-source {
        display: none;
        font-size: .8125rem;
        color: rgba(255,255,255,.72);
        margin: 0;
    }
    .login-quote-source.is-active { display: block; }
    .login-quote-dots { margin-top: 1.5rem; display: flex; gap: .25rem; }
    /* 24x24 hit targets (WCAG 2.5.8); the visible dot is drawn inside. The
       active state changes SHAPE (dot -> pill), not colour alone. */
    .login-quote-dots button {
        width: 24px; height: 24px; padding: 0;
        border: 0; border-radius: 12px;
        background: transparent;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .login-quote-dots button::before {
        content: "";
        width: 10px; height: 10px;
        border-radius: 5px;
        background: rgba(255,255,255,.6);   /* 3.9:1 on the green field */
        transition: width .2s ease;
    }
    .login-quote-dots button.is-active::before {
        width: 22px;
        background: #B4A269;
    }
    @media (prefers-reduced-motion: reduce) {
        .login-quote-dots button::before { transition: none; }
    }
    .login-quote-dots button:focus-visible { outline: 2px solid #FFFFFF; outline-offset: 2px; }
    .login-signoff {
        font-size: .8125rem;
        color: rgba(255,255,255,.72);
    }
    @media (prefers-reduced-motion: no-preference) {
        .login-quote.is-active, .login-quote-source.is-active { animation: quoteIn .45s ease; }
        @keyframes quoteIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    }
</style>
</head>
<body>
<div class="row">
    <div class="col-lg-6 d-none d-lg-block">
        <div class="login-bg">
            <div class="login-brand">
                <img src="/media/logo/africacdc_logo_white.png" alt="Africa CDC">
            </div>
            <div class="login-quotes">
                <div class="kicker">Digital Health and Information Systems</div>
                <div class="rule" aria-hidden="true"></div>
                <p class="login-quote is-active">Safeguarding Africa&rsquo;s Health.</p>
                <p class="login-quote">One Organisation, One Platform.</p>
                <p class="login-quote">Outbreak digital response in 48 hours.</p>
                <p class="login-quote-source is-active">Africa CDC</p>
                <p class="login-quote-source">MIS Key Deliverables &middot; Internal Lens</p>
                <p class="login-quote-source">MIS Key Deliverables &middot; External Lens</p>
                <div class="login-quote-dots" role="group" aria-label="Choose quote">
                    <button type="button" class="is-active" aria-pressed="true"  aria-label="Quote 1"></button>
                    <button type="button" aria-pressed="false" aria-label="Quote 2"></button>
                    <button type="button" aria-pressed="false" aria-label="Quote 3"></button>
                </div>
            </div>
            <div class="login-signoff">An entity of the African Union</div>
        </div>
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

<script>
    // Quote rotation. Auto-advances only when the visitor has not asked for
    // reduced motion; the dots always work either way, and with JS unavailable
    // the first quote simply stays put.
    (function () {
        var quotes  = document.querySelectorAll('.login-quote');
        var sources = document.querySelectorAll('.login-quote-source');
        var dots    = document.querySelectorAll('.login-quote-dots button');
        if (!quotes.length) return;
        var current = 0, timer = null;
        function show(i) {
            [quotes, sources, dots].forEach(function (set) {
                Array.prototype.forEach.call(set, function (el, j) {
                    el.classList.toggle('is-active', j === i);
                    if (el.hasAttribute('aria-pressed')) {
                        el.setAttribute('aria-pressed', j === i ? 'true' : 'false');
                    }
                });
            });
            current = i;
        }
        Array.prototype.forEach.call(dots, function (dot, i) {
            dot.addEventListener('click', function () {
                show(i);
                if (timer) { clearInterval(timer); timer = null; }   // manual choice wins
            });
        });
        if (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            timer = setInterval(function () { show((current + 1) % quotes.length); }, 9000);
        }
    })();
</script>
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