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
        $data = [];
        $data['commit_version'] = get_current_git_commit();
        $data['hostname'] = trim(exec('uname -n'));
        $data['system'] = trim(exec('uname -s'));
        $data['kernel'] = trim(exec('uname -r'));
        $data['architecture'] = trim(exec('uname -m'));
        $data['cpu_number'] = trim(exec('cat /proc/cpuinfo | grep vendor | wc -l'));
        $data['cpu_vendor'] = trim(exec('cat /proc/cpuinfo | grep vendor_id | head -1 | awk \'{print $3}\''));
        $data['cpu_model'] = trim(exec('cat /proc/cpuinfo | grep \'model name\' | head -1 | awk \'{print $4,$5,$6,$7}\''));
        $data['cpu_frequency'] = trim(exec('cat /proc/cpuinfo | grep \'cpu MHz\' | head -1 | awk \'{print $4,"MHz"}\''));
        $data['cpu_cache'] = trim(exec('cat /proc/cpuinfo | grep \'cache size\' | head -1 | awk \'{print $4,$5}\''));
        $data['memory_total'] = trim(exec('free -m | grep Mem | awk \'{print $2, "MB"\'}'));
        $data['memory_used'] = trim(exec('free -m | grep Mem | awk \'{print $3, "MB"\'}'));
        $data['memory_free'] = trim(exec('free -m | grep Mem | awk \'{print $4, "MB"\'}'));
        $data['swap_total'] = trim(exec('free -m | grep Swap | awk \'{print $2, "MB"\'}'));
        $data['swap_used'] = trim(exec('free -m | grep Swap | awk \'{print $3, "MB"\'}'));
        $data['swap_free'] = trim(exec('free -m | grep Swap | awk \'{print $4, "MB"\'}'));
        $data['os_version'] = trim(exec('lsb_release -ds'));
        $data['http_version'] =  trim($_SERVER['SERVER_SOFTWARE']);
        $data['mysql_version'] = trim($this->DB->MQ("SELECT VERSION()", "one")['VERSION()']);
        $data['php_version'] = trim(phpversion());
//        $data['typesense_version'] = trim(exec('typesense --version'));
        $data['git_version'] = trim(exec("git --version | awk '{print $3}'"));
        $data['uptime'] = trim(exec('uptime'));
        $data['date'] = trim(exec('date'));
        $data['tz'] = trim(date_default_timezone_get());
        $data['php'] = ini_get_all(null, false);
        $this->render($data);
    }

    public function about(){
        $this->render();
    }

    public function typesense(){
        $this->render($data);
    }
}