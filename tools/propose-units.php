<?php
/**
 * Load units proposed for objectives into the vetting table.
 *
 *   docker compose exec -T app php /var/www/html/tools/propose-units.php - < unit-proposals.json
 *   ... add --dry-run to see what it would do; it then writes nothing.
 *
 * The file is what the unit vetting produced: {"proposals": [{"objective_id",
 * "name", "unit" (software_development | infrastructure_networking |
 * procurement | digital_health | none), "agreement" (agreed | majority |
 * split), "reason", "dissent": [...], "other_units": [...]}]}. It is kept
 * off the repository. Nothing here sets a unit: each proposal waits on the
 * Objectives list for a person. An objective is skipped when its name here
 * is not the name the proposal was made for (renamed, or the id is a
 * different objective on this copy), when it already has a unit, or when a
 * person has already decided its proposal.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require_once __DIR__ . '/../app/includes/library.php';
require_once __DIR__ . '/../app/db.class.php';

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$args = array_values(array_filter($args, function ($a) { return $a !== '--dry-run'; }));
$src = (string)($args[0] ?? '');
if ($src === '') { fwrite(STDERR, "usage: propose-units.php <file.json|-> [--dry-run]\n"); exit(1); }
$raw = ($src === '-') ? stream_get_contents(STDIN) : @file_get_contents($src);
$doc = json_decode((string)$raw, true);
if (!is_array($doc) || !isset($doc['proposals']) || !is_array($doc['proposals'])) { fwrite(STDERR, "that is not a proposals file\n"); exit(1); }

$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
if (!unit_review_available($db)) { fwrite(STDERR, "units are switched off or their tables are missing: set UNITS_ENABLED=true in .env and recreate the app container so its migrations run\n"); exit(1); }

$idByName = []; $nameById = [];
foreach ((array)$db->MQ("SELECT id, name FROM pm_units_tbl", "all") as $u) { $idByName[mb_strtolower((string)$u['name'])] = (int)$u['id']; $nameById[(int)$u['id']] = (string)$u['name']; }
$keys = ['software_development' => 'software development', 'infrastructure_networking' => 'infrastructure and networking',
         'procurement' => 'procurement', 'digital_health' => 'digital health', 'none' => ''];
foreach ($keys as $n) {
    if ($n !== '' && !isset($idByName[$n])) { fwrite(STDERR, "there is no unit called \"$n\" in pm_units_tbl - was it renamed? Nothing loaded.\n"); exit(1); }
}
$unitId = function ($k) use ($keys, $idByName) { $n = $keys[(string)$k] ?? ''; return $n === '' ? 0 : $idByName[$n]; };

$loaded = 0; $skipped = 0;
foreach ($doc['proposals'] as $p) {
    if (!is_array($p)) { continue; }
    $oid = (int)($p['objective_id'] ?? 0);
    $label = '#' . $oid . ' ' . mb_substr((string)($p['name'] ?? ''), 0, 60);
    $o = $oid > 0 ? $db->MQ("SELECT id, name, IFNULL(unit_id, 0) AS unit_id FROM pm_objectives_tbl WHERE id = ?", "one", [$oid]) : null;
    if (!is_set($o)) { $skipped++; echo "skip  $label: no such objective here\n"; continue; }
    if (trim((string)$o['name']) !== trim((string)($p['name'] ?? ''))) { $skipped++; echo "skip  $label: here it is called \"" . $o['name'] . "\"\n"; continue; }
    if ((int)$o['unit_id'] > 0) { $skipped++; echo "skip  $label: already has a unit\n"; continue; }
    if (!array_key_exists((string)($p['unit'] ?? ''), $keys)) { $skipped++; echo "skip  $label: unknown unit \"" . (string)($p['unit'] ?? '') . "\"\n"; continue; }
    $existing = $db->MQ("SELECT status FROM pm_unit_review_tbl WHERE objective_id = ?", "one", [$oid]);
    if (is_set($existing) && (string)$existing['status'] !== 'proposed') { $skipped++; echo "skip  $label: a person already decided (" . $existing['status'] . ")\n"; continue; }
    $uid = $unitId($p['unit']);
    $agreement = in_array((string)($p['agreement'] ?? ''), ['agreed', 'majority', 'split'], true) ? (string)$p['agreement'] : 'split';
    if ($uid === 0) { $agreement = 'split'; }
    $others = [];
    foreach ((array)($p['other_units'] ?? []) as $k) { $u = $unitId($k); if ($u > 0 && $u !== $uid) { $others[$u] = $u; } }
    $reason = mb_substr(trim((string)($p['reason'] ?? '')), 0, 2000);
    $dissent = mb_substr(implode("\n", array_map('strval', (array)($p['dissent'] ?? []))), 0, 2000);
    echo ($dry ? 'would load ' : 'load  ') . "$label -> " . ($uid ? $nameById[$uid] : 'no unit agreed') . " ($agreement)\n";
    $loaded++;
    if ($dry) { continue; }
    $db->MQ("INSERT INTO pm_unit_review_tbl (objective_id, unit_id, agreement, reason, dissent, other_unit_ids, status)
             VALUES (?, ?, ?, ?, ?, ?, 'proposed')
             ON DUPLICATE KEY UPDATE unit_id = VALUES(unit_id), agreement = VALUES(agreement), reason = VALUES(reason),
                                     dissent = VALUES(dissent), other_unit_ids = VALUES(other_unit_ids)", false,
            [$oid, $uid, $agreement, $reason, $dissent, implode(',', $others)]);
}
fwrite(STDERR, ($dry ? '[dry run, nothing written] ' : '') . "$loaded loaded, $skipped skipped\n");
