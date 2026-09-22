<?php
require_once __DIR__ . '/import.php';
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 2/3/2017
 * Time: 12:39 μμ
 */

function debug($message) {
	print "<pre>";
	print_r($message);
	print "</pre>";
}

function array2str($data){
    if(is_array($data)){
        $output = "";
            foreach ($data as $line) {
                $output .= "$line\n";
            }
    } else {
        $output = $data;
    }
    return $output;
}

function getHTTPcode($code){
	$http_codes = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Switch Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Unordered Collection',
		426 => 'Upgrade Required',
		449 => 'Retry With',
		450 => 'Blocked by Windows Parental Controls',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		509 => 'Bandwidth Limit Exceeded',
		510 => 'Not Extended'
	);
	return $http_codes[$code];
}

function CE_ErrorHandler($errno, $errstr, $errfile, $errline) {
    global $registry;
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }

// $errstr may need to be escaped:
    $errstr = htmlspecialchars($errstr);

    $text = "Error: <b>".$errno."</b> - Message: <b>".$errstr."</b><hr>Found on line <b>".$errline."</b> on file ".$errfile;

    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_PARSE:
        case E_STRICT:
        case E_RECOVERABLE_ERROR:
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
        case E_USER_ERROR:
            $registry->__set("CE_Notification", [
                "title" => "<b>ERROR</b>",
                "text"=> $text,
                "type"=>"error"
            ]);
            exit(1);

        case E_WARNING:
        case E_CORE_WARNING:
        case E_USER_WARNING:
        case E_COMPILE_WARNING:
            $registry->__set("CE_Notification", [
                "title" => "<b>WARNING</b>",
                "text" => $text,
                "type" => ""
            ]);
            break;

        case E_NOTICE:
        case E_USER_NOTICE:
            $registry->__set("CE_Notification", [
                "title" => "<b>NOTIFICATION</b>",
                "text"=> $text,
                "type"=>"info"
            ]);
            break;

        default:
            $registry->__set("CE_Notification", [
                "title" => "<b>INFORMATION</b>",
                "text"=> $text,
                "type"=>"info"
            ]);
            break;
    }
    /* Don't execute PHP internal error handler */
    return true;
}

//set_error_handler(_ERROR_HANDLER);

function writeToLog($line, $logFile = _LOG_FILE) {
	$flog = fopen($logFile, 'a') or die("Cannot open log file ".$logFile.". Aborting now.");
	$entry = date("Y-m-d H:i:s")." | ".$line."\n";
	fwrite($flog, $entry);
}

function getEndTime($start = _START_TIME) {
	$end = microtime(true);
	$creationTime = ($end - $start);
	return sprintf("%.5f",$creationTime);
}

function is_set($data) {
    if ((is_array($data)&&count($data)>0)&&!empty($data)){
		return true;
	} else {
		return false;
	}
}

function get_current_git_commit( $branch='master' ){
    $answer = date("YmdHi");
    if(file_exists(sprintf(_ROOT_PATH.'.git/refs/heads/%s', $branch))){
        if ($hash = file_get_contents(sprintf(_ROOT_PATH.'.git/refs/heads/%s', $branch))) {
            return trim($hash);
        }
    }
    return $answer;
}

function clear_url($url){
	$newurl = explode("/", $url);
	array_shift($newurl);
	array_shift($newurl);
	return implode("/", $newurl);
}

function request_log($name, $request){
	$line = date("d-m-Y H:i:s")."\t".json_encode($request)."\n";
	$fp = fopen('logs/'.$name.'.log', 'a');
	fwrite($fp, $line);
	fclose($fp);
}

function readJSONFile($filename, $language=""){
    if(file_exists($filename)){
        $json = file_get_contents($filename);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
        $reply = json_decode($json, true);
        if(isset($reply['languages'])&&($language!="")){
            $reply = array_merge($reply, $reply['languages'][$language]);
            unset($reply['languages']);
        }
        return $reply;
    } else {
        return [];
    }
}

function JSON_reply($code, $message, $data=[]) {
    $answer = [
        "status" => getHTTPcode($code),
        "code" => $code,
        "message" => $message,
        "generationTime" => getEndTime(),
        "data" => $data
    ];

    header("Content-Type: application/json;charset=utf-8");
    http_response_code($code);
    print json_encode($answer);
    exit();
}


function json_to_db($json_array) {
    return addslashes(json_encode($json_array,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function json_from_db($json_data){
    $json = preg_replace('/[[:cntrl:]]/', '',$json_data);
    return json_decode($json, true);
}

function load_plugins($category, $active){
    $path = _PLUGINS_PATH.$category.DS;
    $result = [];
    foreach ($active as $plugin){
        include $path.$plugin.".php";
        $function = "get_".$plugin."_info";
        $result[] = $function();
    }
    return $result;
}

function load_plugin($category, $plugin){
    $path = _PLUGINS_PATH.$category.DS;

    require_once $path.$plugin.".php";
    $function = "get_".$plugin."_info";
    return $function();
}

function nameSort($a,$b){
	return ($a["name"] <= $b["name"]) ? -1 : 1;
}

function redirect($url) {
	header('Status: 302 Found', false);
	header('Location: ' . $url, true, 302);
	exit();
}

function clear_cache(){
    header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
    header("Last-Modified: " . date("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

// Escapes for HTML. No stripslashes(): values are stored exactly as typed
// (writes are bound, not addslashes()d), so stripping here ate a backslash a
// user typed - N\A, a path - and then lost it for good on the next save.
// ENT_QUOTES so the result is also safe inside single-quoted attributes.
function display($text = ""){
    $text = is_null($text) ? "" : (string)$text;
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function display_from_db($text = ""){
    return ($text!="") ? htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8') : "N/A";
}

// Every percentage on the graph pages goes through here, so the number
// format is decided once. English locale, two decimals, no thousands
// separator - "12.50", never "12,50" (the comma was a leftover of the
// vendor's Greek locale; budgets already print "USD 0.00").
function pct($value){
    return number_format((float)$value, 2, '.', '');
}

// The per-request CSP nonce. nginx generates it ($request_id), sends it in
// the Content-Security-Policy header and hands the same value to PHP as
// CSP_NONCE; every inline script tag carries nonce=(this value) so the
// policy can drop 'unsafe-inline'. Empty outside nginx (the dev server),
// where no CSP is sent either. (Never write a PHP close tag in a comment:
// the first version of this note did, and PHP ended the file right there.)
function csp_nonce(){
    return htmlspecialchars((string)($_SERVER['CSP_NONCE'] ?? ''), ENT_QUOTES, 'UTF-8');
}

function display_weight($text = "", $in="kg"){
    $answer = "N/A";
    if($in=="kg"){
        $answer = round((float)$text/1000, 2, PHP_ROUND_HALF_UP)." kg";
    } else {
        $answer = $text. " gr";
    }
    return $answer;
}

function display_array($value){
//    $array = json_from_db($value);
//    $answer  = "<ul>";
//    foreach ($array as $key){
//        $answer .= "<li>".$key."</li>";
//    }
//    return $answer."</ul>";
    return $value;
}

function display_time($value, $format = "d/m/Y @ H:i"){
    return ($value!==NULL) ? date($format, strtotime($value)) : "N/A";
}

function display_universal_time($value, $format = "Y-m-d H:i:s"){
    $dateTime = DateTime::createFromFormat($format, $value); // Create a DateTime object from the standard string time
    return $dateTime->format(DateTime::RFC3339);
}


function display_price($value, $decimals=2, $comma = ",", $thousands = "."){
    return number_format((float)$value, $decimals, $comma, $thousands);
}

function display_price_currency($value, $decimals=2, $comma = ",", $thousands = "."){
    return "&euro; ".number_format((float)$value, $decimals, $comma, $thousands);
}

function display_generation_time(){
    $creationTime = (microtime(true) - _START_TIME);
    return printf("%.5f",$creationTime);
}

function display_image($height, $width, $filename){
    if($filename!=""){
        $path_parts = pathinfo($filename);

        $file_type = ".".$path_parts['extension'];
        $prefix = $height . "_" . $width . "_";
        $new_filename = $prefix . $path_parts['filename'] . $file_type;

        $cachedir=_CACHE_PATH.$path_parts['dirname'].DS;
        if (!file_exists($cachedir)) {
            mkdir($cachedir, 0755, true);
        }

        $cache_actual_file = $cachedir.$new_filename;
        $return_cache_file = _CACHE.$path_parts['dirname'].DS.$new_filename;

        if (file_exists($cache_actual_file)){
            return $return_cache_file;
        } else {
            return "/ngine_image.php?w=" . $width . "&h=" . $height . "&f=" . $filename;
        }
    }
}

function display_image_resize($width, $filename){
    if($filename!=""){
        $path_parts = pathinfo($filename);
        $file_type = ".jpg";
        $prefix = $width . "_";
        $new_filename = $prefix . $path_parts['filename'] . $file_type;

        $cachedir = _CACHE_PATH . $path_parts['dirname'] . DS;
        if (!file_exists($cachedir)) {
            mkdir($cachedir, 0755, true);
        }

        $cache_actual_file = $cachedir . $new_filename;
        $return_cache_file = _CACHE . $path_parts['dirname'] . DS . $new_filename;

        if (file_exists($cache_actual_file)) {
            return $return_cache_file;
        } else {
            return "/ngine_resize.php?w=" . $width . "&f=" . $filename;
        }
    }
}

//function displayimage_wm($height, $width, $wm_type, $filename) {
//	$file_name = $filename;
//	$crop_height = $height;
//	$crop_width = $width;
//	$prefix = "wm_" . $wm_type . "_";
//	$path_parts = pathinfo($file_name);
//	$file_type = $path_parts['extension'];
//
//	$cachedir = "cache/" . $path_parts['dirname'] . "/";
//	if (!file_exists($cachedir)) {
//		mkdir($cachedir, 0755, true);
//	}
//	$cachefile = $cachedir . $prefix . $crop_height . "_" . $crop_width . "_" . $path_parts['filename'] . ".jpg";
//	if (file_exists($cachefile))
//		return "/".$cachefile;
//	else
//		return "/ngine_crop_wm.php?w=" . $width . "&h=" . $height . "&t=" . $wm_type . "&f=" . $filename;
//}
//


//function displayimage_resize_wm($width, $wm_type, $filename) {
//	$file_name = $filename;
//	$crop_width = $width;
//	$prefix = "wm_" . $wm_type . "_";
//	$path_parts = pathinfo($file_name);
//	$file_type = $path_parts['extension'];
//
//	$cachedir = "cache/" . $path_parts['dirname'] . "/";
//	if (!file_exists($cachedir)) {
//		mkdir($cachedir, 0755, true);
//	}
//	$cachefile = $cachedir . $prefix . $crop_width . "_" . $path_parts['filename'] . ".jpg";
//	if (file_exists($cachefile))
//		return "/".$cachefile;
//	else
//		return "/ngine_resize_wm.php?w=" . $width . "&t=" . $wm_type . "&f=" . $filename;
//}
//
//function fb_wm($height, $width, $filename) {
//    $prefix = "wm_".$height . "_" . $width . "_";
//    $path_parts = pathinfo($filename);
//    $file_type = ".".$path_parts['extension'];
//    $cachedir = "cache". DS . $path_parts['dirname'] . DS;
//    if (!file_exists($cachedir)) {
//        mkdir($cachedir, 0755, true);
//    }
//    $cachefile = $cachedir . $prefix . $path_parts['filename'] . $file_type;
//    if (file_exists($cachefile))
//        return "/".$cachefile;
//    else
//        return "/ngine_image.php?w=" . $width . "&h=".$height."&t=1&f=" . $filename;
//}

function sluggify($str, $options = []){
	// Make sure string is in UTF-8 and strip invalid UTF-8 characters
	$str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
	$defaults = [
		'delimiter' => '-',
		'limit' => null,
		'lowercase' => true,
		'replacements' => [],
		'transliterate' => true,
	];
	// Merge options
	$options = array_merge($defaults, $options);
	$char_map = [
		// Latin
		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
		'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
		'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
		'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
		'ß' => 'ss',
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
		'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
		'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
		'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
		'ÿ' => 'y',
		// Latin symbols
		'©' => '(c)',
		// Greek
		'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'I', 'Θ' => '8',
		'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O', 'Π' => 'P',
		'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'O',
		'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'O', 'Ϊ' => 'I',
		'Ϋ' => 'Y',
		'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'i', 'θ' => '8',
		'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'π' => 'p',
		'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'u', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'o',
		'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'u', 'ή' => 'h', 'ώ' => 'o', 'ς' => 's',
		'ϊ' => 'i', 'ΰ' => 'u', 'ϋ' => 'u', 'ΐ' => 'i',
		// Turkish
		'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
		'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',
		// Russian
		'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
		'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
		'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
		'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
		'Я' => 'Ya',
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
		'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
		'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
		'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
		'я' => 'ya',
		// Ukrainian
		'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
		'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',
		// Czech
		'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
		'Ž' => 'Z',
		'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
		'ž' => 'z',
		// Polish
		'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
		'Ż' => 'Z',
		'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
		'ż' => 'z',
		// Latvian
		'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
		'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
		'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
		'š' => 's', 'ū' => 'u', 'ž' => 'z'
	];
	// Make custom replacements
	$str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
	// Transliterate characters to ASCII
	if ($options['transliterate']) {
		$str = str_replace(array_keys($char_map), $char_map, $str);
	}
	// Replace non-alphanumeric characters with our delimiter
	$str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
	// Remove duplicate delimiters
	$str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
	// Truncate slug to max. characters
	$str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
	// Remove delimiter from ends
	$str = trim($str, $options['delimiter']);
	return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
}

function grstrtoupper($string) {

    $latin_check = '/[\x{0030}-\x{007f}]/u';

    if (preg_match($latin_check, $string))
    {

        $string = strtoupper($string);

    }

    $letters  								= array('α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω');
    $letters_accent 						= array('ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ');
    $letters_upper_accent 					= array('Ά', 'Έ', 'Ή', 'Ί', 'Ό', 'Ύ', 'Ώ');
    $letters_upper_solvents 				= array('ϊ', 'ϋ');
    $letters_other 							= array('ς');

    $letters_to_uppercase					= array('Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ', 'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ', 'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ', 'Ψ', 'Ω');
    $letters_accent_to_uppercase 			= array('Α', 'Ε', 'Η', 'Ι', 'Ο', 'Υ', 'Ω');
    $letters_upper_accent_to_uppercase 		= array('Α', 'Ε', 'Η', 'Ι', 'Ο', 'Υ', 'Ω');
    $letters_upper_solvents_to_uppercase 	= array('Ι', 'Υ');
    $letters_other_to_uppercase 			= array('Σ');

    $lowercase = array_merge($letters, $letters_accent, $letters_upper_accent, $letters_upper_solvents, $letters_other);
    $uppercase = array_merge($letters_to_uppercase, $letters_accent_to_uppercase, $letters_upper_accent_to_uppercase, $letters_upper_solvents_to_uppercase, $letters_other_to_uppercase);

    $uppecase_string = str_replace($lowercase, $uppercase, $string);

    return $uppecase_string;

}

function _getCountryByShort($short, $lang) {
    $countries = readJSONFile(_JSON_MODELS_PATH."countries_".$lang.".json");
    foreach ($countries as $country) {
        if (strtoupper($country['alpha2']) == strtoupper($short)) {
            return $country['name'];
        }
    }
    return '';
}

function uniqidReal($lenght = 13) {
    // uniqid gives 13 chars, but you could adjust it to your needs.
    if (function_exists("random_bytes")) {
        $bytes = random_bytes(ceil($lenght / 2));
    } elseif (function_exists("openssl_random_pseudo_bytes")) {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
    } else {
        throw new Exception("no cryptographically secure random function available");
    }
    return substr(bin2hex($bytes), 0, $lenght);
}

function sortByLength( $arr1, $arr2 ){
    $c1 = count($arr1['children']);
    $c2 = count($arr2['children']);
    return ($c1 > $c2 ? -1 : $c1 == $c2) ? 0 : 1;
}

function generateMenu($elements, $parent = 0){
    $menu = [];
    foreach($elements as $item) {
        if ($item["parent_id"] == $parent) {
            $menu[$item['id']] = $item;
            $menu[$item['id']]['children'] = generateMenu($elements, $item["id"]);
        }
    }
    return $menu;
}

function get_field_property($word, $field){
    if(array_key_exists($word, $field)){
        return $field[$word];
    } else {
        return false;
    }
}

function get_folder_contents($folder = "", $extensions = "jpg,gif,png", $includes = "*"){
    $files = glob($folder.$includes.".{".$extensions."}", GLOB_BRACE);
    return $files;
}

function ends_with($string, $endString){
    $len = strlen($endString);
    if ($len == 0) {
        return true;
    }
    return (substr($string, -$len) === $endString);
}

function filterData(&$str){
    $str = preg_replace("/\t/", "\\t", $str);
    $str = preg_replace("/\r?\n/", "\\n", $str);
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
}

function ce_compare_values($value1, $operator, $value2) {
    switch ($operator) {
        case '>':
            return $value1 > $value2;
        case '<':
            return $value1 < $value2;
        case '>=':
            return $value1 >= $value2;
        case '<=':
            return $value1 <= $value2;
        case '==':
            return $value1 == $value2;
        case '!=':
            return $value1 != $value2;
        case 'null':
            return is_null($value1);
        case '!null':
            return !is_null($value1);
        default:
            return false;
    }
}

function db_error($query, $error){
    // Monitoring and scripts must see the failure; the page itself stays generic.
    if (!headers_sent()) { http_response_code(500); }
    include _TEMPLATE_PATH."db_error.php";
    exit();
}

function db_esc($text){
    return addslashes(trim($text));
}

function isactive($service)
{
    $output = shell_exec("systemctl is-active $service");
    if (trim($output) == "active") {
        return true;
    } else {
        return false;
    }
}

/**
 * "Applies to" for a task: the reporting entities it counts for. An empty
 * choice means every active entity - a task that applies to nobody would be
 * invisible on Progress and count for nothing on the gauges.
 * $chosen is the posted value (array of ids, or null); returns an array of
 * string ids, the shape every seeded row uses (["1"]).
 */
function default_applies_to($db, $chosen) {
    $ids = [];
    if (is_array($chosen)) {
        foreach ($chosen as $v) { if ((string)$v !== '' && (int)$v > 0) { $ids[] = (string)(int)$v; } }
    }
    if ($ids) { return array_values(array_unique($ids)); }
    foreach ((array)$db->MQ("SELECT id FROM pm_members_tbl WHERE active = 1", "all") as $m) { $ids[] = (string)(int)$m['id']; }
    return $ids;
}

/**
 * Next code for a row whose abbreviation is empty, from where it sits:
 *   objective  -> next whole number, kept in the "N.0" form the data uses ("18.0")
 *   programme  -> parent objective's number + ".n"          ("16.2")
 *   activity   -> parent programme's numeric code + ".n"    ("16.1.6"; "7.1 PRG" counts as 7.1)
 * n is one more than the highest sibling already using that prefix; siblings
 * whose code does not follow the pattern (the AWP codes of the seeded
 * activities, "4.2.4.06.01") are ignored. "" when nothing can be derived.
 */
function auto_wbs_code($db, $model, array $row) {
    $numeric = function ($code) { return preg_match('/^\d+(?:\.\d+)*/', trim((string)$code), $m) ? $m[0] : ''; };
    if ($model === 'pm_objectives') {
        $max = 0;
        foreach ((array)$db->MQ("SELECT abbr FROM pm_objectives_tbl", "all") as $r) {
            if (preg_match('/^(\d+)/', trim((string)$r['abbr']), $m)) { $max = max($max, (int)$m[1]); }
        }
        return ($max + 1) . ".0";
    }
    if ($model === 'pm_programmes') {
        $parent = $db->MQ("SELECT abbr FROM pm_objectives_tbl WHERE id = ?", "one", [(int)($row['objective_id'] ?? 0)]);
        if (!is_set($parent) || !preg_match('/^(\d+)/', trim((string)$parent['abbr']), $m)) { return ''; }
        $prefix = $m[1];
        $rows = (array)$db->MQ("SELECT abbr FROM pm_programmes_tbl WHERE objective_id = ?", "all", [(int)$row['objective_id']]);
    } elseif ($model === 'pm_projects') {
        $parent = $db->MQ("SELECT abbr FROM pm_programmes_tbl WHERE id = ?", "one", [(int)($row['programme_id'] ?? 0)]);
        $prefix = is_set($parent) ? $numeric($parent['abbr']) : '';
        if ($prefix === '') { return ''; }
        $rows = (array)$db->MQ("SELECT abbr FROM pm_projects_tbl WHERE programme_id = ?", "all", [(int)$row['programme_id']]);
    } else {
        return '';
    }
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '\.(\d+)(?:\D|$)/', trim((string)$r['abbr']), $m)) { $max = max($max, (int)$m[1]); }
    }
    return $prefix . '.' . ($max + 1);
}

/**
 * A number as a person types it ("10,000", "$ 1 500.75", "USD 2000") as the
 * database wants it ("10000", "1500.75"). Anything with no digits in it
 * becomes "" so the column takes its default instead of the save failing
 * with "Data truncated". Arrays and already-clean values pass through.
 */
function normalise_number($value, $integer = false) {
    if (is_array($value) || $value === null) { return $value; }
    $s = trim((string)$value);
    if ($s === '' || is_numeric($s)) { return $integer && $s !== '' ? (string)(int)$s : $s; }
    $s = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $s));
    if (substr_count($s, '.') > 1) { $s = preg_replace('/\.(?=.*\.)/', '', $s); }   // keep the last dot
    if ($s === '' || $s === '-' || $s === '.' || !is_numeric($s)) { return ''; }
    return $integer ? (string)(int)$s : $s;
}

/**
 * A light stem, so "registrations" meets "registration" and "operating" meets "operations".
 */
function filing_stem($w) {
    if (strlen($w) > 6 && substr($w, -2) === 'al') { $w = substr($w, 0, -2); }
    if (preg_match('/^(.{3,})(ations|ation|ating|ated|ates|ate)$/', $w, $m)) { return $m[1] . 'at'; }
    if (strlen($w) > 5 && substr($w, -3) === 'ies') { return substr($w, 0, -3) . 'y'; }
    if (strlen($w) > 5 && substr($w, -3) === 'ing') { return substr($w, 0, -3); }
    if (strlen($w) > 4 && substr($w, -2) === 'ed') { return substr($w, 0, -2); }
    if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') { return substr($w, 0, -1); }
    return $w;
}

/**
 * The words of a text as the guesser sees them: lower-cased, filler and
 * bare numbers dropped, lightly stemmed, each once. Shared by the filing
 * suggestion and the workbook import, which compares activities the same way.
 */
function filing_words($s) {
    static $stop = null;
    if ($stop === null) {
        $stop = array_flip(explode(' ', 'a an the and or of to in on at by for with from as is are was were be been '
            . 'being it its this that these those there their they them we our us you your he she his her not no nor '
            . 'but if then than so such into onto over under within without between across through during before '
            . 'after above below up down out off again further once here where when why how all any both each few '
            . 'more most other some own same only very can could may might shall should will would just also per via '
            . 'etc use using used new one two three four five ensure ensuring support supporting develop developing '
            . 'development establish establishing implement implementing implementation conduct conducting provide '
            . 'providing define defining deliver delivering deploy deploying design designing build building prepare '
            . 'preparing organize organise strengthen enhance maintain improve review plan planning activity '
            . 'activities programme programmes program programs project projects objective objectives task tasks '
            . 'goal goals key deliverable deliverables africa cdc afcdc member members state states least selected '
            . 'relevant related based including include includes'));
    }
    $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s, 'UTF-8'));
    $out = [];
    foreach (preg_split('/\s+/', trim((string)$s)) as $w) {
        if ($w === '' || mb_strlen($w) < 2 || ctype_digit($w) || isset($stop[$w])) { continue; }
        $out[filing_stem($w)] = true;
    }
    return array_keys($out);
}

