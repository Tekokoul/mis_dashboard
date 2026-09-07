<?php
/**
 * The vetted moves as SQL for the live database, by exact id.
 *
 *   docker compose exec -T app php /var/www/html/tools/export-allocations.php [accepted|all] > allocations.sql
 *
 * "accepted" (default) exports only moves a person has accepted; "all" also
 * exports the ones still pending. Each row is an UPDATE of its four
 * placement columns; a rollback block with the old values follows, and a
 * SELECT at the end reports what changed. Run it on the server as root:
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
$rows = (array)$db->MQ("SELECT r.*, p.name FROM pm_allocation_review_tbl r JOIN pm_projects_tbl p ON p.id = r.project_id
                        WHERE r.status IN ($marks) ORDER BY r.project_id", "all", $which);
$q = function ($v) { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'"; };
echo "-- Re-filed imported activities: ", count($rows), " rows (", implode('+', $which), "), exported ", date('c'), "\n";
echo "-- Apply on the live database as root. Every statement names its row by id.\n\n";
foreach ($rows as $r) {
    printf("UPDATE pm_projects_tbl SET pillar_id=%d, objective_id=%d, programme_id=%d, abbr=%s WHERE id=%d; -- %s\n",
        (int)$r['new_pillar_id'], (int)$r['new_objective_id'], (int)$r['new_programme_id'], $q($r['new_abbr']), (int)$r['project_id'], mb_substr(preg_replace('/\s+/', ' ', (string)$r['name']), 0, 60));
}
echo "\n-- Check: how many rows now carry each new code prefix\n";
echo "SELECT SUBSTRING_INDEX(abbr,'.',2) AS programme_code, COUNT(*) FROM pm_projects_tbl WHERE id IN (", implode(',', array_map(function ($r) { return (int)$r['project_id']; }, $rows) ?: [0]), ") GROUP BY 1 ORDER BY 1;\n";
echo "\n-- ROLLBACK (do not run unless undoing): the old placement of every row above\n";
foreach ($rows as $r) {
    printf("-- UPDATE pm_projects_tbl SET pillar_id=%d, objective_id=%d, programme_id=%d, abbr=%s WHERE id=%d;\n",
        (int)$r['old_pillar_id'], (int)$r['old_objective_id'], (int)$r['old_programme_id'], $q($r['old_abbr']), (int)$r['project_id']);
}
