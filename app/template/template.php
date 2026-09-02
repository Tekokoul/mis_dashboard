<!doctype html>
<html lang="en" class="modern fixed has-top-menu has-left-sidebar-half">
<head>
    <meta charset="UTF-8">
    <title><?=_PROJECT_NAME;?></title>
    <meta name="author" content="<?=_AUTHOR;?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?php /* Every POST in the app must carry this token (vanillaController::enforceCSRF);
             the script at the foot of the page attaches it to forms and AJAX. */ ?>
    <meta name="csrf-token" content="<?= htmlspecialchars((string)($_SESSION['token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="<?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png"; ?>">
    <link rel="stylesheet" href="/vendor/fonts/open-sans.css">
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.css" />
    <link rel="stylesheet" href="/vendor/animate/animate.compat.css">
    <link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
    <link rel="stylesheet" href="/vendor/boxicons/css/boxicons.min.css" />
    <link rel="stylesheet" href="/vendor/magnific-popup/magnific-popup.css" />
    <link rel="stylesheet" href="/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />
    <link rel="stylesheet" href="/vendor/pnotify/pnotify.custom.css" />
    <link rel="stylesheet" href="/vendor/jquery-ui/jquery-ui.css" />
    <link rel="stylesheet" href="/vendor/jquery-ui/jquery-ui.theme.css" />
    <?php
    foreach($this->CSS as $CSSfile) {
        print '<link rel="stylesheet" href="'. $CSSfile.'?v='._CURRENT_COMMIT.'">';
    } ?>
    <link rel="stylesheet" href="/css/theme.css" />
    <link rel="stylesheet" href="/css/modern.css" />
    <?php
    if(_WHITELABEL){
        $skin = _WHITELABEL_SKIN;
    } else {
        $skin = "skin_africacdc.css";
    }
    ?>
    <link rel="stylesheet" href="/css/<?=$skin;?>" />
    <link rel="stylesheet" href="/css/custom.css?v=<?=_CURRENT_COMMIT?>">
    <script src="/vendor/modernizr/modernizr.js"></script>
</head>
<body>
<a class="afcdc-skip" href="#content">Skip to main content</a>

<?php
$categoryPage = 0;
$cartPage = 0;
$checkoutPage = 0;

//if(file_exists(_TEMPLATE_PATH."template_header_".$this->R->url['controller']."_".$this->R->url['action'].".php")){
//    include "template_header_".$this->R->url['controller']."_".$this->R->url['action'].".php";
//} else {
    include "template_header.php";
//}
include $viewPath;
include "template_footer.php";
?>
<script nonce="<?= csp_nonce(); ?>">
    var lang = "<?=$this->lang;?>";
    var langid = "<?=$this->langid;?>";
    var lang_prefix = "<?= (_MULTILINGUAL) ? "/".$this->lang : "";?>" ;

    var project_domain = "<?=_PROJECT_URL;?>";
    // Profile > Settings, read by the activities table and the gauges.
    var page_length = <?= (int)($_SESSION['user']['settings']['table_rows'] ?? _PAGINATION); ?>;
    var animate_gauges = <?= (int)($_SESSION['user']['settings']['animate_gauges'] ?? 1); ?>;
    <?php $notification_position = (isset($_SESSION['user']['settings']['notification_area'])) ? $_SESSION['user']['settings']['notification_area'] : "stack-bottomleft";?>
    var notificationclass = '<?=$notification_position;?>';
</script>
<script src="/vendor/jquery/jquery.js"></script>
<script nonce="<?= csp_nonce(); ?>">
    // CSRF. The server refuses any POST without the session token
    // (vanillaController::enforceCSRF). One place attaches it everywhere:
    // a hidden field on every POST form - including forms loaded later into
    // a modal, via the delegated submit handler - and a header on every
    // jQuery request that is not a plain GET.
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = meta ? meta.getAttribute('content') : '';
        if (!token) return;
        window.CSRF_TOKEN = token;
        function attach(form) {
            if (!form || (form.method || 'get').toLowerCase() !== 'post') return;
            if (form.querySelector('input[name="csrf"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'csrf'; input.value = token;
            form.appendChild(input);
        }
        function attachAll() { Array.prototype.forEach.call(document.querySelectorAll('form'), attach); }
        document.addEventListener('DOMContentLoaded', attachAll);
        document.addEventListener('submit', function (e) { attach(e.target); }, true);
        // Forms that arrive later (the Record-delivery modal is fetched by
        // AJAX and submitted with form.submit(), which fires no submit event).
        if (window.MutationObserver) {
            new MutationObserver(attachAll).observe(document.documentElement, { childList: true, subtree: true });
        }
        $.ajaxSetup({
            beforeSend: function (xhr, settings) {
                if (!/^(GET|HEAD|OPTIONS)$/i.test(settings.type || 'GET')) {
                    xhr.setRequestHeader('X-CSRF-Token', token);
                }
            }
        });
    })();
</script>
<script src="/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="/vendor/popper/umd/popper.min.js"></script>
<script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
<script src="/vendor/common/common.js"></script>
<script src="/vendor/nanoscroller/nanoscroller.js"></script>
<script src="/vendor/magnific-popup/jquery.magnific-popup.js"></script>
<script src="/vendor/pnotify/pnotify.custom.js"></script>
<script src="/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="/vendor/jquery-ui/jquery-ui.js"></script>
<script src="/vendor/jqueryui-touch-punch/jquery.ui.touch-punch.js"></script>
<?php
foreach($this->JS as $JSfile) {
    print '<script src="'. $JSfile.'?v='._CURRENT_COMMIT.'"></script>';
}

$jsfile = "/js/page_".$this->R->url['controller']."_".$this->R->url['action'].".js";
if(file_exists(_PUBLIC_PATH.$jsfile)){
    print '<script src="'.$jsfile.'?v='._CURRENT_COMMIT.'"></script>';
}

?>
<script src="/js/theme.js"></script>
<script src="/js/theme.init.js"></script>
<script src="/js/form.js"></script>
<script src="/js/custom.js"></script>
    <?php
    if(isset($this->R->CE_Notification)&&_DEBUG_MODE){
        ?>
<script nonce="<?= csp_nonce(); ?>">
    $(function() {
        new PNotify(
            {
                title:'<?=$this->R->CE_Notification['title'];?>',
                text: '<?=$this->R->CE_Notification['text'];?>',
                type: '<?=$this->R->CE_Notification['type'];?>',
                addclass: notificationclass,
                stack: {"dir1": "up", "dir2": "left"},
                width: "50%"
            }
        );
    });
</script>
    <?php
    }
    ?>

</body>
</html>