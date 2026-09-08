<?php
/**
 * Importing activities from a work-plan workbook.
 *
 * A workbook goes through four steps, and nothing reaches the catalogue
 * until a person says so at the last one:
 *
 *   read     xlsx.php turns the sheet into rows; import_parse_workbook()
 *            finds the header, the objective headings and the activity rows
 *            under them (a heading is a row with a WBS number and no
 *            activity code; an activity is a row with a code, or a name and
 *            no WBS).
 *   compare  import_analyse() looks each activity up in the catalogue: by
 *            the code an earlier import or the re-filing recorded for it, by
 *            name, and by wording. It comes out as NEW, CHANGED (an existing
 *            activity whose text or budget differs), SAME, or UNCLEAR (it
 *            resembles an existing activity closely enough that a person
 *            must say whether it is the same one).
 *   propose  for a new activity the filing guesser (suggest_parent) says
 *            where the wording belongs, and the workbook's own heading says
 *            where the author put it. When they agree the proposal is
 *            AGREED; when the wording also fits the heading it is HINT; when
 *            they disagree it is SPLIT and both are shown. The proposal is
 *            always the workbook's heading when there is one, because the
 *            author's placement is a statement, and the wording's pick is
 *            offered beside it.
 *   stage    everything is written to pm_import_batches_tbl and
 *            pm_import_rows_tbl for the review page. Accepting a row creates
 *            or updates the activity, gives it the next free code and
 *            records the placement as an example the guesser learns from;
 *            skipping it leaves the catalogue alone.
 *
 * Off unless IMPORT_ENABLED=true in .env (import_enabled()): the code ships
 * with every release, the page and the menu entry do not exist until the
 * switch is thrown, so a release can carry it to the server while it is
 * still being tried out here.
 */

// This file uses library.php (is_set, display, normalise_number, filing_words,
// suggest_parent, auto_wbs_code, record_filing_feedback) and library.php pulls
// this one in for import_enabled(). Naming both is safe - require_once marks a
// file as included before it runs, so the circular reference resolves at once
// and nothing here is called at include time - and it means either file can be
// the one a script reaches for.
require_once __DIR__ . '/library.php';

function import_enabled() {
    return defined('_IMPORT_ENABLED') && _IMPORT_ENABLED === true;
}

/** Do the staging tables exist yet? (They arrive with a migration.) */
function import_available($db) {
    static $ok = null;
    if ($ok === null) {
        $ok = is_set($db->MQ("SHOW TABLES LIKE 'pm_import_rows_tbl'", "one")) && is_set($db->MQ("SHOW TABLES LIKE 'pm_import_batches_tbl'", "one"));
    }
    return $ok;
}

/* ------------------------------------------------------------------ read */

/**
 * The activities of a workbook, each with the objective heading above it.
 * $sheet null = the sheet whose name looks like a schedule, else the first
 * sheet with a usable header.
 */
function import_parse_workbook($path, $sheet = null) {
    require_once __DIR__ . '/xlsx.php';
    $names = array_keys(xlsx_sheets($path));
    $order = [];
    if ($sheet !== null && $sheet !== '') {
        $order[] = $sheet;
    } else {
        foreach ($names as $n) { if (preg_match('/schedule|activit|work\s*plan|awp|plan/i', $n)) { $order[] = $n; } }
        foreach ($names as $n) { if (!in_array($n, $order, true)) { $order[] = $n; } }
    }
    $lastError = 'The workbook has no sheet with a header row naming the activities (a column called "Task", "Activity" or "Name").';
    foreach ($order as $name) {
        // A sheet that cannot be read (a picture, something oversized, broken
        // XML) is a reason to try the next one, not to refuse the workbook.
        try {
            $read = xlsx_read($path, $name);
        } catch (RuntimeException $e) {
            $lastError = $e->getMessage();
            continue;
        }
        $parsed = import_parse_rows($read['rows']);
        if ($parsed !== null) { $parsed['sheet'] = $name; $parsed['sheets'] = $names; return $parsed; }
        $lastError = 'No header row with an activity column was found on the sheet "' . $name . '".';
    }
    throw new RuntimeException($lastError);
}

/**
 * Which column plays which part, from the header texts.
 *
 * A column takes the first part that matches it AND is still free. Stopping
 * at the first match and dropping the column when that part was taken lost
 * whole columns on ordinary sheets: "Activity Start Date" matches "activit"
 * long before it matches "start", so with an "Activity" column already
 * present the dates and the budget went unread.
 */
function import_header_roles(array $cells) {
    static $patterns = [
        ['wbs',         '/^wbs\b|\bwbs\b/'],
        ['code',        '/awp|\bcode\b|\bref\b/'],
        ['name',        '/task|activit|\bname\b|title|deliverable/'],
        ['kpi',         '/indicator|\bkpi\b/'],
        ['quarter',     '/qtr|quarter/'],
        ['start',       '/\bstart\b|\bfrom\b/'],
        // Not a bare "to": "Reports to" was being read as the finish date.
        ['finish',      '/finish|\bend\b|\bdue\b|\bto date\b/'],
        ['budget',      '/budget|usd|cost|amount/'],
        ['description', '/note|description|desc\b|detail|comment/'],
        ['owner',       '/owner|lead|responsib|assign/'],
        ['pct',         '/%|done|progress|complete/'],
        ['days',        '/^days?$|duration/'],
    ];
    $roles = [];
    foreach ($cells as $col => $text) {
        $t = mb_strtolower(trim((string)$text));
        if ($t === '') { continue; }
        foreach ($patterns as $pat) {
            if (isset($roles[$pat[0]]) || !preg_match($pat[1], $t)) { continue; }
            $roles[$pat[0]] = (int)$col;
            break;
        }
    }
    return $roles;
}

/** 0 -> A, 25 -> Z, 26 -> AA: the reverse of xlsx_col_index(). */
function import_column_letter($index) {
    $index = (int)$index;
    if ($index < 0) { return '?'; }
    $out = '';
    while (true) {
        $out = chr(65 + ($index % 26)) . $out;
        $index = intdiv($index, 26) - 1;
        if ($index < 0) { break; }
    }
    return $out;
}

