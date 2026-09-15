<?php
require_once _CONTROLLERS_PATH."core.php";

class projectsController extends coreController{

    public function __construct(Registry $registry){
        parent::__construct($registry);
//        debug($_SESSION);
        // FIND_IN_SET so one entity can have several reporting accounts. The
        // column is a comma-separated list of user ids (widened by
        // db/for_upload/africacdc_dhis_tasks.sql); a single bare number still
        // matches, so this is backward compatible with the old int column.
        $query = "select * from pm_members_tbl where FIND_IN_SET(".(int)$_SESSION['user']['user_id'].", `account`)";
//        debug($query);
        $_SESSION['user']['member_state'] = $this->DB->MQ($query, "one");
        // System Administrators report for the one entity this installation
        // has, whether or not their id is on its account list: every new admin
        // account used to land on a "not linked" page until someone edited
        // Division Users. With more than one active entity the list still rules.
        if (!is_set($_SESSION['user']['member_state']) && (int)($_SESSION['user']['group']['id'] ?? 0) === 1) {
            $entities = $this->DB->MQ("select * from pm_members_tbl where active = 1", "all");
            if (is_array($entities) && count($entities) === 1) {
                $_SESSION['user']['member_state'] = $entities[0];
            }
        }
        $this->member_id = (int)($_SESSION['user']['member_state']['id'] ?? 0);
    }

