<?php
/**
 * Move every activity of one objective into another objective WITHOUT a
 * programme ("unassigned"), each carrying a recommended programme that a
 * person accepts or overrides. For folding one objective into another: the
 * work lands in the right objective at once, and nobody files seventeen
 * activities by hand - but nor does the tool file them for anybody.
 *
 *   docker compose exec -T app php /var/www/html/tools/park-activities.php --from=18.0 --to=7.0 --dry-run
 *   docker compose exec -T app php /var/www/html/tools/park-activities.php --from=18.0 --to=7.0
 *   docker compose exec -T app php /var/www/html/tools/park-activities.php --from=18.0 --to=7.0 --undo
 *
 * --from and --to are objective codes as the overview shows them (18.0, 7.0).
 *
 * For each activity under --from:
 *  - the recommendation is the programme of --to that its wording fits best,
 *    scored by suggest_parent() - the scorer behind the activity form's
 *    suggestion and the placement check - on this server; nothing is sent
 *    anywhere. When no programme of --to shares a word with it, the closest
 *    programme elsewhere (never one of --from) is recommended, and the reason
 *    says so.
 *  - the activity takes --to's goal and objective and no programme. Its code,
 *    tasks and recorded deliveries stay as they are.
 *  - a "check" row in pm_allocation_review_tbl carries the recommendation: the
 *    Projects list and the activity's form say "Unassigned - recommended: ...",
 *    with Move there (the activity takes the next free code in that programme)
 *    and Leave unassigned, for administrators and executives. The list's
 *    Vetting filter shows them all.
 *  - the activity row and any review row it replaces go to core_table_logs_tbl
 *    first.
 *
 * One transaction: all or nothing. Idempotent: an activity no longer under
 * --from is not touched, so a re-run moves only what is left. --undo puts
 * back every activity still unassigned under --to whose recommendation nobody
 * has answered; one a person has moved or kept stays where they put it.
 * Nothing is deleted: --from and its programmes are left empty, to be removed
 * on the Objectives and Programmes lists once nobody needs them.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only\n"); }
define('_DB_DEBUG_MODE', false);
include __DIR__ . '/../app/configuration/settings.local.php';
require __DIR__ . '/../app/includes/library.php';
require __DIR__ . '/../app/db.class.php';

$opt  = getopt('', ['from:', 'to:', 'dry-run', 'undo']);
$dry  = array_key_exists('dry-run', $opt);
$undo = array_key_exists('undo', $opt);
$usage = "usage: park-activities.php --from=<objective code> --to=<objective code> [--dry-run] [--undo]\n";
if (!is_string($opt['from'] ?? null) || !is_string($opt['to'] ?? null)) { fwrite(STDERR, $usage); exit(1); }

$s = $settings['db_master']; $s['db_provider'] = 'mysql'; $db = new DB($s);
if (!is_set($db->MQ("SHOW TABLES LIKE 'pm_allocation_review_tbl'", "one"))) { fwrite(STDERR, "pm_allocation_review_tbl is missing - start the app container once so its migration creates it\n"); exit(1); }

// An objective by the code people see; "18" finds "18.0" too.
$objective = function ($code) use ($db) {
    $code = trim((string)$code);
    $rows = (array)$db->MQ("SELECT id, abbr, name, pillar_id FROM pm_objectives_tbl WHERE TRIM(abbr) = ? OR TRIM(abbr) = ?", "all", [$code, $code . '.0']);
    if (count($rows) !== 1) { fwrite(STDERR, count($rows) === 0 ? "no objective has the code '$code'\n" : "more than one objective has the code '$code' - fix the codes first\n"); exit(1); }
    return $rows[0];
};
$F = $objective($opt['from']); $T = $objective($opt['to']);
if ((int)$F['id'] === (int)$T['id']) { fwrite(STDERR, "--from and --to are the same objective\n"); exit(1); }
$fid = (int)$F['id']; $tid = (int)$T['id'];
$short = function ($v, $n) { $v = trim(preg_replace('/\s+/u', ' ', (string)$v)); return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . "\u{2026}" : $v; };
$prg = []; foreach ((array)$db->MQ("SELECT id, objective_id, abbr, name, active FROM pm_programmes_tbl", "all") as $r) { $prg[(int)$r['id']] = $r; }
$obj = []; foreach ((array)$db->MQ("SELECT id, abbr, pillar_id FROM pm_objectives_tbl", "all") as $r) { $obj[(int)$r['id']] = $r; }
$plabel = function ($id, $n = 60) use ($prg, $obj, $short) {
    $p = $prg[(int)$id] ?? null; if (!$p) { return '?'; }
    return trim(($obj[(int)$p['objective_id']]['abbr'] ?? '?') . ' › ' . $short(trim($p['abbr'] . ' ' . $p['name']), $n));
};
$user = 'tools/park-activities';
$audit = function ($table, $record) use ($db, $user) {
    $db->MQ("INSERT INTO `core_table_logs_tbl` (`tablename`, `record`, `log_date`, `user`) VALUES (?, ?, ?, ?)", false,
        [$table, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), date('Y-m-d H:i:s'), $user]);
};
printf("From %s %s\nTo   %s %s\n%s\n\n", $F['abbr'], $F['name'], $T['abbr'], $T['name'], $dry ? 'DRY RUN - nothing is changed' : ($undo ? 'UNDO' : 'MOVING'));

if ($undo) {
    $rows = (array)$db->MQ("SELECT r.*, p.abbr AS cur_abbr, p.name AS cur_name FROM pm_allocation_review_tbl r JOIN pm_projects_tbl p ON p.id = r.project_id
                             WHERE r.status = 'proposed' AND r.confidence = 'check' AND r.old_objective_id = ? AND p.objective_id = ? AND p.programme_id IS NULL", "all", [$fid, $tid]);
    usort($rows, function ($a, $b) { return strnatcmp((string)$a['cur_abbr'], (string)$b['cur_abbr']); });
    if (!$dry) { $db->txBegin(); }
    foreach ($rows as $r) {
        $op = (int)$r['old_programme_id'];
        // The programme it came from may have been removed since; then it goes back to the objective alone.
        $opOk = $op > 0 && isset($prg[$op]) && (int)$prg[$op]['objective_id'] === $fid;
        printf("  %-12s %-58s back to %s\n", $r['cur_abbr'], $short($r['cur_name'], 58), $opOk ? $plabel($op, 50) : $F['abbr'] . ' (its programme is gone - no programme)');
        if ($dry) { continue; }
        $audit('pm_projects_tbl', ['action' => 'park_undo', 'id' => (int)$r['project_id'], 'review' => $r]);
        $db->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = ? WHERE id = ? AND objective_id = ? AND programme_id IS NULL", false,
            [(int)($obj[$fid]['pillar_id'] ?? $r['old_pillar_id']), $fid, $opOk ? $op : null, (int)$r['project_id'], $tid]);
        $db->MQ("UPDATE pm_allocation_review_tbl SET status = 'reverted', decided_by = 0, decided_at = NOW() WHERE id = ?", false, [(int)$r['id']]);
    }
    if (!$dry) { $db->txCommit(); }
    printf("\n%d activit%s %s back under %s.\n", count($rows), count($rows) === 1 ? 'y' : 'ies', $dry ? 'would go' : 'went', $F['abbr']);
    exit(0);
}

$toPrgs = array_values(array_filter($prg, function ($p) use ($tid) { return (int)$p['objective_id'] === $tid && (int)$p['active'] === 1; }));
usort($toPrgs, function ($a, $b) { return strnatcmp((string)$a['abbr'], (string)$b['abbr']); });
if (!$toPrgs) { fwrite(STDERR, "{$T['abbr']} has no active programme to recommend - add one first\n"); exit(1); }
$acts = (array)$db->MQ("SELECT id, abbr, name, description, kpi, pillar_id, objective_id, programme_id FROM pm_projects_tbl WHERE objective_id = ?", "all", [$fid]);
usort($acts, function ($a, $b) { return strnatcmp((string)$a['abbr'], (string)$b['abbr']) ?: ((int)$a['id'] <=> (int)$b['id']); });
if (!$acts) { printf("%s holds no activity - nothing to move.\n", $F['abbr']); exit(0); }

$plan = [];
foreach ($acts as $a) {
    $text = trim((string)$a['name'] . '. ' . (string)$a['description'] . ' ' . (string)$a['kpi']);
    $sg = suggest_parent($db, 'pm_projects', $text, 8, (int)$a['id']);
    $ps = (array)($sg['scores']['programme'] ?? []);
    $rank = [];
    foreach ($toPrgs as $i => $p) { $rank[] = ['id' => (int)$p['id'], 'score' => (float)($ps[(int)$p['id']] ?? 0), 'i' => $i]; }
    usort($rank, function ($x, $y) { return ($y['score'] <=> $x['score']) ?: ($x['i'] <=> $y['i']); });
    // The closest place elsewhere - never the objective being emptied, whose
    // own activities would otherwise vote for it.
    $else = null;
    foreach ((array)($sg['candidates'] ?? []) as $c) {
        if ((int)$c['objective_id'] === $fid || (int)$c['objective_id'] === $tid) { continue; }
        $else = $c; break;
    }
    $came = trim((string)$a['abbr']) !== '' ? (string)$a['abbr'] : '#' . (int)$a['id'];
    $from = ((int)$a['programme_id'] > 0 && isset($prg[(int)$a['programme_id']])) ? $plabel((int)$a['programme_id'], 40) : $F['abbr'];
    if ($rank[0]['score'] > 0) {
        $recP = $rank[0]['id']; $recO = $tid;
        $why = 'Closest programme in ' . $T['abbr'] . ' by its wording'
             . (isset($rank[1]) && $rank[1]['score'] > 0 ? '; next: ' . $plabel($rank[1]['id'], 40) : '')
             . ($else ? '. Outside ' . $T['abbr'] . ' it reads closest to ' . $plabel((int)$else['programme_id'], 40) : '')
             . '. Came from ' . $came . ' (' . $from . ').';
    } elseif ($else) {
        $recP = (int)$else['programme_id']; $recO = (int)$else['objective_id'];
        $why = 'No programme of ' . $T['abbr'] . ' shares its wording; this is the closest elsewhere. Came from ' . $came . ' (' . $from . ').';
    } else {
        $recP = 0; $recO = 0; $why = '';
    }
    $plan[] = ['a' => $a, 'recP' => $recP, 'recO' => $recO, 'why' => $why, 'score' => $rank[0]['score']];
}

printf("  %-12s %-52s %s\n", 'code', 'activity', 'recommended programme');
foreach ($plan as $x) {
    printf("  %-12s %-52s %s\n", $x['a']['abbr'], $short($x['a']['name'], 52), $x['recP'] > 0 ? $plabel($x['recP'], 55) . ($x['recO'] !== $tid ? '   (outside ' . $T['abbr'] . ')' : '') : '(none - choose one on its form)');
}

if (!$dry) {
    $db->txBegin();
    $moved = 0;
    foreach ($plan as $x) {
        $id = (int)$x['a']['id'];
        // Read again under a lock: a person may have moved it since the plan was made.
        $cur = $db->MQ("SELECT * FROM pm_projects_tbl WHERE id = ? AND objective_id = ? FOR UPDATE", "one", [$id, $fid]);
        if (!is_set($cur)) { continue; }
        $old = $db->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ?", "one", [$id]);
        $audit('pm_projects_tbl', ['action' => 'park', 'id' => $id, 'before' => $cur, 'review_before' => is_set($old) ? $old : null,
                                   'to_objective_id' => $tid, 'recommended_programme_id' => $x['recP'] ?: null]);
        if (!$db->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = NULL WHERE id = ?", false, [(int)$T['pillar_id'], $tid, $id])) {
            $db->txRollBack(); fwrite(STDERR, "could not move activity $id - nothing was changed\n"); exit(1);
        }
        if ($x['recP'] > 0) {
            $vals = [(int)$cur['pillar_id'], (int)$cur['objective_id'], (int)$cur['programme_id'], (string)$cur['abbr'],
                     (int)($obj[$x['recO']]['pillar_id'] ?? 0), $x['recO'], $x['recP'], $x['why']];
            $db->MQ("INSERT INTO pm_allocation_review_tbl (project_id, old_pillar_id, old_objective_id, old_programme_id, old_abbr, new_pillar_id, new_objective_id, new_programme_id, new_abbr, confidence, reason, status, decided_by, decided_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 'check', ?, 'proposed', 0, NULL, NOW())
                     ON DUPLICATE KEY UPDATE old_pillar_id = VALUES(old_pillar_id), old_objective_id = VALUES(old_objective_id), old_programme_id = VALUES(old_programme_id),
                         old_abbr = VALUES(old_abbr), new_pillar_id = VALUES(new_pillar_id), new_objective_id = VALUES(new_objective_id), new_programme_id = VALUES(new_programme_id),
                         new_abbr = NULL, confidence = 'check', reason = VALUES(reason), status = 'proposed', decided_by = 0, decided_at = NULL, created_at = NOW()", false,
                array_merge([$id], $vals));
        } elseif (is_set($old) && (string)$old['status'] === 'proposed') {
            // A move still waiting from an earlier re-filing points at a place this activity has left.
            $db->MQ("DELETE FROM pm_allocation_review_tbl WHERE id = ?", false, [(int)$old['id']]);
        }
        $moved++;
    }
    $db->txCommit();
    printf("\n%d activit%s moved to %s without a programme, each with its recommendation.\n", $moved, $moved === 1 ? 'y' : 'ies', $T['abbr']);
} else {
    printf("\n%d activit%s would move to %s without a programme.\n", count($plan), count($plan) === 1 ? 'y' : 'ies', $T['abbr']);
}
$left = (int)($db->MQ("SELECT COUNT(*) AS n FROM pm_projects_tbl WHERE objective_id = ?", "one", [$fid])['n'] ?? 0);
$fromPrgs = array_filter($prg, function ($p) use ($fid) { return (int)$p['objective_id'] === $fid; });
if (!$dry) {
    printf("%s now holds %d activit%s%s.\n", $F['abbr'], $left, $left === 1 ? 'y' : 'ies',
        $fromPrgs ? ' and ' . count($fromPrgs) . ' programme' . (count($fromPrgs) === 1 ? '' : 's') . ' (' . implode(', ', array_map(function ($p) { return trim($p['abbr']); }, $fromPrgs)) . ')' : '');
    print "Nothing was deleted. Remove the empty objective and its programmes on the Objectives and Programmes lists when nobody needs them.\n";
    print "To see the recommendations: Projects, Filters, Objective = {$T['abbr']}, Vetting = Pending.\n";
}
