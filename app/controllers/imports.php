<?php
/**
 * Content > Import a work plan: an .xlsx work plan in, a review page out,
 * and an activity written only when a person accepts its row.
 *
 * The whole controller answers 404 unless IMPORT_ENABLED=true (.env), so a
 * release can carry it to the server while it is tried out on a local copy;
 * the menu entry is hidden by the same switch. Groups 1 and 2 only
 * (protectedController), like every other content-editing page.
 *
 * The analysis lives in app/includes/import.php and is shared with
 * tools/import-workbook.php, which does the same from the command line.
 */
class importsController extends protectedController {

    public function __construct(Registry $registry) {
        parent::__construct($registry);
        // The same page, and the same wording, as a route that does not exist.
        if (!import_enabled()) { $this->setAnswer(404, "The page you asked for does not exist."); }
        if (!import_available($this->DB)) { $this->setAnswer(503, "The import tables are missing: start the container once so the migration creates them."); }
    }

    private function userId() { return (int)($_SESSION['user']['user_id'] ?? 0); }

    /** GET imports/list: the upload form and the imports so far. */
    public function list() {
        $this->checkMethod("GET");
        $batches = (array)$this->DB->MQ("SELECT b.*, u.username AS uploaded_by_name FROM pm_import_batches_tbl b LEFT JOIN core_users_tbl u ON u.id = b.uploaded_by ORDER BY b.id DESC LIMIT 100", "all");
        foreach ($batches as &$b) { $b['counts'] = import_batch_counts($this->DB, (int)$b['id']); }
        unset($b);
        $data = ['meta_name' => 'Import a work plan', 'batches' => $batches, 'error' => '', 'notice' => ''] + $this->templateChoices();
        if (!empty($this->query['discarded'])) { $data['notice'] = 'The import was discarded; nothing had been written.'; }
        $this->AddJS("/js/imports.js");
        $this->render($data);
    }

    /** What the template can hold: the whole work plan, one unit's objectives, or one objective. */
    private function templateChoices() {
        $units = units_available($this->DB) ? (array)$this->DB->MQ("SELECT id, name FROM pm_units_tbl WHERE active = 1 ORDER BY position, id", "all") : [];
        $objectives = (array)$this->DB->MQ("SELECT o.id, o.abbr, o.name FROM pm_objectives_tbl o LEFT JOIN pm_pillars_tbl g ON g.id = o.pillar_id ORDER BY g.position, g.id, o.position, o.id", "all");
        return ['template_units' => $units, 'template_objectives' => $objectives];
    }

