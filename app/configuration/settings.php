<?php
// Project definitions ------------------------------------
define("_PROJECT_NAME", "Africa CDC DHIS Performance Monitor");
define("_PROJECT_COLOR", "#1A5632");                       // AU Corporate Green - also the gauge/chart colour
define("_PROJECT_DOMAIN", "CHANGE-ME.africacdc.org");   // set to the real host before deploying
define("_PROJECT_VERSION", "1.0");
define("_AUTHOR", "Africa CDC — Digital Health and Information Systems");
define("_WHITELABEL", true);
define("_WHITELABEL_HEADER", true);
define("_WHITELABEL_SKIN", "skin_africacdc.css");          // GENERATED - run tools/make_skin_africacdc.py, never hand-edit
define("_WHITELABEL_LOGO_LIGHT", "/media/logo/africacdc_logo_white.png"); // header plate is dark (theme.css .logo:after), so the white mark
define("_WHITELABEL_LOGO_PADDING", "style='padding: 0px 20px 0 20px;'");
define("_WHITELABEL_LOGO_STYLE", "style='margin: 1px 0 0 15px; padding: 10px 20px 0 20px !important;'");
define("_WHITELABEL_LOGO_DARK", "/media/logo/africacdc_logo_white.png");   // login page sits on the dark AU-green panel
define("_WHITELABEL_LOGO_ON_LIGHT", "/media/logo/africacdc_logo.png");     // pages with a light ground (password recovery)
define("_WHITELABEL_LOGO_FAVICON", "/media/logo/africacdc_favicon.png");   // square: used as favicon, mobile logo and userbox avatar
define("_WHITELABEL_BG", "/media/logo/africacdc_login_bg.svg");            // 1.8 KB brand SVG
define("_WHITELABEL_COPYRIGHT", "© Copyright ".date("Y")." Africa CDC. An entity of the African Union.");
define("_DEBUG_MODE", false);
define("_DB_DEBUG_MODE", false);

// Project settings ---------------------------------------
define("_PAGINATION", 50);
define("_LOGGING", false);
define("_DRM_ACTIVE", false);
//TODO add DB_LOGGING settings
// URL definitions ----------------------------------------
define("_WEB_PROTOCOL", "http");
define("_PROJECT_URL", _WEB_PROTOCOL."://"._PROJECT_DOMAIN);
define("_PROJECT_CDN_URL", _WEB_PROTOCOL."://cdn."._PROJECT_DOMAIN);
define("_DEFAULT_LANGUAGE", "en");
define("_MULTILINGUAL", false);
define("_SET_ANSWER_MODE", "template"); // template = themed HTML page, ce = framework JSON envelope, json = plain JSON
define("_SET_ANSWER_COMPATIBILITY", "http"); // http will return true code and app will always return 200 with code encapsulated
define("_CSRF_EXPIRY", 7200); // CSRF token idle expiry in seconds; it slides forward on every request
define("_ERROR_HANDLER", "CE_ErrorHandler");
define("_PROJECT_HELPER_CLASS", "");   // legacy per-tenant API helper class.
// No such class exists in this build; the two call sites guard on class_exists.