    /**
     * Every reporting page and every delivery write belongs to ONE entity: the
     * one whose `account` list contains the signed-in user. An account that is
     * on no list has nothing to report, and used to get a silently empty page.
     */
    private function requireMember(){
        if ($this->member_id > 0) { return; }
        $this->setAnswer(403,
            "Your account is not linked to a reporting entity, so there is nothing to record here. "
            ."An administrator can add you under Division Users, in the Account field.");
        exit;
//        debug($this->member_id);
//        exit();
    }

    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = "pm_projects";
        $model["model"] = $this->model->get_table_fields("pm_projects");
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
        $data['meta_actions'] = $this->model->get_meta_actions("pm_projects");
        $data['meta_filters'] = $this->model->get_meta_filters("pm_projects");
        $this->addDeliveryFilter($data);
        $this->addVettingFilter($data);
        $data['model_name'] = "pm_projects";
        $data['fields'] = $this->model->get_list_fields($model);

        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        // Key is a model-defined column; the VALUE is raw
                        // request input, so it travels as a bound value.
                        $filters[] = ['sql' => $filter['sql'] ?? "AND `".$filter['key']."` = ?", 'value' => $this->query[$filter['key']] ?? ""];
                    }
                }
                $data['filter_data'][$filter['key']] = $this->query[$filter['key']] ?? "";
            }
        }

        if(is_set($data['fields'])){
//            $results = ;
            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??"", $filters));
        } else {
            $data['data'] = [];
        }

        $this->prepare_edit_mode();
        $data['reviews'] = allocation_reviews($this->DB, array_column((array)($data['data'] ?? []), 'id'));
        $data['gaps'] = activity_gaps_for($this->DB, array_column((array)($data['data'] ?? []), 'id'));
        $this->render($data);
    }

    public function add(){
        $data['model_name'] = "pm_projects";
        $data["model"] = $this->model->get_table_fields("pm_projects");
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
        $data['back'] = $this->backTo('projects/list');
        // Opened from a programme's "Add an activity" button: that programme
        // is preselected, with the objective and goal it belongs to, so the
        // three boxes agree and nothing is filed where nobody put it.
        if ((string)($this->query['from'] ?? '') === 'parent') {
            $programme = (int)($this->query['programme_id'] ?? 0);
            $row = $programme > 0 ? $this->DB->MQ("SELECT g.id, g.objective_id, o.pillar_id FROM pm_programmes_tbl g LEFT JOIN pm_objectives_tbl o ON o.id = g.objective_id WHERE g.id = ?", "one", [$programme]) : null;
            if (is_set($row)) {
                $data['data'] = ['programme_id' => (int)$row['id'], 'objective_id' => (int)$row['objective_id'], 'pillar_id' => (int)$row['pillar_id']];
                $data['filed_from_parent'] = true;
                // "Save and add another" and "Add another under this programme":
                // the one just saved is named at the top so the person knows
                // where they are in the run - only when it really sits under
                // this programme, so a guessed id names nothing.
                $saved = (int)($this->query['saved'] ?? 0);
                if ($saved > 0) {
                    $just = $this->DB->MQ("SELECT id, abbr, name FROM pm_projects_tbl WHERE id = ? AND programme_id = ?", "one", [$saved, (int)$row['id']]);
                    if (is_set($just)) { $data['saved'] = $just; }
                }
            }
        }
        $this->AddJS("/js/pm_projects.js");
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function add_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);

        $additional_tables = (array)($this->query['additional_tables'] ?? []);
        unset($this->query['additional_tables']);

        $back = (string)($this->query['back'] ?? ''); unset($this->query['back']);
        $deliberate = (string)($this->query['filed_from_parent'] ?? '') === '1';
        // "Save and add another": a form marker, not a column.
        $another = (string)($this->query['after_save'] ?? '') === 'another';
        unset($this->query['from'], $this->query['after_save']);
        $newTasks = [];
        if ($validated['tablename'] === 'pm_projects') {
            $this->normaliseParents($this->query);
            $blocking = $this->activityBlockers($this->query);
            // Same rule as the edit form: a row with something typed in it and
            // no name is a mistake to point at, not something to drop quietly.
            foreach ((array)($this->query['new_tasks'] ?? []) as $t) {
                if (!is_array($t)) { continue; }
                if (trim((string)($t['name'] ?? '')) !== '' || trim((string)($t['description'] ?? '')) === '') { continue; }
                $blocking[] = 'a name for every task';
                break;
            }
            if ($blocking) { $this->renderActivityForm('add', $this->query, $blocking, $back); }
            if (trim((string)($this->query['abbr'] ?? '')) === '') { $this->query['abbr'] = auto_wbs_code($this->DB, 'pm_projects', $this->query); }
            // Tasks typed on the add form; created right after the activity.
            foreach ((array)($this->query['new_tasks'] ?? []) as $t) {
                if (!is_array($t) || trim((string)($t['name'] ?? '')) === '') { continue; }
                $newTasks[] = ['name' => mb_substr(trim((string)$t['name']), 0, 250), 'description' => trim((string)($t['description'] ?? ''))];
            }
        }
        unset($this->query['new_tasks']);
        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            $new_id = $executed['common'];
            $id_part = "edit/".$new_id;
            foreach ($additional_tables as $add_tbl => $values){
                $values['project_id'] = $new_id;
                $executed = $this->model->add_data($add_tbl, $values);
            }
            if ($validated['tablename'] === 'pm_projects') {
                foreach ($newTasks as $t) {
                    $this->model->add_data('pm_projects_tasks', [
                        'project_id' => (int)$new_id, 'name' => $t['name'], 'description' => $t['description'],
                        'applies_to' => default_applies_to($this->DB, null),
                    ]);
                }
                $this->ensureDefaultTask((int)$new_id);   // only adds "Delivered" when there is still no task
                // What the form had suggested when this was saved; keeping it
                // is a confirmation, changing it is a correction to learn from.
                record_filing_feedback($this->DB, 'pm_projects', $this->query, [
                    'pillar_id'    => $this->query['suggest_pillar_id']    ?? 0,
                    'objective_id' => $this->query['suggest_objective_id'] ?? 0,
                    'programme_id' => $this->query['suggest_programme_id'] ?? 0,
                ], (int)$new_id, $deliberate);
                $this->checkPlacement((int)$new_id, $this->query);
                if ($another) {
                    // The same form again, under the same programme, with this
                    // one named at the top. Back still leads where the first
                    // form was opened from.
                    redirect($this->L('projects/add') . '?' . http_build_query([
                        'programme_id' => (int)($this->query['programme_id'] ?? 0), 'from' => 'parent',
                        'saved' => (int)$new_id, 'back' => $this->backTo('projects/list', $back),
                    ]));
                }
            }
            redirect($this->L("projects/".$id_part) . '?back=' . rawurlencode($this->backTo('projects/list', $back)));
        } else {
            $this->setAnswer(500, "Problem adding the entry.");
        }
    }

    public function edit(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $data['model_name'] = "pm_projects";
        $data["model"] = $this->model->get_table_fields("pm_projects");
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
        $data['meta_actions'] = $this->model->get_meta_actions("pm_projects");
        $data['data'] = $this->model->get_data("pm_projects", $validated['id']);
        // The AI's filing proposal (with Accept / Undo) and whatever is still
        // missing are shown on the form too, not only in the list.
        $data['review'] = allocation_reviews($this->DB, [(int)$validated['id']])[(int)$validated['id']] ?? null;
        $data['gaps'] = is_array($data['data']) ? activity_gaps($this->DB, $data['data']) : [];
        $data['tasks'] = $this->activityTasks((int)$validated['id']);
        $data['merges'] = merge_history($this->DB, (int)$validated['id'], (int)($this->query['merged'] ?? 0));
        $data['back'] = $this->backTo('projects/list');
        $this->AddJS("/js/pm_projects.js");
        $this->prepare_edit_mode();
        $this->render($data);
    }

    /**
     * The tasks of an activity, with what has been reported against each.
     *
     * Two counts, because they answer different questions. "reports" is every
     * row on the Progress page, delivered or not, and it is what decides
     * whether a task may be removed: reporting "not delivered" with a comment
     * is somebody's work too, and the row hangs off this task's id.
     * "deliveries" is only the ones marked delivered, and it is there so the
     * form can say what is actually on the task instead of calling a
     * not-delivered note a delivery.
     */
    private function activityTasks($projectId) {
        $projectId = (int)$projectId;
        if ($projectId <= 0) { return []; }
        return (array)$this->DB->MQ(
            "SELECT t.id, t.name, t.description,
                    (SELECT COUNT(*) FROM pm_progress_tasks_tbl d WHERE d.task_id = t.id) AS reports,
                    (SELECT COUNT(*) FROM pm_progress_tasks_tbl d WHERE d.task_id = t.id AND d.result = 1) AS deliveries
               FROM pm_projects_tasks_tbl t
              WHERE t.project_id = ?
              ORDER BY t.id", "all", [$projectId]);
    }

    /**
     * The task rows a person typed, applied when the activity is saved.
     *
     * Only ids that really belong to this activity are touched, because a
     * form is not the only thing that can post here. A row marked for removal
     * goes only if nothing has been recorded against it. applies_to is never
     * written: which entities a task counts for is decided elsewhere, and
     * overwriting it here would quietly drop the task off the Progress page.
     * Returns the ids that were kept despite being marked for removal.
     */
    private function applyTaskEdits($projectId, array $tasks, array $newTasks) {
        $projectId = (int)$projectId;
        if ($projectId <= 0) { return []; }
        $kept = [];
        foreach ($tasks as $id => $t) {
            $id = (int)$id;
            if ($id <= 0 || !is_array($t)) { continue; }
            $mine = $this->DB->MQ("SELECT id FROM pm_projects_tasks_tbl WHERE id = ? AND project_id = ?", "one", [$id, $projectId]);
            if (!is_set($mine)) { continue; }
            if ((string)($t['remove'] ?? '0') === '1') {
                // Any progress row at all, not only a delivery: a "not
                // delivered" note is a record of somebody looking, and
                // deleting the task would orphan it.
                $d = $this->DB->MQ("SELECT COUNT(*) AS n FROM pm_progress_tasks_tbl WHERE task_id = ?", "one", [$id]);
                if ((int)($d['n'] ?? 0) > 0) { $kept[] = $id; continue; }
                $this->DB->MQ("DELETE FROM pm_projects_tasks_tbl WHERE id = ? AND project_id = ?", false, [$id, $projectId]);
                continue;
            }
            $name = mb_substr(trim((string)($t['name'] ?? '')), 0, 250);
            if ($name === '') { continue; }   // refused before this point; never blanked here
            $this->DB->MQ("UPDATE pm_projects_tasks_tbl SET name = ?, description = ? WHERE id = ? AND project_id = ?", false,
                          [$name, trim((string)($t['description'] ?? '')), $id, $projectId]);
        }
        foreach ($newTasks as $t) {
            if (!is_array($t)) { continue; }
            $name = mb_substr(trim((string)($t['name'] ?? '')), 0, 250);
            if ($name === '') { continue; }
            $this->model->add_data('pm_projects_tasks', [
                'project_id'  => $projectId,
                'name'        => $name,
                'description' => trim((string)($t['description'] ?? '')),
                'applies_to'  => default_applies_to($this->DB, null),
            ]);
        }
        return $kept;
    }

    public function edit_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);

        $additional_tables = (array)($this->query['additional_tables'] ?? []);
        unset($this->query['additional_tables']);

        $previous = $this->DB->MQ("select * from ".$this->model->get_table_name($validated['tablename'])." where id=".(int)$validated['id'], "one");
        $back = (string)($this->query['back'] ?? ''); unset($this->query['back']);
        $postedTasks = []; $postedNewTasks = [];
        if ($validated['tablename'] === 'pm_projects') {
            $this->normaliseParents($this->query);
            $blocking = $this->activityBlockers($this->query);
            // A task the form is keeping must still have a name. Checked with
            // the rest, so the form comes back once with everything named.
            foreach ((array)($this->query['tasks'] ?? []) as $t) {
                if (!is_array($t) || (string)($t['remove'] ?? '0') === '1') { continue; }
                if (trim((string)($t['name'] ?? '')) === '') { $blocking[] = 'a name for every task'; break; }
            }
            // A new row with a description and no name used to be dropped on
            // save without a word, taking what was typed with it. An entirely
            // empty row is still just an unused row and is ignored.
            foreach ((array)($this->query['new_tasks'] ?? []) as $t) {
                if (!is_array($t)) { continue; }
                if (trim((string)($t['name'] ?? '')) !== '') { continue; }
                if (trim((string)($t['description'] ?? '')) === '') { continue; }
                if (!in_array('a name for every task', $blocking, true)) { $blocking[] = 'a name for every task'; }
                break;
            }
            if ($blocking) { $this->renderActivityForm('edit', $this->query, $blocking, $back); }
            if (array_key_exists('abbr', $this->query) && trim((string)$this->query['abbr']) === '') { $this->query['abbr'] = auto_wbs_code($this->DB, 'pm_projects', $this->query); }
            // Held back so they are not mistaken for columns of the activity.
            $postedTasks = (array)($this->query['tasks'] ?? []);
            $postedNewTasks = (array)($this->query['new_tasks'] ?? []);
        }
        unset($this->query['tasks'], $this->query['new_tasks']);
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $new_id = $validated['id'];
            $id_part = "edit/".$new_id;

            foreach ($additional_tables as $add_tbl => $values){
                $values['project_id'] = $new_id;
                if($previous['type']==$add_tbl){
                    $executed = $this->model->update_data($add_tbl, $values['id'], $values);
                } else {
                    $this->DB->MQ("delete from ".$this->model->get_table_name($previous['type'])." where project_id=".$validated['id']);
                    $executed = $this->model->add_data($add_tbl, $values);
                }
            }
            if ($validated['tablename'] === 'pm_projects') {
                // The tasks first: removing the last one leaves the activity
                // with none, and ensureDefaultTask then gives it "Delivered"
                // back rather than letting it fall off Progress entirely.
                $this->applyTaskEdits((int)$validated['id'], $postedTasks, $postedNewTasks);
                $this->ensureDefaultTask((int)$validated['id']);
                // Moving an activity is the clearest correction there is: the
                // place it sat in was wrong for this wording, whoever chose it.
                record_filing_feedback($this->DB, 'pm_projects', $this->query, $previous, (int)$validated['id']);
                $this->checkPlacement((int)$validated['id'], $this->query, is_array($previous) ? $previous : null);
            }
            redirect($this->L("projects/".$id_part) . '?back=' . rawurlencode($this->backTo('projects/list', $back)));
        }
    }

    /**
     * A safety net after a save, not a gate before it. When the programme
     * chosen for an activity scores well below where its wording points, a
     * "Check placement" row is recorded: the activity keeps the red band and
     * a note with Keep here / Move there until a person answers.
     *
     * The review table doubles as the ledger of what live still has (old_*),
     * which the export relies on, so an existing row's old_* is never
     * rewritten here. Rows under vetting, moves waiting to reach live, a
     * placement kept once, and a proposal a person has undone are left alone.
     * $previous is the row as it was before this save (edit only): what live
     * holds when no review row exists yet.
     */
    private function checkPlacement($id, array $row, $previous = null) {
        if (!allocation_review_available($this->DB)) { return; }
        $chosen = ['pillar_id' => (int)($row['pillar_id'] ?? 0), 'objective_id' => (int)($row['objective_id'] ?? 0), 'programme_id' => (int)($row['programme_id'] ?? 0)];
        if ($chosen['programme_id'] <= 0) { return; }
        $text = trim((string)($row['name'] ?? '') . '. ' . (string)($row['description'] ?? '') . ' ' . (string)($row['kpi'] ?? ''));
        if (mb_strlen($text) < 12) { return; }
        $current = $this->DB->MQ("SELECT abbr FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
        $curAbbr = (string)($current['abbr'] ?? '');
        $existing = $this->DB->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ?", "one", [$id]);
        $sitsAtOld = false; $isCheck = false;
        if (is_set($existing)) {
            $isCheck   = (string)$existing['confidence'] === 'check';
            $sitsAtOld = (int)$existing['old_programme_id'] === $chosen['programme_id'] && (int)$existing['old_objective_id'] === $chosen['objective_id']
                      && (string)$existing['old_abbr'] === $curAbbr;
            $sitsAtNew = (int)$existing['new_programme_id'] === $chosen['programme_id'] && (int)$existing['new_objective_id'] === $chosen['objective_id'];
            if ($existing['status'] === 'proposed' && !$isCheck) { return; }              // being vetted
            if (!$isCheck && !$sitsAtOld) { return; }                                     // a move not yet shipped to live
            if ($isCheck && $existing['status'] !== 'proposed' && $sitsAtNew) { return; } // kept once (new_* = the kept place)
            if ($isCheck && $existing['status'] === 'proposed' && !$sitsAtOld && !$sitsAtNew) {
                // The person moved it by hand while a check was open: their
                // choice answers the check; the row keeps old_* for the export.
                $this->DB->MQ("UPDATE pm_allocation_review_tbl SET new_pillar_id=?, new_objective_id=?, new_programme_id=?, new_abbr=?, status='accepted', decided_by=?, decided_at=NOW() WHERE id=?", false,
                    [$chosen['pillar_id'], $chosen['objective_id'], $chosen['programme_id'], $curAbbr, (int)($_SESSION['user']['user_id'] ?? 0), (int)$existing['id']]);
                return;
            }
        }
        $s = suggest_parent($this->DB, 'pm_projects', $text, 3, $id);
        $best = $s['candidates'][0] ?? null;
        $doubt = false;
        if ($best && !empty($s['confident']) && (int)$best['programme_id'] !== $chosen['programme_id']) {
            $bestScore   = (float)$best['score'];
            // The chosen place scored like a candidate: its objective plus its programme.
            $chosenScore = (float)($s['scores']['objective'][$chosen['objective_id']] ?? 0) + (float)($s['scores']['programme'][$chosen['programme_id']] ?? 0);
            $doubt = $bestScore > 0 && $chosenScore < 0.6 * $bestScore;
            // A proposal a person has already undone is not raised again.
            if ($doubt && is_set($existing) && $existing['status'] === 'reverted' && (int)$existing['new_programme_id'] === (int)$best['programme_id']) { $doubt = false; }
        }
        if (!$doubt) {
            // The wording and the place agree: an open check on this row is over.
            if (is_set($existing) && $isCheck && $existing['status'] === 'proposed' && $sitsAtOld) {
                $this->DB->MQ("DELETE FROM pm_allocation_review_tbl WHERE id = ?", false, [(int)$existing['id']]);
            }
            return;
        }
        $reason = 'Filed under ' . $this->placementLabel($chosen['objective_id'], $chosen['programme_id']) . '; the wording fits ' . (string)$best['label'] . ' better.';
        $new = [(int)$best['pillar_id'], (int)$best['objective_id'], (int)$best['programme_id'], mb_substr($reason, 0, 1000)];
        if (is_set($existing)) {
            // old_* untouched: it is what live has.
            $this->DB->MQ("UPDATE pm_allocation_review_tbl SET new_pillar_id=?, new_objective_id=?, new_programme_id=?, new_abbr='', confidence='check', reason=?, status='proposed', decided_by=0, decided_at=NULL WHERE id=?", false, array_merge($new, [(int)$existing['id']]));
        } else {
            // What live holds: the row before this save on an edit; on an add
            // the activity does not exist on live yet, and an empty old code
            // keeps it out of the export.
            $old = is_array($previous)
                 ? [(int)$previous['pillar_id'], (int)$previous['objective_id'], (int)$previous['programme_id'], (string)$previous['abbr']]
                 : [$chosen['pillar_id'], $chosen['objective_id'], $chosen['programme_id'], ''];
            $this->DB->MQ("INSERT INTO pm_allocation_review_tbl (project_id, old_pillar_id, old_objective_id, old_programme_id, old_abbr, new_pillar_id, new_objective_id, new_programme_id, new_abbr, confidence, reason, status)
                           VALUES (?,?,?,?,?,?,?,?,'','check',?,'proposed')", false, array_merge([$id], $old, $new));
        }
    }

    private function placementLabel($objectiveId, $programmeId) {
        $o = $this->DB->MQ("SELECT abbr FROM pm_objectives_tbl WHERE id = ?", "one", [(int)$objectiveId]);
        $p = $this->DB->MQ("SELECT abbr, name FROM pm_programmes_tbl WHERE id = ?", "one", [(int)$programmeId]);
        return trim((string)($o['abbr'] ?? '?') . ' / ' . (string)($p['abbr'] ?? '?') . ' ' . (string)($p['name'] ?? ''));
    }

    /** POST projects/allocation_move/<id>: a "check placement" answered with "move there" - the activity goes where the wording points. */
    public function allocation_move() {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        $r = $this->DB->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ? AND status = 'proposed' AND confidence = 'check'", "one", [$id]);
        if (!is_set($r)) { $this->setAnswer(404, "Nothing to move.", [], "json"); }
        $p = $this->DB->MQ("SELECT id, objective_id FROM pm_programmes_tbl WHERE id = ?", "one", [(int)$r['new_programme_id']]);
        if (!is_set($p) || (int)$p['objective_id'] !== (int)$r['new_objective_id']) { $this->setAnswer(409, "That programme no longer sits under that objective.", [], "json"); }
        $o = $this->DB->MQ("SELECT pillar_id FROM pm_objectives_tbl WHERE id = ?", "one", [(int)$r['new_objective_id']]);
        if (!is_set($o)) { $this->setAnswer(409, "That objective no longer exists.", [], "json"); }
        $pillar = (int)$o['pillar_id'];   // the goal follows the objective, as on every other write
        $code = auto_wbs_code($this->DB, 'pm_projects', ['programme_id' => (int)$r['new_programme_id']]);
        $this->DB->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = ?, abbr = ? WHERE id = ?", false,
            [$pillar, (int)$r['new_objective_id'], (int)$r['new_programme_id'], $code, $id]);
        // From here on it is an accepted move like any other: green, and exported.
        $this->DB->MQ("UPDATE pm_allocation_review_tbl SET new_pillar_id = ?, new_abbr = ?, confidence = 'agreed', status = 'accepted', decided_by = ?, decided_at = NOW() WHERE id = ?", false,
            [$pillar, $code, (int)($_SESSION['user']['user_id'] ?? 0), (int)$r['id']]);
        $row = $this->DB->MQ("SELECT name, description, kpi, pillar_id, objective_id, programme_id FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
        if (is_set($row)) { record_filing_feedback($this->DB, 'pm_projects', $row, ['pillar_id' => $pillar, 'objective_id' => (int)$r['new_objective_id'], 'programme_id' => (int)$r['new_programme_id']], $id); }
        $this->setAnswer(200, "Moved.", ['code' => $code, 'pending' => allocation_pending_count($this->DB)], "json");
    }

    /**
     * GET projects/search_suggest/<model>?q=: what the list search would find,
     * as you type. Two groups: rows of the list itself (a pick opens one) and
     * the parents a row can be filtered by (a pick sets that filter). Words
     * are ANDed the way the list search does; six rows and three parents at
     * most, in code order. Names and codes leave here, plus the passage of
     * the description for a row found through it.
     */
    public function search_suggest() {
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $model = (string)($this->parts['model'] ?? '');
        $q = trim((string)($this->query['q'] ?? ''));
        $terms = array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY), 0, 4);
        if ($terms === [] || mb_strlen($q) < 2) { $this->setAnswer(200, "OK", ['groups' => []], "json"); }
        $group = (int)($_SESSION['user']['group']['id'] ?? 0);
        $like = function (array $cols) use ($terms, &$bind) {
            $parts = [];
            foreach ($terms as $t) {
                $one = [];
                foreach ($cols as $c) { $one[] = "$c LIKE ?"; $bind[] = '%' . $t . '%'; }
                $parts[] = '(' . implode(' OR ', $one) . ')';
            }
            return implode(' AND ', $parts);
        };
        $groups = [];
        $order = coreModel::natural_order_sql('abbr');
        if ($model === 'pm_projects') {
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT p.id, p.abbr, p.name, p.description, g.abbr AS prg FROM pm_projects_tbl p LEFT JOIN pm_programmes_tbl g ON g.id = p.programme_id WHERE " . $like(['p.name', 'p.abbr', 'p.description']) . " ORDER BY " . coreModel::natural_order_sql('p.abbr') . " LIMIT 6", "all", $bind);
            $groups[] = ['label' => 'Activities', 'items' => array_map(function ($r) use ($q) {
                // Found through the description alone? Say so, with the passage.
                $why = search_match_snippet($r, $q);
                return ['id' => (int)$r['id'], 'label' => trim((string)$r['abbr'] . ' ' . (string)$r['name']), 'hint' => $why !== '' ? '' : (string)($r['prg'] ?? ''), 'why' => $why];
            }, $rows)];
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, abbr, name FROM pm_programmes_tbl WHERE " . $like(['name', 'abbr']) . " ORDER BY $order LIMIT 3", "all", $bind);
            $groups[] = ['label' => 'Programmes', 'items' => array_map(function ($r) { return ['label' => trim((string)$r['abbr'] . ' ' . (string)$r['name']), 'filter' => 'programme_id', 'value' => (int)$r['id']]; }, $rows)];
        } elseif ($model === 'pm_programmes') {
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, abbr, name, description FROM pm_programmes_tbl WHERE " . $like(['name', 'abbr', 'description']) . " ORDER BY $order LIMIT 6", "all", $bind);
            $groups[] = ['label' => 'Programmes', 'items' => array_map(function ($r) use ($q) { return ['id' => (int)$r['id'], 'label' => trim((string)$r['abbr'] . ' ' . (string)$r['name']), 'why' => search_match_snippet($r, $q)]; }, $rows)];
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, abbr, name FROM pm_objectives_tbl WHERE " . $like(['name', 'abbr']) . " ORDER BY $order LIMIT 3", "all", $bind);
            $groups[] = ['label' => 'Objectives', 'items' => array_map(function ($r) { return ['label' => trim((string)$r['abbr'] . ' ' . (string)$r['name']), 'filter' => 'objective_id', 'value' => (int)$r['id']]; }, $rows)];
        } elseif ($model === 'pm_objectives') {
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, abbr, name, description FROM pm_objectives_tbl WHERE " . $like(['name', 'abbr', 'description']) . " ORDER BY $order LIMIT 6", "all", $bind);
            $groups[] = ['label' => 'Objectives', 'items' => array_map(function ($r) use ($q) { return ['id' => (int)$r['id'], 'label' => trim((string)$r['abbr'] . ' ' . (string)$r['name']), 'why' => search_match_snippet($r, $q)]; }, $rows)];
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, name FROM pm_pillars_tbl WHERE " . $like(['name', 'abbr']) . " ORDER BY position LIMIT 3", "all", $bind);
            $groups[] = ['label' => 'Goals', 'items' => array_map(function ($r) { return ['label' => (string)$r['name'], 'filter' => 'pillar_id', 'value' => (int)$r['id']]; }, $rows)];
        } elseif ($model === 'pm_pillars') {
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, abbr, name FROM pm_pillars_tbl WHERE " . $like(['name', 'abbr', 'description']) . " ORDER BY position LIMIT 6", "all", $bind);
            $groups[] = ['label' => 'Goals', 'items' => array_map(function ($r) { return ['id' => (int)$r['id'], 'label' => trim((string)$r['name'])]; }, $rows)];
        } elseif ($model === 'core_users' && in_array($group, [1, 2], true)) {
            $bind = [];
            $rows = (array)$this->DB->MQ("SELECT id, username, givenname, sn FROM core_users_tbl WHERE " . $like(['username', 'givenname', 'sn']) . " ORDER BY sn, givenname LIMIT 6", "all", $bind);
            $groups[] = ['label' => 'Users', 'items' => array_map(function ($r) { return ['id' => (int)$r['id'], 'label' => trim((string)$r['givenname'] . ' ' . (string)$r['sn']) ?: (string)$r['username'], 'hint' => (string)$r['username']]; }, $rows)];
        } else {
            $this->setAnswer(404, "No such list", [], "json");
        }
        $groups = array_values(array_filter($groups, function ($g) { return !empty($g['items']); }));
        $this->setAnswer(200, "OK", ['groups' => $groups], "json");
    }

    /** GET projects/programme_context/<id>?exclude=<activity>: what a programme is for and what already sits under it, for the activity form. */
    public function programme_context() {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0); $exclude = (int)($this->query['exclude'] ?? 0);
        $p = $this->DB->MQ("SELECT g.id, g.abbr, g.name, g.description, o.abbr AS objective_abbr, o.name AS objective_name FROM pm_programmes_tbl g LEFT JOIN pm_objectives_tbl o ON o.id = g.objective_id WHERE g.id = ?", "one", [$id]);
        if (!is_set($p)) { $this->setAnswer(404, "No such programme", [], "json"); }
        $n = $this->DB->MQ("SELECT COUNT(*) AS n FROM pm_projects_tbl WHERE programme_id = ? AND id <> ?", "one", [$id, $exclude]);
        $rows = (array)$this->DB->MQ("SELECT id, abbr, name FROM pm_projects_tbl WHERE programme_id = ? AND id <> ? ORDER BY " . coreModel::natural_order_sql('abbr') . " LIMIT 6", "all", [$id, $exclude]);
        $this->setAnswer(200, "OK", [
            'id' => (int)$p['id'], 'label' => trim((string)$p['abbr'] . ' ' . (string)$p['name']),
            'objective' => trim((string)$p['objective_abbr'] . ' ' . (string)$p['objective_name']),
            'description' => mb_substr(trim(preg_replace('/\s+/', ' ', (string)$p['description'])), 0, 400),
            'count' => (int)($n['n'] ?? 0),
            'activities' => array_map(function ($r) { return ['id' => (int)$r['id'], 'abbr' => (string)$r['abbr'], 'name' => mb_substr((string)$r['name'], 0, 80)]; }, $rows),
        ], "json");
    }

    /**
     * The gaps that stop a save. An activity needs a name, a description and
     * a real goal / objective / programme chain before it counts anywhere;
     * the code is filled in automatically, so it never blocks.
     */
    private function activityBlockers(array $row) {
        $stop = ['name', 'description', 'goal', 'objective', 'programme', 'programme belongs to another objective', 'objective belongs to another goal'];
        return array_values(array_intersect(activity_gaps($this->DB, $row), $stop));
    }

    /** Show the add or edit form again with what was typed and what is missing, instead of saving. */
    private function renderActivityForm($mode, array $posted, array $errors, $back = '') {
        if (!headers_sent()) { http_response_code(422); }
        $data['model_name'] = "pm_projects";
        $data["model"] = $this->model->get_table_fields("pm_projects");
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
        $data['meta_actions'] = $this->model->get_meta_actions("pm_projects");
        $data['data'] = $posted;
        $data['form_errors'] = $errors;
        $data['back'] = $this->backTo('projects/list', $back);
        $data['filed_from_parent'] = (string)($posted['filed_from_parent'] ?? '') === '1';
        if ($mode === 'edit') {
            $id = (int)($posted['id'] ?? 0);
            $data['review'] = allocation_reviews($this->DB, [$id])[$id] ?? null;
            $data['gaps'] = $errors;
            // The task rows come back as they were typed, marks included, so a
            // refused save costs nothing that was entered.
            $data['tasks'] = $this->activityTasks($id);
            foreach ($data['tasks'] as &$t) {
                $p = $posted['tasks'][(int)$t['id']] ?? null;
                if (!is_array($p)) { continue; }
                if (array_key_exists('name', $p)) { $t['name'] = (string)$p['name']; }
                if (array_key_exists('description', $p)) { $t['description'] = (string)$p['description']; }
                $t['remove'] = (string)($p['remove'] ?? '0') === '1';
            }
            unset($t);
        }
        // render() picks the view from the routed action; the registry keeps
        // its url through __set, so the array is replaced whole.
        $url = $this->R->url; $url['action'] = ($mode === 'edit') ? 'edit' : 'add'; $this->R->url = $url;
        $this->AddJS("/js/pm_projects.js");
        $this->prepare_edit_mode();
        $this->render($data);
    }


    /**
     * pm_projects_tbl stores pillar_id and objective_id side by side, and the
     * overview joins on both at once: an activity whose goal disagrees with
     * its objective vanishes from every count with no error. The goal
     * therefore follows the objective. The programme is deliberately NOT
     * used to derive the objective: in this data a programme is a loose
     * grouping (many activities under objectives 2-5 sit in "1.x PRG"
     * programmes), and deriving from it re-parented forty seeded activities.
     */
    private function normaliseParents(array &$row) {
        $objective = (int)($row['objective_id'] ?? 0);
        if ($objective > 0) {
            $o = $this->DB->MQ("SELECT pillar_id FROM pm_objectives_tbl WHERE id = ?", "one", [$objective]);
            if (is_set($o)) { $row['pillar_id'] = (int)$o['pillar_id']; }
        }
    }

    /** The "Delivered" task an activity needs to be reported at all (library: ensure_default_task). */
    private function ensureDefaultTask($projectId) {
        ensure_default_task($this->DB, $projectId);
    }

    public function task(){
        $this->checkMethod("GET");
        $this->mapRoute("project_id/id");
        $this->checkRequired(["project_id","id"], $this->parts);
        $rules = [
            "project_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "pm_projects_tasks";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["project_id"=>$validated['project_id'], "user_id"=>$_SESSION['user']['user_id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function task_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        // A task counts only where it applies. Nothing chosen used to store
        // NULL, which hid the task from Progress and from every gauge.
        $this->query['applies_to'] = default_applies_to($this->DB, $this->query['applies_to'] ?? null);
        if($validated['id']!=""){
            $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $this->query);
        }

        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            redirect($this->L("projects/edit/".$this->query['project_id']));
        }
    }

    public function task_delete(){
        // POST only - see core::db_delete.
        $this->checkMethod("POST");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "pm_projects_tasks";
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function get_details(){
        $this->checkMethod("GET");
        $this->mapRoute("model/project_id");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "project_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['model_name'] = $validated['model'];
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
//        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
        $data['fields'] = $this->model->get_list_fields($model);
        $data['project_id'] = $validated['project_id'];

        if($validated['project_id']!=0) {
            $data['data'] = $this->DB->MQ("select * from ". $this->model->get_table_name($validated['model'])." where project_id=". $validated['project_id'], "all");
        }
        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function get_objectives(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $pillar = (int)($validated['id'] ?? 0);
        $data = $pillar > 0 ? (array)$this->DB->MQ("select id, name, abbr from pm_objectives_tbl where pillar_id = ? and active=1 order by " . coreModel::natural_order_sql('abbr'), "all", [$pillar]) : [];
        $this->setAnswer(200, "Got ".count($data)." objectives", $data, "json");
    }

    public function get_programmes(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $objective = (int)($validated['id'] ?? 0);
        $data = $objective > 0 ? (array)$this->DB->MQ("select id, name, abbr from pm_programmes_tbl where objective_id = ? and active=1 order by " . coreModel::natural_order_sql('abbr'), "all", [$objective]) : [];
        $this->setAnswer(200, "Got ".count($data)." programmes", $data, "json");
    }

    public function progress_list(){
        $this->requireMember();
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;


        $member = $this->member_id;

        $model['model_name'] = "pm_projects";
        $model["model"] = $this->model->get_table_fields("pm_projects");
        // Same model as the Projects list, but this is the reporting view -
        // title it the way the menu does, not "Projects / Interventions".
        $data['meta_name'] = "Progress";
        $data['meta_actions'] = $this->model->get_meta_actions("pm_projects");
        $data['meta_filters'] = $this->model->get_meta_filters("pm_projects");
        $this->addVettingFilter($data);

        $data['model_name'] = "pm_projects";
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        // Key is a model-defined column; the VALUE is raw
                        // request input, so it travels as a bound value.
                        $filters[] = ['sql' => $filter['sql'] ?? "AND `".$filter['key']."` = ?", 'value' => $this->query[$filter['key']] ?? ""];
                    }
                }
                $data['filter_data'][$filter['key']] = $this->query[$filter['key']] ?? "";
            }
        }