    /**
     * GET imports/template[?scope=unit:3|objective:12]: the work plan as it
     * stands, as a workbook this page reads straight back - goals and
     * objectives as heading rows, every activity with its code, name,
     * description, indicator, budget and programme, and its tasks as rows
     * under it (T and the task's number, name, description) - and a second sheet on
     * how to use it. Rows nobody touches come back as "already in"; only
     * what was changed or added waits for review.
     */
    public function template() {
        $this->checkMethod("GET");
        require_once __DIR__ . '/../includes/xlsx.php';
        $scope = is_string($this->query['scope'] ?? null) ? $this->query['scope'] : '';
        $where = ''; $params = []; $what = 'the whole work plan'; $slug = '';
        // Empty: the goal and objective headings with no activity under them,
        // so rows added there are filed under the right objective.
        $empty = ($scope === 'empty');
        if ($empty) { $what = 'no activities yet, only the goal and objective headings'; $slug = 'empty'; }
        if (preg_match('/^objective:(\d{1,10})$/', $scope, $m)) {
            $o = $this->DB->MQ("SELECT id, abbr, name FROM pm_objectives_tbl WHERE id = ?", "one", [(int)$m[1]]);
            if (!is_set($o)) { $this->setAnswer(404, "There is no such objective."); }
            $where = ' WHERE o.id = ?'; $params[] = (int)$o['id'];
            $what = 'objective ' . trim($o['abbr'] . ' ' . $o['name']); $slug = 'objective-' . $o['abbr'];
        } elseif (preg_match('/^unit:(\d{1,10})$/', $scope, $m) && units_available($this->DB)) {
            $u = $this->DB->MQ("SELECT id, name FROM pm_units_tbl WHERE id = ?", "one", [(int)$m[1]]);
            if (!is_set($u)) { $this->setAnswer(404, "There is no such unit."); }
            $where = ' WHERE o.unit_id = ?'; $params[] = (int)$u['id'];
            $what = 'the objectives of the ' . $u['name'] . ' unit'; $slug = $u['name'];
            // Once projects can carry a unit of their own, a unit's template is
            // its PROJECTS: those moved in come with their objective's heading,
            // those moved out are left off.
            if (unit_moves_available($this->DB)) {
                $unitScope = (int)$u['id'];
                $where = ' WHERE (o.unit_id = ? OR o.id IN (SELECT pu.objective_id FROM pm_projects_tbl pu WHERE pu.unit_id = ?))'; $params[] = (int)$u['id'];
                $what = 'the ' . $u['name'] . ' unit';
            }
        }
        $objectives = (array)$this->DB->MQ("SELECT o.id, o.abbr, o.name, o.pillar_id, g.name AS pillar_name, g.position AS pillar_position
                                              FROM pm_objectives_tbl o LEFT JOIN pm_pillars_tbl g ON g.id = o.pillar_id" . $where . "
                                             ORDER BY g.position, g.id, o.position, o.id", "all", $params);
        $byObjective = []; $tasksOf = [];
        if ($objectives && !$empty) {
            $ids = array_map('intval', array_column($objectives, 'id'));
            $acts = (array)$this->DB->MQ("SELECT p.id, p.objective_id, p.abbr, p.name, p.description, p.kpi, p.estimated_budget, g.abbr AS programme_abbr, g.name AS programme_name
                                            FROM pm_projects_tbl p LEFT JOIN pm_programmes_tbl g ON g.id = p.programme_id
                                           WHERE p.objective_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")"
                                          . (!empty($unitScope) ? " AND " . activity_unit_sql('p') . " = ?" : ""), "all", !empty($unitScope) ? array_merge($ids, [$unitScope]) : $ids);
            // Code order as a person reads it (1.2.9 before 1.2.10); this controller has no model to sort in SQL.
            usort($acts, function ($a, $b) { return strnatcmp((string)$a['abbr'], (string)$b['abbr']) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)); });
            foreach ($acts as $a) { $byObjective[(int)$a['objective_id']][] = $a; }
            $actIds = array_map('intval', array_column($acts, 'id'));
            if ($actIds) {
                foreach ((array)$this->DB->MQ("SELECT id, project_id, name, description FROM pm_projects_tasks_tbl WHERE project_id IN (" . implode(',', array_fill(0, count($actIds), '?')) . ") ORDER BY id", "all", $actIds) as $t) {
                    $tasksOf[(int)$t['project_id']][] = $t;
                }
            }
        }

