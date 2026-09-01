<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 31/3/2017
 * Time: 2:41 μμ
 */
#[AllowDynamicProperties]
class vanillaController {
    protected $R;
    protected $S;
    protected $query;
    protected $parts;
    protected $DB;

    public function __construct(Registry $registry) {
        $this->sessionID = session_id();
        $this->R = $registry;
        $this->S = $this->R->settings;

        $this->parts = $this->R->url['parts'];
        $this->query = $this->R->url['query'];
        $this->payload = $this->R->url['payload'];
        $this->DB = $this->R->{$this->R->defaultDB};
        $this->lang = $this->R->url['language']['lang'];
        $this->langid = $this->R->url['language']['langid'];
        $this->url = $this->R->url['request_uri'];
        $this->website = (file_exists(_JSON_MODELS_PATH."data.website.json")) ? readJSONFile(_JSON_MODELS_PATH."data.website.json"): [];
        $this->translations = (file_exists(_JSON_MODELS_PATH."data.translations.json")) ? readJSONFile(_JSON_MODELS_PATH."data.translations.json") : [];
        $chosen_menu = file_exists(_MENUS_PATH."custom_menu.json") ? "custom_menu.json" : "ce_menu.json";
        $this->main_menu = file_exists(_MENUS_PATH.$chosen_menu) ? readJSONFile(_MENUS_PATH.$chosen_menu) : [];
        $this->JS = [];
        $this->CSS = [];
        $this->check_DRM();
    }

    public function render($data = [], $mode = "html", $error = false, $template = "template" ) {
        if ($mode=="html") {
            if (!$error) {
//                $controller = $force_controller ?? $this->R->url['controller'];
//                $viewPath = _VIEWS_PATH.$controller.DS.$this->R->url['action'].".php";
                $viewPath = _VIEWS_PATH.$this->R->url['controller'].DS.$this->R->url['action'].".php";
            } else {
                $viewPath = _VIEWS_PATH."error".DS."index".".php";
            }

            if (file_exists($viewPath)){
                include _TEMPLATE_PATH.$template.".php";
            }
        }
        if ($mode=="json") {
            header("Content-Type: application/json;charset=utf-8");
            print json_encode($data);
        }
        exit();
    }

    public function partial_render($data = [], $mode = "html", $template = "", $error = false) {
        if ($mode=="html") {
            if (!$error) {
                if($template==""){
                    $viewPath = _VIEWS_PATH.$this->R->url['controller'].DS.$this->R->url['action'].".php";
                } else {
                    $viewPath = _VIEWS_PATH.$this->R->url['controller'].DS.$template.".php";
                }
            } else {
                $viewPath = _VIEWS_PATH."error".DS."index".".php";
            }

            if (file_exists($viewPath)){
                include _TEMPLATE_PATH."template_empty.php";
            }
        }
        if ($mode=="json") {
            header("Content-Type: application/json;charset=utf-8");
            print json_encode($data);
        }
        exit();
    }

    public function sanitize($data, $arguements) {
        return filter_var_array($data, $arguements);
    }

    public function mapRoute($route) {
        $parts = explode("/", $route);
        foreach ($parts as $partindex=>$part) {
            if(isset($this->parts[$partindex])){
                $this->parts[$part] = $this->parts[$partindex];
                unset($this->parts[$partindex]);
            }
        }
        $this->R->url['parts'] = $this->parts;
    }

    public function checkMethod($http_method='GET') {
        if($http_method!=$this->R->url['http_method']) {
            $this->setAnswer(405, "Endpoint accepts only '".$http_method."' requests");
        }
    }

    public function checkRequired($fields, $check_against) {
        foreach ($fields as $field){
            if(!array_key_exists($field, $check_against)) {
                $this->setAnswer(422, "Parameters are missing");
            }
        }
    }

