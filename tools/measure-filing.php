<?php
/**
 * How good is the filing suggestion on the catalogue as it stands?
 *
 *   docker compose exec -T app php /var/www/html/tools/measure-filing.php [--no-matcher] [--limit N] [--show-misses]
 *
 * Hides each filed activity in turn and asks suggest_parent() where its
 * wording belongs, then compares with where it actually sits. The activity's
 * own words and its own past corrections are left out of the vote, so this
 * is an honest hold-out, not a lookup. Re-run after the catalogue changes
 * shape, before trusting the numbers in COMMANDS.md.
 *
 *   objective@1  the first guess names the right objective
 *   programme@1  ... and the right programme under it
 *   objective@3  the right objective is among the three shown
 *   confident    how often the guesser claims to be sure, and how often it is
 *                right when it does (the "check placement" safety net keys
 *                off this flag, so its precision matters more than its rate)
 *
 * --no-matcher scores words and corrections alone, whatever MATCHER_URL says,
 * so the meaning matcher's contribution can be read off by running both.
 *
 * --with-heading also scores the workbook import's proposal (import_propose)
 * with each activity's own objective given as the heading, the way a work
 * plan gives it: how often the programme is then right, and how often the
 * heading and the wording agree.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require_once __DIR__ . '/../app/includes/library.php';
require_once __DIR__ . '/../app/includes/import.php';
require __DIR__ . '/../app/db.class.php';

$limit = 0; $showMisses = false; $withHeading = false;
foreach ($argv as $i => $a) {
    if ($a === '--no-matcher') { $GLOBALS['AFCDC_NO_MATCHER'] = true; }
    if ($a === '--show-misses') { $showMisses = true; }
    if ($a === '--with-heading') { $withHeading = true; }
    if ($a === '--limit') { $limit = (int)($argv[$i + 1] ?? 0); }
}
$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);

$programmes = [];
foreach ((array)$db->MQ("SELECT id, objective_id, abbr, name FROM pm_programmes_tbl", "all") as $p) { $programmes[(int)$p['id']] = $p; }
$objectives = [];
foreach ((array)$db->MQ("SELECT id, abbr, name FROM pm_objectives_tbl", "all") as $o) { $objectives[(int)$o['id']] = $o; }
$rows = (array)$db->MQ("SELECT id, abbr, name, description, kpi, objective_id, programme_id FROM pm_projects_tbl ORDER BY id", "all");

$n = 0; $o1 = 0; $p1 = 0; $o3 = 0; $conf = 0; $confRight = 0; $skipped = 0; $t0 = microtime(true);
$misses = [];
$hp1 = 0; $hAgreed = 0; $hAgreedRight = 0; $cat = $withHeading ? import_catalogue($db) : null;
foreach ($rows as $r) {
    $oid = (int)$r['objective_id']; $pid = (int)$r['programme_id'];
    // Only a consistently filed activity is a usable truth: the programme must sit under the objective.
    if ($oid <= 0 || $pid <= 0 || !isset($programmes[$pid]) || (int)$programmes[$pid]['objective_id'] !== $oid) { $skipped++; continue; }
    $text = trim((string)$r['name'] . '. ' . (string)$r['description'] . ' ' . (string)$r['kpi']);
    if (mb_strlen($text) < 12) { $skipped++; continue; }
    if ($limit > 0 && $n >= $limit) { break; }
    $n++;
    $sug = suggest_parent($db, 'pm_projects', $text, 3, (int)$r['id']);
    $top = $sug['candidates'][0] ?? null;
    $hitO = $top && (int)$top['objective_id'] === $oid;
    $hitP = $hitO && (int)$top['programme_id'] === $pid;
    if ($hitO) { $o1++; }
    if ($hitP) { $p1++; }
    foreach ($sug['candidates'] as $c) { if ((int)$c['objective_id'] === $oid) { $o3++; break; } }
    if (!empty($sug['confident'])) { $conf++; if ($hitO) { $confRight++; } }
    if ($withHeading) {
        // The activity's own words must not vote: take it out of the catalogue copy the proposal sees.
        $catX = $cat; unset($catX['activities'][(int)$r['id']]);
        $a = ['name' => (string)$r['name'], 'description' => (string)$r['description'], 'kpi' => (string)$r['kpi']];
        $prop = import_propose_holdout($db, $a, $catX, $oid, (int)$r['id']);
        if ((int)$prop['sug_programme_id'] === $pid) { $hp1++; }
        if ($prop['confidence'] === 'agreed') { $hAgreed++; if ((int)$prop['sug_programme_id'] === $pid) { $hAgreedRight++; } }
    }
    if (!$hitO) {
        $misses[] = sprintf("%-4d %-8s %-60s sits %s / %s, guessed %s%s", (int)$r['id'], (string)$r['abbr'], mb_substr((string)$r['name'], 0, 60),
            (string)($objectives[$oid]['abbr'] ?? '?'), (string)$programmes[$pid]['abbr'], $top ? (string)$top['label'] : '(nothing)', !empty($sug['confident']) ? ' [confident]' : '');
    }
}
$pct = function ($a, $b) { return $b > 0 ? sprintf('%5.1f%%', 100 * $a / $b) : '   n/a'; };
printf("activities scored: %d (skipped %d: inconsistent placement or too little text)\n", $n, $skipped);
printf("matcher: %s\n", (matcher_url() !== '') ? 'on (' . matcher_url() . ')' : 'off');
printf("objective@1  %s  (%d)\n", $pct($o1, $n), $o1);
printf("programme@1  %s  (%d)\n", $pct($p1, $n), $p1);
printf("objective@3  %s  (%d)\n", $pct($o3, $n), $o3);
printf("confident    %s of the time, right %s of those (%d/%d)\n", $pct($conf, $n), $pct($confRight, $conf), $confRight, $conf);
if ($withHeading) {
    printf("with the objective given as a heading (a work plan's row):\n");
    printf("  programme@1  %s  (%d)\n", $pct($hp1, $n), $hp1);
    printf("  heading and wording agree %s of the time, programme right %s of those (%d/%d)\n", $pct($hAgreed, $n), $pct($hAgreedRight, $hAgreed), $hAgreedRight, $hAgreed);
}
printf("%.2f s per activity\n", $n > 0 ? (microtime(true) - $t0) / $n : 0);
if ($showMisses && $misses) { echo "\nmisses (objective wrong):\n"; foreach ($misses as $m) { echo $m, "\n"; } }