/**
 * Where does an item with these words belong? The add and edit forms ask
 * this as the name and description are typed, so the goal, objective and
 * programme dropdowns follow the CONTENT instead of staying at the first
 * option. (The code is numbered separately, from the place chosen.)
 *
 * Every place the item could sit is described by its words: its own name
 * and description, its parent's name, and the names and descriptions of
 * everything already filed under it. That last part is what makes this
 * accurate on this data - "SIEM", "CPHIA", "Starlink" each live in one
 * corner of the tree - and it gets better as staff file more. A word is
 * weighted by how rare it is among the candidates (one found in every
 * objective decides nothing), by where it occurs (a candidate's own name
 * counts most), and acronyms count double. A candidate whose whole name is
 * covered by the text gets a bonus. Both sides are lightly stemmed, so
 * "registrations" meets "registration" and "operating" meets "operations".
 *
 *   pm_objectives -> a goal
 *   pm_programmes -> an objective (with its goal)
 *   pm_projects   -> an objective and one of ITS programmes (with the goal)
 *
 * Returns ['candidates' => [best first: the ids to select, a label, the
 * score], 'confident' => bool]: confident when the best is clearly ahead of
 * the runner-up. $exclude is the id of the row being edited, whose own
 * words must not vote for where it already sits.
 */
