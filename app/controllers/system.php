<?php
require_once _CONTROLLERS_PATH."core.php";
class systemController extends coreController {

    protected $unprotected = ["login", "recover_password"];

    public function login() {
        if($this->isLoggedIn()){
            redirect($this->L("dashboard"));
        } else {
            $this->generateCSRFtoken();
            $this->partial_render();
        }
    }

    public function dashboard(){
        if($this->isLoggedIn()) {
            if(file_exists(_MODELS_SETTINGS_PATH."dashboard.json")){
                $data['settings'] = readJSONFile(_MODELS_SETTINGS_PATH."dashboard.json");
                if((isset($data['settings']['latest_list']))&&($data['settings']['latest_list']['active'])) {
                    $query = "select " . implode(",", array_keys($data['settings']['latest_list']['fields'])) . " from "
                        . $data['settings']['latest_list']['tablename'] . " where " . $data['settings']['latest_list']['where'] . " order by "
                        . $data['settings']['latest_list']['order_by'] . " limit " . $data['settings']['latest_list']['limit'];
                    $data['latest_data'] = $this->DB->MQ($query, "all");
                }
                if((isset($data['settings']['graph']))&&($data['settings']['graph']['active'])) {
                    $query = $data['settings']['graph']['query'];
                    $data['graph'] = $this->DB->MQ($query, "all");
                    $this->AddJS("/vendor/raphael/raphael.js");
                    $this->AddCSS("/vendor/morris/morris.css");
                    $this->AddJS("/vendor/morris/morris.js");
                    $this->AddJS("/js/widget_dashboard_graph.js");
                }
            } else {
                $data['settings'] = [];
            }
            $this->render($data);
        } else {
            redirect($this->L("login"));
        }
    }

    public function recover_password(){
        $this->partial_render();
    }

    public function info(){
        // No shell: exec() and friends are disabled for web requests
        // (docker/php-fpm-pool.conf), and the old uname/free/lsb_release
        // pipeline made this page fatal. Everything here comes from PHP itself.
        $data = [];
        $data['commit_version'] = defined('_CURRENT_COMMIT') ? _CURRENT_COMMIT : '';
        $data['hostname'] = php_uname('n');
        $data['system'] = php_uname('s');
        $data['kernel'] = php_uname('r');
        $data['architecture'] = php_uname('m');
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $data['load_average'] = is_array($load) ? implode(' / ', array_map(fn($v) => number_format((float)$v, 2, '.', ''), $load)) : 'n/a';
        $data['memory_limit'] = ini_get('memory_limit');
        $data['memory_used'] = number_format(memory_get_usage(true) / 1048576, 1, '.', '') . ' MB (this request)';
        $data['os_version'] = PHP_OS_FAMILY;
        $data['http_version'] =  trim((string)($_SERVER['SERVER_SOFTWARE'] ?? ''));
        $data['mysql_version'] = trim((string)($this->DB->MQ("SELECT VERSION()", "one")['VERSION()'] ?? ''));
        $data['php_version'] = trim(phpversion());
        $data['date'] = date('Y-m-d H:i:s');
        $data['tz'] = trim(date_default_timezone_get());
        // Administrators only (see protectedController::$access). The full
        // ini_get_all() dump - every path and disabled function - is not
        // needed for diagnostics; the handful that matter are listed.
        $data['php'] = [];
        foreach (['memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'date.timezone', 'opcache.enable'] as $k) {
            $data['php'][$k] = ini_get($k);
        }
        $this->render($data);
    }

    public function about(){
        $this->render();
    }

    public function typesense(){
        $this->render($data);
    }
}