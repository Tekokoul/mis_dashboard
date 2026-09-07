<?php
/**
 * The vetted moves as SQL for the live database, by exact id.
 *
 *   docker compose exec -T app php /var/www/html/tools/export-allocations.php [accepted|all] > allocations.sql
 *
 * "accepted" (default) exports only moves a person has accepted; "all" also
 * exports the ones still pending. Each row is an UPDATE of its four
 * placement columns to where the activity NOW sits on the local copy - so a
 * placement changed by hand on the edit form during vetting is what ships,
 * not the AI's proposal - guarded by the code the live row is expected to
 * carry, inside one transaction. A rollback block with the old values
 * follows, and two SELECTs at the end must both come back empty. Run it on
 * the server as root:
 *   mariadb ... < allocations.sql
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require __DIR__ . '/../app/includes/library.php';
require __DIR__ . '/../app/db.class.php';
$which = ($argv[1] ?? 'accepted') === 'all' ? ['accepted', 'proposed'] : ['accepted'];
$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
$marks = implode(',', array_fill(0, count($which), '?'));
$rows = (array)$db->MQ("SELECT r.*, p.name, p.pillar_id AS cur_pillar_id, p.objective_id AS cur_objective_id, p.programme_id AS cur_programme_id, p.abbr AS cur_abbr
                        FROM pm_allocation_review_tbl r JOIN pm_projects_tbl p ON p.id = r.project_id
                        WHERE r.status IN ($marks) ORDER BY r.project_id", "all", $which);
$q = function ($v) { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'"; };
// The activity name rides along as a comment: one line, no comment-closers.
$c = function ($v) { return str_replace(['*/', '--'], ['* /', '- -'], mb_substr(preg_replace('/\s+/', ' ', (string)$v), 0, 60)); };
$ids = implode(',', array_map(function ($r) { return (int)$r['project_id']; }, $rows) ?: [0]);
echo "-- Re-filed imported activities: ", count($rows), " rows (", implode('+', $which), "), exported ", date('c'), "\n";
echo "-- Apply on the live database as root. Every statement names its row by id and by the code it carried;\n";
echo "-- a row edited on live since the export is left alone and shows up in the first check below.\n\n";
echo "START TRANSACTION;\n";
$hand = 0;
foreach ($rows as $r) {
    $moved = (int)$r['cur_programme_id'] !== (int)$r['new_programme_id'] || (int)$r['cur_objective_id'] !== (int)$r['new_objective_id'] || (string)$r['cur_abbr'] !== (string)$r['new_abbr'];
    if ($moved) { $hand++; }
    printf("UPDATE pm_projects_tbl SET pillar_id=%d, objective_id=%d, programme_id=%d, abbr=%s WHERE id=%d AND abbr=%s; -- %s%s\n",
        (int)$r['cur_pillar_id'], (int)$r['cur_objective_id'], (int)$r['cur_programme_id'], $q($r['cur_abbr']), (int)$r['project_id'], $q($r['old_abbr']), $c($r['name']), $moved ? ' [placed by hand during vetting]' : '');
}
echo "COMMIT;\n";
echo "\n-- Check 1 (must be empty): rows the guard skipped because live no longer carried the expected code\n";
echo "SELECT id, abbr FROM pm_projects_tbl WHERE id IN ($ids) AND abbr IN (", implode(',', array_map(function ($r) use ($q) { return $q($r['old_abbr']); }, $rows) ?: ["''"]), ");\n";
echo "\n-- Check 2 (must be empty): duplicate codes\n";
echo "SELECT abbr, COUNT(*) AS n FROM pm_projects_tbl GROUP BY abbr HAVING n > 1;\n";
echo "\n-- Check 3: how many rows now carry each new code prefix\n";
echo "SELECT SUBSTRING_INDEX(abbr,'.',2) AS programme_code, COUNT(*) FROM pm_projects_tbl WHERE id IN ($ids) GROUP BY 1 ORDER BY 1;\n";
fwrite(STDERR, "exported " . count($rows) . " rows, " . $hand . " of them placed by hand during vetting\n");
echo "\n-- ROLLBACK (do not run unless undoing): the old placement of every row above\n";
foreach ($rows as $r) {
    printf("-- UPDATE pm_projects_tbl SET pillar_id=%d, objective_id=%d, programme_id=%d, abbr=%s WHERE id=%d;\n",
        (int)$r['old_pillar_id'], (int)$r['old_objective_id'], (int)$r['old_programme_id'], $q($r['old_abbr']), (int)$r['project_id']);
}