function import_parse_rows(array $rows) {
    ksort($rows);
    // The header: within the first 30 rows, the first row that names the activities.
    $header = null; $roles = [];
    foreach ($rows as $n => $cells) {
        if ($n > 30) { break; }
        $r = import_header_roles($cells);
        if (isset($r['name']) && count($r) >= 2) { $header = $n; $roles = $r; break; }
    }
    if ($header === null) { return null; }
    $get = function (array $cells, $role) use ($roles) {
        return isset($roles[$role]) ? trim((string)($cells[$roles[$role]] ?? '')) : '';
    };
    $activities = []; $objectives = []; $warnings = [];
    $hasCodeColumn = isset($roles['code']);
    if (!$hasCodeColumn) {
        $warnings[] = 'This sheet has no activity-code column, so a numbered row is read as an activity when it sits deeper than the headings above it.';
    }
    $current = ['wbs' => '', 'name' => ''];
    foreach ($rows as $n => $cells) {
        if ($n <= $header) { continue; }
        $name = $get($cells, 'name'); $code = $get($cells, 'code'); $wbs = $get($cells, 'wbs');
        if ($name === '' && $code === '') { continue; }
        if ($name === '') { $warnings[] = 'Row ' . $n . ' has a code (' . $code . ') but no activity name; skipped.'; continue; }
        // A heading is a numbered row, with no activity code, no deeper than
        // "1.1". Depth matters twice over: "1" is a lens, which ENDS the
        // objective above it rather than continuing it (without that, the
        // activities of a second lens kept being filed under the last
        // objective of the first); and "1.1.2" is an activity that has not
        // been given a code yet, which used to be swallowed as a heading and
        // never imported at all.
        $depth = ($wbs !== '' && preg_match('/^\d+(\.\d+)*$/', $wbs)) ? substr_count($wbs, '.') : -1;
        if ($code === '' && $depth === 0) {
            $current = ['wbs' => '', 'name' => ''];   // a lens: the objective above no longer applies
            continue;
        }
        if ($code === '' && $depth === 1) {
            $clean = trim((string)preg_replace('/^\s*' . preg_quote($wbs, '/') . '\s*[-–:.]?\s*/u', '', $name));
            $current = ['wbs' => $wbs, 'name' => $clean !== '' ? $clean : $name];
            $objectives[] = $current + ['row' => $n];
            continue;
        }
        // Deeper than a heading and carrying no code: the numbering is the
        // best identifier it has.
        if ($code === '' && $depth >= 2) { $code = $wbs; }
        $budget = normalise_number($get($cells, 'budget'));
        $activities[] = [
            'row'          => (int)$n,
            'code'         => mb_substr($code, 0, 64),
            'name'         => mb_substr($name, 0, 255),
            'description'  => $get($cells, 'description'),
            'kpi'          => mb_substr($get($cells, 'kpi'), 0, 255),
            'budget'       => ($budget === '' || $budget === null) ? null : (float)$budget,
            'quarter'      => $get($cells, 'quarter'),
            'start'        => $get($cells, 'start'),
            'finish'       => $get($cells, 'finish'),
            'owner'        => $get($cells, 'owner'),
            'pct'          => $get($cells, 'pct'),
            'days'         => $get($cells, 'days'),
            'wb_wbs'       => $current['wbs'],
            'wb_objective' => $current['name'],
        ];
    }
    if (!$activities) { return null; }
    // What each column was taken to mean, in the batch note: a header read
    // wrongly is otherwise invisible until the numbers look odd.
    $read = [];
    foreach ($roles as $role => $col) { $read[] = $role . '=' . import_column_letter((int)$col); }
    $warnings[] = 'Columns read as: ' . implode(', ', $read) . ' (header on row ' . $header . ').';
    return ['header_row' => $header, 'columns' => $roles, 'activities' => $activities, 'objectives' => $objectives, 'warnings' => $warnings];
}

/* --------------------------------------------------------------- compare */

/** A name as it is compared: case, quotes, dashes and spacing do not count. */
function import_norm($s) {
    $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8');
    $s = str_replace(['‘', '’', '“', '”', '–', '—', '\u{00A0}'], ["'", "'", '"', '"', '-', '-', ' '], $s);
    $s = mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $s)), 'UTF-8');
    return rtrim($s, " .;:,");
}

/** Dice coefficient over two token lists: 1 when identical, 0 when nothing shared. */
function import_similarity(array $a, array $b) {
    if (!$a || !$b) { return 0.0; }
    $shared = count(array_intersect($a, $b));
    return (2 * $shared) / (count($a) + count($b));
}

/**
 * Everything an activity is compared with, read once per import: the
 * activities with their tokens, the parents, and every code an activity is
 * known by (the code it carries, the code it carried before the re-filing,
 * and the workbook code an earlier import recorded for it).
 */
function import_catalogue($db) {
    $cat = ['activities' => [], 'objectives' => [], 'programmes' => [], 'pillars' => [], 'codes' => [], 'names' => []];
    foreach ((array)$db->MQ("SELECT id, name, abbr FROM pm_pillars_tbl", "all") as $r) { $cat['pillars'][(int)$r['id']] = $r; }
    foreach ((array)$db->MQ("SELECT id, pillar_id, name, abbr, active FROM pm_objectives_tbl", "all") as $r) {
        $r['tokens'] = filing_words($r['name']);
        $cat['objectives'][(int)$r['id']] = $r;
    }
    $programmes = (array)$db->MQ("SELECT id, objective_id, name, abbr, active FROM pm_programmes_tbl", "all");
    usort($programmes, function ($a, $b) { return strnatcmp((string)$a['abbr'], (string)$b['abbr']) ?: ((int)$a['id'] <=> (int)$b['id']); });
    foreach ($programmes as $r) { $cat['programmes'][(int)$r['id']] = $r; }
    foreach ((array)$db->MQ("SELECT id, pillar_id, objective_id, programme_id, name, abbr, description, kpi, estimated_budget FROM pm_projects_tbl", "all") as $r) {
        $id = (int)$r['id'];
        $r['name_tokens'] = filing_words($r['name']);
        $r['text_tokens'] = filing_words($r['name'] . ' ' . $r['description'] . ' ' . $r['kpi']);
        $cat['activities'][$id] = $r;
        $cat['names'][import_norm($r['name'])][] = $id;
        if (trim((string)$r['abbr']) !== '') { $cat['codes'][import_norm($r['abbr'])] = $id; }
    }
    if (allocation_review_available($db)) {
        foreach ((array)$db->MQ("SELECT project_id, old_abbr FROM pm_allocation_review_tbl WHERE old_abbr <> ''", "all") as $r) {
            $k = import_norm($r['old_abbr']);
            if ($k !== '' && !isset($cat['codes'][$k]) && isset($cat['activities'][(int)$r['project_id']])) { $cat['codes'][$k] = (int)$r['project_id']; }
        }
    }
    if (import_available($db)) {
        foreach ((array)$db->MQ("SELECT code, result_project_id FROM pm_import_rows_tbl WHERE status = 'accepted' AND code <> '' AND result_project_id > 0 ORDER BY id", "all") as $r) {
            $k = import_norm($r['code']);
            // Never over a code an activity carries today, and never over the
            // re-filing ledger: what a workbook called something last year
            // is the weakest of the three claims on that code.
            if ($k !== '' && !isset($cat['codes'][$k]) && isset($cat['activities'][(int)$r['result_project_id']])) { $cat['codes'][$k] = (int)$r['result_project_id']; }
        }
    }
    return $cat;
}