    function setAnswer($code, $message, $data=[], $mode = _SET_ANSWER_MODE, $compatibility = _SET_ANSWER_COMPATIBILITY) {
        if($mode=="template"){
            if($this->isLoggedIn()){
                $viewPath = _TEMPLATE_PATH."template_setAnswer_protected.php";
                include _TEMPLATE_PATH."template.php";
            } else {
                $viewPath = _TEMPLATE_PATH."template_setAnswer_unprotected.php";
                include $viewPath;
            }
        }
        if($mode=="ce"){
            $answer_template = _VIEWS_PATH."ngine".DS."ce_setAnswer.php";
            if (file_exists($answer_template)){
                include $answer_template;
            }
        }
        if($mode=="json"){
            $actual_code = ($compatibility=="app") ? 200 : $code; // apps handle only 200
            $answer = [
                "status" => getHTTPcode($code),
                "code" => $code,
                "message" => $message,
                "generationTime" => getEndTime(),
                "data" => $data
            ];
            if (_LOGGING){
                writeToLog($_SERVER['REMOTE_ADDR']." | ".$_SERVER['HTTP_USER_AGENT']." | ".$code." | ".$message);
            }
            header("Content-Type: application/json;charset=utf-8");
            http_response_code($actual_code);
            print json_encode($answer);
        }
        exit();
    }

    // translation
    public function T($key){
        return $this->translations['languages'][$this->lang][$key];
    }

    // link
    public function L($destination){
        return _MULTILINGUAL ? "/".$this->lang."/".$destination : "/".$destination;
    }

    public function GoBack(){
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->L("/");
    }

    public function AddCSS($file){
        $this->CSS[] = $file;
    }

    public function AddJS($file){
        $this->JS[] = $file;
    }

