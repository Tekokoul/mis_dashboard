<?php
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
    $stem = function ($w) {
        if (strlen($w) > 6 && substr($w, -2) === 'al') { $w = substr($w, 0, -2); }
        if (preg_match('/^(.{3,})(ations|ation|ating|ated|ates|ate)$/', $w, $m)) { return $m[1] . 'at'; }
        if (strlen($w) > 5 && substr($w, -3) === 'ies') { return substr($w, 0, -3) . 'y'; }
        if (strlen($w) > 5 && substr($w, -3) === 'ing') { return substr($w, 0, -3); }
        if (strlen($w) > 4 && substr($w, -2) === 'ed') { return substr($w, 0, -2); }
        if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') { return substr($w, 0, -1); }
        return $w;
    };
    $words = function ($s) use ($stop, $stem) {
        $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s, 'UTF-8'));
        $out = [];
        foreach (preg_split('/\s+/', trim((string)$s)) as $w) {
            if ($w === '' || mb_strlen($w) < 2 || ctype_digit($w) || isset($stop[$w])) { continue; }
            $out[$stem($w)] = true;
        }
        return array_keys($out);
    };
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
        $scores = $applyDamp($score($docs, $names, $query), 'pillar');
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
    $oscores = $applyDamp($score($odocs, $onames, $query), 'objective');
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
    $pscores = $applyDamp($score($pdocs, $pnames, $query), 'programme');
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
    if ($top !== null && $ahead($v[0], $v[1] ?? 0)) {
        $best = $pscores[(int)$bestPrg[$top]['id']] ?? 0;
        // Clear within the objective too: the programme is the only one, or
        // it is clearly ahead of the next.
        $confident = ($secondPrg[$top] === null) || ($best > 0 && $ahead($best, $secondPrg[$top]));
    }
    return ['candidates' => $out, 'confident' => $confident];
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
function record_filing_feedback($db, $model, array $posted, $suggested, $rowId = 0) {
    if (!in_array($model, ['pm_objectives', 'pm_programmes', 'pm_projects'], true)) { return; }
    if (!filing_feedback_available($db)) { return; }
    $suggested = (array)$suggested;
    $sug = [
        'pillar'    => (int)($suggested['pillar_id']    ?? 0),
        'objective' => (int)($suggested['objective_id'] ?? 0),
        'programme' => (int)($suggested['programme_id'] ?? 0),
    ];
    if ($sug['pillar'] <= 0 && $sug['objective'] <= 0 && $sug['programme'] <= 0) { return; }
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
    if ($sug[$level] <= 0 || $chosen[$level] <= 0) { return; }
    // Accepted means nothing moved AT ANY level. Judging this by the filing
    // level alone missed the commonest correction in this data: an activity
    // kept its programme but was moved to another objective, because a
    // programme here does not always belong to the objective above it.
    $accepted = 1;
    foreach (['pillar', 'objective', 'programme'] as $lvl) {
        if ($sug[$lvl] > 0 && $sug[$lvl] !== $chosen[$lvl]) { $accepted = 0; }
    }
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