//
//        if(is_set($data['fields'])){
////            $results = ;
//            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??"", $filters));
//        } else {
//            $data['data'] = [];
//        }
        if(is_set($data['fields'])){
//            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??""));
            $query_tasks = "select * from ".$this->model->get_table_name('pm_projects_tasks')." where JSON_CONTAINS(applies_to, '\"".$member."\"') group by project_id";
            $member_tasks = $this->DB->MQ($query_tasks, "all");
            $tasks_ids = array_column($member_tasks, "project_id");
            if(count($tasks_ids)>0){
                $query_tasks = implode(",", $tasks_ids);
            } else {
                $query_tasks = 'NULL';
            }
            // One WHERE clause, built once, used by BOTH the count and the page
            // of rows. Previously the count ignored the search and the filter,
            // so a search for "cloud" still reported "Total of 55 entries" and
            // offered a page 2 that was empty.
            $where  = " where id in (".$query_tasks.") ";
            $params = [];

            // $data['search'] is FILTER_UNSAFE_RAW - raw user input. Bound.
            //
            // Same two rules as the Projects list (coreModel::get_list_data):
            // every word must match somewhere, rather than the whole phrase
            // matching one column, and the programme the activity sits under
            // is searched too, so "CPHIA" finds "Email reminders". This page
            // builds its own query because it is scoped to the tasks that
            // apply to the signed-in entity, so the rules live in both places;
            // the code column was also missing here, which meant a delivery
            // could not be found by its number at all.
            if(trim((string)$data['search']) !== ""){
                $terms = preg_split('/\s+/u', trim((string)$data['search']), -1, PREG_SPLIT_NO_EMPTY);
                foreach (array_slice($terms, 0, 6) as $term) {
                    $where .= "AND ((name like ?) OR (abbr like ?) OR (description like ?)"
                            . " OR (programme_id in (select `id` from `pm_programmes_tbl`"
                            . " where `abbr` like ? or `name` like ?))) ";
                    $params[] = "%".$term."%";
                    $params[] = "%".$term."%";
                    $params[] = "%".$term."%";
                    $params[] = "%".$term."%";
                    $params[] = "%".$term."%";
                }
            }
            // Each $filters entry already begins with "AND", so the old
            // implode(" OR ", ...) produced "AND a=? OR AND b=?" - a syntax
            // error the moment a second filter existed. Concatenate instead.
            if(count($filters)>0){
                $where .= implode(" ", array_column($filters, 'sql'))." ";
                foreach($filters as $f){ $params[] = $f['value']; }
            }

            $count_query = "select count(*) as total from ".$this->model->get_table_name($model['model_name']).$where;
            $data['count'] = $this->DB->MQ($count_query, "one", $params)['total'];

            $query = "select * from ".$this->model->get_table_name($model['model_name']).$where
                   . " order by " . coreModel::natural_order_sql('abbr')
                   . " limit " . (int)$items_per_page . " offset " . (((int)$page - 1) * (int)$items_per_page);

            $data['data'] = $this->DB->MQ($query, "all", $params);

            $data['items'] = $items_per_page;
            $data['page'] = $page;
        } else {
            $data['data'] = [];
        }
        $data['reviews'] = allocation_reviews($this->DB, array_column((array)$data['data'], 'id'));
        $data['gaps'] = activity_gaps_for($this->DB, array_column((array)$data['data'], 'id'));

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function progress_edit(){
        $this->requireMember();
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $member = $this->member_id;

        $data["model"] = [
            "common" => [
                "id" => [
                    "type" => "int",
                    "hidden" => true,
                    "no_update" => true
                ],
                "member_id" => [
                    "type" => "dropdown",
                    "title" => "Division user",
                    "values_from" => "db",
                    "link_to_table" => "pm_members_tbl",
                    "link_to_field" => "name",
                    "order_by" => "`name` asc",
                    "where_clause" => "",
                    "disabled" => true,
                    "no_update" => true
                ],
                "pillar_id" => [
                    "type" => "dropdown",
                    "title" => "Goal",
                    "values_from" => "db",
                    "link_to_table" => "pm_pillars_tbl",
                    "link_to_field" => "name",
                    "order_by" => "`name` asc",
                    "where_clause" => "",
                    "disabled" => true,
                    "no_update" => true
                ],
                "objective_id" => [
                    "type" => "dropdown",
                    "title" => "Objective",
                    "values_from" => "db",
                    "link_to_table" => "pm_objectives_tbl",
                    "link_to_field" => "abbr,name",
                    "order_by" => "`name` asc",
                    "where_clause" => "",
                    "disabled" => true,
                    "no_update" => true
                ],
                "programme_id" => [
                    "type" => "dropdown",
                    "title" => "Programme",
                    "values_from" => "db",
                    "link_to_table" => "pm_programmes_tbl",
                    "link_to_field" => "abbr,name",
                    "order_by" => "`name` asc",
                    "where_clause" => "",
                    "disabled" => true,
                    "no_update" => true
                ],
                "name" => [
                    "title" => "Project",
                    "type" => "varchar",
                    "order_field" => "ASC",
                    "disabled" => true,
                    "no_update" => true
                ],
                "abbr" => [
                    "type" => "varchar",
                    "title" => "Abbreviation",
                    "disabled" => true,
                    "no_update" => true
                ],
                "kpi" => [
                    "type" => "varchar",
                    "title" => "Expected task",
                    "disabled" => true,
                    "no_update" => true
                ],
                "type" => [
                    "hidden" => true,
                    "no_update" => true
                ]
            ]];


        $data['model_name'] = "pm_projects";
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
        $data['meta_actions'] = $this->model->get_meta_actions("pm_projects");
        $data['data'] = $this->model->get_data("pm_projects", $validated['id']) ?? [];
        if (!is_set($data['data'])) {
            $this->setAnswer(404, "There is no activity with that id.");
            exit;
        }
        // An activity added through the form has no type; every activity is
        // the task-reported kind. Without this the matrix lookup below passed
        // an empty model name and the page showed "Database unavailable".
        if (empty($data['data']['type'])) { $data['data']['type'] = 'pm_projects_tasks'; }
        $pm_matrix = readJSONFile(_MODELS_SETTINGS_PATH."pm_type_to_progress_matrix.json");
        $pm_progress = readJSONFile(_MODELS_SETTINGS_PATH.$pm_matrix[$data['data']['type']]['progress_file'].$this->S['db_master']['db_table_suffix'].".json");

        unset($pm_progress['fields']['id']);
        $data['matrix'] = $pm_matrix[$data['data']['type']];
        if($pm_matrix[$data['data']['type']]['include_fields']) {
            $data["model"]['common'] = array_merge($data["model"]['common'], $pm_progress['fields']);
        }

//        debug($data['model']);
//        exit();

        $query = "select * from ".$this->model->get_table_name($data['data']['type'])." where project_id='".$validated['id']."'";
        $prj_progress = $this->DB->MQ($query, "one");
        if(($pm_matrix[$data['data']['type']]['has_value'])&&($pm_matrix[$data['data']['type']]['include_fields'])){
            $data['model']['common']['value'][$pm_matrix[$data['data']['type']]['field']] = $prj_progress[$pm_matrix[$data['data']['type']]['value']];
        }

        $data['data']['member_id'] = $member;
        // Where Back goes: the list page, drill-down or search this was opened
        // from, kept through the save. Until now it was pinned to the Progress
        // list, so a delivery recorded from an activity's page or from page
        // three of a search landed somewhere else on the way back.
        $data['back'] = $this->backTo('projects/progress_list');
        $data['model_name'] = $pm_matrix[$data['data']['type']]['progress_file'];
        $query = "select * from ".$this->model->get_table_name($data['model_name'])." where project_id='".$validated['id']."' and member_id=".$member;
        $progress_data = $this->DB->MQ($query, "one") ?? [];
        if(!is_set($progress_data)){
            $progress_data = [];
        }
        if(($pm_matrix[$data['data']['type']]['has_value'])&&($pm_matrix[$data['data']['type']]['include_fields'])){
            unset($progress_data['id']);
            $data['data'] = array_merge($data['data'], $progress_data);
        }
        $data['data']['progress_date'] = $progress_data['progress_date'] ?? "";
        $data['data']['comment'] = $progress_data['comment'] ?? "";

        if($pm_matrix[$data['data']['type']]['load_extra']){
            $this->AddJS("/js/".$pm_matrix[$data['data']['type']]['js']);
        }
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function progress_edit_update(){
        $this->requireMember();
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $back = (string)($this->query['back'] ?? ''); unset($this->query['back']);
        $data = $this->query;
        // Only the sanitised, integer-cast ids reach the SQL below. The raw
        // POST id used to be interpolated straight into the WHERE clause.
        $data['member_id'] = (int)$this->member_id;
        $data['project_id'] = (int)$validated['id'];
        unset($data['id'], $data['csrf'], $data['tablename']);

        $query = "select * from ".$this->model->get_table_name($validated['tablename'])." where member_id=".(int)$data['member_id']." and project_id=".(int)$data['project_id'];
        $exists = $this->DB->MQ($query, "one");

        if(is_set($exists)){
            $executed = $this->model->update_data($validated['tablename'], $exists['id'],$data);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $data);
        }
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $new_id = (int)$validated['id'];
            $id_part = "progress_edit/".$new_id;

            redirect($this->L("projects/".$id_part) . '?back=' . rawurlencode($this->backTo('projects/progress_list', $back)));
        }
    }

    public function get_tasks_details(){
        $this->requireMember();
        $this->checkMethod("GET");
        $this->mapRoute("project_id");
        $this->checkRequired(["project_id"], $this->parts);
        $rules = [
            "project_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = 'pm_progress_tasks';

        $model['model_name'] = "pm_progress_tasks";
        $model["model"] = [
            "common" => [
                "project_id" => [
                    "type" => "int",
                    "hidden" => true,
                    "fetch_in_list" => true
                ],
                "member_id" => [
                    "type" => "int",
                    "hidden" => true,
                    "fetch_in_list" => true
                ],
                "name" => [
                    "title" => "Task",
                    "type" => "varchar",
                    "order_field" => "ASC",
                    "disabled" => true,
                    "no_update" => true,
                    "appear_in_list" => 0,
                    "list_width" => 40
                ],
                "result" => [
                    "type" => "dropdown",
                    "values_from" => "values_list",
                    "values_list" => [
                        "0" => "Not finished",
                        "1" => "Finished"
                    ],
                    "appear_in_list" => 1,
                    "list_width" => 10
                ]
            ]];
        $data['fields'] = $this->model->get_list_fields($model);
        if($validated['project_id']!=0) {
            $data['data'] = $this->DB->MQ("select * from ". $this->model->get_table_name('pm_projects_tasks')." where JSON_CONTAINS(applies_to, '\"".$this->member_id."\"') and project_id=". $validated['project_id'], "all");
            foreach ($data['data'] as $key=>$value){
                $data['data'][$key]['task_id'] = $value['id'];
                $data['data'][$key]['member_id'] = $this->member_id;
                $data['data'][$key]['project_id'] = $validated['project_id'];
                $result = $this->DB->MQ("select * from ". $this->model->get_table_name('pm_progress_tasks')." where project_id=". $data['data'][$key]['project_id']." and member_id=".$data['data'][$key]['member_id']. " and task_id=".$data['data'][$key]['task_id'] , "one");
                $data['data'][$key]['result'] = $result['result'] ?? 0;
            }
        }
        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function get_task_details(){
        $this->requireMember();
        $this->checkMethod("GET");
        $this->mapRoute("id/project_id/member_id");
        $this->checkRequired(["id", "project_id", "member_id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT,
            "project_id" => FILTER_SANITIZE_NUMBER_INT,
            "member_id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        // The entity is whoever the signed-in user reports for - not whatever
        // the URL says. With more than one division user the old form let any
        // reporter open (and save) another entity's delivery.
        $validated['member_id'] = $this->member_id;

        $data['model_name'] = "pm_progress_tasks";
        $data["model"] = [
            "common" => [
                "id" => [
                    "hidden" => true,
                    "no_update" => true
                ],
                "project_id" => [
                    "type" => "int",
                    "hidden" => true
                ],
                "member_id" => [
                    "type" => "int",
                    "hidden" => true
                ],
                "task_id" => [
                    "type" => "int",
                    "hidden" => true
                ],
                // One vocabulary everywhere: the task table says "Delivered /
                // Not delivered", the button says "Record delivery", so the
                // form does too (it used to say Task / Result / Finished).
                "task" => [
                    "title" => "Task",
                    "type" => "varchar",
                    "order_field" => "ASC",
                    "disabled" => true,
                    "no_update" => true,
                    "appear_in_list" => 0,
                    "list_width" => 50
                ],
                "description" => [
                    "title" => "Description",
                    "type" => "text",
                    "disabled" => true,
                    "no_update" => true,
                    "no_editor" => true
                ],
                "result" => [
                    "title" => "Delivery status",
                    "type" => "dropdown",
                    "values_from" => "values_list",
                    "values_list" => [
                          "0" => "Not delivered",
                          "1" => "Delivered"
                    ]
                ],
                "actual_budget" => [
                    "type" => "double",
                    "title" => "Spend recorded with this delivery, USD (optional)",   // the activity's own Actual budget lives on its edit form
                    "no_editor" => true
                ],
                "comment" => [
                    "title" => "Comment (optional)",
                    "type" => "text",
                    "no_editor" => true
                ],
                "progress_date" => [
                    "type" => "datetime",
                    "title" => "Date delivered"
                ]
            ]];
        $task = $this->DB->MQ("select * from ". $this->model->get_table_name('pm_projects_tasks')." where project_id=". (int)$validated['project_id'] ." and id=".(int)$validated['id'], "one");
        $values = $this->DB->MQ("select * from ". $this->model->get_table_name('pm_progress_tasks')." where project_id=". (int)$validated['project_id']." and member_id=".(int)$validated['member_id']." and task_id=".(int)$validated['id'], "one");
        $data['data'] = [
            "task" => $task['name'],
            "description" => $task['description'],
            "task_id" => $validated['id'],
            "member_id" => $validated['member_id'],
            "project_id" => $validated['project_id']
        ];
        if(is_set($values)){
            $data['data'] = array_merge($data['data'], $values);
        }

        $this->prepare_edit_mode();
        $this->partial_render($data);
    }

    public function task_progress_update(){
        $this->requireMember();
        $this->checkMethod("POST");
        $back = (string)($this->query['back'] ?? ''); unset($this->query['back']);
        $rules = [
            "project_id" => FILTER_SANITIZE_NUMBER_INT,
            "member_id" => FILTER_SANITIZE_NUMBER_INT,
            "task_id" => FILTER_SANITIZE_NUMBER_INT,
            "result" => FILTER_SANITIZE_NUMBER_INT,
            "actual_budget" => FILTER_UNSAFE_RAW,
            "comment" => FILTER_UNSAFE_RAW,
            "progress_date" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        // As in get_task_details: the entity comes from the session, so a
        // posted member_id cannot record delivery for someone else.
        $validated['member_id'] = $this->member_id;

        // actual_budget is optional. It was previously interpolated bare, so
        // leaving the field blank produced "VALUES (..., )" - a syntax error on
        // every tick that did not also record spend. NULL when empty.
        $spend = normalise_number($validated['actual_budget'] ?? '');
        $actual_budget = ($spend !== '') ? (float)$spend : null;

        // progress_date and comment are FILTER_UNSAFE_RAW, i.e. raw request
        // input, and db_esc() is only addslashes(). Bind everything instead.
        // $actual_budget is already a float or null from the block above.
        $keys = [(int)$validated['member_id'], (int)$validated['project_id'], (int)$validated['task_id']];

        $query  = "SELECT * FROM `pm_progress_tasks_tbl` WHERE `member_id` = ? AND `project_id` = ? AND `task_id` = ?";
        $result = $this->DB->MQ($query, "one", $keys);

        if(is_set($result)){
            $query = "UPDATE `pm_progress_tasks_tbl`
                         SET `result` = ?, `progress_date` = ?, `actual_budget` = ?, `comment` = ?
                       WHERE `member_id` = ? AND `project_id` = ? AND `task_id` = ?";
            $executed = $this->DB->MQ($query, false, array_merge(
                [(int)$validated['result'], $validated['progress_date'], $actual_budget, $validated['comment']],
                $keys
            ));
        } else {
            $query = "INSERT INTO `pm_progress_tasks_tbl`
                        (`member_id`, `project_id`, `result`, `task_id`, `progress_date`, `comment`, `actual_budget`)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $executed = $this->DB->MQ($query, false, [
                (int)$validated['member_id'], (int)$validated['project_id'], (int)$validated['result'],
                (int)$validated['task_id'], $validated['progress_date'], $validated['comment'], $actual_budget,
            ]);
        }

        if (!$executed) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            // ?saved=1 lets progress_edit show a confirmation once; the router
            // routes on the path only, so the query string is harmless to it.
            // Back rides along: the redirect makes the form its own referer.
            redirect($this->L("projects/progress_edit/".(int)$validated['project_id']."?saved=1&back=" . rawurlencode($this->backTo('projects/progress_list', $back))));
        }
    }


    /**
     * While tools/allocate-imported.php has left moves waiting for a person,
     * the lists get a "Vetting" filter: pending / accepted / undone. It
     * disappears once nothing is pending, so it never becomes furniture.
     */
    /**
     * Merging activities (library.php, "Merging activities").
     *   GET  projects/merge?ids=3,7        the merge page
     *   POST projects/merge_update         merge them
     *   POST projects/merge_undo/<log id>  put back the newest merge into an activity
     * Content editors only (groups 1 and 2), as for vetting.
     */
    public function merge() {
        $this->checkMethod("GET");
        if (!can_vet() || !merge_available($this->DB)) { $this->setAnswer(404, "Merging activities is not available here."); }
        $page = $this->mergePage($this->mergeIds($this->query['ids'] ?? ''), (int)($this->query['keep'] ?? 0));
        $page['back'] = $this->backTo('projects/list');
        $this->prepare_edit_mode();
        $this->render($page);
    }

    public function merge_update() {
        $this->checkMethod("POST");
        if (!can_vet() || !merge_available($this->DB)) { $this->setAnswer(404, "Merging activities is not available here."); }
        $ids  = $this->mergeIds($this->query['ids'] ?? []);
        $keep = (int)($this->query['keep'] ?? 0);
        $back = is_array($this->query['back'] ?? null) ? '' : (string)($this->query['back'] ?? '');
        $posted = ['keep' => $keep];
        foreach (['name', 'description', 'kpi', 'estimated_budget', 'actual_budget', 'notes'] as $f) {
            $posted[$f] = is_array($this->query[$f] ?? null) ? '' : (string)($this->query[$f] ?? '');
        }
        $page = $this->mergePage($ids, $keep, $posted);
        $errors = $page['problems'];
        if (!$errors) {
            if (!isset($page['by_id'][$keep])) { $errors[] = 'Choose the activity to keep.'; }
            if (trim($posted['name']) === '') { $errors[] = 'The merged activity needs a name.'; }
            if (trim($posted['description']) === '') { $errors[] = 'The merged activity needs a description.'; }
            if (!hash_equals((string)$page['fingerprint'], is_array($this->query['fingerprint'] ?? null) ? '' : (string)($this->query['fingerprint'] ?? ''))) {
                $errors[] = 'One of these activities changed after this page was opened. Check what is shown now, then merge.';
            }
        }
        if ($errors) {
            if (!headers_sent()) { http_response_code(422); }
            $page['form_errors'] = $errors;
            $page['back'] = $this->backTo('projects/list', $back);
            $url = $this->R->url; $url['action'] = 'merge'; $this->R->url = $url;
            $this->prepare_edit_mode();
            $this->render($page);
        }
        $logId = $this->mergeApply($page['by_id'], $keep, $posted);
        redirect($this->L('projects/edit/' . $keep) . '?' . http_build_query(['merged' => $logId, 'back' => $this->backTo('projects/list', $back)]));
    }

    public function merge_undo() {
        $this->checkMethod("POST");
        $this->mapRoute("id");
        if (!can_vet() || !merge_available($this->DB)) { $this->setAnswer(404, "Merging activities is not available here.", [], "json"); }
        $log = $this->DB->MQ("SELECT * FROM pm_merge_log_tbl WHERE id = ? AND undone_at IS NULL", "one", [(int)($this->parts['id'] ?? 0)]);
        if (!is_set($log)) { $this->setAnswer(404, "That merge has already been undone, or there is no such merge.", [], "json"); }
        $keep = (int)$log['project_id'];
        if (is_set($this->DB->MQ("SELECT id FROM pm_merge_log_tbl WHERE project_id = ? AND undone_at IS NULL AND id > ? LIMIT 1", "one", [$keep, (int)$log['id']]))) {
            $this->setAnswer(409, "A later merge into this activity has to be undone first.", [], "json");
        }
        $snap = json_decode((string)$log['snapshot'], true);
        if (!is_array($snap) || (int)($snap['version'] ?? 0) !== 1 || !isset($snap['kept'], $snap['kept_after'], $snap['merged'], $snap['tasks'])
            || !is_array($snap['merged']) || !is_array($snap['tasks'])) {
            $this->setAnswer(409, "The record of this merge cannot be read, so it cannot be undone safely.", [], "json");
        }
        if (!is_set($this->DB->MQ("SELECT id FROM pm_projects_tbl WHERE id = ?", "one", [$keep]))) {
            $this->setAnswer(409, "The merged activity no longer exists, so there is nothing to undo the merge from.", [], "json");
        }
        // Everything that has to come back must be free to, and everything
        // that has to move back must still be where the merge put it.
        $mergedIds = [];
        foreach ($snap['merged'] as $m) {
            $id = (int)($m['id'] ?? 0);
            $label = trim((string)($m['abbr'] ?? '') . ' ' . (string)($m['name'] ?? ''));
            if ($id <= 0 || is_set($this->DB->MQ("SELECT id FROM pm_projects_tbl WHERE id = ?", "one", [$id]))) {
                $this->setAnswer(409, 'Another activity now has the place of "' . $label . '", so it cannot be put back.', [], "json");
            }
            $code = trim((string)($m['abbr'] ?? ''));
            if ($code !== '') {
                $holder = $this->DB->MQ("SELECT id, name FROM pm_projects_tbl WHERE abbr = ? LIMIT 1", "one", [$code]);
                if (is_set($holder)) { $this->setAnswer(409, 'The code ' . $code . ' is now used by "' . $holder['name'] . '". Give that activity another code, then undo the merge.', [], "json"); }
            }
            $mergedIds[] = $id;
        }
        foreach ($snap['tasks'] as $t) {
            $cur = $this->DB->MQ("SELECT project_id FROM pm_projects_tasks_tbl WHERE id = ?", "one", [(int)($t['id'] ?? 0)]);
            if (!is_set($cur)) { $this->setAnswer(409, 'The task "' . (string)($t['name_after'] ?? '') . '" was removed after the merge, so the activities cannot be put back as they were.', [], "json"); }
            if ((int)$cur['project_id'] !== $keep) { $this->setAnswer(409, 'The task "' . (string)($t['name_after'] ?? '') . '" now belongs to another activity, so the merge cannot be undone.', [], "json"); }
        }
        $in = function (array $a) { return implode(',', array_fill(0, count($a), '?')); };
        $safe = function (array $cols) { return array_values(array_filter($cols, function ($c) { return is_string($c) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $c); })); };
        $projectCols = $safe(array_column((array)$this->DB->MQ("SHOW COLUMNS FROM pm_projects_tbl", "all"), 'Field'));

        $this->DB->txBegin();
        foreach ($snap['merged'] as $m) {
            $use = array_values(array_intersect($projectCols, array_keys($m)));
            $this->DB->MQ("INSERT INTO pm_projects_tbl (`" . implode('`, `', $use) . "`) VALUES (" . $in($use) . ")", false,
                          array_map(function ($c) use ($m) { return $m[$c]; }, $use));
        }
        // A task goes back to its activity; its name and description go back
        // only if nobody has changed them since.
        foreach ($snap['tasks'] as $t) {
            $this->DB->MQ("UPDATE pm_projects_tasks_tbl
                              SET name = IF(name <=> ?, ?, name), description = IF(description <=> ?, ?, description), project_id = ?
                            WHERE id = ? AND project_id = ?", false,
                          [$t['name_after'], $t['name'], $t['description_after'], $t['description'], (int)$t['project_id'], (int)$t['id'], $keep]);
        }
        // Deliveries: the ones that moved, and any recorded since on a task that goes back.
        $moved = [];
        foreach ((array)($snap['progress'] ?? []) as $p) { $moved[(int)$p['project_id']][] = (int)$p['id']; }
        foreach ($moved as $orig => $pids) {
            $this->DB->MQ("UPDATE pm_progress_tasks_tbl SET project_id = ? WHERE project_id = ? AND id IN (" . $in($pids) . ")", false, array_merge([$orig, $keep], $pids));
        }
        $tasksOf = [];
        foreach ($snap['tasks'] as $t) { if ((int)$t['project_id'] !== $keep) { $tasksOf[(int)$t['project_id']][] = (int)$t['id']; } }
        foreach ($tasksOf as $orig => $tids) {
            $this->DB->MQ("UPDATE pm_progress_tasks_tbl SET project_id = ? WHERE project_id = ? AND task_id IN (" . $in($tids) . ")", false, array_merge([$orig, $keep], $tids));
        }
        if ($this->mergeTableExists('pm_import_rows_tbl')) {
            foreach ((array)($snap['imports'] ?? []) as $r) {
                foreach (['match_project_id', 'nearest_project_id', 'result_project_id'] as $col) {
                    if (in_array((int)($r[$col] ?? 0), $mergedIds, true)) {
                        $this->DB->MQ("UPDATE pm_import_rows_tbl SET `$col` = ? WHERE id = ? AND `$col` = ?", false, [(int)$r[$col], (int)$r['id'], $keep]);
                    }
                }
            }
        }
        if (allocation_review_available($this->DB) && !empty($snap['reviews'])) {
            $reviewCols = $safe(array_column((array)$this->DB->MQ("SHOW COLUMNS FROM pm_allocation_review_tbl", "all"), 'Field'));
            foreach ((array)$snap['reviews'] as $rv) {
                $use = array_values(array_intersect($reviewCols, array_keys((array)$rv)));
                if (!$use) { continue; }
                $this->DB->MQ("INSERT IGNORE INTO pm_allocation_review_tbl (`" . implode('`, `', $use) . "`) VALUES (" . $in($use) . ")", false,
                              array_map(function ($c) use ($rv) { return $rv[$c]; }, $use));
            }
        }
        // The kept activity's own fields, each only if it still holds what the merge wrote.
        foreach (['name', 'description', 'kpi', 'estimated_budget', 'actual_budget', 'notes'] as $f) {
            if (!array_key_exists($f, $snap['kept_after']) || !array_key_exists($f, $snap['kept'])) { continue; }
            $this->DB->MQ("UPDATE pm_projects_tbl SET `$f` = ? WHERE id = ? AND `$f` <=> ?", false, [$snap['kept'][$f], $keep, $snap['kept_after'][$f]]);
        }
        if ($this->mergeTableExists('pm_embeddings_tbl')) {
            $all = array_merge([$keep], $mergedIds);
            $this->DB->MQ("DELETE FROM pm_embeddings_tbl WHERE kind = 'activity' AND ref_id IN (" . $in($all) . ")", false, $all);
        }
        $this->DB->MQ("UPDATE pm_merge_log_tbl SET undone_by = ?, undone_at = NOW() WHERE id = ?", false, [(int)($_SESSION['user']['user_id'] ?? 0), (int)$log['id']]);
        $this->DB->txCommit();
        $this->setAnswer(200, "Undone.", ['project_id' => $keep, 'restored' => $mergedIds], "json");
    }

    /** "3,7" or ids[]=3&ids[]=7: distinct positive ids, in the order given. */
    private function mergeIds($raw) {
        $ids = [];
        foreach ((is_array($raw) ? $raw : explode(',', (string)$raw)) as $p) {
            if (is_scalar($p) && preg_match('/^\s*\d{1,10}\s*$/', (string)$p) && (int)$p > 0) { $ids[(int)$p] = (int)$p; }
        }
        return array_values($ids);
    }

    private function mergeTableExists($name) {
        static $seen = [];
        if (!array_key_exists($name, $seen)) { $seen[$name] = is_set($this->DB->MQ("SHOW TABLES LIKE ?", "one", [$name])); }
        return $seen[$name];
    }

    /** The chosen activities with their programme, tasks and what is recorded against them. */
    private function mergeActivities(array $ids) {
        if (!$ids) { return []; }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ((array)$this->DB->MQ("SELECT p.*, g.abbr AS programme_abbr, g.name AS programme_name FROM pm_projects_tbl p LEFT JOIN pm_programmes_tbl g ON g.id = p.programme_id WHERE p.id IN ($in)", "all", $ids) as $r) {
            $r['tasks'] = []; $r['other_rows'] = 0; $r['reports'] = 0; $r['deliveries'] = 0;
            $out[(int)$r['id']] = $r;
        }
        if (!$out) { return []; }
        $keys = array_keys($out);
        $in = implode(',', array_fill(0, count($keys), '?'));
        foreach ((array)$this->DB->MQ("SELECT id, project_id, name, description FROM pm_projects_tasks_tbl WHERE project_id IN ($in) ORDER BY id", "all", $keys) as $t) {
            $t['after'] = merge_task_name($t, $out[(int)$t['project_id']]);
            $out[(int)$t['project_id']]['tasks'][] = $t;
        }
        foreach ((array)$this->DB->MQ("SELECT project_id, COUNT(*) AS n, SUM(result = 1) AS d FROM pm_progress_tasks_tbl WHERE project_id IN ($in) GROUP BY project_id", "all", $keys) as $c) {
            $out[(int)$c['project_id']]['reports'] = (int)$c['n'];
            $out[(int)$c['project_id']]['deliveries'] = (int)$c['d'];
        }
        // Activities reported another way (dates, milestones, percentages) are not merged.
        foreach (['pm_projects_dates_tbl', 'pm_projects_milestones_tbl', 'pm_projects_percentages_tbl', 'pm_progress_dates_tbl', 'pm_progress_milestones_tbl', 'pm_progress_percentages_tbl'] as $tbl) {
            if (!$this->mergeTableExists($tbl)) { continue; }
            foreach ((array)$this->DB->MQ("SELECT project_id, COUNT(*) AS n FROM `$tbl` WHERE project_id IN ($in) GROUP BY project_id", "all", $keys) as $c) {
                $out[(int)$c['project_id']]['other_rows'] += (int)$c['n'];
            }
        }
        return $out;
    }

    /** What the merge page shows, and what merge_update checks against. */
    private function mergePage(array $ids, $keep = 0, array $posted = []) {
        $acts = $this->mergeActivities(array_slice($ids, 0, 11));
        $problems = [];
        if (count($ids) < 2) { $problems[] = 'Choose at least two activities to merge.'; }
        if (count($ids) > 10) { $problems[] = 'Merge up to ten activities at a time.'; }
        if (count($acts) < min(count($ids), 11)) { $problems[] = 'Some of the chosen activities no longer exist. Go back and choose again.'; }
        foreach ($acts as $a) {
            if (!in_array((string)($a['type'] ?? ''), ['', 'pm_projects_tasks'], true) || (int)$a['other_rows'] > 0) {
                $problems[] = trim($a['abbr'] . ' ' . $a['name']) . ' is not reported through tasks, so it cannot be merged.';
            }
        }
        uasort($acts, function ($x, $y) { return strnatcmp((string)$x['abbr'], (string)$y['abbr']) ?: ((int)$x['id'] <=> (int)$y['id']); });
        if (!isset($acts[(int)$keep])) { $keep = $acts ? (int)array_key_first($acts) : 0; }
        $ordered = $acts ? [(int)$keep => $acts[(int)$keep]] + $acts : [];
        $parts = [];
        $sorted = $acts; ksort($sorted);
        foreach ($sorted as $id => $a) {
            $parts[] = [(int)$id, (string)$a['abbr'], (string)$a['name'], (int)$a['programme_id'], (int)$a['reports'],
                        array_map(function ($t) { return [(int)$t['id'], (string)$t['name']]; }, $a['tasks'])];
        }
        return [
            'model_name' => 'pm_projects', 'meta_name' => $this->model->get_meta_name('pm_projects'),
            'activities' => array_values($ordered), 'by_id' => $acts, 'keep' => (int)$keep, 'posted' => $posted,
            'suggested' => $ordered ? merge_suggestion(array_values($ordered)) : [],
            'placements_differ' => count(array_unique(array_map(function ($a) { return (int)$a['programme_id']; }, $acts))) > 1,
            'problems' => $problems, 'form_errors' => [],
            'fingerprint' => sha1((string)json_encode($parts)),
        ];
    }

    /** Merge the activities into $keep, record what changed, and return the record's id. */
    private function mergeApply(array $acts, $keep, array $posted) {
        $keep = (int)$keep;
        $others = array_values(array_filter(array_map('intval', array_keys($acts)), function ($id) use ($keep) { return $id !== $keep; }));
        $all = array_merge([$keep], $others);
        $in = function (array $a) { return implode(',', array_fill(0, count($a), '?')); };
        $user = (int)($_SESSION['user']['user_id'] ?? 0);
        $who = $this->DB->MQ("SELECT username, givenname, sn FROM core_users_tbl WHERE id = ?", "one", [$user]);
        $whoName = is_set($who) ? (trim((string)$who['givenname'] . ' ' . (string)$who['sn']) ?: (string)$who['username']) : '';
        $num = function ($v) { $v = normalise_number((string)$v); return $v === '' ? null : (float)$v; };
        $after = [
            'name'             => mb_substr(trim($posted['name']), 0, 255),
            'description'      => trim($posted['description']),
            'kpi'              => trim($posted['kpi']) === '' ? null : mb_substr(trim($posted['kpi']), 0, 255),
            'estimated_budget' => $num($posted['estimated_budget']),
            'actual_budget'    => $num($posted['actual_budget']),
            'notes'            => trim($posted['notes']) === '' ? null : trim($posted['notes']),
        ];
        $rows = [];
        foreach ((array)$this->DB->MQ("SELECT * FROM pm_projects_tbl WHERE id IN (" . $in($all) . ")", "all", $all) as $r) { $rows[(int)$r['id']] = $r; }
        $tasks = [];
        foreach ((array)$this->DB->MQ("SELECT id, project_id, name, description FROM pm_projects_tasks_tbl WHERE project_id IN (" . $in($all) . ") ORDER BY id", "all", $all) as $t) {
            $from = $rows[(int)$t['project_id']];
            $descAfter = $t['description'];
            // A task that arrives from another activity says which, where it says nothing else.
            if ((int)$t['project_id'] !== $keep && trim((string)$descAfter) === '' && trim((string)$from['abbr']) !== '') { $descAfter = 'From activity ' . trim((string)$from['abbr']) . '.'; }
            $tasks[] = ['id' => (int)$t['id'], 'project_id' => (int)$t['project_id'], 'name' => $t['name'], 'description' => $t['description'],
                        'name_after' => merge_task_name($t, $from), 'description_after' => $descAfter];
        }
        $progress = (array)$this->DB->MQ("SELECT id, project_id, task_id FROM pm_progress_tasks_tbl WHERE project_id IN (" . $in($others) . ")", "all", $others);
        $reviews = allocation_review_available($this->DB) ? (array)$this->DB->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id IN (" . $in($others) . ")", "all", $others) : [];
        $imports = $this->mergeTableExists('pm_import_rows_tbl')
            ? (array)$this->DB->MQ("SELECT id, match_project_id, nearest_project_id, result_project_id FROM pm_import_rows_tbl WHERE match_project_id IN (" . $in($others) . ") OR nearest_project_id IN (" . $in($others) . ") OR result_project_id IN (" . $in($others) . ")", "all", array_merge($others, $others, $others))
            : [];
        $snapshot = json_encode([
            'version' => 1, 'merged_by_name' => $whoName,
            'kept' => $rows[$keep], 'kept_after' => $after,
            'merged' => array_map(function ($id) use ($rows) { return $rows[$id]; }, $others),
            'tasks' => $tasks, 'progress' => $progress, 'reviews' => $reviews, 'imports' => $imports,
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($snapshot) || $snapshot === '') { $this->setAnswer(500, "The merge could not be recorded, so nothing was changed."); }

        $this->DB->txBegin();
        foreach ($tasks as $t) {
            $this->DB->MQ("UPDATE pm_projects_tasks_tbl SET project_id = ?, name = ?, description = ? WHERE id = ?", false, [$keep, $t['name_after'], $t['description_after'], $t['id']]);
        }
        $this->DB->MQ("UPDATE pm_progress_tasks_tbl SET project_id = ? WHERE project_id IN (" . $in($others) . ")", false, array_merge([$keep], $others));
        if ($imports) {
            foreach (['match_project_id', 'nearest_project_id', 'result_project_id'] as $col) {
                $this->DB->MQ("UPDATE pm_import_rows_tbl SET `$col` = ? WHERE `$col` IN (" . $in($others) . ")", false, array_merge([$keep], $others));
            }
        }
        if ($reviews) { $this->DB->MQ("DELETE FROM pm_allocation_review_tbl WHERE project_id IN (" . $in($others) . ")", false, $others); }
        if ($this->mergeTableExists('pm_embeddings_tbl')) { $this->DB->MQ("DELETE FROM pm_embeddings_tbl WHERE kind = 'activity' AND ref_id IN (" . $in($all) . ")", false, $all); }
        foreach ($others as $id) {
            // The same audit record a delete leaves (coreModel::delete_data), saying where it went.
            $this->DB->MQ("INSERT INTO core_table_logs_tbl (tablename, record, log_date, user) VALUES (?, ?, ?, ?)", false,
                          ['pm_projects_tbl', json_encode($rows[$id] + ['merged_into' => $keep], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), date('Y-m-d H:i:s'), (string)($_SESSION['user']['username'] ?? '')]);
        }
        $this->DB->MQ("DELETE FROM pm_projects_tbl WHERE id IN (" . $in($others) . ")", false, $others);
        $this->DB->MQ("UPDATE pm_projects_tbl SET name = ?, description = ?, kpi = ?, estimated_budget = ?, actual_budget = ?, notes = ? WHERE id = ?", false,
                      [$after['name'], $after['description'], $after['kpi'], $after['estimated_budget'], $after['actual_budget'], $after['notes'], $keep]);
        $logId = (int)$this->DB->MQ("INSERT INTO pm_merge_log_tbl (project_id, merged_ids, snapshot, merged_by) VALUES (?, ?, ?, ?)", "last",
                                    [$keep, implode(',', $others), $snapshot, $user]);
        $this->DB->txCommit();
        $this->ensureDefaultTask($keep);
        return $logId;
    }

    /**
     * "Delivered or not" on the Projects list, by the overview's arithmetic
     * (library: activity_delivery_groups): Delivered has every task recorded
     * for every entity it applies to, Partly delivered some of them, Not
     * delivered none. The ids are worked out here, as integers; the person's
     * choice is the one bound value every filter carries.
     */
    private function addDeliveryFilter(array &$data) {
        $g = activity_delivery_groups($this->DB);
        $list = function (array $ids) { return $ids ? implode(',', $ids) : '0'; };   // no activity has id 0: IN (0) is nothing
        $done = $list($g['delivered']); $some = $list($g['partly']);
        $data['meta_filters'][] = [
            'title'       => 'Delivery',
            'key'         => 'delivered',
            'type'        => 'dropdown',
            'values_from' => 'values_list',
            'values_list' => ['1' => 'Delivered', '2' => 'Partly delivered', '0' => 'Not delivered'],
            'all_label'   => 'Delivered or not',
            'sql'         => "AND (CASE ? WHEN '1' THEN `id` IN (" . $done . ") WHEN '2' THEN `id` IN (" . $some . ")"
                           . " ELSE `id` NOT IN (" . $done . ") AND `id` NOT IN (" . $some . ") END)",
        ];
    }

    private function addVettingFilter(array &$data) {
        // Unfinished activities are marked on their rows (flag + tag); there
        // is no filter for them by choice.
        // Only while something waits for a person: accepted and undone rows
        // look like any other activity, on the local copy and on live alike.
        $pending = allocation_pending_count($this->DB);
        if ($pending === 0) { return; }
        $data['meta_filters'][] = [
            'title'       => 'Vetting (' . $pending . ' pending)',
            'key'         => 'review',
            'type'        => 'dropdown',
            'values_from' => 'values_list',
            'values_list' => ['proposed' => 'Pending (' . $pending . ')'],
            'all_label'   => 'Everything',
            'sql'         => "AND `id` IN (SELECT `project_id` FROM `pm_allocation_review_tbl` WHERE `status` = ?)",
        ];
    }

    /** POST projects/allocation_accept/<id>: a person confirms where the activity now sits. */
    public function allocation_accept() {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        $review = allocation_review_available($this->DB) ? $this->DB->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ?", "one", [$id]) : null;
        if (!is_set($review)) { $this->setAnswer(404, "No move is recorded for that activity.", [], "json"); exit; }
        $row = $this->DB->MQ("SELECT name, description, kpi, pillar_id, objective_id, programme_id, abbr FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
        if ((string)$review['confidence'] === 'check') {
            // "Keep here": the wording's pointer (new_*) was wrong for this
            // text and the kept place is right - a correction the guesser
            // learns from. The row then records the kept place as new_*, so
            // the same question is not asked again; old_* stays what live has.
            if (is_set($row)) { record_filing_feedback($this->DB, 'pm_projects', $row, ['pillar_id' => (int)$review['new_pillar_id'], 'objective_id' => (int)$review['new_objective_id'], 'programme_id' => (int)$review['new_programme_id']], $id); }
            $this->DB->MQ("UPDATE pm_allocation_review_tbl SET new_pillar_id = ?, new_objective_id = ?, new_programme_id = ?, new_abbr = ?, status = 'accepted', decided_by = ?, decided_at = NOW() WHERE project_id = ?", false,
                          [(int)($row['pillar_id'] ?? 0), (int)($row['objective_id'] ?? 0), (int)($row['programme_id'] ?? 0), (string)($row['abbr'] ?? ''), (int)($_SESSION['user']['user_id'] ?? 0), $id]);
            $this->setAnswer(200, "Kept.", ['pending' => allocation_pending_count($this->DB)], "json");
        }
        $this->DB->MQ("UPDATE pm_allocation_review_tbl SET status = 'accepted', decided_by = ?, decided_at = NOW() WHERE project_id = ?", false,
                      [(int)($_SESSION['user']['user_id'] ?? 0), $id]);
        // A confirmation for the matcher: the new place was right for this wording.
        if (is_set($row)) { record_filing_feedback($this->DB, 'pm_projects', $row, ['pillar_id' => $row['pillar_id'], 'objective_id' => $row['objective_id'], 'programme_id' => $row['programme_id']], $id); }
        $this->setAnswer(200, "Accepted.", ['pending' => allocation_pending_count($this->DB)], "json");
    }

    /** POST projects/allocation_revert/<id>: put the activity back where it was; the matcher learns from that. */
    public function allocation_revert() {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        $review = allocation_review_available($this->DB) ? $this->DB->MQ("SELECT * FROM pm_allocation_review_tbl WHERE project_id = ?", "one", [$id]) : null;
        if (!is_set($review)) { $this->setAnswer(404, "No move is recorded for that activity.", [], "json"); exit; }
        $this->DB->MQ("UPDATE pm_projects_tbl SET pillar_id = ?, objective_id = ?, programme_id = ?, abbr = ? WHERE id = ?", false,
                      [(int)$review['old_pillar_id'], (int)$review['old_objective_id'], (int)$review['old_programme_id'], (string)$review['old_abbr'], $id]);
        $this->DB->MQ("UPDATE pm_allocation_review_tbl SET status = 'reverted', decided_by = ?, decided_at = NOW() WHERE project_id = ?", false,
                      [(int)($_SESSION['user']['user_id'] ?? 0), $id]);
        // The proposed place was wrong for this wording: a correction the matcher learns from.
        $row = $this->DB->MQ("SELECT name, description, kpi, pillar_id, objective_id, programme_id FROM pm_projects_tbl WHERE id = ?", "one", [$id]);
        if (is_set($row)) { record_filing_feedback($this->DB, 'pm_projects', $row, ['pillar_id' => $review['new_pillar_id'], 'objective_id' => $review['new_objective_id'], 'programme_id' => $review['new_programme_id']], $id); }
        $this->setAnswer(200, "Put back.", ['pending' => allocation_pending_count($this->DB)], "json");
    }

    /** POST projects/allocation_accept_all: everything still pending is accepted at once, after the person has looked. */
    public function allocation_accept_all() {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        if (!allocation_review_available($this->DB)) { $this->setAnswer(404, "Nothing to accept.", [], "json"); exit; }
        $this->DB->MQ("UPDATE pm_allocation_review_tbl SET status = 'accepted', decided_by = ?, decided_at = NOW() WHERE status = 'proposed'", false,
                      [(int)($_SESSION['user']['user_id'] ?? 0)]);
        $this->setAnswer(200, "All pending moves accepted.", ['pending' => 0], "json");
    }
}
