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
        $data = ['meta_name' => 'Import a work plan', 'batches' => $batches, 'error' => '', 'notice' => ''];
        if (!empty($this->query['discarded'])) { $data['notice'] = 'The import was discarded; nothing had been written.'; }
        $this->AddJS("/js/imports.js");
        $this->render($data);
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
            $this->render(['meta_name' => 'Import a work plan', 'batches' => $batches, 'error' => $error, 'notice' => '']);
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
            'can_act'    => can_vet() && empty($counts['incomplete']),
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