// Path definitions ---------------------------------------
define("DS", DIRECTORY_SEPARATOR);
define("_APP_PATH", realpath(dirname(__FILE__, 2)).DS);
define("_ROOT_PATH", dirname(_APP_PATH, 1).DS);
define("_CONTROLLERS_PATH", _APP_PATH."controllers".DS);
define("_MODELS_PATH", _APP_PATH."models".DS);
define("_PLUGINS_PATH", _APP_PATH."plugins".DS);
define("_INCLUDES_PATH", _APP_PATH."includes".DS);
define("_VIEWS_PATH", _APP_PATH."views".DS);
define("_TEMPLATE_PATH", _APP_PATH."template".DS);
define("_MODELS_SETTINGS_PATH", _ROOT_PATH."db".DS."models_settings".DS);
define("_USERS_SETTINGS_PATH", _ROOT_PATH."db".DS."users_settings".DS);
define("_JSON_MODELS_PATH", _ROOT_PATH."db".DS."json_models".DS);
define("_REPORTS_PATH", _ROOT_PATH."db".DS."reports".DS);
define("_MENUS_PATH", _ROOT_PATH."db".DS."menus".DS);
define("_LOGS_PATH", _ROOT_PATH."logs".DS);
define("_PUBLIC_PATH", _ROOT_PATH."public".DS);
define("_CACHE_PATH", _PUBLIC_PATH."cache".DS);
define("_CACHE", DS."cache".DS);
define("_CUSTOM_ROUTES_FILE", _APP_PATH."configuration".DS."routes.json");
define("_LANGUAGES_FILE", _APP_PATH."configuration".DS."languages.json");
define("_MEDIA_PATH", _PUBLIC_PATH."media".DS);
define("_MEDIA_FOLDER", DS."media".DS);
define("_CSS_FOLDER", DS."css".DS);
define("_JS_FOLDER", DS."js".DS);
define("_LOG_FILE", _LOGS_PATH."app.log");
// MAIN WEBSITE PATHS --------------------------------------
define("_RFM_BASE_URL","");
define("_RFM_UPLOAD_DIR","/media/");
define("_RFM_CURRENT_PATH","..".DS."..".DS."..".DS._RFM_UPLOAD_DIR);
define("_RFM_BASE_PATH","");
define("_MAIN_WEBSITE_JSON_MODELS_PATH", "");
define("_MAIN_WEBSITE_PUBLIC_PATH", "");
define("_MAIN_WEBSITE_MEDIA_PATH", "");

// Settings Array -----------------------------------------
$settings = [
    "project_name" => _PROJECT_NAME,
    "project_domain" => _PROJECT_DOMAIN,
    "project_version" => _PROJECT_VERSION,
    "project_protocol" => _WEB_PROTOCOL,
    "project_url" => _PROJECT_URL,
    "project_cdn_url" => _PROJECT_CDN_URL,
    "pagination" => _PAGINATION,
    "api_key" => "xxx",
    "encryption_key" => "xxx",
    "db_master" => [
        "db_provider" => "mysql",
        "db_host" => "localhost",
        "db_port" => 3306,
        "db_database" => "db_database_here",
        "db_user" => "db_username_here",
        "db_password" => base64_encode("db_password_here"),
        "db_table_prefix" => "",
        "db_table_suffix" => "_tbl",
        "db_table_languages_suffix" => "_lang",
        "default" => true
    ],
    "mail"=> [
        "enable_email" => false,
        "smtp_auth" => true,
        "password"=> base64_encode("xxx"),
        "template"=> _TEMPLATE_PATH."email_template.html"
    ],
    "slack"=> [
        "enable_slack" => false,
        "orders_hook" => ""
    ],
    "pdf"=> [
        "enable_pdf" => false
    ],
    "graphs" => [
        "overview_title" => "MIS Key Deliverables — next six months",
        "members_title" => "Per RCC / Division User",
        "members_ranking" => "RCC / Division User Ranking",
        "members_overall_tasks" => "Overall Activities by RCC / Division User",
        "pillar_title" => "Goal",
        // overview_link was missing entirely. Six drill-down views read it for their
        // "Overview" breadcrumb, so the link rendered as /projects_graphs/ and the
        // router answered 501. The views already prefix "projects_graphs/", so this
        // value is the action name alone.
        "overview_link" => "overview",
        // Africa CDC vocabulary for the hierarchy levels, used by the breadcrumbs.
        "objective_title" => "Objective",
        "programme_title" => "Programme",
        "project_title" => "Project"
    ]

];


// Set time -----------------------------------------------
date_default_timezone_set("Europe/Athens");
// Error reporting ----------------------------------------
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors',_DEBUG_MODE);
ini_set('display_startup_errors',_DEBUG_MODE);

// Local development overrides ----------------------------
// Optional, and absent in production. Lets a developer point the app at a local
// database without editing the credentials above and risking committing them.
// See tools/dev/README.md. Keep settings.local.php out of version control.
if (file_exists(__DIR__ . DS . "settings.local.php")) {
    require __DIR__ . DS . "settings.local.php";
}

