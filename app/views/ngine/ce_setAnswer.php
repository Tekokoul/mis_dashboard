<?php
/**
 * HTTP answer page ("ce" answer mode).
 *
 * Included by vanillaController::setAnswer() (app/vanillaController.class.php:121),
 * which runs long after app/configuration/settings.php has been loaded, so the
 * _WHITELABEL_* constants are normally available - every read is still guarded
 * with defined() so this page can never fatal on a half-configured install.
 *
 * All assets are served from this host: a status page must not depend on any
 * external site to render.
 *
 * Framework: CrystalEngine (vendor platform). No vendor branding is presented.
 */
error_reporting(0);
define("PHP_REQ_VER", "7.2.0");
$branded   = defined("_WHITELABEL") && _WHITELABEL;
$logo      = ($branded && defined("_WHITELABEL_LOGO_LIGHT")) ? _WHITELABEL_LOGO_LIGHT : "/media/logo/africacdc_logo_white.png";
$favicon   = ($branded && defined("_WHITELABEL_LOGO_FAVICON")) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png";
$product   = defined("_PROJECT_NAME") ? _PROJECT_NAME : "Africa CDC DHIS Performance Monitor";
$copyright = defined("_WHITELABEL_COPYRIGHT") ? _WHITELABEL_COPYRIGHT : "An entity of the African Union.";
$status    = getHTTPcode($code);
$isfault   = ($code >= 400);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?=$code." ".$status;?> | <?=$product;?></title>
    <link href="<?=$favicon;?>" rel="shortcut icon" type="image/png"/>
    <style type="text/css">
        body {
            margin: 0;
            background-color: #EEF0F1;
            color: #2B2D2E;
            font-family: "Open Sans", "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 14px;
        }

        .infotable {
            width: 900px;
            max-width: calc(100% - 40px);
            margin: 50px auto 14px auto;
            background: #FFFFFF;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        }

        .brandbar {
            background: #1A5632;
            border-bottom: 3px solid #B4A269;
            padding: 16px 25px;
        }

        .brandbar img {
            height: 26px;
            display: block;
        }

        .brandbar .product {
            color: #FFFFFF;
            font-size: 12px;
            letter-spacing: 0.04em;
            margin-top: 9px;
        }

        .content {
            padding: 22px 25px 26px 25px;
        }

        h1 {
            font-family: "Open Sans", "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: #1A5632;
            margin: 0 0 12px 0;
        }

        h1.fault {
            color: #9F2241;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Open Sans", "Segoe UI", Helvetica, Arial, sans-serif;
            font-size: 12px;
            margin-top: 20px;
            border-top: 1px solid #DCDFE0;
        }

        th {
            text-align: left;
            font-weight: 600;
            color: #58595B;
            width: 160px;
            padding: 8px 12px;
            vertical-align: top;
        }

        td {
            color: #2B2D2E;
            padding: 8px 12px;
            vertical-align: top;
            word-break: break-word;
        }

        tr:nth-child(even) {
            background-color: #F5F6F6;
        }

        /* legacy class names kept so existing markup keeps working */
        .error {
            color: #9F2241;
            font-weight: 600;
        }

        .warning {
            background: #B4A269;
            color: #241F0E;
            padding: 1px 6px;
            border-radius: 2px;
            font-weight: 600;
        }

        .white {
            color: #58595B;
        }

        .footnote {
            width: 900px;
            max-width: calc(100% - 40px);
            margin: 0 auto 50px auto;
            color: #58595B;
            font-size: 11px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="infotable">
    <div class="brandbar">
        <img src="<?=$logo;?>" alt="Africa CDC">
        <div class="product"><?=$product;?></div>
    </div>
    <div class="content">
        <h1<?=$isfault ? " class='fault'" : "";?>><?=$code.": ".$status;?></h1>
        <table cellpadding='0' cellspacing='0'>
        <tr><th>The server replied:</th><td><?=$message;?></td></tr>
        </table>
    </div>
</div>
<div class="footnote">
    <?=$copyright;?> Safeguarding Africa's Health.
</div>
</body>
</html>