function suggest_parent($db, $model, $text, $limit = 3, $exclude = 0) {
    $stem  = function ($w) { return filing_stem($w); };
    $words = function ($s) { return filing_words($s); };
    $acronyms = [];
    $noteAcronyms = function ($s) use (&$acronyms, $stem) {
        if (preg_match_all('/\b[A-Z][A-Z0-9]+(?=s?\b)/', strip_tags((string)$s), $m)) {
            foreach ($m[0] as $a) { if ($a !== 'IT') { $acronyms[$stem(strtolower($a))] = true; } }
        }
    };
    // doc: token => weight (the strongest place the word occurs in)
    $add = function (array &$doc, $s, $w) use ($words, $noteAcronyms) {
        $noteAcronyms($s);
        foreach ($words($s) as $t) { if (!isset($doc[$t]) || $doc[$t] < $w) { $doc[$t] = $w; } }
    };
    $score = function (array $docs, array $names, array $query) use (&$acronyms) {
        $n = count($docs); $df = [];
        foreach ($docs as $d) { foreach ($d as $t => $w) { $df[$t] = ($df[$t] ?? 0) + 1; } }
        $out = [];
        foreach ($docs as $id => $d) {
            $s = 0.0;
            foreach ($query as $t) {
                if (!isset($d[$t])) { continue; }
                $s += log(1 + $n / $df[$t]) * $d[$t] * (isset($acronyms[$t]) ? 2 : 1);
            }
            if ($s > 0 && count($names[$id]) >= 2 && !array_diff($names[$id], $query)) { $s += 3; }
            $out[$id] = $s;
        }
        arsort($out);
        return $out;
    };
    $label = function ($row) { return trim((string)($row['abbr'] ?? '') . ' ' . (string)($row['name'] ?? '')); };
    // Clearly ahead: above a floor (a lone weak word is not a filing) and
    // well clear of the runner-up.
    $ahead = function ($best, $second, $floor = 8) { return $best >= $floor && ($second <= 0 || $best >= 1.3 * $second); };

    $query = $words($text);
    $none  = ['candidates' => [], 'confident' => false];
    if (!$query) { return $none; }
    $exclude = (int)$exclude;

    // What people actually did when the matcher was wrong. A correction is
    // stronger evidence than anything filed by hand long ago, so the chosen
    // place gets the text at a higher weight than any other source, and the
    // place that was wrong is damped - never below 0.6, so a correction can
    // tilt a close call but can never invent an answer on its own. An item's
    // own past correction is excluded with the item, for the same reason.
    $corrections = [];
    if (filing_feedback_available($db)) {
        $corrections = (array)$db->MQ(
            "SELECT row_id, words, chosen_pillar_id, chosen_objective_id, chosen_programme_id,
                    suggested_pillar_id, suggested_objective_id, suggested_programme_id
               FROM pm_filing_feedback_tbl
              WHERE model = ? AND accepted = 0 AND words <> ''
              ORDER BY id DESC LIMIT 500", "all", [$model]);
        if ($exclude > 0) {
            $corrections = array_values(array_filter($corrections, function ($c) use ($exclude) {
                return (int)$c['row_id'] !== $exclude;
            }));
        }
    }
    // A rejection only counts against a place when THIS text resembles the
    // text that was corrected. Pooling the rejected words and counting any
    // overlap punished the right place whenever two activities shared
    // ordinary words like "establish" or "operating model", and cost four
    // correctly filed rows in the measurement.
    $rejected = ['pillar' => [], 'objective' => [], 'programme' => []];
    $learned  = ['pillar' => [], 'objective' => [], 'programme' => []];
    foreach ($corrections as $ci => $c) {
        $corrections[$ci]['_sim'] = 0.0;
        $ct = $words($c['words']);
        if (!$ct) { continue; }
        $shared = count(array_intersect($ct, $query));
        $sim    = $shared / max(1, min(count($ct), count($query)));
        // Weight the evidence by how much this text looks like the corrected
        // one: a correction is a statement about a wording, not a licence to
        // enlarge a whole objective's vocabulary.
        $corrections[$ci]['_sim'] = ($shared >= 2) ? $sim : 0.0;
        if (!($shared >= 3 && $sim >= 0.35)) { continue; }
        foreach (['pillar', 'objective', 'programme'] as $lvl) {
            $was = (int)$c['suggested_' . $lvl . '_id'];
            $now = (int)$c['chosen_' . $lvl . '_id'];
            if ($was > 0 && $was !== $now) {
                $rejected[$lvl][$was] = max($rejected[$lvl][$was] ?? 0, $sim);
            }
            if ($now > 0) { $learned[$lvl][$now] = true; }
        }
    }
    $damp = function ($level, $id) use ($rejected) {
        $sim = $rejected[$level][$id] ?? 0;
        return $sim > 0 ? max(0.6, 1 - 0.4 * $sim) : 1.0;
    };
    $applyDamp = function (array $scores, $level) use ($damp) {
        foreach ($scores as $id => $s) { $scores[$id] = $s * $damp($level, $id); }
        arsort($scores);
        return $scores;
    };

    // The meaning score, when the sidecar is configured and reachable. Word
    // counting cannot tell that "conference sign-ups" and "CPHIA
    // Registrations" are the same idea; this can. It is blended with the word
    // score, never trusted alone, and its absence changes nothing.
    $qvec = null;
    if (matcher_url() !== '') {
        $vecs = matcher_embed([mb_substr(trim((string)$text), 0, 4000)], 'query');
        if ($vecs !== null && isset($vecs[0]) && is_array($vecs[0])) { $qvec = $vecs[0]; }
    }
    $blend = function (array $lex, $kind, array $texts) use ($db, $qvec) {
        if ($qvec === null || !$lex) { return $lex; }
        $vecs = matcher_vectors($db, $kind, $texts);
        if (!$vecs) { return $lex; }
        $dense = [];
        foreach ($lex as $id => $_) { $dense[$id] = isset($vecs[$id]) ? matcher_cosine($qvec, $vecs[$id]) : 0.0; }
        $w = matcher_weight();
        $L = matcher_rescale($lex);
        $D = matcher_rescale($dense);
        // Both sides are put on 0..1 and mixed, then returned on the word
        // score's original scale so the confidence test and the objective +
        // programme sum still mean what they meant before.
        $scale = max($lex);
        if ($scale <= 0) { return $lex; }
        $out = [];
        foreach ($lex as $id => $_) { $out[$id] = $scale * ((1 - $w) * $L[$id] + $w * $D[$id]); }
        arsort($out);
        return $out;
    };

    $pillars    = (array)$db->MQ("SELECT id, name, abbr, description FROM pm_pillars_tbl WHERE active = 1", "all");
    $objectives = (array)$db->MQ("SELECT id, pillar_id, name, abbr, description, outcomes FROM pm_objectives_tbl WHERE active = 1", "all");
    $programmes = (array)$db->MQ("SELECT id, objective_id, name, abbr, description FROM pm_programmes_tbl WHERE active = 1", "all");
    $activities = (array)$db->MQ("SELECT id, objective_id, programme_id, name, description, kpi FROM pm_projects_tbl", "all");
    $byId = function (array $rows) { $o = []; foreach ($rows as $r) { $o[(int)$r['id']] = $r; } return $o; };
    $pillarById = $byId($pillars); $objectiveById = $byId($objectives);

    if ($model === 'pm_objectives') {
        $docs = []; $names = [];
        foreach ($pillars as $p) {
            $d = []; $add($d, $p['name'], 3); $add($d, $p['description'], 2);
            $docs[(int)$p['id']] = $d; $names[(int)$p['id']] = $words($p['name']);
        }
        foreach ($objectives as $o) {
            if ((int)$o['id'] === $exclude || !isset($docs[(int)$o['pillar_id']])) { continue; }
            $add($docs[(int)$o['pillar_id']], $o['name'], 1.5);
            $add($docs[(int)$o['pillar_id']], $o['description'] . ' ' . $o['outcomes'], 1);
        }
        foreach ($programmes as $p) {
            $o = $objectiveById[(int)$p['objective_id']] ?? null;
            if ($o && (int)$o['id'] !== $exclude && isset($docs[(int)$o['pillar_id']])) { $add($docs[(int)$o['pillar_id']], $p['name'], 0.75); }
        }
        foreach ($corrections as $c) {
            $g = (int)$c['chosen_pillar_id'];
            if ($g > 0 && isset($docs[$g]) && $c['_sim'] > 0) { $add($docs[$g], $c['words'], 1.5 + 1.5 * $c['_sim']); }
        }
        $ptexts = [];
        foreach ($pillars as $p) { $ptexts[(int)$p['id']] = $p['name'] . '. ' . $p['description']; }
        $scores = $applyDamp($blend($score($docs, $names, $query), 'pillar', $ptexts), 'pillar');
        $out = [];
        foreach ($scores as $id => $s) {
            if ($s <= 0 || count($out) >= $limit) { break; }
            $out[] = ['pillar_id' => $id, 'label' => $label($pillarById[$id]), 'score' => round($s, 2), 'learned' => !empty($learned['pillar'][$id])];
        }
        $v = array_values($scores);
        return ['candidates' => $out, 'confident' => $ahead($v[0] ?? 0, $v[1] ?? 0, 4)];
    }

    // Objective docs (used by both remaining models).
    $odocs = []; $onames = [];
    foreach ($objectives as $o) {
        $d = []; $add($d, $o['name'], 3); $add($d, $o['description'] . ' ' . $o['outcomes'], 2);
        if (isset($pillarById[(int)$o['pillar_id']])) { $add($d, $pillarById[(int)$o['pillar_id']]['name'], 0.5); }
        $odocs[(int)$o['id']] = $d; $onames[(int)$o['id']] = $words($o['name']);
    }
    foreach ($programmes as $p) {
        if ($model === 'pm_programmes' && (int)$p['id'] === $exclude) { continue; }
        if (!isset($odocs[(int)$p['objective_id']])) { continue; }
        $add($odocs[(int)$p['objective_id']], $p['name'], 1.5);
        $add($odocs[(int)$p['objective_id']], $p['description'], 1);
    }
    foreach ($activities as $a) {
        if ($model === 'pm_projects' && (int)$a['id'] === $exclude) { continue; }
        if (!isset($odocs[(int)$a['objective_id']])) { continue; }
        $add($odocs[(int)$a['objective_id']], $a['name'], 1.2);
        $add($odocs[(int)$a['objective_id']], $a['description'] . ' ' . $a['kpi'], 0.8);
    }
    foreach ($corrections as $c) {
        $o = (int)$c['chosen_objective_id'];
        if ($o > 0 && isset($odocs[$o]) && $c['_sim'] > 0) { $add($odocs[$o], $c['words'], 1.5 + 1.5 * $c['_sim']); }
    }
    $otexts = [];
    foreach ($objectives as $o) { $otexts[(int)$o['id']] = $o['name'] . '. ' . $o['description'] . ' ' . $o['outcomes']; }
    $oscores = $applyDamp($blend($score($odocs, $onames, $query), 'objective', $otexts), 'objective');
    $olabel = function ($id) use ($objectiveById, $label) { return $label($objectiveById[$id]); };

    if ($model === 'pm_programmes') {
        $out = [];
        foreach ($oscores as $id => $s) {
            if ($s <= 0 || count($out) >= $limit) { break; }
            $out[] = ['pillar_id' => (int)$objectiveById[$id]['pillar_id'], 'objective_id' => $id, 'label' => $olabel($id), 'score' => round($s, 2), 'learned' => !empty($learned['objective'][$id])];
        }
        $v = array_values($oscores);
        return ['candidates' => $out, 'confident' => $ahead($v[0] ?? 0, $v[1] ?? 0)];
    }

    // pm_projects: programme docs hold only the activities filed consistently
    // under them (same objective), so the loose seeded grouping - objective-3
    // activities inside "1.x PRG" programmes - cannot pull a new activity into
    // the wrong objective.
    $pdocs = []; $pnames = []; $programmeById = $byId($programmes);
    foreach ($programmes as $p) {
        $d = []; $add($d, $p['name'], 3); $add($d, $p['description'], 2);
        $pdocs[(int)$p['id']] = $d; $pnames[(int)$p['id']] = $words($p['name']);
    }
    foreach ($activities as $a) {
        if ((int)$a['id'] === $exclude) { continue; }
        $p = $programmeById[(int)$a['programme_id']] ?? null;
        if (!$p || (int)$p['objective_id'] !== (int)$a['objective_id']) { continue; }
        $add($pdocs[(int)$p['id']], $a['name'], 1.5);
        $add($pdocs[(int)$p['id']], $a['description'] . ' ' . $a['kpi'], 1);
    }
    // A correction counts for its programme only when that programme really
    // sits under the objective the person chose. Without this the commonest
    // correction in this data - "same programme, different objective" -
    // boosted the objective it was moved AWAY from, through that programme.
    foreach ($corrections as $c) {
        $g = (int)$c['chosen_programme_id'];
        if ($g <= 0 || !isset($pdocs[$g]) || !isset($programmeById[$g])) { continue; }
        if ((int)$programmeById[$g]['objective_id'] !== (int)$c['chosen_objective_id']) { continue; }
        if ($c['_sim'] <= 0) { continue; }
        $add($pdocs[$g], $c['words'], 1.5 + 1.5 * $c['_sim']);
    }
    // Both levels are blended, not just the objective: an objective's standing
    // is its own score plus that of its best programme, so scoring only one of
    // the two makes the pair incoherent. Measured over the filed activities,
    // blending both lifts the objective from 77.3% to 80.5%, while blending
    // the objective alone leaves it at 77.3%.
    $ptexts = [];
    foreach ($programmes as $p) { $ptexts[(int)$p['id']] = $p['name'] . '. ' . $p['description']; }
    $pscores = $applyDamp($blend($score($pdocs, $pnames, $query), 'programme', $ptexts), 'programme');
    // The best programme of each objective (ties broken by code order), and
    // the runner-up's score so the choice within the objective can be judged.
    $byObjective = [];
    foreach ($programmes as $p) { $byObjective[(int)$p['objective_id']][] = $p; }
    $bestPrg = []; $secondPrg = [];
    foreach ($byObjective as $o => $list) {
        usort($list, function ($a, $b) use ($pscores) {
            $d = ($pscores[(int)$b['id']] ?? 0) <=> ($pscores[(int)$a['id']] ?? 0);
            return $d ?: strnatcmp((string)$a['abbr'], (string)$b['abbr']);
        });
        $bestPrg[$o]   = $list[0];
        $secondPrg[$o] = isset($list[1]) ? ($pscores[(int)$list[1]['id']] ?? 0) : null;
    }
    $pairs = [];
    foreach ($oscores as $o => $s) {
        if (isset($bestPrg[$o])) { $pairs[$o] = $s + ($pscores[(int)$bestPrg[$o]['id']] ?? 0); }
    }
    // The strongest evidence last: what is already filed under each objective.
    // Mixed into the finished objective + programme score rather than into the
    // objective score alone - measured both ways, and mixing before the
    // programme is added is worth a point and a half where mixing after is
    // worth eleven, because the programme score then breaks the ties the
    // averages leave.
    $history = filing_history_similarity($db, $qvec, $activities, $programmeById, $exclude);
    $historyScale = 0.0;
    if ($history && $pairs) {
        $w = matcher_history_weight();
        $scale = max($pairs);
        if ($scale > 0) {
            $historyScale = $scale;
            $pv = []; $hv = [];
            // Only objectives that can actually hold an activity - the ones
            // with a programme - are in $pairs, and nothing new may be added
            // here: the candidate list below reads $bestPrg for every key.
            foreach ($pairs as $o => $v) { $pv[$o] = $v; $hv[$o] = $history[$o] ?? 0.0; }
            $P = matcher_rescale($pv); $H = matcher_rescale($hv);
            foreach ($pairs as $o => $v) {
                // An objective nothing has been filed under yet has no history
                // to weigh, so it keeps its description score in full. Without
                // that, an objective created this morning could never be
                // suggested again.
                $wk = isset($history[$o]) ? $w : 0.0;
                // Returned on the word score's scale, so the confidence test
                // below still means what it meant.
                $pairs[$o] = $scale * ((1 - $wk) * $P[$o] + $wk * $H[$o]);
            }
        }
    }
    arsort($pairs);
    $out = [];
    foreach ($pairs as $o => $s) {
        if ($s <= 0 || count($out) >= $limit) { break; }
        $p = $bestPrg[$o];
        $out[] = [
            'pillar_id'    => (int)$objectiveById[$o]['pillar_id'],
            'objective_id' => $o,
            'programme_id' => (int)$p['id'],
            'label'        => $olabel($o) . ' › ' . $label($p),
            'score'        => round($s, 2),
            'learned'      => !empty($learned['objective'][$o]) || !empty($learned['programme'][(int)$p['id']]),
        ];
    }
    $v = array_values($pairs); $top = array_key_first($pairs);
    $confident = false;
    // "Clearly ahead" has to be read off the scale actually in use. Once the
    // filed activities are mixed in, every objective's score is a blend of two
    // numbers each stretched onto 0..1, and on that scale the runner-up sits
    // close to the winner far more often than the raw word score ever did - a
    // ratio test tuned for word counts called almost nothing confident. So on
    // the mixed scale the test is the gap between first and second, as a
    // fraction of the top score, and the threshold was measured the same way
    // as the weight.
    $clear = ($historyScale > 0)
           ? (($v[0] ?? 0) > 0 && ((($v[0] ?? 0) - ($v[1] ?? 0)) / $historyScale) >= 0.06)
           : $ahead($v[0] ?? 0, $v[1] ?? 0);
    if ($top !== null && $clear) {
        $best = $pscores[(int)$bestPrg[$top]['id']] ?? 0;
        // Clear within the objective too: the programme is the only one, or
        // it is clearly ahead of the next.
        $confident = ($secondPrg[$top] === null) || ($best > 0 && $ahead($best, $secondPrg[$top]));
    }
    // The per-level scores as well, so a caller can judge a place that is
    // not one of the candidates (the check after a save needs the score of
    // the programme a person chose, which may be a sibling of the best).
    return ['candidates' => $out, 'confident' => $confident, 'scores' => ['objective' => $oscores, 'programme' => $pscores]];
}


/**
 * Does the corrections table exist yet? The table arrives with a migration,
 * and a query against a missing table would answer every page with
 * "Database unavailable", so both the reader and the writer check first.
 * SHOW TABLES succeeds either way, and the answer is cached per request.
 */
function filing_feedback_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = is_set($db->MQ("SHOW TABLES LIKE 'pm_filing_feedback_tbl'", "one")); }
    return $ok;
}

