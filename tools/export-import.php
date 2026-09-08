<?php
/**
 * Turn the accepted rows of a workbook import into SQL for the live server.
 *
 *   docker compose exec -T app php /var/www/html/tools/export-import.php <batch-id|all> > import-live.sql
 *
 * The import page writes to the local copy only. When the feature is still
 * off on the server, this is how what was accepted here reaches live: one
 * guarded statement per row, in a transaction, with checks that must come
 * back empty before COMMIT. Nothing is sent anywhere by this tool; the file
 * is copied to the server and run there by hand, as root, after a backup:
 *
 *   docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < /tmp/import-live.sql
 *
 *   NEW activities  - INSERT with the local id, so the two copies keep the
 *                     same ids (they have since the local copy was taken
 *                     from live), guarded by NOT EXISTS on that id AND on the
 *                     code, plus the "Delivered" task with its local id.
 *   CHANGED ones    - UPDATE ... WHERE id = ? AND name = <the name before>,
 *                     so a row edited on live since is left alone and shows
 *                     up in the check.
 *
 * Skipped and unchanged rows produce nothing. Filing corrections
 * (pm_filing_feedback_tbl) stay local: live learns from its own users.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require_once __DIR__ . '/../app/includes/library.php';
require_once __DIR__ . '/../app/includes/import.php';
require_once __DIR__ . '/../app/db.class.php';

$which = (string)($argv[1] ?? '');
if ($which === '') { fwrite(STDERR, "usage: export-import.php <batch-id|all>\n"); exit(1); }
$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
if (!import_available($db)) { fwrite(STDERR, "the import tables are missing\n"); exit(1); }

$where = $which === 'all' ? "" : " AND r.batch_id = " . (int)$which;
$rows = (array)$db->MQ("SELECT r.*, b.filename FROM pm_import_rows_tbl r JOIN pm_import_batches_tbl b ON b.id = r.batch_id
                         WHERE r.status = 'accepted' AND r.result_project_id > 0" . $where . " ORDER BY r.batch_id, r.row_no, r.id", "all");
if (!$rows) { fwrite(STDERR, "nothing accepted" . ($which === 'all' ? '' : " in import #" . (int)$which) . "\n"); exit(1); }

$q = function ($v) use ($db) {
    if ($v === null) { return 'NULL'; }
    if (is_int($v) || is_float($v)) { return (string)$v; }
    return "'" . addcslashes((string)$v, "\\'\0\n\r\x1a") . "'";
};
$out = [];
$out[] = "-- Workbook import" . ($which === 'all' ? 's' : ' #' . (int)$which) . " accepted on the local copy, exported " . date('Y-m-d H:i') . ".";
$out[] = "-- Run as root inside a transaction; the checks at the end must return no rows before COMMIT.";
$out[] = "START TRANSACTION;";
$newIds = []; $updated = [];
foreach ($rows as $r) {
    $pid = (int)$r['result_project_id'];
    $p = $db->MQ("SELECT * FROM pm_projects_tbl WHERE id = ?", "one", [$pid]);
    if (!is_set($p)) { $out[] = "-- row " . (int)$r['row_no'] . " of " . $r['filename'] . ": activity #" . $pid . " no longer exists locally; skipped"; continue; }
    if ($r['kind'] === 'changed') {
        $changes = json_decode((string)$r['changes'], true) ?: [];
        $sets = [];
        foreach (['name', 'description', 'kpi', 'estimated_budget'] as $f) {
            if (!isset($changes[$f])) { continue; }
            $sets[] = "`" . $f . "` = " . $q($f === 'estimated_budget' ? (float)$changes[$f][1] : (string)$changes[$f][1]);
        }
        if (!$sets) { continue; }
        $before = isset($changes['name']) ? (string)$changes['name'][0] : (string)$p['name'];
        $out[] = "-- row " . (int)$r['row_no'] . " of " . $r['filename'] . ": update #" . $pid . " " . $p['abbr'];
        $out[] = "UPDATE pm_projects_tbl SET " . implode(', ', $sets) . " WHERE id = " . $pid . " AND name = " . $q($before) . ";";
        $updated[] = [$pid, isset($changes['name']) ? (string)$changes['name'][1] : $before];
        continue;
    }
    // A new activity, with its local id and its task.
    $cols = ['id', 'pillar_id', 'objective_id', 'programme_id', 'name', 'abbr', 'description', 'kpi', 'estimated_budget', 'actual_budget', 'notes', 'type', 'applies_to', 'active'];
    $vals = [];
    foreach ($cols as $c) {
        $v = $p[$c];
        if (in_array($c, ['id', 'pillar_id', 'objective_id', 'programme_id', 'active'], true)) { $v = $v === null ? null : (int)$v; }
        elseif (in_array($c, ['estimated_budget', 'actual_budget'], true)) { $v = $v === null ? null : (float)$v; }
        $vals[] = $q($v);
    }
    $out[] = "-- row " . (int)$r['row_no'] . " of " . $r['filename'] . ": new #" . $pid . " " . $p['abbr'] . " " . mb_substr((string)$p['name'], 0, 60);
    $out[] = "INSERT INTO pm_projects_tbl (" . implode(', ', $cols) . ")\n  SELECT " . implode(', ', $vals) . " FROM DUAL\n   WHERE NOT EXISTS (SELECT 1 FROM pm_projects_tbl WHERE id = " . $pid . " OR abbr = " . $q($p['abbr']) . ");";
    foreach ((array)$db->MQ("SELECT * FROM pm_projects_tasks_tbl WHERE project_id = ? ORDER BY id", "all", [$pid]) as $t) {
        // Both halves of the guard matter. The first stops the task being
        // written twice; the second stops it being written at all when the
        // activity insert above was refused because that id already belongs
        // to something else on live - otherwise a stray task hangs off a
        // stranger's activity AND satisfies the "every new activity has its
        // task" check, hiding the refusal.
        $out[] = "INSERT INTO pm_projects_tasks_tbl (id, project_id, tasks, name, description, applies_to)\n  SELECT " . (int)$t['id'] . ", " . $pid . ", " . $q($t['tasks']) . ", " . $q($t['name']) . ", " . $q($t['description']) . ", " . $q($t['applies_to']) . " FROM DUAL\n   WHERE NOT EXISTS (SELECT 1 FROM pm_projects_tasks_tbl WHERE id = " . (int)$t['id'] . ")\n     AND EXISTS (SELECT 1 FROM pm_projects_tbl WHERE id = " . $pid . " AND abbr = " . $q($p['abbr']) . ");";
    }
    $newIds[] = [$pid, (string)$p['abbr']];
}
$out[] = "";
$out[] = "-- Checks: each must return no rows.";
if ($newIds) {
    $pairs = implode(' OR ', array_map(function ($n) use ($q) { return "(id = " . $n[0] . " AND abbr = " . $q($n[1]) . ")"; }, $newIds));
    $out[] = "-- 1. every new activity is there under its code";
    $out[] = "SELECT " . count($newIds) . " - COUNT(*) AS missing_new FROM pm_projects_tbl WHERE " . $pairs . " HAVING missing_new <> 0;";
    $out[] = "-- 2. and each has its task";
    $out[] = "SELECT p.id FROM pm_projects_tbl p LEFT JOIN pm_projects_tasks_tbl t ON t.project_id = p.id WHERE p.id IN (" . implode(',', array_column($newIds, 0)) . ") AND t.id IS NULL;";
}
if ($updated) {
    $pairs = implode(' OR ', array_map(function ($u) use ($q) { return "(id = " . $u[0] . " AND name = " . $q($u[1]) . ")"; }, $updated));
    $out[] = "-- 3. every update landed (the WHERE ... AND name = <before> guard refused none)";
    $out[] = "SELECT " . count($updated) . " - COUNT(*) AS missing_updates FROM pm_projects_tbl WHERE " . $pairs . " HAVING missing_updates <> 0;";
}
$out[] = "-- 4. no code is used twice";
$out[] = "SELECT abbr, COUNT(*) AS n FROM pm_projects_tbl GROUP BY abbr HAVING n > 1;";
$out[] = "";
$out[] = "-- If every check returned nothing:";
$out[] = "COMMIT;";
$out[] = "-- Otherwise:";
$out[] = "-- ROLLBACK;";
echo implode("\n", $out), "\n";
fwrite(STDERR, sprintf("%d new, %d updated\n", count($newIds), count($updated)));
