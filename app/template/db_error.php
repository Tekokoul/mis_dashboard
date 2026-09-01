<?php
/**
 * Database error page.
 *
 * Included by db_error() (app/includes/library.php) when the database cannot be
 * reached or a query fails. It renders after app/configuration/settings.php has
 * been loaded (app/bootstrap.php:9, the DB opens at :29), so the _WHITELABEL_*
 * constants are normally available - every read is still guarded with defined()
 * so this page can never fatal on a half-configured install.
 *
 * All assets are served from this host: an error page must not depend on any
 * external site to render.
 *
 * Framework: CrystalEngine (vendor platform). No vendor branding is presented.
 */
$branded   = defined("_WHITELABEL") && _WHITELABEL;
$logo      = ($branded && defined("_WHITELABEL_LOGO_LIGHT")) ? _WHITELABEL_LOGO_LIGHT : "/media/logo/africacdc_logo_white.png";
$favicon   = ($branded && defined("_WHITELABEL_LOGO_FAVICON")) ? _WHITELABEL_LOGO_FAVICON : "/media/logo/africacdc_favicon.png";
$product   = defined("_PROJECT_NAME") ? _PROJECT_NAME : "Africa CDC DHIS Performance Monitor";
$copyright = defined("_WHITELABEL_COPYRIGHT") ? _WHITELABEL_COPYRIGHT : "An entity of the African Union.";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Database unavailable | <?=$product;?></title>
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

        p {
            line-height: 1.55;
            margin: 0 0 12px 0;
            max-width: 76ch;
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
            width: 100px;
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

        .error {
            color: #9F2241;
            font-weight: 600;
        }

        /* legacy class names kept so existing markup keeps working */
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
        <h1>Database unavailable</h1>
        <p>The dashboard is running, but it cannot reach its database, so no data can be
            read or saved at the moment.</p>
        <p>Nothing you did caused this. Please wait a moment and reload the page. If it keeps
            happening, report it to the Africa CDC DHIS team and quote the time below.</p>
        <p class="white">Administrators: check that the database service is up and that this
            deployment's connection settings are still valid.</p>
        <table cellpadding='0' cellspacing='0'>
            <tr><th>Time:</th><td><?=date("Y-m-d H:i:s");?></td></tr>
            <tr><th>Query:</th><td><?=$query;?></td></tr>
            <tr><th>Error:</th><td class='error'><?=$error;?></td></tr>
        </table>
    </div>
</div>
<div class="footnote">
    <?=$copyright;?> Safeguarding Africa's Health.
</div>
</body>
</html>
