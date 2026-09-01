<?php
// Dev-only router for PHP's built-in server: serve real files, front-controller
// the rest. Mirrors public/.htaccess lines 20-23. Not part of the application.
//   php -S 127.0.0.1:8791 -t . tools/dev/router.php     (run from the repo root)
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . '/public' . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        // `return false` would make the built-in server look for the script
        // relative to ITS docroot (the repo root), not public/. Run it here
        // instead, from public/, the way Apache's DocumentRoot does.
        chdir($root . '/public');
        require $file;
        return true;
    }
    $mime = [
        'css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml',
        'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
        'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf',
        'eot'=>'application/vnd.ms-fontobject','map'=>'application/json',
        'ico'=>'image/x-icon','json'=>'application/json',
    ][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($file);
    return true;
}
// index.php does `require_once "../app/bootstrap.php"`, which is relative to the
// working directory, so the built-in server has to stand in public/ the way
// Apache's DocumentRoot does.
chdir($root . '/public');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/public/index.php';
