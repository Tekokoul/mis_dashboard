<?php
/**
 * Re-file imported activities from a decisions file, and record every move
 * for vetting.
 *
 *   docker compose exec -T app php /var/www/html/tools/allocate-imported.php /tmp/decisions.json [--dry-run]
 *
 * decisions.json is a list of {"id", "objective_id", "programme_id",
 * "confidence": "agreed|split|low|code", "reason", "abbr"}. For each activity
 * the tool checks the programme really belongs to the objective, takes the
 * goal from the objective, gives the activity the next free code under that
 * programme (in the order of its old code, so imported siblings keep their
 * order), writes the old and new placement to pm_allocation_review_tbl with
 * status "proposed", and updates the activity. Tasks and recorded deliveries
 * are not touched: they hang off the activity id, which does not change.
 *
 * A decision may leave objective_id and programme_id out: the activity stays
 * where it is and only its code is fixed ("7.1.2 Act" -> "7.1.2"). An "abbr"
 * in the decision is used as the new code when it fits the programme's code
 * and no other activity has it; otherwise the next free code is taken.
 *
 * Idempotent: an activity that already has a review row is skipped, so the
 * tool can be re-run after a partial failure without moving anything twice.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require __DIR__ . '/../app/includes/library.php';
require __DIR__ . '/../app/db.class.php';

$file = $argv[1] ?? '';
$dry  = in_array('--dry-run', $argv, true);
if ($file === '' || !is_readable($file)) { fwrite(STDERR, "usage: allocate-imported.php <decisions.json> [--dry-run]\n"); exit(1); }
$decisions = json_decode((string)file_get_contents($file), true);
if (!is_array($decisions)) { fwrite(STDERR, "decisions.json is not a JSON list\n"); exit(1); }

$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
if (!is_set($db->MQ("SHOW TABLES LIKE 'pm_allocation_review_tbl'", "one"))) { fwrite(STDERR, "pm_allocation_review_tbl is missing - start the container once so the migration creates it\n"); exit(1); }

$objectives = []; foreach ((array)$db->MQ("SELECT id, pillar_id, abbr FROM pm_objectives_tbl", "all") as $r) { $objectives[(int)$r['id']] = $r; }
$programmes = []; foreach ((array)$db->MQ("SELECT id, objective_id, abbr FROM pm_programmes_tbl", "all") as $r) { $programmes[(int)$r['id']] = $r; }

// Siblings keep the order of their old codes, so "…06.01" is numbered before "…06.03".
usort($decisions, function ($a, $b) use ($db) { return strnatcmp((string)($a['_old_abbr'] ?? $a['id']), (string)($b['_old_abbr'] ?? $b['id'])); });
$rows = [];
foreach ((array)$db->MQ("SELECT id, abbr, pillar_id, objective_id, programme_id FROM pm_projects_tbl", "all") as $r) { $rows[(int)$r['id']] = $r; }
usort($decisions, function ($a, $b) use ($rows) { return strnatcmp((string)($rows[(int)$a['id']]['abbr'] ?? ''), (string)($rows[(int)$b['id']]['abbr'] ?? '')); });

$moved = 0; $skipped = 0; $bad = 0;
printf("%-4s %-13s %-9s %s\n", 'id', 'old code', 'new code', 'placement');
foreach ($decisions as $d) {
    $id = (int)($d['id'] ?? 0);
    $row = $rows[$id] ?? null;
    if (!$row) { printf("%-4d SKIP: no such activity\n", $id); $bad++; continue; }
    // No placement in the decision = a code-only fix: the activity stays put.
    $o = (int)($d['objective_id'] ?? $row['objective_id']); $p = (int)($d['programme_id'] ?? $row['programme_id']);
    if (!isset($objectives[$o]) || !isset($programmes[$p]) || (int)$programmes[$p]['objective_id'] !== $o) {
        printf("%-4d SKIP: programme %d is not under objective %d\n", $id, $p, $o); $bad++; continue;
    }
    if (is_set($db->MQ("SELECT id FROM pm_allocation_review_tbl WHERE project_id = ?", "one", [$id]))) { $skipped++; continue; }
    $pillar = (int)$objectives[$o]['pillar_id'];
    $code   = '';
    $wanted = trim((string)($d['abbr'] ?? ''));
    if ($wanted !== '') {
        // Keep the code the decision names when it sits under the programme's
        // code and nobody else has it - "7.1.2 Act" keeps its number as 7.1.2.
        $prefix = preg_match('/^\d+(?:\.\d+)*/', trim((string)$programmes[$p]['abbr']), $m) ? $m[0] : '';
        $taken  = $db->MQ("SELECT id FROM pm_projects_tbl WHERE abbr = ? AND id <> ?", "one", [$wanted, $id]);
        if ($prefix !== '' && preg_match('/^' . preg_quote($prefix, '/') . '\.\d+$/', $wanted) && !is_set($taken)) { $code = $wanted; }
    }
    if ($code === '') { $code = auto_wbs_code($db, 'pm_projects', ['programme_id' => $p]); }
    if ($code === '') { printf("%-4d SKIP: programme %d has no numeric code to number under\n", $id, $p); $bad++; continue; }
    $conf = in_array($d['confidence'] ?? '', ['agreed', 'split', 'low', 'code'], true) ? $d['confidence'] : 'agreed';
    printf("%-4d %-13s %-9s %s / %s  [%s]\n", $id, $row['abbr'], $code, $objectives[$o]['abbr'], $programmes[$p]['abbr'], $conf);
    if ($dry) { $moved++; continue; }
    $db->MQ("INSERT INTO pm_allocation_review_tbl (project_id, old_pillar_id, old_objective_id, old_programme_id, old_abbr,
                new_pillar_id, new_objective_id, new_programme_id, new_abbr, confidence, reason, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,'proposed')", false,
            [$id, (int)$row['pillar_id'], (int)$row['objective_id'], (int)$row['programme_id'], (string)$row['abbr'],
             $pillar, $o, $p, $code, $conf, mb_substr((string)($d['reason'] ?? ''), 0, 1000)]);
    $db->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = ?, abbr = ? WHERE id = ?", false, [$pillar, $o, $p, $code, $id]);
    $moved++;
}
printf("\n%s %d activities, skipped %d already reviewed, %d rejected as invalid.\n", $dry ? 'Would move' : 'Moved', $moved, $skipped, $bad);