        // The Work plan sheet, in the shape import_parse_rows() reads: a header
        // row, a goal as a row numbered "1", an objective as "1.0", and each
        // activity under it with its code. Heading cells in every column carry
        // the row's colour so a heading reads as one band.
        $band = function ($style, $wbs, $name) { return [['v' => (string)$wbs, 's' => $style], ['v' => '', 's' => $style], ['v' => (string)$name, 's' => $style], ['v' => '', 's' => $style], ['v' => '', 's' => $style], ['v' => '', 's' => $style], ['v' => '', 's' => $style], ['v' => '', 's' => $style], ['v' => '', 's' => $style]]; };
        $rows = [array_map(function ($h) { return ['v' => $h, 's' => 1]; }, ['WBS', 'AWP Code', 'Activity', 'Task', 'Description', 'Indicator', 'Budget (USD)', 'Programme', 'Status'])];
        // Whether each activity has been delivered, counted as the Progress page
        // counts it (delivery_rollup), in the dashboard's colours. For reading
        // only: no import column role matches "Status", so changing it in the
        // workbook records nothing.
        $roll = $byObjective ? delivery_rollup($this->DB) : ['activity' => [], 'task' => []];
        $statusStyle = ['completed' => 7, 'in_progress' => 8, 'not_started' => 9];
        $pillar = null; $pos = 0; $n = 0; $count = 0; $taskCount = 0;
        foreach ($objectives as $o) {
            if ($pillar !== (int)$o['pillar_id']) {
                $pillar = (int)$o['pillar_id']; $n = 0;
                $pos = (int)$o['pillar_position'] > 0 ? (int)$o['pillar_position'] : max(1, $pillar);
                if (count($rows) > 1) { $rows[] = []; }
                $rows[] = $band(2, $pos, $o['pillar_name'] ?? 'Goal');
            }
            $n++;
            // Only a number with one dot is read as an objective heading.
            $wbs = preg_match('/^\d+\.\d+$/', trim((string)$o['abbr'])) ? trim((string)$o['abbr']) : $pos . '.' . $n;
            $rows[] = $band(3, $wbs, $o['name']);
            foreach ($byObjective[(int)$o['id']] ?? [] as $a) {
                $count++;
                $budget = ($a['estimated_budget'] === null || $a['estimated_budget'] === '') ? null : (float)$a['estimated_budget'];
                $status = delivery_rollup_status($roll['activity'][(int)$a['id']] ?? null);
                $rows[] = [null, ['v' => (string)$a['abbr'], 's' => 0], ['v' => (string)$a['name'], 's' => 4], null, ['v' => (string)$a['description'], 's' => 4], ['v' => (string)$a['kpi'], 's' => 4],
                           $budget === null ? null : ['v' => $budget, 's' => 5], ['v' => trim((string)$a['programme_abbr'] . ' ' . (string)$a['programme_name']), 's' => 4],
                           ['v' => delivery_status_label($status), 's' => $statusStyle[$status] ?? 4]];
                // Its tasks, a row each under it: the task's number in AWP Code
                // (how the import finds it again) and its name in Task.
                foreach ($tasksOf[(int)$a['id']] ?? [] as $t) {
                    // The default task ("Task") says nothing a person wrote: left out.
                    if (is_default_task_name($t['name'])) { continue; }
                    $taskCount++;
                    $ts = delivery_rollup_status($roll['task'][(int)$t['id']] ?? null);
                    $rows[] = [null, ['v' => 'T' . (int)$t['id'], 's' => 0], null, ['v' => (string)$t['name'], 's' => 4], ['v' => (string)$t['description'], 's' => 4], null, null, null,
                               ['v' => delivery_status_label($ts), 's' => $statusStyle[$ts] ?? 4]];
                }
            }
        }
        $how = [
            [['v' => 'How to use this template', 's' => 6]],
            [['v' => $empty ? 'An empty template: ' . $what . ', as the dashboard had them on ' . date('j F Y') . '. Add each activity as a row under its objective.' : 'Holds ' . $what . ': ' . $count . ' activit' . ($count === 1 ? 'y' : 'ies') . ' and their ' . $taskCount . ' task' . ($taskCount === 1 ? '' : 's') . ', as the dashboard had them on ' . date('j F Y') . '.', 's' => 4]],
            [],
            [['v' => '1. Change what needs changing on the Work plan sheet: an activity\'s name, description, indicator or budget, or a task\'s name or description. Leave the AWP Code of an existing activity or task (T and a number) as it is - it is how each row finds what it updates.', 's' => 4]],
            [['v' => '2. To add an activity, add a row under the objective it belongs to. Leave AWP Code empty (the code is given when the row is accepted), fill in Activity and Description, and copy the Programme cell from another activity of the same programme, for example "1.2 PRG Network Connectivity Programme".', 's' => 4]],
            [['v' => '3. Tasks are the rows under their activity, with the task\'s name in Task and Activity left empty. To add a task, add such a row under the activity with AWP Code empty; a new activity\'s tasks go under it the same way. An activity that only has the default task, "Task", shows no task rows: the first task you add under it takes that task\'s place - unless a delivery has already been recorded against it, in which case both are kept.', 's' => 4]],
            [['v' => '4. Keep the header row, and the goal and objective rows (a number in WBS and no code): they tell the dashboard where rows belong.', 's' => 4]],
            [['v' => '5. A blank description, indicator or budget never erases what the dashboard has. Deleting a row does not delete the activity or the task: a workbook only adds and updates.', 's' => 4]],
            [['v' => '6. Save as .xlsx and upload it on Content > Import a work plan. Nothing changes until someone accepts each row there. Rows you did not touch are listed as already in.', 's' => 4]],
            [],
            [['v' => 'Budget is in US dollars, as a number.', 's' => 4]],
            [['v' => 'Status says whether each activity and task has been delivered - Completed, In progress or Not started - as the Progress page showed it that day. It is there to read: changing it here records nothing. Record a delivery on the Progress page.', 's' => 4]],
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'afcdc-template-');
        try {
            xlsx_write($tmp, [
                ['name' => 'Work plan', 'rows' => $rows, 'widths' => [8, 12, 44, 34, 56, 32, 14, 36, 16], 'freeze' => 1],
                ['name' => 'How to use', 'rows' => $how, 'widths' => [120]],
            ]);
        } catch (RuntimeException $e) {
            @unlink($tmp);
            $this->setAnswer(500, $e->getMessage());
        }
        $part = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
        $file = 'work-plan-template' . ($part !== '' ? '-' . $part : '') . '-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . (int)filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** POST imports/upload: read the workbook, stage it, open the review. Nothing is written to the catalogue here. */
    public function upload() {
        $this->checkMethod("POST");
        $f = $_FILES['workbook'] ?? null;
        $error = '';
        if (!is_array($f) || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $error = 'Choose a workbook (.xlsx) first.'; }
        elseif ((int)$f['error'] !== UPLOAD_ERR_OK) { $error = in_array((int)$f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'The file is too large to upload.' : 'The upload did not complete; try again.'; }
        elseif (!is_uploaded_file((string)$f['tmp_name'])) { $error = 'The upload did not complete; try again.'; }
        $name = preg_replace('/[^\p{L}\p{N} ._()\-]/u', '_', basename((string)($f['name'] ?? 'workbook.xlsx')));
        if ($error === '' && !preg_match('/\.xlsx$/i', $name)) { $error = 'Only .xlsx workbooks (Excel 2007 or later) can be read. Save the file as .xlsx and try again.'; }
        $batch = 0;
        if ($error === '') {
            $sheet = trim((string)($this->query['sheet'] ?? ''));
            try {
                $parsed = import_parse_workbook((string)$f['tmp_name'], $sheet !== '' ? mb_substr($sheet, 0, 64) : null);
                $batch = import_stage($this->DB, $name, $parsed, $this->userId());
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
        if ($error !== '') {
            $batches = (array)$this->DB->MQ("SELECT b.*, u.username AS uploaded_by_name FROM pm_import_batches_tbl b LEFT JOIN core_users_tbl u ON u.id = b.uploaded_by ORDER BY b.id DESC LIMIT 100", "all");
            foreach ($batches as &$b) { $b['counts'] = import_batch_counts($this->DB, (int)$b['id']); }
            unset($b);
            $this->AddJS("/js/imports.js");
            http_response_code(422);
            $this->R->url = array_merge($this->R->url, ['action' => 'list']);
            $this->render(['meta_name' => 'Import a work plan', 'batches' => $batches, 'error' => $error, 'notice' => ''] + $this->templateChoices());
        }
        redirect($this->L("imports/review/" . $batch));
    }

    /** GET imports/review/<batch>?show=pending|accepted|skipped|unchanged|all */
    public function review() {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        $batch = import_batch($this->DB, $id);
        if (!is_set($batch)) { $this->setAnswer(404, "No such import."); }
        $show = (string)($this->query['show'] ?? 'pending');
        if (!in_array($show, ['pending', 'accepted', 'skipped', 'unchanged', 'all'], true)) { $show = 'pending'; }
        $cat = import_catalogue($this->DB);
        $rows = import_rows($this->DB, $id, $show);
        $counts = import_batch_counts($this->DB, $id);
        // With nothing left to decide, the page opens on everything.
        if ($show === 'pending' && !$rows && $counts['status']['pending'] === 0 && !isset($this->query['show'])) { $show = 'all'; $rows = import_rows($this->DB, $id, 'all'); }
        $objectives = array_filter($cat['objectives'], function ($o) { return (string)$o['active'] !== '0'; });
        uasort($objectives, function ($a, $b) { return strnatcmp((string)$a['abbr'], (string)$b['abbr']); });
        $byObjective = [];
        foreach ($cat['programmes'] as $p) { if ((string)$p['active'] !== '0') { $byObjective[(int)$p['objective_id']][] = $p; } }
        $this->AddJS("/js/imports.js");
        $this->render([
            'meta_name'  => 'Import a work plan',
            'batch'      => $batch,
            'rows'       => $rows,
            // The code each pending row would be given, worked out from where
            // it is proposed to go. Two rows headed for the same programme get
            // consecutive numbers rather than the same one twice.
            'codes'      => $this->previewCodes($rows),
            'counts'     => $counts,
            'show'       => $show,
            'cat'        => $cat,
            'objectives' => $objectives,
            'programmes' => $byObjective,
            // Nothing may be acted on until the whole workbook is in.
            'can_act'    => can_edit() && empty($counts['incomplete']),
        ]);
    }

    /**
     * What each pending row would be numbered, in the order they will be
     * accepted. auto_wbs_code() answers "the next free code under this
     * programme", which is the right answer once - so the second row headed
     * for the same programme has its last segment stepped on here, or a
     * workbook adding five activities to one programme would show 3.2.7 five
     * times over. The number an activity actually gets is still assigned when
     * the row is accepted; this only says what to expect.
     */
    private function previewCodes(array $rows) {
        $next = []; $out = [];
        foreach ($rows as $r) {
            if ((string)$r['status'] !== 'pending' || !in_array((string)$r['kind'], ['new', 'unclear'], true)) { continue; }
            $programme = (int)$r['sug_programme_id'];
            if ($programme <= 0) { continue; }
            if (!isset($next[$programme])) {
                $code = auto_wbs_code($this->DB, 'pm_projects', ['programme_id' => $programme]);
                if ($code === '') { continue; }
                $next[$programme] = $code;
            } else {
                $next[$programme] = preg_replace_callback('/(\d+)$/', function ($m) { return (string)((int)$m[1] + 1); }, $next[$programme]);
            }
            $out[(int)$r['id']] = $next[$programme];
        }
        return $out;
    }

    private function rowOr404($id) {
        $row = $this->DB->MQ("SELECT * FROM pm_import_rows_tbl WHERE id = ?", "one", [(int)$id]);
        if (!is_set($row)) { $this->setAnswer(404, "No such row.", [], "json"); }
        return $row;
    }

    private function answer(array $res, $batchId) {
        $counts = import_batch_counts($this->DB, (int)$batchId);
        $this->setAnswer(!empty($res['ok']) ? 200 : 409, (string)($res['message'] ?? ''), ['pending' => $counts['status']['pending'], 'agreed' => $counts['agreed']] + array_intersect_key($res, ['project_id' => 1, 'code' => 1, 'failed' => 1, 'done' => 1]), "json");
    }

    /** POST imports/accept/<row> with objective_id and programme_id (new rows) - or nothing (changed rows). */
    public function accept() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $row = $this->rowOr404($this->parts['id'] ?? 0);
        $batch = import_batch($this->DB, (int)$row['batch_id']);
        // The description as it stands on the page: the workbook's own words, or
        // the composed suggestion, or whatever the person typed over either.
        $description = array_key_exists('description', $this->query) ? (string)$this->query['description'] : null;
        $res = import_accept($this->DB, $row, (int)($this->query['objective_id'] ?? 0), (int)($this->query['programme_id'] ?? 0), $this->userId(), (string)($batch['filename'] ?? ''), $description);
        $this->answer($res, (int)$row['batch_id']);
    }

    /** POST imports/skip/<row>: leave the catalogue alone for this row. */
    public function skip() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $row = $this->rowOr404($this->parts['id'] ?? 0);
        $this->answer(import_skip($this->DB, $row, $this->userId()), (int)$row['batch_id']);
    }

    /** POST imports/same/<row>: an unclear row IS the activity it resembles. */
    public function same() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $row = $this->rowOr404($this->parts['id'] ?? 0);
        $this->answer(import_settle($this->DB, $row, true), (int)$row['batch_id']);
    }

    /** POST imports/asnew/<row>: an unclear row is a new activity after all. */
    public function asnew() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $row = $this->rowOr404($this->parts['id'] ?? 0);
        $this->answer(import_settle($this->DB, $row, false), (int)$row['batch_id']);
    }

    /** POST imports/accept_all/<batch>: every agreed new row and every plain update, after the person has looked. */
    public function accept_all() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        // The descriptions as they stand on the review page, keyed by row id.
        $descriptions = [];
        foreach ((array)($this->query['descriptions'] ?? []) as $rowId => $text) {
            if (is_array($text)) { continue; }
            $descriptions[(int)$rowId] = (string)$text;
        }
        $this->answer(import_accept_all($this->DB, $id, $this->userId(), $descriptions), $id);
    }

    /** POST imports/discard/<batch>: throw the staging away (only while nothing from it was accepted). */
    public function discard() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        if (!is_set(import_batch($this->DB, $id))) { $this->setAnswer(404, "No such import.", [], "json"); }
        $res = import_discard($this->DB, $id);
        $this->setAnswer(!empty($res['ok']) ? 200 : 409, (string)$res['message'], [], "json");
    }
}