/**
 * Remember where a person actually filed something, so the matcher learns.
 * Two moments are worth recording, and nothing else is:
 *
 *   ADD  - the form suggested a place and the person saved. Keeping it is a
 *          confirmation (accepted=1, recorded but not scored: the new row
 *          joins the corpus anyway); moving it is a correction.
 *   EDIT - the person moved an existing item. The place it sat in is the
 *          "suggestion" that was wrong, whatever the note said. This is read
 *          from the stored row, not from the browser.
 *
 * Saving an unchanged item records nothing: ignoring a hint is not a
 * correction, and recording it would damp good places on every save.
 */
function record_filing_feedback($db, $model, array $posted, $suggested, $rowId = 0, $deliberate = false) {
    if (!in_array($model, ['pm_objectives', 'pm_programmes', 'pm_projects'], true)) { return; }
    if (!filing_feedback_available($db)) { return; }
    $suggested = (array)$suggested;
    $sug = [
        'pillar'    => (int)($suggested['pillar_id']    ?? 0),
        'objective' => (int)($suggested['objective_id'] ?? 0),
        'programme' => (int)($suggested['programme_id'] ?? 0),
    ];
    // A placement made from the parent's own "Add a ..." button is a
    // statement about where this wording belongs, so it is worth recording
    // even when the guesser had nothing to say about it.
    if (!$deliberate && $sug['pillar'] <= 0 && $sug['objective'] <= 0 && $sug['programme'] <= 0) { return; }
    $chosen = [
        'pillar'    => (int)($posted['pillar_id']    ?? 0),
        'objective' => (int)($posted['objective_id'] ?? 0),
        'programme' => (int)($posted['programme_id'] ?? 0),
    ];
    $words = trim(preg_replace('/\s+/u', ' ', strip_tags(
        (string)($posted['name'] ?? '') . ' ' .
        (string)($posted['description'] ?? '') . ' ' .
        (string)($posted['kpi'] ?? ''))));
    if ($words === '') { return; }
    // The level this model is filed AT: an objective sits in a goal, a
    // programme in an objective, an activity in a programme.
    $level = $model === 'pm_objectives' ? 'pillar' : ($model === 'pm_programmes' ? 'objective' : 'programme');
    if ($chosen[$level] <= 0) { return; }
    if ($sug[$level] <= 0 && !$deliberate) { return; }
    // Accepted means nothing moved AT ANY level. Judging this by the filing
    // level alone missed the commonest correction in this data: an activity
    // kept its programme but was moved to another objective, because a
    // programme here does not always belong to the objective above it.
    $accepted = 1;
    foreach (['pillar', 'objective', 'programme'] as $lvl) {
        if ($sug[$lvl] > 0 && $sug[$lvl] !== $chosen[$lvl]) { $accepted = 0; }
    }
    // Nothing was suggested at this level and the person said where it goes:
    // a clean example. It reads back as "filed here before for wording like
    // this" and damps nothing, because there is no wrong place to damp.
    if ($deliberate && $sug[$level] <= 0) { $accepted = 0; }
    $db->MQ("INSERT INTO pm_filing_feedback_tbl
                (model, words, chosen_pillar_id, chosen_objective_id, chosen_programme_id,
                 suggested_pillar_id, suggested_objective_id, suggested_programme_id,
                 accepted, row_id, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)", false, [
        $model, mb_substr($words, 0, 4000),
        $chosen['pillar'], $chosen['objective'], $chosen['programme'],
        $sug['pillar'], $sug['objective'], $sug['programme'],
        $accepted, (int)$rowId,
        (int)($_SESSION['user']['user_id'] ?? 0),
    ]);
}

/**
 * An activity is reported through its tasks: with none it is missing from
 * Progress and counts for nothing on the overview. Every seeded activity
 * has exactly one task, "Task" (called "Delivered" before September 2026),
 * applying to every reporting entity; an activity added or saved through
 * the form, or created by an import, gets the same when it has none.
 */
function ensure_default_task($db, $projectId) {
    $projectId = (int)$projectId;
    if ($projectId <= 0) { return; }
    $project = $db->MQ("SELECT id, abbr, name, type FROM pm_projects_tbl WHERE id = ?", "one", [$projectId]);
    if (!is_set($project)) { return; }
    $type = (string)($project['type'] ?? '');
    if ($type !== '' && $type !== 'pm_projects_tasks') { return; }
    if ($type === '') {
        // The add form leaves the type empty; every seeded activity is the
        // task-reported kind, and the progress pages pick their view by it.
        $db->MQ("UPDATE pm_projects_tbl SET type = 'pm_projects_tasks' WHERE id = ?", false, [$projectId]);
    }
    $has = $db->MQ("SELECT COUNT(*) AS n FROM pm_projects_tasks_tbl WHERE project_id = ?", "one", [$projectId]);
    if ((int)($has['n'] ?? 0) > 0) { return; }
    $ids = [];
    foreach ((array)$db->MQ("SELECT id FROM pm_members_tbl WHERE active = 1", "all") as $m) { $ids[] = (string)(int)$m['id']; }
    if (!$ids) { return; }
    $db->MQ("INSERT INTO pm_projects_tasks_tbl (project_id, name, description, applies_to) VALUES (?, ?, ?, ?)", false,
        [$projectId, default_task_name(), trim((string)$project['abbr'] . ' ' . (string)$project['name']), json_encode($ids)]);
}

/**
 * Is a switchable feature on? Named in the menu ("requires") and checked by
 * the controller that serves it, so a feature can ship in a release and
 * stay invisible until its switch in .env is thrown.
 */
function feature_enabled($name) {
    if ($name === 'import') { return import_enabled(); }
    if ($name === 'units') { return units_enabled(); }
    return false;
}

/**
 * The meaning matcher, when one is configured. It turns short texts into
 * lists of numbers whose closeness reflects sense rather than shared words,
 * which is the one thing word counting cannot do: "conference sign-ups" and
 * "CPHIA Registrations" have nothing in common on the page.
 *
 * It is a container on this server with no outbound network and no published
 * port (docker/matcher, compose.matcher.yml). When _MATCHER_URL is empty, or
 * the sidecar is down, slow or answers nonsense, every function here returns
 * null and the caller carries on with word matching alone. A filing
 * suggestion must never depend on it.
 */
function matcher_url() {
    // tools/measure-filing.php --no-matcher: score words and corrections alone.
    if (PHP_SAPI === 'cli' && !empty($GLOBALS['AFCDC_NO_MATCHER'])) { return ''; }
    return defined('_MATCHER_URL') ? trim((string)_MATCHER_URL) : '';
}

function matcher_weight() {
    $w = defined('_MATCHER_WEIGHT') ? (float)_MATCHER_WEIGHT : 0.5;
    return max(0.0, min(1.0, $w));
}

/**
 * How much the activities already filed under an objective count against the
 * objective's own description, 0 to 1. See filing_history_similarity().
 */
function matcher_history_weight() {
    $w = defined('_MATCHER_HISTORY_WEIGHT') ? (float)_MATCHER_HISTORY_WEIGHT : 0.95;
    return max(0.0, min(1.0, $w));
}

/**
 * How close a wording sits to the activities ALREADY FILED under each
 * objective, as a cosine per objective.
 *
 * Everything else in suggest_parent compares the typed text with what an
 * objective SAYS ABOUT ITSELF - its name, its description, its outcomes. This
 * asks the other question: does this read like the work already filed there?
 * Two hundred activities are a better description of an objective than its
 * own sentence, and the sentence was written before most of them existed.
 *
 * An objective is represented by the average of its activities' vectors.
 * Averaging then scaling to unit length is the same direction as scaling the
 * sum, so the division is skipped. Only consistently filed activities count -
 * one sitting under a programme that belongs to another objective says
 * nothing about either - and the row being edited is left out, so an item
 * never votes for where it already sits.
 *
 * Measured by hiding each filed activity and asking where it belongs, this
 * takes the objective from 74.1% to 84.9% and the programme, which is chosen
 * underneath it, from 64.9% to 73.2%. The weight was chosen on four fifths of
 * the activities and scored on the remaining fifth, five times over; all five
 * folds picked the same one. Re-measure with tools/measure-filing.php after
 * the catalogue changes shape.
 *
 * Empty when no matcher is configured, so the suggestion falls back to
 * exactly what it did before.
 */
function filing_history_similarity($db, $qvec, array $activities, array $programmeById, $exclude = 0) {
    if ($qvec === null || matcher_url() === '') { return []; }
    $texts = []; $objectiveOf = [];
    foreach ($activities as $a) {
        $id = (int)$a['id'];
        if ($id === (int)$exclude) { continue; }
        $o = (int)$a['objective_id']; $p = (int)$a['programme_id'];
        if ($o <= 0 || $p <= 0 || !isset($programmeById[$p]) || (int)$programmeById[$p]['objective_id'] !== $o) { continue; }
        $t = trim((string)$a['name'] . '. ' . (string)$a['description'] . ' ' . (string)$a['kpi']);
        if ($t === '') { continue; }
        $texts[$id] = $t; $objectiveOf[$id] = $o;
    }
    if (!$texts) { return []; }
    // Cached in pm_embeddings_tbl by text, so this is one SELECT after the
    // first run and recomputes only what someone has edited.
    // Marked as a query, like the text being filed: both sides are the same
    // sort of thing here, and marking one as a passage measured four points
    // worse on the objective.
    $vecs = matcher_vectors($db, 'activity', $texts, 'query');
    if (!$vecs) { return []; }
    $sum = []; $seen = [];
    foreach ($vecs as $id => $v) {
        $o = $objectiveOf[(int)$id] ?? 0;
        if ($o <= 0 || !is_array($v)) { continue; }
        if (!isset($sum[$o])) { $sum[$o] = array_fill(0, count($v), 0.0); $seen[$o] = 0; }
        foreach ($v as $d => $x) { if (isset($sum[$o][$d])) { $sum[$o][$d] += (float)$x; } }
        $seen[$o]++;
    }
    $out = [];
    foreach ($sum as $o => $vec) {
        $norm = 0.0;
        foreach ($vec as $x) { $norm += $x * $x; }
        $norm = sqrt($norm);
        if ($norm <= 0) { continue; }
        $unit = [];
        foreach ($vec as $d => $x) { $unit[$d] = $x / $norm; }
        $out[$o] = matcher_cosine($qvec, $unit);
    }
    return $out;
}

/**
 * [[float,...], ...] in the order given, or null if the matcher cannot be
 * reached. $kind is "query" for something a person just typed and "passage"
 * for a stored description: the model was trained to treat the two
 * differently.
 */
function matcher_embed(array $texts, $kind = 'query', $timeoutMs = 4000) {
    static $down = false;
    $url = matcher_url();
    if ($url === '' || !$texts || $down || !function_exists('curl_init')) { return null; }
    $ch = curl_init(rtrim($url, '/') . '/embed');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['texts' => array_values($texts), 'kind' => $kind]),
        CURLOPT_TIMEOUT_MS     => (int)$timeoutMs,
        CURLOPT_CONNECTTIMEOUT_MS => 1500,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) {
        // One failure stops the rest of this request from waiting again.
        $down = true;
        return null;
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data) || !isset($data['vectors']) || count($data['vectors']) !== count($texts)) { return null; }
    return $data['vectors'];
}

/** Cosine of two unit-length vectors, which is just their dot product. */
function matcher_cosine(array $a, array $b) {
    $n = min(count($a), count($b));
    $s = 0.0;
    for ($i = 0; $i < $n; $i++) { $s += $a[$i] * $b[$i]; }
    return $s;
}

/**
 * Vectors for stored descriptions, cached in the database so a page load
 * embeds only what a person just typed. The cache is keyed by the text and
 * the model, so editing a programme or swapping the model recomputes just
 * what changed. Returns [ref_id => vector] for whatever is available.
 */
function matcher_vectors($db, $kind, array $items, $embedKind = null) {
    // $kind names the cache; $embedKind decides which marker the model is
    // given. They are usually the same, but not always: e5 was trained with
    // "query:" and "passage:" for asymmetric retrieval, so comparing two
    // texts of the SAME sort - a typed activity against the activities
    // already filed - has to mark both the same way, while still caching
    // those vectors under their own name.
    if ($embedKind === null) { $embedKind = $kind; }
    if (matcher_url() === '' || !$items) { return []; }
    if (!is_set($db->MQ("SHOW TABLES LIKE 'pm_embeddings_tbl'", "one"))) { return []; }
    $model = defined('_MATCHER_MODEL') ? (string)_MATCHER_MODEL : 'default';
    $want = [];
    foreach ($items as $id => $text) {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$text)));
        if ($text !== '') { $want[(int)$id] = $text; }
    }
    if (!$want) { return []; }
    $rows = (array)$db->MQ("SELECT ref_id, hash, vec FROM pm_embeddings_tbl WHERE kind = ? AND model = ?", "all", [$kind, $model]);
    $have = [];
    foreach ($rows as $r) { $have[(int)$r['ref_id']] = $r; }
    $out = [];
    $missing = [];
    foreach ($want as $id => $text) {
        $hash = sha1($text);
        if (isset($have[$id]) && $have[$id]['hash'] === $hash) {
            $v = unpack('g*', $have[$id]['vec']);
            if ($v) { $out[$id] = array_values($v); continue; }
        }
        $missing[$id] = ['text' => $text, 'hash' => $hash];
    }
    if ($missing) {
        // In batches, so a first run over sixty programmes is a handful of calls.
        foreach (array_chunk($missing, 32, true) as $chunk) {
            $vectors = matcher_embed(array_column($chunk, 'text'), $embedKind, 30000);
            if ($vectors === null) { break; }
            $ids = array_keys($chunk);
            foreach ($vectors as $i => $vec) {
                $id = $ids[$i];
                $out[$id] = $vec;
                $packed = pack('g*', ...array_map('floatval', $vec));
                $db->MQ("INSERT INTO pm_embeddings_tbl (kind, ref_id, model, hash, vec) VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE hash = VALUES(hash), vec = VALUES(vec)", false,
                        [$kind, (int)$id, $model, $chunk[$id]['hash'], $packed]);
            }
        }
    }
    return $out;
}

