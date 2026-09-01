<?php
// Container health endpoint. Deliberately tiny and dependency-light: it must be
// able to answer while the rest of the app is failing, so it does not bootstrap
// the framework. It reports 200 only when PHP is serving AND the database
// answers, which is what an orchestrator needs in order to route traffic.
header('Content-Type: application/json');
header('Cache-Control: no-store');

$out = ['status' => 'ok', 'php' => PHP_VERSION, 'database' => 'unknown'];

$local = __DIR__ . '/../app/configuration/settings.local.php';
$settings = [];
if (is_readable($local)) {
    // settings.local.php only assigns into $settings; including it here gives
    // the credentials without loading the whole framework.
    include $local;
}
$db = $settings['db_master'] ?? [];

try {
    if (empty($db['db_host'])) {
        throw new RuntimeException('no database configuration');
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $db['db_host'], $db['db_port'] ?? 3306, $db['db_database']);
    $pdo = new PDO($dsn, $db['db_user'], base64_decode($db['db_password']), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT            => 3,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->query('SELECT 1');
    $out['database'] = 'ok';
} catch (Throwable $e) {
    // Never leak the DSN, credentials or driver message to an unauthenticated
    // caller - the health endpoint is reachable without logging in.
    $out['status']   = 'degraded';
    $out['database'] = 'unreachable';
    http_response_code(503);
}

echo json_encode($out), "\n";
