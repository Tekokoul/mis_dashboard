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

function display_weight($text = "", $in="kg"){
    $answer = "N/A";
    if($in=="kg"){
        $answer = round((double)$text/1000, 2, PHP_ROUND_HALF_UP)." kg";
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