/** The objective the workbook's heading means, by name: [id, similarity] or [0, 0]. */
function import_hint_objective(array $cat, $heading) {
    $heading = trim((string)$heading);
    if ($heading === '') { return [0, 0.0]; }
    $norm = import_norm($heading);
    $tokens = filing_words($heading);
    $best = 0; $bestSim = 0.0;
    foreach ($cat['objectives'] as $id => $o) {
        // A deactivated objective is not on the form's dropdown, so proposing
        // it would leave a row nobody could accept.
        if ((string)$o['active'] === '0') { continue; }
        if (import_norm($o['name']) === $norm) { return [(int)$id, 1.0]; }
        $sim = import_similarity($tokens, $o['tokens']);
        // Whole-name containment: "Data Centre operating 24/7/365" inside a
        // longer heading. Two words at least - "Capacity Building" reduces to
        // the single token "capacity", and on one token this fires on any
        // heading that happens to mention it, at a score that outranks real
        // overlap.
        if (count($o['tokens']) >= 2 && !array_diff($o['tokens'], $tokens)) { $sim = max($sim, 0.9); }
        if ($sim > $bestSim) { $bestSim = $sim; $best = (int)$id; }
    }
    return $bestSim >= 0.5 ? [$best, $bestSim] : [0, $bestSim];
}

/** The fields of an existing activity that the workbook row would change: [field => [old, new]]. */
function import_changes(array $existing, array $a) {
    $out = [];
    if (import_norm($existing['name']) !== import_norm($a['name'])) { $out['name'] = [(string)$existing['name'], (string)$a['name']]; }
    // A blank workbook cell never erases what the catalogue has.
    if ($a['description'] !== '' && import_norm($existing['description']) !== import_norm($a['description'])) { $out['description'] = [(string)$existing['description'], (string)$a['description']]; }
    if ($a['kpi'] !== '' && import_norm($existing['kpi']) !== import_norm($a['kpi'])) { $out['kpi'] = [(string)$existing['kpi'], (string)$a['kpi']]; }
    if ($a['budget'] !== null) {
        $old = $existing['estimated_budget'];
        if ($old === null || $old === '' || abs((float)$old - (float)$a['budget']) > 0.005) { $out['estimated_budget'] = [$old === null ? '' : (string)$old, (string)$a['budget']]; }
    }
    return $out;
}

/**
 * One workbook activity against the catalogue and the guesser. Returns the
 * columns of a pm_import_rows_tbl row (without batch_id / row_no).
 */
function import_analyse($db, array $a, array $cat) {
    [$hint, $hintSim] = import_hint_objective($cat, $a['wb_objective']);
    $nameTokens = filing_words($a['name']);
    $textTokens = filing_words($a['name'] . ' ' . $a['description'] . ' ' . $a['kpi']);

    // 1. The code it is known by.
    $match = 0; $how = ''; $score = 0.0;
    $codeKey = import_norm($a['code']);
    if ($codeKey !== '' && isset($cat['codes'][$codeKey])) { $match = $cat['codes'][$codeKey]; $how = 'code'; $score = 1.0; }

    // 2. The same name. Names repeat across objectives here ("Conduct a
    //    training workshop for at least 3 Member States"), so among several
    //    the one under the workbook's own objective wins, and a lone match
    //    under another objective is a question, not an answer.
    //    A single activity with this exact name is that activity, wherever
    //    it sits now: a person may well have moved it since the workbook
    //    was written, and that move stands (the note says so).
    $nameKey = import_norm($a['name']);
    if ($match === 0 && $nameKey !== '' && !empty($cat['names'][$nameKey])) {
        $ids = $cat['names'][$nameKey];
        $underHint = [];
        foreach ($ids as $id) { if ($hint > 0 && (int)$cat['activities'][$id]['objective_id'] === $hint) { $underHint[] = $id; } }
        $pick = 0;
        // Exactly one candidate is an answer; two are a question. The
        // catalogue really does hold repeated names ("Conduct a training
        // workshop for at least 3 Member States"), and picking whichever the
        // loop met first would silently update the wrong activity.
        if (count($underHint) === 1) { $pick = $underHint[0]; }
        elseif (!$underHint && count($ids) === 1) { $pick = $ids[0]; }
        if ($pick > 0) { $match = $pick; $how = 'name'; $score = 1.0; }
        else { $match = ($underHint ? $underHint[0] : $ids[0]); $how = 'similar'; $score = 0.99; }
    }

    // 3. The nearest activity by wording, always: evidence for the reviewer,
    //    and the duplicate check when nothing else matched.
    $nearest = 0; $nearestSim = 0.0;
    foreach ($cat['activities'] as $id => $r) {
        $sim = max(import_similarity($nameTokens, $r['name_tokens']), 0.85 * import_similarity($textTokens, $r['text_tokens']));
        if ($sim > $nearestSim) { $nearestSim = $sim; $nearest = (int)$id; }
    }
    if ($match === 0 && $nearest > 0 && $nearestSim >= 0.6) { $match = $nearest; $how = 'similar'; $score = $nearestSim; }

    $out = [
        'kind' => 'new', 'code' => $a['code'], 'name' => $a['name'], 'description' => $a['description'], 'kpi' => $a['kpi'], 'budget' => $a['budget'],
        'extra' => json_encode(['quarter' => $a['quarter'], 'start' => $a['start'], 'finish' => $a['finish'], 'owner' => $a['owner'], 'pct' => $a['pct'], 'days' => $a['days'], 'wb_wbs' => $a['wb_wbs'], 'wb_objective' => $a['wb_objective'], 'hint_similarity' => round($hintSim, 2)], JSON_UNESCAPED_UNICODE),
        'match_project_id' => $match, 'match_how' => $how, 'match_score' => round($score, 3), 'changes' => null,
        'hint_objective_id' => $hint,
        'sug_pillar_id' => 0, 'sug_objective_id' => 0, 'sug_programme_id' => 0, 'alt_objective_id' => 0, 'alt_programme_id' => 0,
        'confidence' => 'none', 'reason' => '', 'candidates' => null,
        'nearest_project_id' => $nearest, 'nearest_score' => round($nearestSim, 3),
    ];
    if ($match > 0 && $how !== 'similar') {
        $changes = import_changes($cat['activities'][$match], $a);
        $out['kind'] = $changes ? 'changed' : 'same';
        $out['changes'] = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;
        return $out;
    }
    if ($match > 0) { $out['kind'] = 'unclear'; }
    // A new (or possibly new) activity: where does it go?
    return array_merge($out, import_propose($db, $a, $cat, $hint, $nearest, $nearestSim));
}

/**
 * The placement proposal for a new activity: the workbook's heading and
 * the wording, reconciled. See the file comment for the four outcomes.
 */
