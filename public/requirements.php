<?php
/**
 * Standalone installation requirements check.
 *
 * Reachable directly at /requirements.php (public/.htaccess exempts real files
 * from the front controller), so it must render even when the application is
 * broken or not configured at all.
 *
 * Two rules follow from that:
 *   1. Every asset is served from this host - an install checker must not
 *      depend on an external site to render.
 *   2. Nothing above the settings.php require may read a _WHITELABEL_* or
 *      _PROJECT_* constant. The branding variables below start as literals and
 *      are only upgraded from constants AFTER the require, each behind a
 *      defined() guard, so the one page whose job is diagnosing a broken
 *      install can never itself fatal on an undefined constant.
 *
 * Framework: CrystalEngine (vendor platform). No vendor branding is presented.
 */

define("PHP_REQ_VER", "8.2.0");
$installation_ok = true;

// Safe literals: valid before settings.php exists, let alone loads.
$logo      = "/media/logo/africacdc_logo_white.png";
$favicon   = "/media/logo/africacdc_favicon.png";
$product   = "Africa CDC DHIS Performance Monitor";
$copyright = "An entity of the African Union.";

$root = dirname(pathinfo(__FILE__)['dirname']);
if (!file_exists($root . "/app/configuration/settings.php")) {
    $installation_ok = false;
} else {
    require_once($root ."/app/configuration/settings.php");
}

// $installation_ok only means the config FILE is on disk. A truncated or
// half-written settings.php satisfies file_exists() while leaving its constants
// undefined, and reading an undefined constant is an uncaught Error that
// display_errors cannot suppress - on the one page whose job is diagnosing a
// broken install. Gate the path checks on the constants themselves.
$paths_ok = defined("_MEDIA_PATH") && defined("_CACHE_PATH");

// Constants may exist from here on - never assume, always guard.
if (defined("_WHITELABEL") && _WHITELABEL) {
    if (defined("_WHITELABEL_LOGO_LIGHT"))   { $logo    = _WHITELABEL_LOGO_LIGHT; }
    if (defined("_WHITELABEL_LOGO_FAVICON")) { $favicon = _WHITELABEL_LOGO_FAVICON; }
}
if (defined("_PROJECT_NAME"))         { $product   = _PROJECT_NAME; }
if (defined("_WHITELABEL_COPYRIGHT")) { $copyright = _WHITELABEL_COPYRIGHT; }

$mode = ($_GET['mode'] ?? "web") == "json" ? "json" : "web";

function JSON_reply($code, $message, $data=[]) {
    $answer = [
        "code" => $code,
        "message" => $message,
        "data" => $data
    ];

    header("Content-Type: application/json;charset=utf-8");
    http_response_code($code);
    print json_encode($answer);
    exit();
}

/**
 * Render a checked location relative to the install root.
 *
 * The verdict (ACCESS GRANTED / ACCESS DENIED) is the diagnostic; the server's
 * absolute directory layout is not, and this page needs no login. An operator
 * still sees which folder failed, an anonymous visitor no longer learns where
 * the application lives on disk.
 */
function display_path($path, $root) {
    $path = rtrim((string)$path, DIRECTORY_SEPARATOR);
    $root = rtrim((string)$root, DIRECTORY_SEPARATOR);
    if ($root !== "" && strpos($path, $root) === 0) {
        $path = substr($path, strlen($root));
    }
    return "." . ($path === "" ? DIRECTORY_SEPARATOR : $path);
}

$opcacheenabled = false;
if (function_exists("opcache_get_status")) {
    // Returns false when OPcache is disabled, and the key is absent on some
    // builds even when it is an array. Any notice emitted here would be output
    // BEFORE header() runs in JSON mode, breaking the response.
    $data = opcache_get_status();
    $opcacheenabled = is_array($data) && ($data["opcache_enabled"] ?? false) === true;
}

$exec_allowed = function_exists("exec");
if($exec_allowed){
    $git_version = exec('git --version | grep git | awk \'{print $3}\'');
    $response = json_decode(trim(exec('curl -s http://localhost:9200/health')), true);
    $typesense = (isset($response['ok']) && $response['ok']) ? true : false;
}

