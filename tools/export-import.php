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
 *                     code, plus its tasks with their local ids.
 *   CHANGED ones    - UPDATE ... WHERE id = ? AND name = <the name before>,
 *                     so a row edited on live since is left alone and shows
 *                     up in the check. Task edits the same way (WHERE id,
 *                     project_id, and the name - or the description - before);
 *                     tasks the workbook added go in with the ids they were
 *                     given here, or with ids of live's own where those are
 *                     taken. A task that took the place of a default one is
 *                     renamed on live only while that row is still the default
 *                     AND nothing has been recorded against it there; when it
 *                     has, the task is added beside it instead.
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
$newIds = []; $updated = []; $taskUpdates = []; $taskDescUpdates = []; $taskAdds = []; $newTasks = []; $replaced = 0;
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
        $tc = (array)($changes['tasks'] ?? []);
        if (!$sets && !$tc) { continue; }
        $out[] = "-- row " . (int)$r['row_no'] . " of " . $r['filename'] . ": update #" . $pid . " " . $p['abbr'];
        if ($sets) {
            $before = isset($changes['name']) ? (string)$changes['name'][0] : (string)$p['name'];
            $out[] = "UPDATE pm_projects_tbl SET " . implode(', ', $sets) . " WHERE id = " . $pid . " AND name = " . $q($before) . ";";
            $updated[] = [$pid, isset($changes['name']) ? (string)$changes['name'][1] : $before];
        }
        foreach ((array)($tc['edit'] ?? []) as $e) {
            $set = [];
            if (isset($e['name'])) { $set[] = "name = " . $q((string)$e['name'][1]); }
            if (isset($e['description'])) { $set[] = "description = " . $q((string)$e['description'][1]); }
            if (!$set) { continue; }
            // Guarded by what the workbook was compared against, so an edit made
            // on live since is refused and shows up in the checks rather than
            // being written over. A description-only change is guarded by the
            // description; it used to travel with no guard at all.
            $guard = isset($e['name']) ? " AND name = " . $q((string)$e['name'][0])
                                       : " AND IFNULL(description, '') = " . $q((string)$e['description'][0]);
            $out[] = "UPDATE pm_projects_tasks_tbl SET " . implode(', ', $set) . " WHERE id = " . (int)$e['id'] . " AND project_id = " . $pid . $guard . ";";
            if (isset($e['name'])) { $taskUpdates[] = [(int)$e['id'], (string)$e['name'][1]]; }
            else { $taskDescUpdates[] = [(int)$e['id'], (string)$e['description'][1]]; }
        }
        foreach ((array)($tc['add'] ?? []) as $a) {
            $tid = (int)($a['id'] ?? 0);
            $t = $tid > 0 ? $db->MQ("SELECT * FROM pm_projects_tasks_tbl WHERE id = ? AND project_id = ?", "one", [$tid, $pid]) : null;
            if (!is_set($t)) { $out[] = "-- task " . $q((string)($a['name'] ?? '')) . " added to #" . $pid . " is not there locally (not accepted, or removed since); skipped"; continue; }
            $has  = "EXISTS (SELECT 1 FROM pm_projects_tbl WHERE id = " . $pid . " AND abbr = " . $q($p['abbr']) . ")";
            $fresh = "NOT EXISTS (SELECT 1 FROM pm_projects_tasks_tbl WHERE project_id = " . $pid . " AND name = " . $q($t['name']) . ")";
            if ((int)($a['replaces'] ?? 0) === $tid) {
                // Here it took the place of the default task. On live that same row
                // may be the one everybody has been reporting against, so it is
                // renamed only while it is still the default AND nothing has been
                // recorded against it. When it has, the two INSERTs below add the
                // task beside it instead - which is what accepting would have done.
                $out[] = "UPDATE pm_projects_tasks_tbl SET name = " . $q($t['name']) . ", description = " . $q($t['description'])
                       . " WHERE id = " . $tid . " AND project_id = " . $pid
                       . " AND LOWER(TRIM(IFNULL(name, ''))) IN ('', 'task', 'delivered')"
                       . " AND NOT EXISTS (SELECT 1 FROM pm_progress_tasks_tbl WHERE task_id = " . $tid . ");";
                $replaced++;
            }
            // With the id it carries here when live has not given that id to
            // something else, otherwise with an id of live's own. Either way once:
            // the second is skipped as soon as the activity has a task of that
            // name, which the rename above or the first INSERT has just made true.
            $out[] = "INSERT INTO pm_projects_tasks_tbl (id, project_id, tasks, name, description, applies_to)\n  SELECT " . $tid . ", " . $pid . ", " . $q($t['tasks']) . ", " . $q($t['name']) . ", " . $q($t['description']) . ", " . $q($t['applies_to']) . " FROM DUAL\n   WHERE NOT EXISTS (SELECT 1 FROM pm_projects_tasks_tbl WHERE id = " . $tid . ")\n     AND " . $fresh . "\n     AND " . $has . ";";
            $out[] = "INSERT INTO pm_projects_tasks_tbl (project_id, tasks, name, description, applies_to)\n  SELECT " . $pid . ", " . $q($t['tasks']) . ", " . $q($t['name']) . ", " . $q($t['description']) . ", " . $q($t['applies_to']) . " FROM DUAL\n   WHERE " . $fresh . "\n     AND " . $has . ";";
            $taskAdds[] = [$pid, (string)$t['name']];
        }
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
        $has  = "EXISTS (SELECT 1 FROM pm_projects_tbl WHERE id = " . $pid . " AND abbr = " . $q($p['abbr']) . ")";
        $fresh = "NOT EXISTS (SELECT 1 FROM pm_projects_tasks_tbl WHERE project_id = " . $pid . " AND name = " . $q($t['name']) . ")";
        $out[] = "INSERT INTO pm_projects_tasks_tbl (id, project_id, tasks, name, description, applies_to)\n  SELECT " . (int)$t['id'] . ", " . $pid . ", " . $q($t['tasks']) . ", " . $q($t['name']) . ", " . $q($t['description']) . ", " . $q($t['applies_to']) . " FROM DUAL\n   WHERE NOT EXISTS (SELECT 1 FROM pm_projects_tasks_tbl WHERE id = " . (int)$t['id'] . ")\n     AND " . $fresh . "\n     AND " . $has . ";";
        // And with an id of live's own when that id is taken there, so a task is
        // never quietly left behind - check 2b below counts them all.
        $out[] = "INSERT INTO pm_projects_tasks_tbl (project_id, tasks, name, description, applies_to)\n  SELECT " . $pid . ", " . $q($t['tasks']) . ", " . $q($t['name']) . ", " . $q($t['description']) . ", " . $q($t['applies_to']) . " FROM DUAL\n   WHERE " . $fresh . "\n     AND " . $has . ";";
        $newTasks[] = [$pid, (string)$t['name']];
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
if ($newTasks) {
    $pairs = implode(' OR ', array_map(function ($t) use ($q) { return "(project_id = " . $t[0] . " AND name = " . $q($t[1]) . ")"; }, $newTasks));
    $out[] = "-- 2b. every one of their tasks, not just the first";
    $out[] = "SELECT " . count($newTasks) . " - COUNT(DISTINCT project_id, name) AS missing_new_tasks FROM pm_projects_tasks_tbl WHERE " . $pairs . " HAVING missing_new_tasks <> 0;";
}
if ($updated) {
    $pairs = implode(' OR ', array_map(function ($u) use ($q) { return "(id = " . $u[0] . " AND name = " . $q($u[1]) . ")"; }, $updated));
    $out[] = "-- 3. every update landed (the WHERE ... AND name = <before> guard refused none)";
    $out[] = "SELECT " . count($updated) . " - COUNT(*) AS missing_updates FROM pm_projects_tbl WHERE " . $pairs . " HAVING missing_updates <> 0;";
}
if ($taskUpdates) {
    $pairs = implode(' OR ', array_map(function ($u) use ($q) { return "(id = " . $u[0] . " AND name = " . $q($u[1]) . ")"; }, $taskUpdates));
    $out[] = "-- 5. every task rename landed";
    $out[] = "SELECT " . count($taskUpdates) . " - COUNT(*) AS missing_task_updates FROM pm_projects_tasks_tbl WHERE " . $pairs . " HAVING missing_task_updates <> 0;";
}
if ($taskDescUpdates) {
    $pairs = implode(' OR ', array_map(function ($u) use ($q) { return "(id = " . $u[0] . " AND IFNULL(description, '') = " . $q($u[1]) . ")"; }, $taskDescUpdates));
    $out[] = "-- 5b. every task whose description alone changed took it";
    $out[] = "SELECT " . count($taskDescUpdates) . " - COUNT(*) AS missing_task_descriptions FROM pm_projects_tasks_tbl WHERE " . $pairs . " HAVING missing_task_descriptions <> 0;";
}
if ($taskAdds) {
    // By activity and name: a task added here may carry an id live had already
    // given to something else, in which case it went in under live's own id.
    $pairs = implode(' OR ', array_map(function ($a) use ($q) { return "(project_id = " . $a[0] . " AND name = " . $q($a[1]) . ")"; }, $taskAdds));
    $out[] = "-- 6. every task the workbook added is on its activity (renamed in place, or added beside a default that already had deliveries)";
    $out[] = "SELECT " . count($taskAdds) . " - COUNT(DISTINCT project_id, name) AS missing_tasks FROM pm_projects_tasks_tbl WHERE " . $pairs . " HAVING missing_tasks <> 0;";
}
$out[] = "-- 4. no code is used twice";
$out[] = "SELECT abbr, COUNT(*) AS n FROM pm_projects_tbl GROUP BY abbr HAVING n > 1;";
$out[] = "";
$out[] = "-- If every check returned nothing:";
$out[] = "COMMIT;";
$out[] = "-- Otherwise:";
$out[] = "-- ROLLBACK;";
echo implode("\n", $out), "\n";
fwrite(STDERR, sprintf("%d new, %d updated, %d task renames, %d task descriptions, %d tasks added (%d of them taking a default task's place)\n",
    count($newIds), count($updated), count($taskUpdates), count($taskDescUpdates), count($taskAdds), $replaced));