/** 0..1 across a set of raw scores, so two different scales can be added. */
function matcher_rescale(array $scores) {
    if (!$scores) { return []; }
    $lo = min($scores); $hi = max($scores);
    $span = $hi - $lo;
    foreach ($scores as $k => $v) { $scores[$k] = $span > 1e-9 ? ($v - $lo) / $span : 0.0; }
    return $scores;
}

/**
 * Re-filing review (tools/allocate-imported.php). A moved activity keeps a
 * row here saying where it was, where it went and whether a person has
 * vetted the move. The lists band such rows in colour until they are
 * accepted or undone. Both readers check the table exists first.
 */
function allocation_review_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = is_set($db->MQ("SHOW TABLES LIKE 'pm_allocation_review_tbl'", "one")); }
    return $ok;
}

/** [project_id => review row (+ old_programme_label)] for the ids given. */
function allocation_reviews($db, array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !allocation_review_available($db)) { return []; }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    // current_programme_id: where the activity sits NOW - a recommendation for
    // an activity with no programme reads "Unassigned", not "Check placement".
    $rows = (array)$db->MQ("SELECT r.*, g.abbr AS old_programme_abbr, o.abbr AS old_objective_abbr,
                                     ng.abbr AS new_programme_abbr, ng.name AS new_programme_name, nob.abbr AS new_objective_abbr,
                                     IFNULL(cp.programme_id, 0) AS current_programme_id
                              FROM pm_allocation_review_tbl r
                              LEFT JOIN pm_projects_tbl cp ON cp.id = r.project_id
                              LEFT JOIN pm_programmes_tbl g ON g.id = r.old_programme_id
                              LEFT JOIN pm_objectives_tbl o ON o.id = r.old_objective_id
                              LEFT JOIN pm_programmes_tbl ng ON ng.id = r.new_programme_id
                              LEFT JOIN pm_objectives_tbl nob ON nob.id = r.new_objective_id
                             WHERE r.project_id IN (" . $marks . ")", "all", $ids);
    $out = [];
    foreach ($rows as $r) {
        $r['old_programme_label'] = $r['old_programme_abbr'] !== null ? (string)$r['old_programme_abbr']
                                  : ((int)$r['old_programme_id'] > 0 ? 'a programme that no longer exists (#' . (int)$r['old_programme_id'] . ')' : 'no programme');
        $r['new_programme_label'] = trim((string)($r['new_objective_abbr'] ?? '?') . ' / ' . (string)($r['new_programme_abbr'] ?? '?') . ' ' . (string)($r['new_programme_name'] ?? ''));
        $out[(int)$r['project_id']] = $r;
    }
    return $out;
}

/**
 * Pending MOVES only, for "Accept all pending": a placement check or a
 * recommendation for an unassigned activity needs its own answer. Accepting
 * one means "leave it where it is", so a blanket Accept would have thrown
 * every recommendation away and left the activities unassigned.
 */
function allocation_pending_moves_count($db) {
    if (!allocation_review_available($db)) { return 0; }
    $r = $db->MQ("SELECT COUNT(*) AS n FROM pm_allocation_review_tbl WHERE status = 'proposed' AND confidence <> 'check'", "one");
    return (int)($r['n'] ?? 0);
}

/** How many moves still wait for a person, or 0. */
function allocation_pending_count($db) {
    if (!allocation_review_available($db)) { return 0; }
    $r = $db->MQ("SELECT COUNT(*) AS n FROM pm_allocation_review_tbl WHERE status = 'proposed'", "one");
    return (int)($r['n'] ?? 0);
}

/** The vetting note under a moved activity's name, or "" when there is none to show. */
function allocation_review_note(array $review) {
    $status = (string)$review['status'];
    // Only what still needs a person shows: once a move is accepted or
    // undone, or a check answered, the activity simply sits where it sits.
    if ($status !== 'proposed') { return ''; }
    // A "check placement" row is not a move: the activity sits where a
    // person put it, and the wording points elsewhere.
    if ((string)$review['confidence'] === 'check') {
        // No programme at all (moved in from another objective to be filed,
        // tools/park-activities.php): the recommendation is the whole point.
        if (array_key_exists('current_programme_id', $review) && (int)$review['current_programme_id'] <= 0) {
            $html  = '<div class="afcdc-review__note"><span class="afcdc-review__tag">Unassigned</span> ';
            $html .= 'recommended: <strong>' . display($review['new_programme_label']) . '</strong>';
            if (trim((string)$review['reason']) !== '') { $html .= ' <span class="afcdc-review__why">' . display($review['reason']) . '</span>'; }
            if (can_vet()) {
                $html .= ' <a href="#" class="afcdc-review__act" data-review-action="move" data-id="' . (int)$review['project_id'] . '">Move there</a>';
                $html .= ' <a href="#" class="afcdc-review__act" data-review-action="accept" data-id="' . (int)$review['project_id'] . '">Leave unassigned</a>';
            }
            return $html . '</div>';
        }
        $html  = '<div class="afcdc-review__note"><span class="afcdc-review__tag">Check placement</span> ';
        $html .= 'the wording points to <strong>' . display($review['new_programme_label']) . '</strong>';
        if (trim((string)$review['reason']) !== '') { $html .= ' <span class="afcdc-review__why">' . display($review['reason']) . '</span>'; }
        if (can_vet()) {
            $html .= ' <a href="#" class="afcdc-review__act" data-review-action="accept" data-id="' . (int)$review['project_id'] . '">Keep here</a>';
            $html .= ' <a href="#" class="afcdc-review__act" data-review-action="move" data-id="' . (int)$review['project_id'] . '">Move there</a>';
        }
        return $html . '</div>';
    }
    $tag = ['agreed' => 'Proposed by AI', 'split' => 'Check: judges disagreed', 'low' => 'Check', 'code' => 'Code fixed'];
    $label = $tag[$review['confidence']] ?? 'Proposed by AI';
    $html  = '<div class="afcdc-review__note">';
    $html .= '<span class="afcdc-review__tag">' . display($label) . '</span> ';
    // A code-only fix names just the old code; a move names where it sat.
    $html .= 'was <code>' . display($review['old_abbr']) . '</code>';
    if ((int)$review['old_programme_id'] !== (int)$review['new_programme_id'] || (int)$review['old_objective_id'] !== (int)$review['new_objective_id']) {
        $html .= ' under ' . display($review['old_objective_abbr'] ?? '?') . ' / ' . display($review['old_programme_label']);
    }
    if (trim((string)$review['reason']) !== '') { $html .= ' <span class="afcdc-review__why">' . display($review['reason']) . '</span>'; }
    if (can_vet()) {
        $html .= ' <a href="#" class="afcdc-review__act" data-review-action="accept" data-id="' . (int)$review['project_id'] . '">Accept</a>';
        $html .= ' <a href="#" class="afcdc-review__act" data-review-action="revert" data-id="' . (int)$review['project_id'] . '">Undo</a>';
    }
    return $html . '</div>';
}

/**
 * The same note as a panel on the edit form: the row's colour band becomes
 * the panel's left edge, so the vetting reads the same in both places.
 */
function allocation_review_panel($review) {
    if (!$review) { return ''; }
    $note = allocation_review_note($review);
    if ($note === '') { return ''; }   // undone, or a check that was answered: nothing to show
    return '<div class="afcdc-review-panel afcdc-review--' . display($review['status']) . ' afcdc-review--' . display($review['confidence']) . '">' . $note . '</div>';
}

/**
 * Merging activities (projectsController::merge): two or more activities
 * that are the same piece of work - "Purchase 140 Starlink kits" and
 * "Purchase 600 Starlink kits" - become one, "Purchase Starlink kits", and
 * each merged activity's default task ("Task", or "Delivered" from before)
 * takes that activity's old name, so
 * the difference between them lives on as its tasks. pm_merge_log_tbl keeps
 * what each merge changed, so it can be undone.
 */
function merge_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = is_set($db->MQ("SHOW TABLES LIKE 'pm_merge_log_tbl'", "one")); }
    return $ok;
}

/**
 * A name for the merged activity: the words every name shares, in order,
 * spelt as in the first name. "Purchase 140 Starlink kits" + "Purchase 600
 * Starlink kits" -> "Purchase Starlink kits"; "Procurement of 700 devices" +
 * "Procurement of 1000 mobile devices" -> "Procurement of devices". A name
 * never starts or ends on a joining word or stray punctuation, never keeps
 * half of a pair of brackets, and "" means nothing useful is shared (the
 * caller keeps the name of the activity kept).
 */
function merge_common_name(array $names) {
    $names = array_values(array_filter(array_map(function ($n) { return trim((string)preg_replace('/\s+/u', ' ', (string)$n)); }, $names), 'strlen'));
    if (!$names) { return ''; }
    if (count($names) === 1) { return $names[0]; }
    $tokens = function ($s) { return preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY); };
    $key = function ($w) { return mb_strtolower((string)preg_replace('/[^\p{L}\p{N}]+/u', '', (string)$w), 'UTF-8'); };
    $common = $tokens($names[0]);
    foreach (array_slice($names, 1) as $other) {
        $a = array_map($key, $common);
        $b = array_map($key, $tokens($other));
        $n = count($a); $m = count($b);
        if ($n === 0 || $m === 0) { return ''; }
        // Longest common subsequence of words.
        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = ($a[$i] !== '' && $a[$i] === $b[$j]) ? $len[$i + 1][$j + 1] + 1 : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }
        $kept = []; $i = 0; $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] !== '' && $a[$i] === $b[$j]) { $kept[] = $common[$i]; $i++; $j++; }
            elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) { $i++; }
            else { $j++; }
        }
        $common = $kept;
    }
    $joins = ['of', 'and', 'or', 'for', 'the', 'a', 'an', 'to', 'in', 'on', 'at', 'with', 'by', 'from', 'via', ''];
    while ($common && in_array($key(end($common)), $joins, true)) { array_pop($common); }
    while ($common && in_array($key($common[0]), $joins, true)) { array_shift($common); }
    $meaningful = array_filter($common, function ($w) use ($key) { $k = $key($w); return $k !== '' && !ctype_digit($k); });
    if (!$meaningful) { return ''; }
    $out = implode(' ', $common);
    // Half a pair of brackets is left over when only one side's word was shared.
    foreach ([['(', ')'], ['[', ']']] as $pair) {
        if (substr_count($out, $pair[0]) !== substr_count($out, $pair[1])) { $out = str_replace($pair, '', $out); }
    }
    $out = trim((string)preg_replace(['/\s+/u', '/^[\s,;:.\-\x{2013}\x{2014}]+/u', '/[\s,;:.\-\x{2013}\x{2014}(\[]+$/u'], [' ', '', ''], $out));
    if ($out === '') { return ''; }
    // Capitalised only if the first name began with a capital.
    $first = mb_substr($names[0], 0, 1, 'UTF-8');
    return $first !== mb_strtolower($first, 'UTF-8') ? mb_strtoupper(mb_substr($out, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($out, 1, null, 'UTF-8') : $out;
}

/**
 * The name the default task carries ("Delivered" until September 2026), and
 * whether a name is that placeholder rather than something a person wrote:
 * no name, "Task", or the old "Delivered".
 */
function default_task_name() { return 'Task'; }

function is_default_task_name($name) {
    $n = mb_strtolower(trim((string)$name), 'UTF-8');
    return $n === '' || $n === 'task' || $n === 'delivered';
}

/**
 * What a task is called once its activity is merged: the default task (or
 * no name) takes the activity's old name - or its code, for an activity with
 * no name - and any other name stays. Never "".
 */
function merge_task_name(array $task, array $activity) {
    $name = trim((string)($task['name'] ?? ''));
    if (is_default_task_name($name)) {
        $label = trim((string)($activity['name'] ?? ''));
        if ($label === '') { $label = trim((string)($activity['abbr'] ?? '')); }
        if ($label !== '') { return mb_substr($label, 0, 250); }
        return $name !== '' ? $name : default_task_name();
    }
    return $name;
}

/**
 * The merged activity's fields as first proposed: the common name, and the
 * descriptions, indicators, budgets and notes of all of them combined - the
 * kept one first. Descriptions that differ are kept apart under their names.
 * Indicators are joined whole, as many as fit in the column ($dropped says how
 * many did not). $activities is in display order, keep first.
 */
function merge_suggestion(array $activities) {
    $first = reset($activities) ?: [];
    $name = merge_common_name(array_column($activities, 'name'));
    if ($name === '') { $name = (string)($first['name'] ?? ''); }
    $descs = [];
    foreach ($activities as $a) {
        $d = trim((string)($a['description'] ?? ''));
        if ($d !== '' && !in_array($d, $descs, true)) { $descs[(int)$a['id']] = $d; }
    }
    if (count($descs) <= 1) {
        $description = $descs ? reset($descs) : '';
    } else {
        $parts = [];
        foreach ($activities as $a) { if (isset($descs[(int)$a['id']])) { $parts[] = trim((string)$a['name']) . ': ' . $descs[(int)$a['id']]; } }
        $description = implode("\n\n", $parts);
    }
    $unique = function ($field) use ($activities) {
        $out = [];
        foreach ($activities as $a) { $v = trim((string)($a[$field] ?? '')); if ($v !== '' && !in_array($v, $out, true)) { $out[] = $v; } }
        return $out;
    };
    $sum = function ($field) use ($activities) {
        $total = null;
        foreach ($activities as $a) { if (isset($a[$field]) && $a[$field] !== null && $a[$field] !== '' && is_numeric($a[$field])) { $total = ($total ?? 0) + (float)$a[$field]; } }
        return $total;
    };
    $kpi = ''; $dropped = 0;
    foreach ($unique('kpi') as $k) {
        $next = $kpi === '' ? $k : $kpi . '; ' . $k;
        if (mb_strlen($next, 'UTF-8') <= 255) { $kpi = $next; } else { $dropped++; }
    }
    return [
        'name'             => mb_substr($name, 0, 255),
        'description'      => $description,
        'kpi'              => $kpi,
        'kpi_dropped'      => $dropped,
        'estimated_budget' => $sum('estimated_budget'),
        'actual_budget'    => $sum('actual_budget'),
        'notes'            => implode("\n\n", $unique('notes')),
    ];
}

/**
 * Where an activity removed by a merge went: the id of the activity it became
 * part of, or 0. Follows a chain (merged into one that was later merged
 * itself), at most ten steps, and ignores merges that have been undone - so
 * an old link, a bookmark or a search result still lands somewhere real.
 */
function merge_forwarding($db, $projectId) {
    if (!merge_available($db)) { return 0; }
    $id = (int)$projectId;
    $seen = [];
    for ($i = 0; $i < 10 && $id > 0 && !isset($seen[$id]); $i++) {
        $seen[$id] = true;
        if ($i > 0 && is_set($db->MQ("SELECT id FROM pm_projects_tbl WHERE id = ?", "one", [$id]))) { return $id; }
        $row = $db->MQ("SELECT project_id FROM pm_merge_log_tbl WHERE undone_at IS NULL AND FIND_IN_SET(?, merged_ids) ORDER BY id DESC LIMIT 1", "one", [(string)$id]);
        if (!is_set($row)) { return 0; }
        $id = (int)$row['project_id'];
    }
    return 0;
}

/**
 * Merges into this activity that have not been undone, newest first. Only
 * the newest can be undone: an older one is untangled after it.
 */
function merge_history($db, $projectId, $justId = 0) {
    if (!merge_available($db) || (int)$projectId <= 0) { return []; }
    $out = [];
    $rows = (array)$db->MQ("SELECT id, merged_at, merged_by, snapshot FROM pm_merge_log_tbl WHERE project_id = ? AND undone_at IS NULL ORDER BY id DESC", "all", [(int)$projectId]);
    foreach ($rows as $i => $r) {
        $snap = json_decode((string)$r['snapshot'], true) ?: [];
        $merged = [];
        foreach ((array)($snap['merged'] ?? []) as $a) { $merged[] = ['abbr' => (string)($a['abbr'] ?? ''), 'name' => (string)($a['name'] ?? '')]; }
        $out[] = ['id' => (int)$r['id'], 'merged_at' => (string)$r['merged_at'], 'by' => (string)($snap['merged_by_name'] ?? ''),
                  'merged' => $merged, 'can_undo' => $i === 0, 'just' => (int)$r['id'] === (int)$justId];
    }
    return $out;
}

/**
 * Units: the division an objective belongs to (pm_units_tbl, set on
 * pm_objectives_tbl.unit_id), and the vetting of the units proposed for
 * objectives (pm_unit_review_tbl, loaded by tools/propose-units.php).
 * Programmes and activities have no unit of their own: they are in their
 * objective's. A proposal only stands while its objective has no unit: once
 * a person sets one, on the form or by accepting, the note goes and nothing
 * here writes over it. Everything answers empty until the migration has run.
 *
 * Switched off unless UNITS_ENABLED=true (.env): the code ships with every
 * release, but while it is off the Units menu entry, the Unit filters and
 * field, the vetting and the migration that creates the tables all wait, and
 * nothing here reads or writes a unit.
 */
function units_enabled() {
    return defined('_UNITS_ENABLED') && _UNITS_ENABLED === true;
}

/**
 * Model settings - a meta.filters list, or the fields keyed by column -
 * without what points at the units table, while units are switched off.
 */
function units_strip_settings(array $items) {
    if (units_enabled()) { return $items; }
    $isList = array_is_list($items);
    foreach ($items as $key => $item) {
        if (is_array($item) && (string)($item['link_to_table'] ?? '') === 'pm_units_tbl') { unset($items[$key]); }
    }
    return $isList ? array_values($items) : $items;
}

function units_available($db) {
    if (!units_enabled()) { return false; }
    static $ok = null;
    if ($ok === null) {
        $ok = is_set($db->MQ("SHOW TABLES LIKE 'pm_units_tbl'", "one"))
           && is_set($db->MQ("SHOW COLUMNS FROM pm_objectives_tbl LIKE 'unit_id'", "one"));
    }
    return $ok;
}

function unit_review_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = units_available($db) && is_set($db->MQ("SHOW TABLES LIKE 'pm_unit_review_tbl'", "one")); }
    return $ok;
}

