<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 2/3/2017
 * Time: 12:17 μμ
 */

#[AllowDynamicProperties]
class Router {
    protected $R;

    function getPath($url) {
        $req_path = parse_url($url)['path'];
        $parts = explode('/', $req_path);
        array_shift($parts);
        if(_MULTILINGUAL){
            $controller_index = 1;
            $action_index = 2;
            $id_index = 3;
        } else {
            $controller_index = 0;
            $action_index = 1;
            $id_index = 2;
        }
        if (_MULTILINGUAL&&((!isset($parts[0]))||($parts[0] == ""))) { $parts[0]=_DEFAULT_LANGUAGE; $this->redirect("/".implode("/", $parts));}
        if (!empty($parts[$controller_index])) {
            $customController = $parts[$controller_index];
            if(array_key_exists($parts[$controller_index], $this->customRoutes)){
                $parts[$controller_index] = $this->customRoutes[$customController]['controller'];
                array_splice( $parts, $action_index, 0, $this->customRoutes[$customController]['action'] ); // splice in at position 3
                if(isset($this->customRoutes[$customController]['id'] )){
                    array_splice( $parts, $id_index, 0, $this->customRoutes[$customController]['id'] ); // splice in at position 4
                }
            }
        } else {
            $parts[$controller_index] ="index";
        }// 0+ for one language, 1+ for multilingual
        if ((!isset($parts[$action_index]))||($parts[$action_index] == "")) { $parts[$action_index]= "index";}
        return $parts;
    }

    function getQueryString() {
        return $_REQUEST;
    }

    function getLanguage($path) {
        if(_MULTILINGUAL){
            $lang_var = filter_var(trim($path[0]), FILTER_UNSAFE_RAW);// 0 for one language, 1 for multilingual
            if (isset($this->languages[$lang_var])) {
                return $this->languages[$lang_var];
            } else {
                return $this->languages[_DEFAULT_LANGUAGE];
                //todo better handling
            }
        } else {
            return $this->languages[_DEFAULT_LANGUAGE];
        }
    }

    function getControllerFile($path) {
        $lang_index = _MULTILINGUAL ? 1 : 0;
        return filter_var(trim($path[$lang_index]), FILTER_UNSAFE_RAW);// 0 for one language, 1 for multilingual
    }

    function getAction($path) {
        $lang_index = _MULTILINGUAL ? 2 : 1;
        return filter_var(trim($path[$lang_index]), FILTER_UNSAFE_RAW);// 2 for one language, 2 for multilingual
    }

    function getCustomRoutes(){
        return readJSONFile(_CUSTOM_ROUTES_FILE);
    }

    function getWebsiteLanguages(){
        return readJSONFile(_LANGUAGES_FILE);
    }

    function load($registry) {
        $class = trim($registry->url['controller'])."Controller";
        if (file_exists(_CONTROLLERS_PATH. $registry->url['controller'].".php")) {
            require_once _CONTROLLERS_PATH . $registry->url['controller'] . ".php";
            $controller = new $class($registry);
        } else {
			include _CONTROLLERS_PATH . "error" . ".php";
			$controller = new errorController($registry);
            // A generic 404: the requested path is not echoed back.
            $controller->index(["code" => 404, "message" => "The page you asked for does not exist."]);
        }

        if (!method_exists($controller, $registry->url['action'])){
			include _CONTROLLERS_PATH . "error" . ".php";
			$controller = new errorController($registry);
            // A generic 404: the requested path is not echoed back.
            $controller->index(["code" => 404, "message" => "The page you asked for does not exist."]);
        }
        $controller->{$registry->url['action']}();
    }

    function redirect($url){
        header('Status: 301 Moved Permanently', true);
        header('Location: ' . $url, true, 301);
        exit();
    }

    function __construct(Registry $registry){
        $this->R = $registry;
        $this->customRoutes = $this->getCustomRoutes();
        $this->languages = $this->getWebsiteLanguages();
        $path = $this->getPath($_SERVER['REQUEST_URI']);
        $offset = _MULTILINGUAL ? 3 : 2;
        $path_array = [
            "session_id"=> session_id(),
            "request_uri" => $_SERVER['REQUEST_URI'],
            "language" =>  $this->getLanguage($path),
            "controller" => $this->getControllerFile($path),
            "action" => $this->getAction($path),
            "parts" => array_filter(array_slice($path, $offset)),
            "query" => $this->getQueryString(),
            "http_method" => $_SERVER['REQUEST_METHOD'],
            "payload" => file_get_contents('php://input')
        ];
        $this->R->__set("url", $path_array);

        $this->R->__set("languages", $this->languages);
        $this->load($this->R);
    }
}

