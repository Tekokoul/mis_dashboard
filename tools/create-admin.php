<?php
/**
 * Create (or re-password) an administrator account.
 *
 *   docker compose exec app php /var/www/html/tools/create-admin.php you@africacdc.org
 *
 * The password is read from the terminal with echo disabled, so it never
 * appears in the argument list, the shell history, or `docker inspect`.
 *
 * HASHING: password_hash() (Argon2id where PHP has it, else bcrypt), the same
 * as app/models/core.php::create_password. Accounts created before 2 Sep 2026
 * still hold the old unsalted MD5(MD5()) hash; the login accepts it once and
 * rehashes, so nobody has to be re-passworded.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("This script is CLI-only.\n");
}

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, "usage: php tools/create-admin.php <username-or-email> [group-id]\n"
                 . "groups: 1 System Administrators (default) | 2 Executive | 3 Power | 4 Custom | 5 Member State\n");
    exit(1);
}
$group = (int)($argv[2] ?? 1);
if ($group < 1 || $group > 5) {
    fwrite(STDERR, "group must be 1-5\n");
    exit(1);
}

// --- database credentials, from the file the entrypoint generates ------------
$settings = [];
$local = __DIR__ . '/../app/configuration/settings.local.php';
if (!is_readable($local)) {
    fwrite(STDERR, "cannot read $local - is the container configured?\n");
    exit(1);
}
include $local;
$db = $settings['db_master'] ?? [];
if (empty($db['db_host'])) {
    fwrite(STDERR, "no database configuration found\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $db['db_host'], $db['db_port'] ?? 3306, $db['db_database']),
        $db['db_user'],
        base64_decode($db['db_password']),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "cannot connect to the database: " . $e->getMessage() . "\n");
    exit(1);
}

// --- read the password twice, with echo off ----------------------------------
function prompt_hidden(string $label): string
{
    $tty = stream_isatty(STDIN);
    fwrite(STDOUT, $label);
    if ($tty) { shell_exec('stty -echo'); }
    $value = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) { shell_exec('stty echo'); fwrite(STDOUT, "\n"); }
    return $value;
}

$pw  = prompt_hidden("Password for {$username}: ");
$pw2 = prompt_hidden("Confirm: ");

if ($pw === '')      { fwrite(STDERR, "empty password refused - nothing changed\n"); exit(1); }
if ($pw !== $pw2)    { fwrite(STDERR, "passwords did not match - nothing changed\n"); exit(1); }
if (strlen($pw) < 12) {
    fwrite(STDERR, "refusing a password under 12 characters.\n");
    exit(1);
}

// Same scheme as app/models/core.php::create_password.
$hash = password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
// Wipe the plaintext from memory where the extension is available.
if (function_exists('sodium_memzero')) { sodium_memzero($pw); sodium_memzero($pw2); }
unset($pw, $pw2);

// --- upsert -------------------------------------------------------------------
$existing = $pdo->prepare('SELECT id FROM core_users_tbl WHERE username = ?');
$existing->execute([$username]);
$id = $existing->fetchColumn();

if ($id) {
    $pdo->prepare('UPDATE core_users_tbl SET password = ?, active = 1, `group` = ? WHERE id = ?')
        ->execute([$hash, $group, $id]);
    printf("updated account %s (id %d, group %d)\n", $username, $id, $group);
} else {
    $pdo->prepare('INSERT INTO core_users_tbl (username, password, givenname, sn, active, `group`)
                   VALUES (?, ?, ?, ?, 1, ?)')
        ->execute([$username, $hash, 'Africa CDC', 'Administrator', $group]);
    $id = (int)$pdo->lastInsertId();
    printf("created account %s (id %d, group %d)\n", $username, $id, $group);
}

// Administrators must also be able to record delivery, which is gated on
// pm_members_tbl.account - a comma-separated list of user ids.
if ($group === 1) {
    $pdo->exec("UPDATE pm_members_tbl
                   SET account = (SELECT GROUP_CONCAT(id ORDER BY id)
                                    FROM core_users_tbl
                                   WHERE `group` = 1 AND active = 1)
                 WHERE id = 1");
    $who = $pdo->query('SELECT account FROM pm_members_tbl WHERE id = 1')->fetchColumn();
    printf("accounts permitted to record delivery: %s\n", $who ?: '(none)');
}