if($mode=="web"){
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Installation requirements | <?=$product;?></title>
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
                width: 1000px;
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
                margin: 0 0 6px 0;
            }

            p {
                line-height: 1.55;
                margin: 0;
                max-width: 76ch;
                color: #58595B;
                font-size: 13px;
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
                width: 280px;
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

            .minilogo {
                height: 14px;
                vertical-align: -2px;
            }

            /* legacy class names kept so the print statements below keep working */
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
                width: 1000px;
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
        <h1>Installation requirements</h1>
        <p>Server-side checks for this deployment. Every row below reports whether the
            platform meets a requirement of the dashboard.</p>
        <?php

        print "<table cellpadding='0' cellspacing='0'>";
        //HTTPD SERVER===================================================================================
        print "<tr><th>HTTPd Server:</th><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</td></tr>";
        //PHP VERSION====================================================================================
        print "<tr><th>PHP Version:</th><td>" . ((version_compare(phpversion(), PHP_REQ_VER, '<') === false) ? phpversion() . " <span class='white'>(compatible)</span>" : "<span class='warning'>" . phpversion() . "</span> <span class='white'>(please install PHP ver. " . PHP_REQ_VER . " or greater)</span>") . "</td></tr>";
        //PDO============================================================================================
        print "<tr><th>PDO/MySQL extension:</th><td>" . (extension_loaded('pdo_mysql') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //MYSQLI=========================================================================================
        print "<tr><th>MySQLi extension:</th><td>" . (function_exists("mysqli_connect") ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //OPCACHE========================================================================================
        print "<tr><th>OPCache extension:</th><td>" . ($opcacheenabled ? "OK <span class='white'>(free: " . round(($data["memory_usage"]["free_memory"]) / 1024 / 1024, 2) . " MB)</span>" : "<span class='warning'>WARNING</span> <span class='white'>(OPCache improves PHP performance)</span>") . "</td></tr>";
        //CURL======================================================================================
        print "<tr><th>cURL extension:</th><td>" . (extension_loaded('curl') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //JSON======================================================================================
        print "<tr><th>JSON extension:</th><td>" . (extension_loaded('json') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //XML======================================================================================
        print "<tr><th>XML extension:</th><td>" . (extension_loaded('xml') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //SOAP======================================================================================
        print "<tr><th>SOAP extension:</th><td>" . (extension_loaded('soap') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //MULTIBYTE======================================================================================
        print "<tr><th>Multibyte String functions:</th><td>" . (extension_loaded('mbstring') ? "OK" : "<span class='error'>ERROR</span>") . "</td></tr>";
        //GD LIBRARY=====================================================================================
        print "<tr><th>GD Library:</th><td>";
        if (extension_loaded('gd') && function_exists('gd_info')) {
            $settings = gd_info();
            print "OK <span class='white'>(ver.: " . $settings["GD Version"] . " | ";

            $supported_settings = [];
            $settingsList = [
                'JPEG Support', 'PNG Support', 'WebP Support', 'GIF Read Support', 'GIF Create Support',
                'BMP Support', 'AVIF Support', 'TGA Read Support', 'FreeType Support',
                'T1Lib Support', 'WBMP Support', 'XPM Support', 'XBM Support',
                'JIS-mapped Japanese Font Support'
            ];

            foreach ($settingsList as $setting) {
                $cleanedSetting = str_replace(' Support', '', $setting);
                // Not every key is present on every GD build (T1Lib is gone in
                // modern GD), and a notice here would precede header() in JSON mode.
                $supported_settings[] = (($settings[$setting] ?? 0) == 1) ? $cleanedSetting : "";
            }

            print implode(", ", array_filter($supported_settings)).")</span>";
        } else print "<span class='error'>ERROR</span>";
        print "</td></tr>";
        //EXEC===========================================================================================
        print "<tr><th>System Execution (exec):</th><td>" . ($exec_allowed ? "ACCESS GRANTED" : "<span class='error'>ACCESS DENIED</span>") . "</td></tr>";
//        print "<tr><th>Accessible Signal Handler:</th><td>" . (extension_loaded("pcntl") ? "ACCESS GRANTED" : "<span class='error'>ACCESS DENIED</span>") . "</td></tr>";
        if ($exec_allowed) {
            print "<tr><th>TypeSense Server:</th><td>" . ($typesense ? 'RUNNING' : "<span class='error'>NOT RUNNING</span>") . "</td></tr>";
        } else {
            print "<tr><th>TypeSense Server:</th><td><span class='warning'>SYSTEM EXECUTION NOT ALLOWED</span></td></tr>";
        }
        //GIT============================================================================================
        if ($exec_allowed) {
            print "<tr><th>Git installed:</th><td>" . ($git_version != "" ? "OK <span class='white'>(ver.: " . $git_version . ")</span>" : "<span class='warning'>WARNING</span> <span class='white'>(you are advised to use git for deployment)</span>") . "</td></tr>";
        } else {
            print "<tr><th>Git installed:</th><td><span class='warning'>SYSTEM EXECUTION NOT ALLOWED</span></td></tr>";
        }
        //INSTALLATION & FOLDERS=========================================================================
        print "<tr><th><img src='".$favicon."' class='minilogo' alt=''>&nbsp;&nbsp;Application installation:</th><td>" . ($installation_ok ? "OK <span class='white'>(based on configuration files)</span>" : "<span class='error'>ERROR</span> <span class='white'>(configuration files are absent)</span>") . "</td></tr>";
        if ($installation_ok && $paths_ok) {
            // Paths are shown relative to the install root - see display_path().
            print "<tr><th>&nbsp;&nbsp;&nbsp;&nbsp;&#10551; Media folder:</th><td>" . (((file_exists(_MEDIA_PATH)) && (is_writable(_MEDIA_PATH))) ? "ACCESS GRANTED" : "<span class='error'>ACCESS DENIED</span>") . " <span class='white'>(" . display_path(_MEDIA_PATH, $root) . ")</span></td></tr>";
            print "<tr><th>&nbsp;&nbsp;&nbsp;&nbsp;&#10551; Cache folder:</th><td>" . (((file_exists(_CACHE_PATH)) && (is_writable(_CACHE_PATH))) ? "ACCESS GRANTED" : "<span class='error'>ACCESS DENIED</span>") . " <span class='white'>(" . display_path(_CACHE_PATH, $root) . ")</span></td></tr>";
            if(file_exists($root . "/composer.json")) {
                print "<tr><th>&nbsp;&nbsp;&nbsp;&nbsp;&#10551; Vendor folder:</th><td>" . (((file_exists($root."/vendor")) && (is_writable($root."/vendor"))) ? "ACCESS GRANTED" : "<span class='error'>ACCESS DENIED</span>") . " <span class='white'>(" . display_path($root."/vendor", $root) . ")</span></td></tr>";
            }
        }
        print "</table>";
        ?>
        </div>
    </div>
    <div class="footnote">
        <?=$copyright;?> Safeguarding Africa's Health.
    </div>
    </body>
    </html>
    <?php
}

if($mode=="json"){
    $reply = [
        "HTTPd Server" => ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
        "PHP Version" => phpversion(),
        "PDO/MySQL extension" => extension_loaded('pdo_mysql'),
        "MySQLi extension" => function_exists("mysqli_connect"),
        "OPCache extension" => $opcacheenabled,
        "cURL extension" => extension_loaded('curl'),
        "JSON extension" => extension_loaded('json'),
        "XML extension" => extension_loaded('xml'),
        "SOAP extension" => extension_loaded('soap'),
        "Multibyte String functions" => extension_loaded('mbstring'),
        "GD Library" => extension_loaded('gd'),
        "System Execution (exec)" => function_exists("exec"),
        "TypeSense Server" => $exec_allowed ? $typesense : "N/A",
        "Git installed" => $exec_allowed ? ($git_version != "") : "N/A",
        "Application installation" => $installation_ok
    ];
    if($installation_ok && $paths_ok){
        $reply["Writable folders"] = [
            "Media folder" => ((file_exists(_MEDIA_PATH)) && (is_writable(_MEDIA_PATH))),
            "Cache folder" => ((file_exists(_CACHE_PATH)) && (is_writable(_CACHE_PATH))),
            "Vendor folder" => ((file_exists($root."/vendor")) && (is_writable($root."/vendor")))
        ];
    }
    JSON_reply(200, "Installation requirements check", $reply);
}
?>