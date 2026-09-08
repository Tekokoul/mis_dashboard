<?php
/**
 * Read a work-plan workbook and say what an import would do - or stage it
 * for the review page without going through the browser.
 *
 *   docker compose exec -T app php /var/www/html/tools/import-workbook.php /tmp/plan.xlsx [--sheet Schedule] [--stage] [--show-all]
 *
 * Without --stage nothing is written: every activity of the sheet is
 * printed with what the catalogue knows about it (NEW, CHANGED, SAME,
 * UNCLEAR) and, for a new one, where the wording and the workbook heading
 * say it belongs. --stage writes the same analysis to the staging tables
 * as a batch (uploaded_by 0, "the command line") so the review page can be
 * used on it; the feature switch is not consulted here, the page checks it.
 *
 * A dry run on the workbook the catalogue was seeded from should come back
 * all SAME or CHANGED; a NEW row there means the matching missed something,
 * which is what to test before trusting it with a real import.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require_once __DIR__ . '/../app/includes/library.php';
require_once __DIR__ . '/../app/includes/import.php';
require __DIR__ . '/../app/db.class.php';

$file = ''; $sheet = null; $stage = false; $showAll = false;
for ($i = 1; $i < count($argv); $i++) {
    $a = $argv[$i];
    if ($a === '--sheet') { $sheet = (string)($argv[++$i] ?? ''); }
    elseif ($a === '--stage') { $stage = true; }
    elseif ($a === '--show-all') { $showAll = true; }
    elseif ($file === '') { $file = $a; }
}
if ($file === '' || !is_readable($file)) { fwrite(STDERR, "usage: import-workbook.php <workbook.xlsx> [--sheet NAME] [--stage] [--show-all]\n"); exit(1); }

$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
try {
    $parsed = import_parse_workbook($file, $sheet);
} catch (RuntimeException $e) {
    fwrite(STDERR, "cannot read the workbook: " . $e->getMessage() . "\n"); exit(1);
}
printf("sheet \"%s\", header on row %d, columns: %s\n", $parsed['sheet'], $parsed['header_row'],
    implode(', ', array_map(function ($k, $v) { return $k . '=' . import_column_letter($v); }, array_keys($parsed['columns']), $parsed['columns'])));
printf("%d headings, %d activities%s\n", count($parsed['objectives']), count($parsed['activities']), $parsed['warnings'] ? ', ' . count($parsed['warnings']) . ' warnings' : '');
foreach ($parsed['warnings'] as $w) { echo "  ! ", $w, "\n"; }

if ($stage) {
    if (!import_available($db)) { fwrite(STDERR, "the staging tables are missing - start the container once so the migration creates them\n"); exit(1); }
    $batch = import_stage($db, basename($file), $parsed, 0);
    $c = import_batch_counts($db, $batch);
    printf("staged as import #%d: %d new, %d changed, %d unclear, %d already in the system\n", $batch, $c['kind']['new'], $c['kind']['changed'], $c['kind']['unclear'], $c['kind']['same']);
    echo "open /imports/review/", $batch, " to vet it\n";
    exit(0);
}

$cat = import_catalogue($db);
$counts = ['new' => 0, 'changed' => 0, 'same' => 0, 'unclear' => 0];
$t0 = microtime(true);
foreach ($parsed['activities'] as $a) {
    $r = import_analyse($db, $a, $cat);
    $counts[$r['kind']] = ($counts[$r['kind']] ?? 0) + 1;
    if (!$showAll && $r['kind'] === 'same') { continue; }
    $line = sprintf("%-4d %-8s %-12s %s", $a['row'], strtoupper($r['kind']), $a['code'], mb_substr($a['name'], 0, 70));
    if ($r['match_project_id'] > 0) {
        $m = $cat['activities'][$r['match_project_id']];
        $line .= sprintf("\n     = #%d %s %s (by %s%s)", $r['match_project_id'], $m['abbr'], mb_substr($m['name'], 0, 60), $r['match_how'], $r['match_how'] === 'similar' ? ' ' . (int)round(100 * $r['match_score']) . '%' : '');
    }
    if ($r['kind'] === 'changed') {
        foreach ((array)json_decode((string)$r['changes'], true) as $f => $pair) { $line .= sprintf("\n     %s: %s -> %s", $f, mb_substr((string)$pair[0], 0, 50), mb_substr((string)$pair[1], 0, 50)); }
    }
    if (in_array($r['kind'], ['new', 'unclear'], true)) {
        $line .= sprintf("\n     -> %s [%s] %s", import_place_label($cat, $r['sug_objective_id'], $r['sug_programme_id']), $r['confidence'], $r['reason']);
        if ($r['alt_objective_id'] > 0) { $line .= sprintf("\n        wording: %s", import_place_label($cat, $r['alt_objective_id'], $r['alt_programme_id'])); }
        if ($r['nearest_project_id'] > 0) { $n = $cat['activities'][$r['nearest_project_id']]; $line .= sprintf("\n        most like #%d %s %s (%d%%)", $r['nearest_project_id'], $n['abbr'], mb_substr($n['name'], 0, 50), (int)round(100 * $r['nearest_score'])); }
    }
    echo $line, "\n";
}
printf("\n%d new, %d changed, %d unclear, %d already in the system; %.1f s\n", $counts['new'], $counts['changed'], $counts['unclear'], $counts['same'], microtime(true) - $t0);