/** Whether a project can carry a unit of its own (pm_projects_tbl.unit_id, added at start-up while units are on). */
function unit_moves_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = units_available($db) && is_set($db->MQ("SHOW COLUMNS FROM pm_projects_tbl LIKE 'unit_id'", "one")); }
    return $ok;
}

/**
 * The unit a project counts under: its own once it has been moved
 * (projects/unit_move), its objective's otherwise. $p is the name or alias
 * of pm_projects_tbl in the query - written by the caller, never request
 * input. Used by the Unit filter of the lists and by the template of a unit.
 */
function activity_unit_sql($p = '`pm_projects_tbl`') {
    return "COALESCE(NULLIF(" . $p . ".`unit_id`, 0), (SELECT uo.`unit_id` FROM `pm_objectives_tbl` uo WHERE uo.`id` = " . $p . ".`objective_id`))";
}

/** [project id => ['unit' => name, 'from' => its objective's unit name]] for the projects among $ids that carry a unit of their own. */
function activity_unit_overrides($db, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) { return $v > 0; })));
    if (!$ids || !unit_moves_available($db)) { return []; }
    $names = unit_names($db); $out = [];
    foreach ((array)$db->MQ("SELECT p.id, p.unit_id, IFNULL(o.unit_id, 0) AS objective_unit FROM pm_projects_tbl p LEFT JOIN pm_objectives_tbl o ON o.id = p.objective_id
                              WHERE p.unit_id IS NOT NULL AND p.unit_id > 0 AND p.id IN (" . implode(',', $ids) . ")", "all") as $r) {
        $out[(int)$r['id']] = ['unit' => $names[(int)$r['unit_id']] ?? '', 'from' => $names[(int)$r['objective_unit']] ?? ''];
    }
    return $out;
}

/**
 * The ticked projects go to another unit ($unitId), or back to their
 * objective's ($unitId = 0). A project keeps its goal, objective, programme
 * and code: only the unit it counts under changes. Moving a project to the
 * unit its objective is already under stores nothing - it simply follows
 * its objective again. One audit row per project that changed. Returns
 * ['moved' => n, 'unit' => name] or ['error' => message, 'code' => http].
 */
function activities_move_to_unit($db, array $ids, $unitId) {
    $unitId = (int)$unitId;
    foreach ($ids as $one) { if (!is_int($one) && !(is_string($one) && ctype_digit($one))) { return ['error' => 'That is not a list of projects.', 'code' => 422]; } }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) { return $v > 0; })));
    if (!unit_moves_available($db)) { return ['error' => 'Units are not switched on here.', 'code' => 404]; }
    if (!$ids) { return ['error' => 'Tick at least one project first.', 'code' => 422]; }
    if (count($ids) > 500) { return ['error' => 'Move up to 500 projects at a time.', 'code' => 422]; }
    $unit = null;
    if ($unitId > 0) {
        $unit = $db->MQ("SELECT id, name FROM pm_units_tbl WHERE id = ? AND active = 1", "one", [$unitId]);
        if (!is_set($unit)) { return ['error' => 'There is no such unit.', 'code' => 404]; }
    } elseif ($unitId < 0) {
        return ['error' => 'Choose a unit.', 'code' => 422];
    }
    $user = (string)($_SESSION['user']['username'] ?? ''); $moved = 0;
    $db->txBegin();
    $rows = (array)$db->MQ("SELECT p.id, p.unit_id, IFNULL(o.unit_id, 0) AS objective_unit FROM pm_projects_tbl p LEFT JOIN pm_objectives_tbl o ON o.id = p.objective_id
                             WHERE p.id IN (" . implode(',', $ids) . ") FOR UPDATE", "all");
    foreach ($rows as $r) {
        $was = (int)($r['unit_id'] ?? 0);
        $now = ($unitId > 0 && $unitId !== (int)$r['objective_unit']) ? $unitId : 0;
        if ($was === $now) { continue; }
        if (!$db->MQ("UPDATE pm_projects_tbl SET unit_id = ? WHERE id = ?", false, [$now > 0 ? $now : null, (int)$r['id']])) { $db->txRollBack(); return ['error' => 'Problem moving the projects - nothing was changed.', 'code' => 500]; }
        $db->MQ("INSERT INTO `core_table_logs_tbl` (`tablename`, `record`, `log_date`, `user`) VALUES (?, ?, ?, ?)", false,
            ['pm_projects_tbl', json_encode(['action' => 'unit_move', 'id' => (int)$r['id'], 'unit_id_was' => $was ?: null, 'unit_id_now' => $now ?: null, 'objective_unit_id' => (int)$r['objective_unit'] ?: null]), date("Y-m-d H:i:s"), $user]);
        $moved++;
    }
    $db->txCommit();
    return ['moved' => $moved, 'asked' => count($rows), 'unit' => $unit ? (string)$unit['name'] : ''];
}

/** [unit id => name], in the units' own order. */
function unit_names($db) {
    if (!units_available($db)) { return []; }
    $out = [];
    foreach ((array)$db->MQ("SELECT id, name FROM pm_units_tbl ORDER BY position, id", "all") as $u) { $out[(int)$u['id']] = (string)$u['name']; }
    return $out;
}

/** [objective_id => review row (+ unit_name, other_names, current_unit_id)] for the ids given. */
function unit_reviews($db, array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !unit_review_available($db)) { return []; }
    $names = unit_names($db);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach ((array)$db->MQ("SELECT r.*, IFNULL(o.unit_id, 0) AS current_unit_id FROM pm_unit_review_tbl r JOIN pm_objectives_tbl o ON o.id = r.objective_id WHERE r.objective_id IN ($marks)", "all", $ids) as $r) {
        $r['unit_name'] = $names[(int)$r['unit_id']] ?? '';
        $r['other_names'] = [];
        foreach (array_unique(array_filter(array_map('intval', explode(',', (string)$r['other_unit_ids'])))) as $u) {
            if (isset($names[$u]) && $u !== (int)$r['unit_id']) { $r['other_names'][] = $names[$u]; }
        }
        $out[(int)$r['objective_id']] = $r;
    }
    return $out;
}

/** How many objectives still wait for a person to confirm a unit, or 0. */
function unit_pending_count($db) {
    if (!unit_review_available($db)) { return 0; }
    $r = $db->MQ("SELECT COUNT(*) AS n FROM pm_unit_review_tbl r JOIN pm_objectives_tbl o ON o.id = r.objective_id WHERE r.status = 'proposed' AND IFNULL(o.unit_id, 0) = 0", "one");
    return (int)($r['n'] ?? 0);
}

/** Whether a proposal names a unit enough readers agreed on. */
function unit_review_agreed($review) {
    return $review && in_array((string)$review['agreement'], ['agreed', 'majority'], true) && (int)$review['unit_id'] > 0 && (string)($review['unit_name'] ?? '') !== '';
}

/**
 * The vetting note under an objective's name, or "" when nothing waits for a
 * person. On a list ($short) the reason is cut to about a line and a half,
 * with the whole of it on hover: sixteen full paragraphs made the list a wall.
 * The objective's form shows it all.
 */
function unit_review_note($review, $short = false) {
    if (!$review || (string)$review['status'] !== 'proposed' || (int)($review['current_unit_id'] ?? 0) > 0) { return ''; }
    $agreed = unit_review_agreed($review);
    $tag = !$agreed ? 'Unit: readers disagreed' : ((string)$review['agreement'] === 'agreed' ? 'Unit proposed by AI' : 'Unit proposed by AI, 2 of 3');
    $html  = '<div class="afcdc-review__note afcdc-unit-note"><span class="afcdc-review__tag">' . display($tag) . '</span> ';
    $html .= $agreed ? '<strong>' . display($review['unit_name']) . '</strong>' : 'choose one on the objective';
    $reason = trim((string)$review['reason']);
    if ($reason !== '') {
        $shown = $reason;
        if ($short && mb_strlen($reason) > 120) {
            // About a line and a half, ending on a word - not the first
            // sentence alone, which can say the opposite of the proposal
            // ("The conference itself falls outside all four units.").
            $cut = mb_substr($reason, 0, 110);
            $sp = mb_strrpos($cut, ' ');
            $shown = rtrim($sp !== false && $sp > 60 ? mb_substr($cut, 0, $sp) : $cut, " ,;:") . "\u{2026}";
        }
        $html .= ' <span class="afcdc-review__why"' . ($shown !== $reason ? ' title="' . display($reason) . '"' : '') . '>' . display($shown) . '</span>';
    }
    if ($review['other_names']) { $html .= ' <span class="afcdc-review__also">Some of its programmes lean to ' . display(implode(' and ', $review['other_names'])) . '.</span>'; }
    if (can_vet()) {
        if ($agreed) { $html .= ' <a href="#" class="afcdc-review__act" data-unit-review="accept" data-id="' . (int)$review['objective_id'] . '">Accept</a>'; }
        $html .= ' <a href="#" class="afcdc-review__act" data-unit-review="dismiss" data-id="' . (int)$review['objective_id'] . '">' . ($agreed ? 'Not this unit' : 'Dismiss') . '</a>';
    }
    return $html . '</div>';
}

/** The same note as a panel on the objective's edit form. */
function unit_review_panel($review) {
    $note = unit_review_note($review);
    if ($note === '') { return ''; }
    return '<div class="afcdc-review-panel afcdc-review--proposed afcdc-review--' . (unit_review_agreed($review) ? 'agreed' : 'split') . '">' . $note . '</div>';
}

/**
 * Status: Completed, In progress or Not started - for a task, an activity, a
 * programme or an objective, on every page that shows one. The arithmetic is
 * the graphs' (projects_graphs::activityProgress): every task counts once for
 * each reporting entity it applies to. Completed when every one of those is
 * recorded as completed (result 1); In progress when some are, or any is
 * recorded as in progress (result 2); Not started otherwise. Something with
 * no task to measure is "none". The percentages still count completed only.
 */
function delivery_status($assignments, $completed, $started = 0) {
    if ((int)$assignments <= 0) { return 'none'; }
    if ((int)$completed >= (int)$assignments) { return 'completed'; }
    if ((int)$completed > 0 || (int)$started > 0) { return 'in_progress'; }
    return 'not_started';
}

/** Completed first, then In progress, then Not started, then nothing to measure. */
function delivery_status_rank($status) {
    return ['completed' => 0, 'in_progress' => 1, 'not_started' => 2][(string)$status] ?? 3;
}