function import_propose($db, array $a, array $cat, $hint, $nearestId = 0, $nearestSim = 0.0, $exclude = 0) {
    $text = trim($a['name'] . '. ' . $a['description'] . ' ' . $a['kpi']);
    $s = mb_strlen($text) >= 4 ? suggest_parent($db, 'pm_projects', $text, 3, (int)$exclude) : ['candidates' => [], 'confident' => false, 'scores' => []];
    $top = $s['candidates'][0] ?? null;
    $cands = array_map(function ($c) { return ['objective_id' => (int)$c['objective_id'], 'programme_id' => (int)$c['programme_id'], 'label' => (string)$c['label'], 'score' => (float)$c['score'], 'learned' => !empty($c['learned'])]; }, (array)$s['candidates']);
    $out = ['candidates' => $cands ? json_encode($cands, JSON_UNESCAPED_UNICODE) : null, 'sug_pillar_id' => 0, 'sug_objective_id' => 0, 'sug_programme_id' => 0, 'alt_objective_id' => 0, 'alt_programme_id' => 0, 'confidence' => 'none', 'reason' => ''];
    $label = function ($oid, $pid) use ($cat) {
        $o = $cat['objectives'][$oid] ?? null; $p = $cat['programmes'][$pid] ?? null;
        return trim((string)($o['abbr'] ?? '?') . ' / ' . (string)($p['abbr'] ?? '?') . ' ' . (string)($p['name'] ?? ''));
    };
    // The best programme under an objective by the wording; the first by code when the wording says nothing.
    $bestUnder = function ($oid) use ($cat, $s) {
        $best = 0; $bestScore = -1.0; $first = 0;
        foreach ($cat['programmes'] as $pid => $p) {
            if ((int)$p['objective_id'] !== (int)$oid || (string)$p['active'] === '0') { continue; }
            if ($first === 0) { $first = (int)$pid; }
            $sc = (float)($s['scores']['programme'][$pid] ?? 0);
            if ($sc > $bestScore) { $bestScore = $sc; $best = (int)$pid; }
        }
        return [$bestScore > 0 ? $best : $first, max(0.0, $bestScore)];
    };
    $learnedNote = ($top && !empty($top['learned'])) ? ' Filed here before for wording like this.' : '';
    // Near-duplicate wording already filed under the proposed objective is
    // the best evidence there is for the programme: a person put its twin
    // there. Applied at the end, whichever way the objective was decided.
    $nearest = ($nearestId > 0 && $nearestSim >= 0.6) ? ($cat['activities'][$nearestId] ?? null) : null;
    $followTwin = function (array $out) use ($nearest, $cat) {
        if (!$nearest || (int)$nearest['objective_id'] !== (int)$out['sug_objective_id'] || (int)$nearest['programme_id'] === (int)$out['sug_programme_id']) { return $out; }
        $p = $cat['programmes'][(int)$nearest['programme_id']] ?? null;
        if (!$p || (int)$p['objective_id'] !== (int)$out['sug_objective_id'] || (string)$p['active'] === '0') { return $out; }
        $out['sug_programme_id'] = (int)$nearest['programme_id'];
        $out['reason'] .= ' The programme follows ' . (string)$nearest['abbr'] . ', whose wording is nearly the same.';
        return $out;
    };
    if ($hint > 0) {
        [$prg, $prgScore] = $bestUnder($hint);
        $out['sug_pillar_id'] = (int)($cat['objectives'][$hint]['pillar_id'] ?? 0);
        $out['sug_objective_id'] = $hint;
        $out['sug_programme_id'] = $prg;
        $noPrg = $prg === 0 ? ' That objective has no programme yet: add one first.' : ($prgScore <= 0 ? ' No programme under it fits the wording; the first is offered.' : '');
        if ($top && (int)$top['objective_id'] === $hint) {
            // The wording's own programme pick, when it is under this objective.
            $out['sug_programme_id'] = (int)$top['programme_id'] ?: $prg;
            $out['confidence'] = 'agreed';
            $out['reason'] = 'The workbook heading and the wording agree.' . $learnedNote;
        } elseif ($top) {
            $hintScore = (float)($s['scores']['objective'][$hint] ?? 0) + $prgScore;
            $out['alt_objective_id'] = (int)$top['objective_id'];
            $out['alt_programme_id'] = (int)$top['programme_id'];
            if ($hintScore >= 0.6 * (float)$top['score']) {
                $out['confidence'] = 'hint';
                $out['reason'] = 'Filed under the workbook heading; the wording also fits ' . (string)$top['label'] . '.' . $noPrg;
            } else {
                $out['confidence'] = 'split';
                $out['reason'] = 'The workbook files it under ' . $label($hint, $prg) . '; the wording points to ' . (string)$top['label'] . '.' . $noPrg;
            }
        } else {
            $out['confidence'] = 'hint';
            $out['reason'] = 'Only the workbook heading to go on: the wording matched nothing.' . $noPrg;
        }
        return $followTwin($out);
    }
    if ($top) {
        $out['sug_pillar_id'] = (int)$top['pillar_id'];
        $out['sug_objective_id'] = (int)$top['objective_id'];
        $out['sug_programme_id'] = (int)$top['programme_id'];
        if (!empty($s['confident'])) {
            $out['confidence'] = 'agreed';
            $out['reason'] = 'The wording points clearly here.' . $learnedNote;
        } else {
            $others = array_slice(array_map(function ($c) { return $c['label']; }, $cands), 1, 2);
            $out['confidence'] = 'low';
            $out['reason'] = 'The wording is not clear-cut' . ($others ? '; it could also be ' . implode(' or ', $others) : '') . '.' . $learnedNote;
        }
        return $followTwin($out);
    }
    $out['reason'] = 'Nothing in the catalogue resembles this wording, and the workbook gives no heading for it.';
    return $out;
}

/** The proposal for an activity already in the catalogue, with its own row left out of the vote (tools/measure-filing.php --with-heading). */
function import_propose_holdout($db, array $a, array $cat, $hint, $excludeId) {
    $nameTokens = filing_words($a['name']); $textTokens = filing_words($a['name'] . ' ' . $a['description'] . ' ' . $a['kpi']);
    $nearest = 0; $nearestSim = 0.0;
    foreach ($cat['activities'] as $id => $r) {
        if ((int)$id === (int)$excludeId) { continue; }
        $sim = max(import_similarity($nameTokens, $r['name_tokens']), 0.85 * import_similarity($textTokens, $r['text_tokens']));
        if ($sim > $nearestSim) { $nearestSim = $sim; $nearest = (int)$id; }
    }
    return import_propose($db, $a, $cat, (int)$hint, $nearest, $nearestSim, (int)$excludeId);
}

