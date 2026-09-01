<!doctype html>
<html class="modern fixed has-top-menu has-left-sidebar-half">
<head>
    <meta charset="UTF-8">
    <title><?=_PROJECT_NAME;?></title>
    <meta name="author" content="<?=_AUTHOR;?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="icon" href="<?= (defined("_WHITELABEL") && _WHITELABEL) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png"; ?>">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">
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
<script>
    var lang = "<?=$this->lang;?>";
    var langid = "<?=$this->langid;?>";
    var lang_prefix = "<?= (_MULTILINGUAL) ? "/".$this->lang : "";?>" ;

    var project_domain = "<?=_PROJECT_URL;?>";
    <?php $notification_position = (isset($_SESSION['user']['settings']['notification_area'])) ? $_SESSION['user']['settings']['notification_area'] : "stack-bottomleft";?>
    var notificationclass = '<?=$notification_position;?>';
</script>
<script src="/vendor/jquery/jquery.js"></script>
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
<script>
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