    public function AutoInclude($includes){
        if(isset($includes['js'])){
            foreach ($includes['js'] as $file){
                $this->AddJS($file);
            }
        }
        if(isset($includes['css'])){
            foreach ($includes['css'] as $file){
                $this->AddCSS($file);
            }
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user']);
    }

    public function setUser($user) {
        unset($user['password']);
        unset($user['active']);
//        unset($user['validated']);
//        unset($user['uniqueid']);
        $_SESSION['user'] = $user;
    }

    function checkCSRF($token){
        if (($token!=$_SESSION['token'])||time()>$_SESSION['token_expiry']){
            $this->setAnswer(403, "Invalid CSRF token. Try to login within one minute.");
        }
    }

    function generateCSRFtoken(){
        if (empty($_SESSION['token'])||time()>$_SESSION['token_expiry']) {
            $_SESSION['token'] = bin2hex(random_bytes(35));
            $_SESSION['token_expiry'] = time()+_CSRF_EXPIRY;
        } else {
            $_SESSION['token_expiry'] = time()+_CSRF_EXPIRY;
        }
        return $_SESSION['token'];
    }

    protected function checkAuthorization(){
        if(isset($_SERVER['HTTP_AUTHORIZATION'])){
            if (preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                $bearer = $matches[1];
                $key = $this->CE_decrypt($bearer);
                if($key != $this->S["api_key"]){
                    $this->setAnswer(401, "Invalid credentials");
                }
            }
        } else {
            $this->setAnswer(401, "Authorization headers not found");
        }
    }

    protected function CE_encrypt($data){
//        $iv_size = openssl_cipher_iv_length('aes-256-ctr');
//        $iv = openssl_random_pseudo_bytes($iv_size);
//        $encryptedMessage = strtr(base64_encode(openssl_encrypt($data, 'aes-256-ctr', hash('sha256', $this->S["encryption_key"], true), 0, $iv)), '+/=', '-_,');
//        return $iv.$encryptedMessage;

        return strtr(base64_encode(openssl_encrypt($data, 'aes-256-ctr', hash('sha256', $this->S["encryption_key"], true), 0, substr($this->S["encryption_key"],0,16))), '+/=', '-_,');
    }

    protected function CE_decrypt($data){
//        $iv_size = openssl_cipher_iv_length('aes-256-ctr');
//        $iv = substr($data, 0, $iv_size);
//        $decryptedMessage = openssl_decrypt(base64_decode(strtr(substr($data, $iv_size), '-_,', '+/=')), 'aes-256-ctr', hash('sha256', $this->S["encryption_key"], true), 0, $iv);
//        return trim($decryptedMessage);

        return trim(openssl_decrypt(base64_decode(strtr($data, '-_,', '+/=')), 'aes-256-ctr', hash('sha256', $this->S["encryption_key"], true), 0, substr($this->S["encryption_key"],0,16)));
    }

    protected function check_DRM(){
        if(_DRM_ACTIVE){
            $drm_settings = readJSONFile(_DRM_FILE);
            $group = $_SESSION['user']['group'] ?? null;
            $controller = $this->R->url['controller'];
            if(isset($group['id'])){
                if(isset($drm_settings[$group['id']])){
                    $active_drm_full = $drm_settings[$group['id']];
                } else {
                    $active_drm_full = $drm_settings['default'];
                }
                if(isset($active_drm_full['controllers'][$controller])){
                    $active_drm_controller = $active_drm_full['controllers'][$controller];
                } else {
                    $active_drm_controller = $active_drm_full['controllers']['default'];
                }
                if(!$active_drm_controller){
                    $this->setAnswer(403, "You do not have the appropriate access rights.");
                }
            }
        }
    }

    protected function _sendEmail($email_template, $recipients, $fields, $attachment = ""){
        $email_texts = readJSONFile(_JSON_MODELS_PATH."data.email.json", _DEFAULT_LANGUAGE);
        $mailService = new Mail($this->S['mail']);
        $email['subject'] = $email_texts[$email_template.'_title'];
        $email['from'] = $email_texts['from_email'];
        $email['from_name'] = $email_texts['from_name'];
        $email['recipients'] = $recipients;
        $email['parameters']['preview_text'] = $email_texts[$email_template.'_preview_text'];
        $email['parameters']['text'] = $email_texts[$email_template.'_text'];
        $email['parameters']['footer'] = "<img width='150' src='"._PROJECT_URL."/".$email_texts['logo']."'>";
        //parameters from now on are per case------------------
        foreach ($fields as $field => $value){
            $email['parameters'][$field] =  $value;
        }
        if($attachment!=""){
            $email['attachment'] = $attachment;
        }
        return $mailService->sendMail($email);
    }

    protected function prepare_edit_mode(){
        require_once _INCLUDES_PATH."list_builder.php";
        require_once _INCLUDES_PATH."form_builder.php";
        $this->AddCSS("/vendor/select2/css/select2.css");
        $this->AddCSS("/vendor/select2-bootstrap-theme/select2-bootstrap.min.css");
        $this->AddCSS("/vendor/bootstrap-multiselect/css/bootstrap-multiselect.css");
        $this->AddCSS("/vendor/jquery-ui-timepicker/jquery-ui-timepicker-addon.css");
        $this->AddCSS("/vendor/datatables/media/css/dataTables.bootstrap5.css");
		$this->AddJS("/vendor/jquery-ui-timepicker/jquery-ui-timepicker-addon.js");
        $this->AddJS("/vendor/datatables/media/js/jquery.dataTables.min.js");
        $this->AddJS("/vendor/datatables/media/js/dataTables.bootstrap5.min.js");
        $this->AddJS("/vendor/select2/js/select2.js");
        $this->AddJS("/vendor/bootstrapv5-multiselect/js/bootstrap-multiselect.js");
        $this->AddJS("/vendor/jquery-maskedinput/jquery.maskedinput.js");
        $this->AddJS("/vendor/tinymce/js/tinymce/tinymce.min.js");
        $this->AddJS("/js/ce.js");
    }

    protected function update_redirect(){
        return $_SESSION['user']['settings']['update_redirect'] ?? "db_edit";
    }
}