/* ----------------------------------------------------------------- stage */

/** Write a parsed workbook to the staging tables. Returns the batch id. */
function import_stage($db, $filename, array $parsed, $userId) {
    $cat = import_catalogue($db);
    $db->MQ("INSERT INTO pm_import_batches_tbl (filename, sheet, rows_total, uploaded_by, note) VALUES (?,?,?,?,?)", false,
        [mb_substr((string)$filename, 0, 255), mb_substr((string)$parsed['sheet'], 0, 64), count($parsed['activities']), (int)$userId,
         $parsed['warnings'] ? mb_substr(implode("\n", $parsed['warnings']), 0, 4000) : null]);
    $b = $db->MQ("SELECT LAST_INSERT_ID() AS id", "one");
    $batch = (int)($b['id'] ?? 0);
    if ($batch <= 0) { throw new RuntimeException('The batch could not be recorded.'); }
    $seen = [];
    foreach ($parsed['activities'] as $a) {
        $r = import_analyse($db, $a, $cat);
        // The same activity twice in one workbook: the second is a duplicate of
        // the first, not of the catalogue. Two rows only collapse when they
        // resolve to the SAME thing - same code, same matched activity - or a
        // workbook holding two same-named activities under one heading would
        // lose one of them.
        $dupKey = import_norm($a['name']) . '|' . import_norm($a['wb_objective'])
                . '|' . import_norm($a['code']) . '|' . (int)$r['match_project_id'];
        if (isset($seen[$dupKey])) { $r['kind'] = 'same'; $r['match_project_id'] = 0; $r['match_how'] = 'duplicate'; $r['changes'] = null; $r['reason'] = 'The same activity appears on row ' . $seen[$dupKey] . ' of this workbook.'; }
        else { $seen[$dupKey] = (int)$a['row']; }
        $status = $r['kind'] === 'same' ? 'unchanged' : 'pending';
        $db->MQ("INSERT INTO pm_import_rows_tbl
                    (batch_id, row_no, kind, code, name, description, kpi, budget, extra,
                     match_project_id, match_how, match_score, changes, hint_objective_id,
                     sug_pillar_id, sug_objective_id, sug_programme_id, alt_objective_id, alt_programme_id,
                     confidence, reason, candidates, nearest_project_id, nearest_score, status)
                 VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?,?)", false, [
            $batch, (int)$a['row'], $r['kind'], $r['code'], $r['name'], $r['description'], $r['kpi'], $r['budget'], $r['extra'],
            $r['match_project_id'], $r['match_how'], $r['match_score'], $r['changes'], $r['hint_objective_id'],
            $r['sug_pillar_id'], $r['sug_objective_id'], $r['sug_programme_id'], $r['alt_objective_id'], $r['alt_programme_id'],
            $r['confidence'], mb_substr((string)$r['reason'], 0, 2000), $r['candidates'], $r['nearest_project_id'], $r['nearest_score'], $status,
        ]);
    }
    return $batch;
}

function import_batch($db, $batchId) {
    return $db->MQ("SELECT b.*, u.username AS uploaded_by_name FROM pm_import_batches_tbl b LEFT JOIN core_users_tbl u ON u.id = b.uploaded_by WHERE b.id = ?", "one", [(int)$batchId]);
}

/** [status => n] and [kind => n] for a batch. */
function import_batch_counts($db, $batchId) {
    $out = ['status' => ['pending' => 0, 'accepted' => 0, 'skipped' => 0, 'unchanged' => 0], 'kind' => ['new' => 0, 'changed' => 0, 'same' => 0, 'unclear' => 0], 'total' => 0, 'agreed' => 0, 'expected' => 0, 'incomplete' => false];
    foreach ((array)$db->MQ("SELECT status, kind, confidence, COUNT(*) AS n FROM pm_import_rows_tbl WHERE batch_id = ? GROUP BY status, kind, confidence", "all", [(int)$batchId]) as $r) {
        $n = (int)$r['n'];
        $out['status'][(string)$r['status']] = ($out['status'][(string)$r['status']] ?? 0) + $n;
        $out['kind'][(string)$r['kind']] = ($out['kind'][(string)$r['kind']] ?? 0) + $n;
        $out['total'] += $n;
        if ((string)$r['status'] === 'pending' && (((string)$r['kind'] === 'new' && (string)$r['confidence'] === 'agreed') || (string)$r['kind'] === 'changed')) { $out['agreed'] += $n; }
    }
    // The rows are written one at a time after the batch row, so a run that
    // died halfway - a timeout, the matcher going away mid-staging - leaves a
    // batch that reads as complete and is missing activities nobody will ever
    // see. The count the workbook promised is compared with what arrived.
    $b = $db->MQ("SELECT rows_total FROM pm_import_batches_tbl WHERE id = ?", "one", [(int)$batchId]);
    $out['expected'] = (int)($b['rows_total'] ?? 0);
    $out['incomplete'] = $out['expected'] > 0 && $out['total'] < $out['expected'];
    return $out;
}