/**
 * What a reporting entity says about a task: its status, and - from the
 * Progress page - the date, spend and comment behind it. One row per entity,
 * activity and task. A detail left out of $more keeps what the row already
 * had, so the activity form can set the status alone without wiping a
 * comment written on Progress; when no date is given, the date moves to now
 * only if the status changed. Whether the task belongs to the activity is
 * the caller's to check. Returns false when the write failed.
 */
function task_result_save($db, $memberId, $projectId, $taskId, $result, array $more = []) {
    $result = in_array((int)$result, [0, 1, 2], true) ? (int)$result : 0;
    $keys = [(int)$memberId, (int)$projectId, (int)$taskId];
    $row = $db->MQ("SELECT * FROM `pm_progress_tasks_tbl` WHERE `member_id` = ? AND `project_id` = ? AND `task_id` = ?", "one", $keys);
    $had = is_set($row);
    // In progress carries how far along it is (25/50/75, $more['progress_pct']):
    // the one given, else what the record had while it was in progress already.
    // Any other status clears it.
    $pctOn = delivery_pct_available($db);
    $pct = null;
    if ($pctOn && $result === 2) {
        $pct = array_key_exists('progress_pct', $more) ? delivery_pct(2, $more['progress_pct'])
             : (($had && (int)$row['result'] === 2) ? delivery_pct(2, $row['progress_pct'] ?? null) : null);
    }
    $same = $had && (int)$row['result'] === $result && (!$pctOn || delivery_pct($row['result'], $row['progress_pct'] ?? null) === $pct);
    $date    = array_key_exists('progress_date', $more) ? $more['progress_date'] : ($same ? $row['progress_date'] : date('Y-m-d H:i:s'));
    $comment = array_key_exists('comment', $more)       ? $more['comment']       : ($had ? $row['comment'] : '');
    $budget  = array_key_exists('actual_budget', $more) ? $more['actual_budget'] : ($had ? $row['actual_budget'] : null);
    if ($had) {
        return (bool)$db->MQ("UPDATE `pm_progress_tasks_tbl`
                                 SET `result` = ?, " . ($pctOn ? "`progress_pct` = ?, " : "") . "`progress_date` = ?, `actual_budget` = ?, `comment` = ?
                               WHERE `member_id` = ? AND `project_id` = ? AND `task_id` = ?", false,
            array_merge($pctOn ? [$result, $pct, $date, $budget, $comment] : [$result, $date, $budget, $comment], $keys));
    }
    return (bool)$db->MQ("INSERT INTO `pm_progress_tasks_tbl`
                            (`member_id`, `project_id`, `result`, " . ($pctOn ? "`progress_pct`, " : "") . "`task_id`, `progress_date`, `comment`, `actual_budget`)
                          VALUES (?, ?, ?, " . ($pctOn ? "?, " : "") . "?, ?, ?, ?)", false,
        $pctOn ? [$keys[0], $keys[1], $result, $pct, $keys[2], $date, $comment, $budget]
               : [$keys[0], $keys[1], $result, $keys[2], $date, $comment, $budget]);
}

/** How far along a task in progress can be marked, in percent. */
function delivery_pct_values() {
    return [25, 50, 75];
}

/**
 * Whether records can carry that percentage: pm_progress_tasks_tbl.progress_pct
 * is added at start-up (docker/entrypoint-app.sh, 10). Without it everything
 * reads as before - a task in progress counts for nothing.
 */
function delivery_pct_available($db) {
    static $ok = null;
    if ($ok === null) { $ok = is_set($db->MQ("SHOW COLUMNS FROM pm_progress_tasks_tbl LIKE 'progress_pct'", "one")); }
    return $ok;
}

/** The percentage a record stands for: 25, 50 or 75 when its status is In progress (2), otherwise null. */
function delivery_pct($result, $pct) {
    $p = (is_int($pct) || (is_string($pct) && ctype_digit($pct))) ? (int)$pct : 0;
    return ((int)$result === 2 && in_array($p, delivery_pct_values(), true)) ? $p : null;
}

/**
 * How much of a task one record counts for, as SQL for SUM(): Completed 1,
 * In progress its share (0.25, 0.5 or 0.75; nothing when no share was given),
 * Not started 0. Every percentage and bar on the overview is made of this;
 * "n of m completed" still counts finished work only, SUM(result = 1).
 */
function delivery_weight_sql($db, $alias = '') {
    $a = $alias !== '' ? $alias . '.' : '';
    if (!delivery_pct_available($db)) { return "(CASE WHEN {$a}`result` = 1 THEN 1 ELSE 0 END)"; }
    // Only the three values the screens offer count: a number written some
    // other way (a generic edit screen, a hand-made record) counts for nothing.
    return "(CASE WHEN {$a}`result` = 1 THEN 1 WHEN {$a}`result` = 2 AND {$a}`progress_pct` IN (" . implode(', ', delivery_pct_values()) . ") THEN {$a}`progress_pct` / 100 ELSE 0 END)";
}

function delivery_status_label($status) {
    return ['completed' => 'Completed', 'in_progress' => 'In progress', 'not_started' => 'Not started'][(string)$status] ?? 'Nothing to measure yet';
}

/**
 * The status as a tag, with an icon so colour is never the only sign.
 * 'colour' (projects - activities - and their tasks): green, orange or red.
 * 'green' (objectives and programmes on the main dashboard): the dashboard's
 * own greens, and no tag at all for something not started yet.
 */
function delivery_status_chip($status, $tone = 'colour', $pct = null) {
    $icon = ['completed' => 'bx-check-circle', 'in_progress' => 'bx-adjust', 'not_started' => 'bx-time-five'][(string)$status] ?? 'bx-minus-circle';
    if ($tone === 'green') {
        if (!in_array((string)$status, ['completed', 'in_progress'], true)) { return ''; }
        $class = $status === 'completed' ? 'good' : 'active';
    } else {
        $class = ['completed' => 'completed', 'in_progress' => 'in-progress', 'not_started' => 'not-started'][(string)$status] ?? 'idle';
    }
    // In progress may say how far along: "In progress · 50%".
    $label = delivery_status_label($status) . (((string)$status === 'in_progress' && (int)$pct > 0) ? " \u{00B7} " . (int)$pct . '%' : '');
    return '<span class="afcdc-status afcdc-status--' . $class . '"><i class="bx ' . $icon . '" aria-hidden="true"></i> ' . display($label) . '</span>';
}

/** The status as a small square in its colour, the word for the screen reader and on hover (the Tasks column of the Projects list). */
function delivery_status_square($status) {
    $class = ['completed' => 'completed', 'in_progress' => 'in-progress', 'not_started' => 'not-started'][(string)$status] ?? 'idle';
    // The chip's own icon inside the square: red and orange are a hair apart
    // for anyone who does not see colour, so the shape carries it too.
    $icon = ['completed' => 'bx-check', 'in_progress' => 'bx-dots-horizontal-rounded', 'not_started' => 'bx-time-five'][(string)$status] ?? 'bx-minus';
    $label = delivery_status_label($status);
    return '<span class="afcdc-square afcdc-square--' . $class . '" title="' . display($label) . '"><i class="bx ' . $icon . '" aria-hidden="true"></i>'
         . '<span class="visually-hidden">' . display($label) . '</span></span>';
}

/** The class that colours a progress bar by its status ("" leaves the bar as it was). */
function delivery_status_bar($status) {
    return ['completed' => ' afcdc-bar--completed', 'in_progress' => ' afcdc-bar--in-progress'][(string)$status] ?? '';
}

/**
 * [assignments, completed, in progress] for every task, activity, programme
 * and objective, from three queries. Programmes are keyed "objective:programme"
 * and objectives "goal:objective", because the graphs count an activity under
 * the programme and objective it sits in, not under a parent's other parent.
 */
function delivery_rollup($db) {
    static $cache = null;
    if ($cache !== null) { return $cache; }
    // objective_all: every activity with that objective, whatever its goal (the goal page counts them so).
    $out = ['task' => [], 'activity' => [], 'programme' => [], 'objective' => [], 'objective_all' => []];
    $acts = [];
    foreach ((array)$db->MQ("SELECT id, pillar_id, objective_id, programme_id FROM pm_projects_tbl", "all") as $a) { $acts[(int)$a['id']] = $a; }
    $tasks = [];
    foreach ((array)$db->MQ("SELECT id, project_id, applies_to FROM pm_projects_tasks_tbl", "all") as $t) {
        $to = json_decode((string)$t['applies_to'], true);
        $entities = is_array($to) ? array_values(array_unique(array_filter(array_map('intval', $to)))) : [];
        $tasks[(int)$t['id']] = [(int)$t['project_id'], $entities];
        $out['task'][(int)$t['id']] = [count($entities), 0, 0];
    }
    foreach ((array)$db->MQ("SELECT task_id, project_id, member_id, result FROM pm_progress_tasks_tbl WHERE result IN (1, 2)", "all") as $r) {
        $tid = (int)$r['task_id'];
        if (!isset($tasks[$tid]) || $tasks[$tid][0] !== (int)$r['project_id'] || !in_array((int)$r['member_id'], $tasks[$tid][1], true)) { continue; }
        $out['task'][$tid][(int)$r['result'] === 1 ? 1 : 2]++;
    }
    foreach ($out['task'] as $tid => $v) {
        $pid = $tasks[$tid][0];
        $keys = [['activity', $pid]];
        if (isset($acts[$pid])) {
            $keys[] = ['programme', (int)$acts[$pid]['objective_id'] . ':' . (int)$acts[$pid]['programme_id']];
            $keys[] = ['objective', (int)$acts[$pid]['pillar_id'] . ':' . (int)$acts[$pid]['objective_id']];
            $keys[] = ['objective_all', (int)$acts[$pid]['objective_id']];
        }
        foreach ($keys as $k) {
            if (!isset($out[$k[0]][$k[1]])) { $out[$k[0]][$k[1]] = [0, 0, 0]; }
            for ($i = 0; $i < 3; $i++) { $out[$k[0]][$k[1]][$i] += $v[$i]; }
        }
    }
    return $cache = $out;
}

/** The status of one entry of delivery_rollup(): delivery_rollup_status($roll['activity'][$id] ?? null). */
function delivery_rollup_status($counts) {
    $c = is_array($counts) ? $counts : [0, 0, 0];
    return delivery_status($c[0] ?? 0, $c[1] ?? 0, $c[2] ?? 0);
}

/** Order a list by status, keeping the order it had within each status (usort is stable). */
function sort_by_delivery_status(array $rows, callable $statusOf) {
    $rows = array_values($rows);
    usort($rows, function ($a, $b) use ($statusOf) { return delivery_status_rank($statusOf($a)) <=> delivery_status_rank($statusOf($b)); });
    return $rows;
}

/**
 * Activity ids by status, for the Status box on the Projects and Progress
 * lists: 'delivered' = completed, 'partly' = in progress, 'none' = not
 * started. All three are positive lists, so an activity with nothing to
 * measure (no task at all) is in none of them and the box never offers it
 * under a status its own row denies.
 */
function activity_delivery_groups($db) {
    $out = ['delivered' => [], 'partly' => [], 'none' => []];
    foreach (delivery_rollup($db)['activity'] as $pid => $c) {
        $st = delivery_rollup_status($c);
        if ($st === 'completed') { $out['delivered'][] = (int)$pid; }
        elseif ($st === 'in_progress') { $out['partly'][] = (int)$pid; }
        elseif ($st === 'not_started') { $out['none'][] = (int)$pid; }
    }
    return $out;
}

/**
 * "Add an objective", "Add a programme", "Add an activity": the button that
 * carries a parent into the child's form. A child opened this way is never
 * filed under a parent nobody picked, and the placement is recorded as an
 * example the guesser learns from. meta.child in the model settings says
 * where it goes; child_add_href() returns the path, and the view prefixes it
 * with the language the way it does every other link.
 */
function child_add_label(array $child) {
    $label = (string)($child['label'] ?? 'entry');
    return 'Add ' . (preg_match('/^[aeiou]/i', $label) ? 'an ' : 'a ') . $label;
}

function child_add_href(array $child, $parentId) {
    $parentId = (int)$parentId;
    if ($parentId <= 0 || empty($child['route']) || empty($child['parent_field'])) { return ''; }
    return (string)$child['route'] . '?' . rawurlencode((string)$child['parent_field']) . '=' . $parentId . '&from=parent';
}

/**
 * May this person act on a vetting proposal? Accepting, undoing or moving an
 * activity is content editing, which protectedController grants to groups 1
 * and 2 only ('projects/*'). Power Users can open the Progress list and read
 * the note, so the note shows for them without the action links - they used
 * to be offered a button that answered 403.
 */
/**
 * Who may do what. Four levels (core_groups_tbl): 1 System Administrators
 * run the platform; 2 Executive Users oversee and decide; 3 Power Users keep
 * the plan current and record delivery; 4 Custom Users view. Every route
 * (protectedController), menu entry and button reads these, so a right is
 * changed in one place. Executives decide, Power Users do: an Executive
 * accepts what the AI proposes and merges activities, a Power User edits
 * activities, tasks and programmes, records delivery and imports the work
 * plan. Only an administrator changes the structure above programmes
 * (goals, objectives, units), deletes anything, or manages accounts.
 */
function access_group() { return (int)($_SESSION['user']['group']['id'] ?? 0); }
function access_is(array $groups) { return in_array(access_group(), $groups, true); }
function can_browse()    { return access_is([1, 2, 3]); }   // the lists and the import page
function can_edit()      { return access_is([1, 3]); }      // activities, tasks, programmes; the work plan import
function can_record()    { return access_is([1, 3]); }      // delivery
function can_vet()       { return access_is([1, 2]); }      // AI proposals, merging
function can_structure() { return access_is([1]); }         // goals, objectives, units
function can_delete()    { return access_is([1]); }
function can_admin()     { return access_is([1]); }

/**
 * The generic screens (core/db_*) and the forms that name a table decide by
 * the model named: read, write or delete it. A model nothing here knows is
 * the administrator's.
 */