/** The rows of a batch with the labels the page needs, in workbook order. */
function import_rows($db, $batchId, $show = 'pending') {
    $where = "r.batch_id = ?"; $bind = [(int)$batchId];
    if (in_array($show, ['pending', 'accepted', 'skipped', 'unchanged'], true)) { $where .= " AND r.status = ?"; $bind[] = $show; }
    $rows = (array)$db->MQ("SELECT r.*,
                                   m.abbr AS match_abbr, m.name AS match_name, m.objective_id AS match_objective_id, m.programme_id AS match_programme_id,
                                   n.abbr AS nearest_abbr, n.name AS nearest_name, n.objective_id AS nearest_objective_id, n.programme_id AS nearest_programme_id,
                                   x.abbr AS result_abbr
                              FROM pm_import_rows_tbl r
                              LEFT JOIN pm_projects_tbl m ON m.id = r.match_project_id
                              LEFT JOIN pm_projects_tbl n ON n.id = r.nearest_project_id
                              LEFT JOIN pm_projects_tbl x ON x.id = r.result_project_id
                             WHERE " . $where . "
                             ORDER BY r.row_no, r.id", "all", $bind);
    foreach ($rows as &$r) {
        $r['extra_data'] = json_decode((string)$r['extra'], true) ?: [];
        $r['changes_data'] = json_decode((string)$r['changes'], true) ?: [];
        $r['candidates_data'] = json_decode((string)$r['candidates'], true) ?: [];
    }
    unset($r);
    return $rows;
}

/** "1.0 / 1.2 PRG Network Connectivity Programme" for an objective and programme id. */
function import_place_label(array $cat, $objectiveId, $programmeId) {
    $o = $cat['objectives'][(int)$objectiveId] ?? null; $p = $cat['programmes'][(int)$programmeId] ?? null;
    if (!$o && !$p) { return 'nowhere yet'; }
    return trim((string)($o['abbr'] ?? '?') . ' / ' . (string)($p['abbr'] ?? '?') . ' ' . (string)($p['name'] ?? ''));
}

/* ---------------------------------------------------------------- accept */

/**
 * A person accepts a row, with the objective and programme the boxes hold.
 * A new activity is created and filed there; a changed one has its text
 * and budget updated in place (its placement is not touched here - moving
 * an existing activity is the edit form's job, so the move is vetted and
 * exported like any other). Returns ['ok' => bool, 'message' => ..., 'project_id' => ..., 'code' => ...].
 */
function import_accept($db, array $row, $objectiveId, $programmeId, $userId, $batchFile = '') {
    if ((string)$row['status'] !== 'pending') { return ['ok' => false, 'message' => 'That row was already decided.']; }
    $counts = import_batch_counts($db, (int)$row['batch_id']);
    if (!empty($counts['incomplete'])) {
        return ['ok' => false, 'message' => 'This import did not finish reading: ' . (int)$counts['total'] . ' of ' . (int)$counts['expected']
            . ' rows were recorded. Discard it and read the workbook again rather than accepting part of it.'];
    }
    $userId = (int)$userId;
    if ($row['kind'] === 'changed') {
        $id = (int)$row['match_project_id'];
        $changes = json_decode((string)$row['changes'], true) ?: [];
        $existing = $db->MQ("SELECT id, name, abbr, description, kpi, estimated_budget FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
        if (!is_set($existing)) { return ['ok' => false, 'message' => 'The activity this row would update no longer exists.']; }
        // The difference on screen was worked out when the workbook was read.
        // If someone has edited the activity since, applying it would silently
        // undo their edit and the reviewer would never see what they replaced.
        $names = ['name' => 'name', 'description' => 'description', 'kpi' => 'indicator', 'estimated_budget' => 'budget'];
        $stale = [];
        foreach ($changes as $f => $pair) {
            if (!isset($names[$f])) { continue; }
            $was = $pair[0]; $now = $existing[$f] ?? null;
            $same = ($f === 'estimated_budget')
                  ? ((trim((string)$was) === '' && ($now === null || (string)$now === '')) || (is_numeric($was) && is_numeric($now) && abs((float)$was - (float)$now) <= 0.005))
                  : (import_norm($was) === import_norm($now));
            if (!$same) { $stale[] = $names[$f]; }
        }
        if ($stale) {
            return ['ok' => false, 'message' => 'Not applied: the ' . implode(' and ', $stale) . ' of ' . (string)$existing['abbr']
                . ' has been edited since this workbook was read, so the difference shown is out of date. Read the workbook again to compare against the activity as it stands.'];
        }
        $sets = []; $bind = [];
        foreach (['name', 'description', 'kpi', 'estimated_budget'] as $f) {
            if (!isset($changes[$f])) { continue; }
            $sets[] = "`" . $f . "` = ?";
            $bind[] = $f === 'estimated_budget' ? (float)$changes[$f][1] : (string)$changes[$f][1];
        }
        if ($sets) {
            $bind[] = $id;
            $db->MQ("UPDATE pm_projects_tbl SET " . implode(', ', $sets) . " WHERE id = ?", false, $bind);
        }
        $db->MQ("UPDATE pm_import_rows_tbl SET status = 'accepted', result_project_id = ?, decided_by = ?, decided_at = NOW() WHERE id = ?", false, [$id, $userId, (int)$row['id']]);
        return ['ok' => true, 'message' => 'Updated ' . (string)$existing['abbr'] . '.', 'project_id' => $id, 'code' => (string)$existing['abbr']];
    }
    if (!in_array($row['kind'], ['new', 'unclear'], true)) { return ['ok' => false, 'message' => 'Nothing to accept on that row.']; }
    // The comparison with the catalogue was made when the workbook was read.
    // Read the same workbook twice - or stage it from the command line and
    // then upload it - and both batches call every activity new, because
    // neither had been accepted yet when the other was staged. Checked again
    // here, against the catalogue as it is at this moment.
    $clash = null;
    if ((string)$row['code'] !== '') {
        $prev = $db->MQ("SELECT result_project_id FROM pm_import_rows_tbl
                          WHERE status = 'accepted' AND code = ? AND result_project_id > 0 AND id <> ?
                          ORDER BY id LIMIT 1", "one", [(string)$row['code'], (int)$row['id']]);
        if (is_set($prev)) { $clash = $db->MQ("SELECT id, abbr FROM pm_projects_tbl WHERE id = ?", "one", [(int)$prev['result_project_id']]); }
    }
    if (!is_set($clash)) {
        // The column collates case-insensitively, so this is an exact-name test.
        $clash = $db->MQ("SELECT id, abbr FROM pm_projects_tbl WHERE name = ? LIMIT 1", "one", [(string)$row['name']]);
    }
    if (is_set($clash)) {
        return ['ok' => false, 'message' => 'Not created: ' . (string)$clash['abbr'] . ' already covers this activity. It was probably added by an earlier import of the same workbook. Skip this row, or open that activity if the workbook has something new to say about it.'];
    }
    $objectiveId = (int)$objectiveId; $programmeId = (int)$programmeId;
    $o = $db->MQ("SELECT id, pillar_id FROM pm_objectives_tbl WHERE id = ?", "one", [$objectiveId]);
    $p = $db->MQ("SELECT id, objective_id FROM pm_programmes_tbl WHERE id = ?", "one", [$programmeId]);
    if (!is_set($o) || !is_set($p)) { return ['ok' => false, 'message' => 'Choose an objective and one of its programmes first.']; }
    if ((int)$p['objective_id'] !== $objectiveId) { return ['ok' => false, 'message' => 'That programme does not sit under that objective.']; }
    $pillar = (int)$o['pillar_id'];
    $code = auto_wbs_code($db, 'pm_projects', ['programme_id' => $programmeId]);
    if ($code === '') { return ['ok' => false, 'message' => 'The programme has no numeric code, so no activity code can be made under it.']; }
    $extra = json_decode((string)$row['extra'], true) ?: [];
    $notes = import_notes_text($row, $extra, $batchFile);
    $db->MQ("INSERT INTO pm_projects_tbl (pillar_id, objective_id, programme_id, name, abbr, description, kpi, estimated_budget, notes, type, active)
             VALUES (?,?,?,?,?,?,?,?,?,'pm_projects_tasks',1)", false, [
        $pillar, $objectiveId, $programmeId, (string)$row['name'], $code, (string)$row['description'], (string)$row['kpi'],
        ($row['budget'] === null || $row['budget'] === '') ? null : (float)$row['budget'], $notes,
    ]);
    $n = $db->MQ("SELECT LAST_INSERT_ID() AS id", "one");
    $newId = (int)($n['id'] ?? 0);
    if ($newId <= 0) { return ['ok' => false, 'message' => 'The activity could not be created.']; }
    ensure_default_task($db, $newId);
    // Where the person put it is a statement about this wording: kept as
    // proposed it confirms the proposal, moved it corrects it, and with no
    // proposal at all it is still a clean example.
    record_filing_feedback($db, 'pm_projects', [
        'pillar_id' => $pillar, 'objective_id' => $objectiveId, 'programme_id' => $programmeId,
        'name' => (string)$row['name'], 'description' => (string)$row['description'], 'kpi' => (string)$row['kpi'],
    ], ['pillar_id' => (int)$row['sug_pillar_id'], 'objective_id' => (int)$row['sug_objective_id'], 'programme_id' => (int)$row['sug_programme_id']], $newId, true);
    $db->MQ("UPDATE pm_import_rows_tbl SET status = 'accepted', result_project_id = ?, decided_by = ?, decided_at = NOW() WHERE id = ?", false, [$newId, $userId, (int)$row['id']]);
    return ['ok' => true, 'message' => 'Created ' . $code . '.', 'project_id' => $newId, 'code' => $code];
}

/** What the workbook knew that the catalogue has no column for, kept on the activity's notes. */
function import_notes_text(array $row, array $extra, $batchFile) {
    $bits = [];
    $from = 'Imported ' . date('Y-m-d') . ($batchFile !== '' ? ' from ' . $batchFile : '') . ' (row ' . (int)$row['row_no'] . ((string)$row['code'] !== '' ? ', code ' . (string)$row['code'] : '') . ')';
    $bits[] = $from;
    $when = trim((string)($extra['quarter'] ?? ''));
    if (($extra['start'] ?? '') !== '' || ($extra['finish'] ?? '') !== '') { $when = trim($when . ' ' . (string)($extra['start'] ?? '?') . ' to ' . (string)($extra['finish'] ?? '?')); }
    if ($when !== '') { $bits[] = $when; }
    if (($extra['owner'] ?? '') !== '') { $bits[] = 'Owner: ' . (string)$extra['owner']; }
    if (($extra['wb_objective'] ?? '') !== '') { $bits[] = 'Workbook heading: ' . (string)($extra['wb_wbs'] ?? '') . ' ' . (string)$extra['wb_objective']; }
    return mb_substr(implode('. ', array_map('trim', $bits)) . '.', 0, 4000);
}

function import_skip($db, array $row, $userId) {
    if ((string)$row['status'] !== 'pending') { return ['ok' => false, 'message' => 'That row was already decided.']; }
    $db->MQ("UPDATE pm_import_rows_tbl SET status = 'skipped', decided_by = ?, decided_at = NOW() WHERE id = ?", false, [(int)$userId, (int)$row['id']]);
    return ['ok' => true, 'message' => 'Skipped.'];
}

/** An UNCLEAR row settled by a person: it is the existing activity (recompare), or it is new (propose). */
function import_settle($db, array $row, $isSame) {
    if ((string)$row['status'] !== 'pending' || (string)$row['kind'] !== 'unclear') { return ['ok' => false, 'message' => 'That row is not waiting for this answer.']; }
    $cat = import_catalogue($db);
    if ($isSame) {
        $id = (int)$row['match_project_id'];
        if (!isset($cat['activities'][$id])) { return ['ok' => false, 'message' => 'The activity it resembled no longer exists.']; }
        $a = ['name' => (string)$row['name'], 'description' => (string)$row['description'], 'kpi' => (string)$row['kpi'], 'budget' => $row['budget'] === null ? null : (float)$row['budget']];
        $changes = import_changes($cat['activities'][$id], $a);
        $db->MQ("UPDATE pm_import_rows_tbl SET kind = ?, match_how = 'person', match_score = 1, changes = ?, status = ? WHERE id = ?", false,
            [$changes ? 'changed' : 'same', $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null, $changes ? 'pending' : 'unchanged', (int)$row['id']]);
        return ['ok' => true, 'message' => $changes ? 'Treated as the same activity; the differences are ready to accept.' : 'Treated as the same activity; nothing differs.'];
    }
    $db->MQ("UPDATE pm_import_rows_tbl SET kind = 'new', match_project_id = 0, match_how = '', match_score = 0 WHERE id = ?", false, [(int)$row['id']]);
    return ['ok' => true, 'message' => 'Treated as a new activity.'];
}

/** The pending rows a person can accept in one go: agreed new rows and plain updates. */
function import_accept_all($db, $batchId, $userId) {
    $batch = import_batch($db, $batchId);
    if (!is_set($batch)) { return ['ok' => false, 'message' => 'No such import.']; }
    $rows = (array)$db->MQ("SELECT * FROM pm_import_rows_tbl WHERE batch_id = ? AND status = 'pending' AND ((kind = 'new' AND confidence = 'agreed') OR kind = 'changed') ORDER BY row_no, id", "all", [(int)$batchId]);
    $done = 0; $failed = [];
    foreach ($rows as $r) {
        $res = import_accept($db, $r, (int)$r['sug_objective_id'], (int)$r['sug_programme_id'], $userId, (string)$batch['filename']);
        if (!empty($res['ok'])) { $done++; } else { $failed[] = 'Row ' . (int)$r['row_no'] . ': ' . (string)$res['message']; }
    }
    // The rows that would not go through are the whole point of reading this
    // message, so they travel as their own field: a caller that only looks at
    // the status code would otherwise show a clean run.
    $message = $done . ' accepted';
    if ($failed) { $message .= '. ' . count($failed) . ' left for you to look at: ' . implode(' ', array_slice($failed, 0, 3)) . (count($failed) > 3 ? ' …' : ''); }
    return ['ok' => true, 'message' => $message . '.', 'done' => $done, 'failed' => count($failed)];
}

/** Throw a batch away. Only while nothing from it has been accepted: an accepted row is the record of where an activity came from. */
function import_discard($db, $batchId) {
    $c = import_batch_counts($db, $batchId);
    if ($c['status']['accepted'] > 0) { return ['ok' => false, 'message' => 'This import has accepted rows; it stays as the record of them.']; }
    $db->MQ("DELETE FROM pm_import_rows_tbl WHERE batch_id = ?", false, [(int)$batchId]);
    $db->MQ("DELETE FROM pm_import_batches_tbl WHERE id = ?", false, [(int)$batchId]);
    return ['ok' => true, 'message' => 'Discarded.'];
}

/* ------------------------------------------------------------------ view */

/** The tag, the note and the actions under a staged row's name, like the vetting note on the lists. */
function import_row_note(array $r, array $cat, $canAct) {
    $kind = (string)$r['kind']; $status = (string)$r['status'];
    $tags = ['new' => 'New', 'changed' => 'Changed', 'same' => 'Already in the system', 'unclear' => 'Looks familiar'];
    $html = '<div class="afcdc-review__note afcdc-import__note">';
    $html .= '<span class="afcdc-review__tag">' . display($tags[$kind] ?? $kind) . '</span> ';
    if ($status === 'accepted') {
        $html .= 'accepted' . ((int)$r['result_project_id'] > 0 ? ' as <a href="' . display('/projects/edit/' . (int)$r['result_project_id']) . '"><code>' . display($r['result_abbr'] ?? '') . '</code></a>' : '');
        return $html . '</div>';
    }
    if ($status === 'skipped') { return $html . 'skipped: nothing was written.</div>'; }
    // The workbook may file an existing activity under another objective
    // than the one it sits under now. That is not applied: the placement in
    // the catalogue is a person's decision, moved through the edit form
    // when it should change. It is said, so nothing is hidden.
    $elsewhere = '';
    if ((int)$r['hint_objective_id'] > 0 && (int)($r['match_objective_id'] ?? 0) > 0 && (int)$r['hint_objective_id'] !== (int)$r['match_objective_id']) {
        $ho = $cat['objectives'][(int)$r['hint_objective_id']] ?? null;
        $elsewhere = ' The workbook lists it under ' . display(trim((string)($ho['abbr'] ?? '?') . ' ' . (string)($ho['name'] ?? ''))) . '; it stays where it sits.';
    }
    if ($kind === 'same') {
        if ((string)$r['match_how'] === 'duplicate') { $html .= display($r['reason']); }
        else { $html .= 'matches <a href="' . display('/projects/edit/' . (int)$r['match_project_id']) . '"><code>' . display($r['match_abbr'] ?? '') . '</code></a> ' . display(mb_substr((string)($r['match_name'] ?? ''), 0, 70)) . ' under ' . display(import_place_label($cat, $r['match_objective_id'] ?? 0, $r['match_programme_id'] ?? 0)) . ' (by ' . display($r['match_how']) . '); nothing differs.' . $elsewhere; }
        return $html . '</div>';
    }
    if ($kind === 'changed') {
        $html .= 'is <a href="' . display('/projects/edit/' . (int)$r['match_project_id']) . '"><code>' . display($r['match_abbr'] ?? '') . '</code></a> under ' . display(import_place_label($cat, $r['match_objective_id'] ?? 0, $r['match_programme_id'] ?? 0)) . ' (matched by ' . display($r['match_how']) . ').' . $elsewhere . ' The workbook differs:';
        $names = ['name' => 'Name', 'description' => 'Description', 'kpi' => 'Indicator', 'estimated_budget' => 'Budget'];
        $html .= '<ul class="afcdc-import__diff">';
        foreach ((array)$r['changes_data'] as $f => $pair) {
            $fmt = function ($v) use ($f) {
                $v = trim((string)$v);
                if ($v === '') { return '<em>nothing</em>'; }
                if ($f === 'estimated_budget' && is_numeric($v)) { return display(number_format((float)$v, 0, '.', ',')); }
                return display(mb_substr($v, 0, 160)) . (mb_strlen($v) > 160 ? '…' : '');
            };
            $html .= '<li><strong>' . display($names[$f] ?? $f) . '</strong>: <del>' . $fmt($pair[0]) . '</del> → <ins>' . $fmt($pair[1]) . '</ins></li>';
        }
        $html .= '</ul>';
        if ($canAct) {
            $html .= '<a href="#" class="afcdc-review__act" data-import-action="accept" data-id="' . (int)$r['id'] . '">Apply the changes</a>';
            $html .= ' <a href="#" class="afcdc-review__act" data-import-action="skip" data-id="' . (int)$r['id'] . '">Skip</a>';
        }
        // (custom.css makes .afcdc-review__act.disabled unclickable, so the
        //  class the script adds while a POST is in flight has an effect here
        //  too - these are links, not buttons.)
        return $html . '</div>';
    }
    if ($kind === 'unclear') {
        $html .= 'resembles <a href="' . display('/projects/edit/' . (int)$r['match_project_id']) . '"><code>' . display($r['match_abbr'] ?? '') . '</code></a> ' . display(mb_substr((string)($r['match_name'] ?? ''), 0, 80))
               . ' under ' . display(import_place_label($cat, $r['match_objective_id'] ?? 0, $r['match_programme_id'] ?? 0))
               . ' (' . (int)round(100 * (float)$r['match_score']) . '% alike). Is it that activity, or a new one?';
        if ($canAct) {
            $html .= ' <a href="#" class="afcdc-review__act" data-import-action="same" data-id="' . (int)$r['id'] . '">It is that one</a>';
            $html .= ' <a href="#" class="afcdc-review__act" data-import-action="asnew" data-id="' . (int)$r['id'] . '">It is new</a>';
        }
        $html .= '</div>';
        // The proposal stays visible underneath, so "it is new" needs no second look.
    }
    $conf = (string)$r['confidence'];
    $lead = ['agreed' => 'Proposed', 'hint' => 'From the workbook heading', 'split' => 'Two answers', 'low' => 'Best guess', 'none' => 'No proposal'];
    $html .= '<div class="afcdc-review__note afcdc-import__proposal"><span class="afcdc-review__tag afcdc-import__tag--' . display($conf) . '">' . display($lead[$conf] ?? $conf) . '</span> ';
    if ((int)$r['sug_objective_id'] > 0) { $html .= '<strong>' . display(import_place_label($cat, $r['sug_objective_id'], $r['sug_programme_id'])) . '</strong> '; }
    if (trim((string)$r['reason']) !== '') { $html .= '<span class="afcdc-review__why">' . display($r['reason']) . '</span>'; }
    if ((int)$r['alt_objective_id'] > 0 && $canAct && $kind !== 'unclear') {
        $html .= ' <a href="#" class="afcdc-review__act" data-import-pick="1" data-objective="' . (int)$r['alt_objective_id'] . '" data-programme="' . (int)$r['alt_programme_id'] . '" data-id="' . (int)$r['id'] . '">Use the wording\'s pick</a>';
    }
    if ((int)$r['nearest_project_id'] > 0 && (float)$r['nearest_score'] >= 0.3 && $kind !== 'unclear') {
        $html .= '<div class="afcdc-import__nearest">Most like <a href="' . display('/projects/edit/' . (int)$r['nearest_project_id']) . '"><code>' . display($r['nearest_abbr'] ?? '') . '</code></a> ' . display(mb_substr((string)($r['nearest_name'] ?? ''), 0, 70))
               . ' under ' . display(import_place_label($cat, $r['nearest_objective_id'] ?? 0, $r['nearest_programme_id'] ?? 0)) . ' (' . (int)round(100 * (float)$r['nearest_score']) . '% alike).</div>';
    }
    return $html . '</div>';
}