function model_may($model, $op) {
    $model = strtolower(trim((string)$model));
    if ($model === '' || $model === 'core_users') { return can_admin(); }
    if ($op === 'delete') { return can_delete(); }
    if ($op === 'read') { return can_browse(); }
    if (in_array($model, ['pm_pillars', 'pm_objectives', 'pm_units'], true)) { return can_structure(); }
    if (in_array($model, ['pm_programmes', 'pm_projects', 'pm_projects_tasks', 'pm_projects_dates', 'pm_projects_milestones', 'pm_projects_percentages',
                          'pm_progress_tasks', 'pm_progress_dates', 'pm_progress_milestones', 'pm_progress_percentages'], true)) { return can_edit(); }
    return can_admin();
}

/** Where a row on a list opens for someone who may read it but not edit it: its page on the dashboard, or nowhere. */
function model_view_route($model, $id) {
    $to = ['pm_pillars' => 'projects_graphs/pillar/', 'pm_objectives' => 'projects_graphs/objective/', 'pm_programmes' => 'projects_graphs/programme/', 'pm_projects' => 'projects_graphs/project/'][strtolower((string)$model)] ?? '';
    return $to !== '' ? $to . (int)$id : '';
}

/** Whether a review row still has something to show on a list row (band + note). */
function allocation_review_visible($review) {
    return $review && (string)$review['status'] === 'proposed';
}

/**
 * What is missing or wrong on an activity, as short labels ("description",
 * "programme", "programme belongs to another objective"). Empty when the
 * activity is complete. Used three ways: the save refuses an activity that
 * lacks a name, description, goal, objective or programme; the lists and the
 * edit form flag one that is unfinished; and the "Unfinished" filter finds
 * them. The parent tables are read once per request.
 */
function activity_gaps($db, array $row) {
    static $objectives = null, $programmes = null, $pillars = null;
    if ($objectives === null) {
        $objectives = []; $programmes = []; $pillars = [];
        foreach ((array)$db->MQ("SELECT id, pillar_id FROM pm_objectives_tbl", "all") as $r) { $objectives[(int)$r['id']] = (int)$r['pillar_id']; }
        foreach ((array)$db->MQ("SELECT id, objective_id FROM pm_programmes_tbl", "all") as $r) { $programmes[(int)$r['id']] = (int)$r['objective_id']; }
        foreach ((array)$db->MQ("SELECT id FROM pm_pillars_tbl", "all") as $r) { $pillars[(int)$r['id']] = true; }
    }
    $gaps = [];
    if (trim((string)($row['name'] ?? '')) === '') { $gaps[] = 'name'; }
    if (trim((string)($row['description'] ?? '')) === '') { $gaps[] = 'description'; }
    $g = (int)($row['pillar_id'] ?? 0); $o = (int)($row['objective_id'] ?? 0); $p = (int)($row['programme_id'] ?? 0);
    if ($g <= 0 || !isset($pillars[$g])) { $gaps[] = 'goal'; }
    if ($o <= 0 || !isset($objectives[$o])) { $gaps[] = 'objective'; }
    elseif ($g > 0 && isset($pillars[$g]) && $objectives[$o] !== $g) { $gaps[] = 'objective belongs to another goal'; }
    if ($p <= 0 || !isset($programmes[$p])) { $gaps[] = 'programme'; }
    elseif ($o > 0 && isset($objectives[$o]) && $programmes[$p] !== $o) { $gaps[] = 'programme belongs to another objective'; }
    if (trim((string)($row['abbr'] ?? '')) === '') { $gaps[] = 'code'; }
    return $gaps;
}

/** Gaps for a page of activities, keyed by id; rows without gaps are absent. */
function activity_gaps_for($db, array $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) { return []; }
    $out = [];
    $rows = (array)$db->MQ("SELECT id, name, description, abbr, pillar_id, objective_id, programme_id FROM pm_projects_tbl WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", "all", $ids);
    foreach ($rows as $r) {
        $gaps = activity_gaps($db, $r);
        if ($gaps) { $out[(int)$r['id']] = $gaps; }
    }
    return $out;
}

/** SQL (no bound values) that is true for an unfinished activity - the "Unfinished" list filter. */
function activity_gaps_sql($table = 'pm_projects_tbl') {
    return "(TRIM(IFNULL(`$table`.`name`,'')) = '' OR TRIM(IFNULL(`$table`.`description`,'')) = '' OR TRIM(IFNULL(`$table`.`abbr`,'')) = ''"
        . " OR `$table`.`pillar_id` NOT IN (SELECT `id` FROM `pm_pillars_tbl`)"
        . " OR `$table`.`objective_id` NOT IN (SELECT `id` FROM `pm_objectives_tbl` WHERE `pillar_id` = `$table`.`pillar_id`)"
        . " OR `$table`.`programme_id` NOT IN (SELECT `id` FROM `pm_programmes_tbl` WHERE `objective_id` = `$table`.`objective_id`))";
}

function activity_unfinished_count($db) {
    static $n = null;
    if ($n === null) { $r = $db->MQ("SELECT COUNT(*) AS n FROM pm_projects_tbl WHERE " . activity_gaps_sql(), "one"); $n = (int)($r['n'] ?? 0); }
    return $n;
}

/** A flag beside the code of an activity that still needs input; the tooltip names what. */
function activity_flag(array $gaps) {
    if (!$gaps) { return ''; }
    return '<i class="bx bxs-flag afcdc-flag" role="img" aria-label="Needs input" title="Needs input: ' . display(implode(', ', $gaps)) . '"></i>';
}

/** The red "Unfinished" tag with what is missing, for a list cell or the edit form. */
function activity_gap_note(array $gaps) {
    if (!$gaps) { return ''; }
    return '<div class="afcdc-gap__note"><span class="afcdc-gap__tag">Unfinished</span> missing: ' . display(implode(', ', $gaps)) . '</div>';
}

/** The words of a search box, at most $max: a sentence is not a search. */
function search_terms($search, $max = 6) {
    return array_slice(preg_split('/\s+/u', trim((string)$search), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, $max);
}

/**
 * A word as a LIKE pattern, bound. A typed % or _ is a character, not a
 * wildcard: "100%" used to match "100 days", and "_" alone every row.
 * MariaDB's LIKE escape is the backslash (no ESCAPE clause needed).
 */
function search_like($term) {
    return '%' . addcslashes((string)$term, '\\%_') . '%';
}

/**
 * Ranking a search of several words. A row used to need EVERY word, so
 * "Theo CPHIA" found nothing when one word was nowhere. Now a row is kept
 * when ANY word is found, and the rows holding ALL of them come first.
 *
 * The caller builds one clause per word from column names it controls
 * (never request input), with the bound values for it, in order. Back come
 * the pieces of a query:
 *   'select' / 'select_bind'  one 0/1 column per word (afcdc_hit_1 ...) and
 *                             their sum, afcdc_hits - put after the columns,
 *                             then "HAVING afcdc_hits > 0" and
 *                             "ORDER BY afcdc_hits DESC" ahead of the usual order
 *   'where'  / 'where_bind'   "(word OR word ...)" for a plain count
 * The per-word columns let a list say which words a row was found by.
 */
function search_rank(array $clauses, array $binds) {
    $clauses = array_values($clauses); $binds = array_values($binds);
    $hit = function ($c) { return "(CASE WHEN " . $c . " THEN 1 ELSE 0 END)"; };
    $select = []; $select_bind = [];
    foreach ($clauses as $i => $c) { $select[] = $hit($c) . " AS afcdc_hit_" . ($i + 1); $select_bind = array_merge($select_bind, $binds[$i]); }
    $select[] = "(" . implode(" + ", array_map($hit, $clauses)) . ") AS afcdc_hits";
    foreach ($binds as $b) { $select_bind = array_merge($select_bind, $b); }
    return [
        'select'      => implode(", ", $select),
        'select_bind' => $select_bind,
        'where'       => "(" . implode(" OR ", array_map(function ($c) { return "(" . $c . ")"; }, $clauses)) . ")",
        'where_bind'  => $binds ? array_merge(...$binds) : [],
        'words'       => count($clauses),
    ];
}

/**
 * For a row of a ranked search (search_rank's columns present): which words
 * it holds and which it does not, or null when it holds them all - or the
 * search was a single word, where there is nothing to explain.
 */
function search_hits_words(array $row, $search) {
    $terms = search_terms($search);
    if (count($terms) < 2 || !array_key_exists('afcdc_hits', $row)) { return null; }
    $found = []; $missed = [];
    foreach ($terms as $i => $t) {
        if ((int)($row['afcdc_hit_' . ($i + 1)] ?? 0) > 0) { $found[] = $t; } else { $missed[] = $t; }
    }
    return ($missed && $found) ? ['found' => $found, 'missed' => $missed] : null;
}

/** Under the name of a row found by some of the words only: which ones, so its place further down the list makes sense. */
function search_hits_note(array $row, $search) {
    $w = search_hits_words($row, $search);
    if ($w === null) { return ''; }
    $mark = function ($t) { return '<mark>' . display($t) . '</mark>'; };
    $gone = function ($t) { return '<s>' . display($t) . '</s>'; };
    return '<div class="afcdc-match afcdc-match--partial"><span class="afcdc-match__tag">Partial match</span> '
        . implode(', ', array_map($mark, $w['found'])) . " \u{00B7} not " . implode(', ', array_map($gone, $w['missed'])) . '</div>';
}

/**
 * Everything that hangs off an activity, deleted with it: its tasks and
 * what was recorded against them, its dates, milestones and percentages
 * with their records, and any filing proposal still waiting on it. The
 * list's delete used to remove the activity alone and leave all of this
 * pointing at nothing (four such tasks were found). Called inside the
 * caller's transaction, before the activity row itself goes. Returns what
 * went, for the confirmation.
 */
function activity_children_delete($db, $projectId) {
    $projectId = (int)$projectId;
    $gone = activity_children_count($db, $projectId);
    // Every row goes into the audit log before it goes, as
    // coreModel::delete_data does for the activity itself: a delivery record
    // says who reported what and when, and the log must not claim that one
    // row went when a dozen did.
    $user = (string)($_SESSION['user']['username'] ?? '');
    $remove = function ($table, $where, array $p) use ($db, $user) {
        foreach ((array)$db->MQ("SELECT * FROM `" . $table . "` WHERE " . $where, "all", $p) as $row) {
            $db->MQ("INSERT INTO `core_table_logs_tbl` (`tablename`, `record`, `log_date`, `user`) VALUES (?, ?, ?, ?)", false,
                [$table, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date("Y-m-d H:i:s"), $user]);
        }
        $db->MQ("DELETE FROM `" . $table . "` WHERE " . $where, false, $p);
    };
    $remove('pm_progress_tasks_tbl', "project_id = ? OR task_id IN (SELECT id FROM pm_projects_tasks_tbl WHERE project_id = ?)", [$projectId, $projectId]);
    foreach (['pm_progress_dates_tbl', 'pm_progress_milestones_tbl', 'pm_progress_percentages_tbl',
              'pm_projects_tasks_tbl', 'pm_projects_dates_tbl', 'pm_projects_milestones_tbl', 'pm_projects_percentages_tbl',
              'pm_allocation_review_tbl'] as $t) {
        $remove($t, "project_id = ?", [$projectId]);
    }
    return $gone;
}

/**
 * What activity_children_delete would remove: the tasks, and the delivery
 * records filed on the activity or on any of its tasks - the same
 * predicate as the delete, so the confirm on the form counts what goes.
 */
function activity_children_count($db, $projectId) {
    $projectId = (int)$projectId;
    $n = function ($sql, array $p) use ($db) { return (int)($db->MQ($sql, "one", $p)['n'] ?? 0); };
    return [
        'tasks'      => $n("SELECT COUNT(*) AS n FROM pm_projects_tasks_tbl WHERE project_id = ?", [$projectId]),
        'deliveries' => $n("SELECT COUNT(*) AS n FROM pm_progress_tasks_tbl WHERE project_id = ? OR task_id IN (SELECT id FROM pm_projects_tasks_tbl WHERE project_id = ?)", [$projectId, $projectId]),
    ];
}

/**
 * When a list is being searched and a row is there because of its
 * description rather than its name or code, say so under the name: the
 * passage around the first such word, with the words marked. Every word of
 * the search is looked for; a word already visible in the name or code
 * needs no explanation.
 */
function search_match_note(array $row, $search, array $visible = ['name', 'abbr']) {
    $search = trim((string)$search);
    if ($search === '') { return ''; }
    $terms = array_slice(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);
    if (!$terms) { return ''; }
    // Simple (1:1) case mapping, so offsets found in the lowered text hold in the original.
    $lower = function ($v) { return mb_convert_case((string)$v, MB_CASE_LOWER_SIMPLE, 'UTF-8'); };
    $shown = $lower(implode(' ', array_map(function ($f) use ($row) { return (string)($row[$f] ?? ''); }, $visible)));
    $desc  = trim(preg_replace('/\s+/u', ' ', (string)($row['description'] ?? '')));
    if ($desc === '') { return ''; }
    $ldesc = $lower($desc);
    $hidden = [];
    foreach ($terms as $t) {
        $lt = $lower($t);
        if (mb_strpos($shown, $lt) === false && mb_strpos($ldesc, $lt) !== false) { $hidden[] = $t; }
    }
    if (!$hidden) { return ''; }
    $at = (int)mb_strpos($ldesc, $lower($hidden[0]));
    $from = max(0, $at - 60);
    $snippet = mb_substr($desc, $from, 60 + mb_strlen($hidden[0]) + 90);
    // One pass over the raw passage, longest words first, each piece escaped
    // on its own: marking already-marked HTML would cut the tags themselves.
    usort($terms, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
    $re = '/(' . implode('|', array_map(function ($t) { return preg_quote($t, '/'); }, $terms)) . ')/iu';
    $parts = preg_split($re, $snippet, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) { $parts = [$snippet]; }
    $html = '';
    foreach ($parts as $k => $piece) { $html .= ($k % 2) ? '<mark>' . display($piece) . '</mark>' : display($piece); }
    return '<div class="afcdc-match"><span class="afcdc-match__tag">In description</span> '
        . ($from > 0 ? "\u{2026}" : '') . $html . (mb_strlen($desc) > $from + mb_strlen($snippet) ? "\u{2026}" : '') . '</div>';
}

/** The same passage as plain text (no marks), for the search dropdown; '' when the name or code already shows the words. */
function search_match_snippet(array $row, $search, array $visible = ['name', 'abbr']) {
    $note = search_match_note($row, $search, $visible);
    if ($note === '') { return ''; }
    $text = html_entity_decode(strip_tags(preg_replace('/^<div[^>]*><span[^>]*>In description<\/span> /', '', $note)), ENT_QUOTES, 'UTF-8');
    return trim($text);
}

