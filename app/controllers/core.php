<?php
class coreController extends protectedController{

    public function __construct(Registry $registry) {
        parent::__construct($registry);
        require_once _MODELS_PATH."core.php";
        $this->model = new coreModel($registry);
        $this->update_redirect = $this->update_redirect();
    }

    public function index(){
    }

    public function json_edit(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $model_file_common = _JSON_MODELS_PATH."table.".$validated['model'].".json";
        $model_file_languages = _JSON_MODELS_PATH."table.".$validated['model']."_languages.json";
        $data_file = _JSON_MODELS_PATH."data.".$validated['model'].".json";

        $model = [];

        if(file_exists($model_file_common)) {
            $model["common"] = readJSONFile($model_file_common);
        }
        if(file_exists($model_file_languages)){
            foreach ($this->R->languages as $language=>$properties){
                $model["languages"][$language] = readJSONFile($model_file_languages);
            }
        }
        $data['model_name'] = $validated['model'];
        $data['model'] = $model;
        $data['data'] = readJSONFile($data_file);
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function json_update(){
        $this->checkMethod("POST");

        $values = $this->R->url['query'];
        $table = $values['tablename'];
        unset($values['tablename']);

        $json_table = fopen(_JSON_MODELS_PATH."data.".$table.".json", "w") or die(__FILE__." Unable to open json_models/data.".$table.".json");
        fwrite($json_table, json_encode($values));
        fclose($json_table);
        redirect($this->L("core/json_edit/".$table));
    }

    public function db_list(){
        $this->checkMethod("GET");
        $this->mapRoute("model/page");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "model" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $this->refuseSwitchedOff($validated['model']);
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
        // Objectives whose proposed unit still waits for a person can be listed
        // on their own, as the activities waiting on a move can.
        if ($validated['model'] === 'pm_objectives' && ($pending = unit_pending_count($this->DB)) > 0) {
            $data['meta_filters'][] = [
                'title'       => 'Unit vetting',
                'key'         => 'unit_review',
                'type'        => 'dropdown',
                'values_from' => 'values_list',
                'values_list' => ['proposed' => 'Pending (' . $pending . ')'],
                'all_label'   => 'Everything',
                'sql'         => "AND `id` IN (SELECT `objective_id` FROM `pm_unit_review_tbl` WHERE `status` = ?) AND IFNULL(`unit_id`, 0) = 0",
            ];
        }

        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    // An empty value ("?pillar_id=" from a trimmed link) is no filter:
                    // bound as = '' it emptied the list with nothing to say why.
                    if (!is_array($this->query[$filter['key']]) && (string)$this->query[$filter['key']] !== '' && $this->query[$filter['key']] != '%') {
                        // Key is a model-defined column; the VALUE is raw
                        // request input, so it travels as a bound value.
                        // A filter may bring its own clause (Programmes filter by goal
                        // through their objective); the VALUE is always bound.
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
        if ($validated['model'] === 'pm_objectives') {
            $data['unit_reviews'] = unit_reviews($this->DB, array_column((array)($data['data'] ?? []), 'id'));
            $data['unit_pending'] = can_vet() ? unit_pending_count($this->DB) : 0;
        }

        $this->prepare_edit_mode();
        $this->render($data);
    }

    /**
     * POST core/unit_accept/<objective id>: a person confirms the unit proposed
     * for an objective. core/unit_dismiss/<id>: not this one - the objective
     * stays without a unit until someone picks one on its form.
     * core/unit_accept_all: every agreed proposal still waiting. None of them
     * writes over a unit a person has already set.
     */
    public function unit_accept() { $this->unitDecide('accept'); }
    public function unit_dismiss() { $this->unitDecide('dismiss'); }

    public function unit_accept_all() {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        if (!can_vet() || !unit_review_available($this->DB)) { $this->setAnswer(404, "Nothing to accept.", [], "json"); }
        $user = (int)($_SESSION['user']['user_id'] ?? 0);
        $rows = (array)$this->DB->MQ("SELECT r.objective_id, r.unit_id FROM pm_unit_review_tbl r
                                        JOIN pm_objectives_tbl o ON o.id = r.objective_id
                                        JOIN pm_units_tbl u ON u.id = r.unit_id
                                       WHERE r.status = 'proposed' AND r.agreement IN ('agreed', 'majority') AND IFNULL(o.unit_id, 0) = 0", "all");
        foreach ($rows as $r) {
            $this->DB->MQ("UPDATE pm_objectives_tbl SET unit_id = ? WHERE id = ? AND IFNULL(unit_id, 0) = 0", false, [(int)$r['unit_id'], (int)$r['objective_id']]);
            $this->DB->MQ("UPDATE pm_unit_review_tbl SET status = 'accepted', decided_by = ?, decided_at = NOW() WHERE objective_id = ? AND status = 'proposed'", false, [$user, (int)$r['objective_id']]);
        }
        $this->setAnswer(200, count($rows) . " accepted.", ['accepted' => count($rows), 'pending' => unit_pending_count($this->DB)], "json");
    }

    private function unitDecide($how) {
        $this->checkMethod("POST");
        $this->enforceCSRF();
        $this->mapRoute("id");
        $id = (int)($this->parts['id'] ?? 0);
        if (!can_vet() || !unit_review_available($this->DB)) { $this->setAnswer(404, "Nothing to decide.", [], "json"); }
        $r = $this->DB->MQ("SELECT r.unit_id, r.agreement, IFNULL(o.unit_id, 0) AS current_unit_id, u.id AS unit_exists
                              FROM pm_unit_review_tbl r JOIN pm_objectives_tbl o ON o.id = r.objective_id LEFT JOIN pm_units_tbl u ON u.id = r.unit_id
                             WHERE r.objective_id = ? AND r.status = 'proposed'", "one", [$id]);
        if (!is_set($r)) { $this->setAnswer(404, "No unit is waiting to be confirmed for that objective.", [], "json"); }
        if ((int)$r['current_unit_id'] > 0) { $this->setAnswer(409, "That objective already has a unit. Change it on its form.", [], "json"); }
        $user = (int)($_SESSION['user']['user_id'] ?? 0);
        if ($how === 'accept') {
            // unit_exists is a number, not a row: is_set() answers for arrays only.
            if ((int)$r['unit_id'] <= 0 || (int)($r['unit_exists'] ?? 0) <= 0 || !in_array((string)$r['agreement'], ['agreed', 'majority'], true)) {
                $this->setAnswer(409, "No unit was agreed for that objective. Choose one on its form.", [], "json");
            }
            $this->DB->MQ("UPDATE pm_objectives_tbl SET unit_id = ? WHERE id = ? AND IFNULL(unit_id, 0) = 0", false, [(int)$r['unit_id'], $id]);
        }
        $this->DB->MQ("UPDATE pm_unit_review_tbl SET status = ?, decided_by = ?, decided_at = NOW() WHERE objective_id = ? AND status = 'proposed'", false,
                      [$how === 'accept' ? 'accepted' : 'dismissed', $user, $id]);
        $this->setAnswer(200, $how === 'accept' ? "Accepted." : "Dismissed.", ['pending' => unit_pending_count($this->DB)], "json");
    }

    /**
     * Content > Units and the unit vetting table are switched off unless
     * UNITS_ENABLED=true: their lists, forms, saves and deletes answer as if
     * they did not exist (library.php units_enabled()).
     */
    private function refuseSwitchedOff($model, $json = false) {
        if (!in_array(strtolower((string)$model), ['pm_units', 'pm_unit_review'], true) || units_enabled()) { return; }
        if ($json) { $this->setAnswer(404, "Units are switched off.", [], "json"); }
        $this->setAnswer(404, "The page you asked for does not exist.");
    }

    public function db_view(){
        $this->render();
    }

    public function db_add(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $this->refuseSwitchedOff($validated['model']);

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        // Opened from a parent's "Add a ..." button: the parent arrives in the
        // query and is preselected, so the child is never filed under a parent
        // nobody picked. Only this model's own parent dropdowns may be preset.
        $data['data'] = $this->presetFromParent($data['model']);
        $data['child'] = $this->model->get_meta_child($validated['model']);
        $data['back'] = $this->backTo('core/db_list/' . $validated['model']);
        $this->AutoInclude($this->model->get_includes($validated['model'], "add"));
        $this->prepare_edit_mode();
        $this->render($data);
    }

    /**
     * The parent a "Add a ..." button carried over, as prepopulated values.
     * Empty unless the link said so (from=parent), and never a column this
     * model does not own or that is not a parent dropdown.
     */
    private function presetFromParent(array $modelFields) {
        if ((string)($this->query['from'] ?? '') !== 'parent') { return []; }
        $common = (array)($modelFields['common'] ?? []);
        $preset = [];
        foreach ($this->query as $field => $value) {
            if (!is_string($field) || !array_key_exists($field, $common)) { continue; }
            // A parent link, not any dropdown: "active" is a dropdown too, and
            // presetting it would mark the form as filed from a parent when
            // no parent came with it.
            if (($common[$field]['type'] ?? '') !== 'dropdown') { continue; }
            if (($common[$field]['values_from'] ?? '') !== 'db') { continue; }
            if ((int)$value > 0) { $preset[$field] = (int)$value; }
        }
        return $preset;
    }

    public function db_add_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $this->refuseSwitchedOff($validated['tablename']);
        // Activities are saved on their own form (projects/add_update and
        // projects/edit_update), which keeps goal, objective and programme
        // consistent; this generic save would skip that.
        if ($validated['tablename'] === 'pm_projects') { $this->setAnswer(409, "Activities are saved on their own form: Projects / Interventions."); }
        if ($validated['tablename'] === 'pm_projects_tasks') {
            // A task applies to every active reporting entity unless chosen otherwise (see library.php).
            $this->query['applies_to'] = default_applies_to($this->DB, $this->query['applies_to'] ?? null);
        }
        if (in_array($validated['tablename'], ['pm_objectives', 'pm_programmes'], true)
            && array_key_exists('abbr', $this->query) && trim((string)$this->query['abbr']) === '') {
            // An empty code is numbered from where the row sits (library.php auto_wbs_code).
            $this->query['abbr'] = auto_wbs_code($this->DB, $validated['tablename'], $this->query);
        }
        // Form markers, not columns: what the wording had suggested is read
        // below, the rest only steers where the save lands.
        $deliberate = (string)($this->query['filed_from_parent'] ?? '') === '1';
        $addChild   = (string)($this->query['after_save'] ?? '') === 'child';
        $back       = (string)($this->query['back'] ?? '');
        unset($this->query['filed_from_parent'], $this->query['after_save'], $this->query['from'], $this->query['back']);
        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            record_filing_feedback($this->DB, $validated['tablename'], $this->query, [
                'pillar_id'    => $this->query['suggest_pillar_id']    ?? 0,
                'objective_id' => $this->query['suggest_objective_id'] ?? 0,
                'programme_id' => $this->query['suggest_programme_id'] ?? 0,
            ], (int)$executed['common'], $deliberate);
            // "Save and add a programme": on to the child's form, with this
            // row as its parent.
            $child = $this->model->get_meta_child($validated['tablename']);
            if ($addChild && $child) {
                $href = child_add_href($child, (int)$executed['common']);
                if ($href !== '') { redirect($this->L($href)); }
            }
            $id_part = ($this->update_redirect=="db_edit") ? "/".$executed['common'] : "";
            redirect($this->L("core/".$this->update_redirect."/".$validated['tablename'].$id_part) . '?back=' . rawurlencode($this->backTo('core/db_list/' . $validated['tablename'], $back)));
        } else {
            $this->setAnswer(500, "Problem adding the entry.");
        }
    }

    public function db_edit(){
        $this->checkMethod("GET");
        $this->mapRoute("model/id");
        $this->checkRequired(["model", "id"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $this->refuseSwitchedOff($validated['model']);

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['child'] = $this->model->get_meta_child($validated['model']);
        $data['back'] = $this->backTo('core/db_list/' . $validated['model']);
        $data['data'] = $this->model->get_data($validated['model'], $validated['id']);
        if ($validated['model'] === 'pm_objectives') {
            $data['unit_review'] = unit_reviews($this->DB, [(int)$validated['id']])[(int)$validated['id']] ?? null;
        }
        $this->AutoInclude($this->model->get_includes($validated['model'], "edit"));
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function db_edit_update(){
        $this->checkMethod("POST");
        $deliberate = (string)($this->query['filed_from_parent'] ?? '') === '1';
        $back       = (string)($this->query['back'] ?? '');
        unset($this->query['filed_from_parent'], $this->query['after_save'], $this->query['from'], $this->query['back']);
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $this->refuseSwitchedOff($validated['tablename']);
        // Activities are saved on their own form (projects/add_update and
        // projects/edit_update), which keeps goal, objective and programme
        // consistent; this generic save would skip that.
        if ($validated['tablename'] === 'pm_projects') { $this->setAnswer(409, "Activities are saved on their own form: Projects / Interventions."); }
        if ($validated['tablename'] === 'pm_projects_tasks') {
            // A task applies to every active reporting entity unless chosen otherwise (see library.php).
            $this->query['applies_to'] = default_applies_to($this->DB, $this->query['applies_to'] ?? null);
        }
        if (in_array($validated['tablename'], ['pm_objectives', 'pm_programmes'], true)
            && array_key_exists('abbr', $this->query) && trim((string)$this->query['abbr']) === '') {
            // An empty code is numbered from where the row sits (library.php auto_wbs_code).
            $this->query['abbr'] = auto_wbs_code($this->DB, $validated['tablename'], $this->query);
        }
        // Where the row sat before the save: moving it is the correction the
        // matcher learns from (read from the row, never from the browser).
        $previous = [];
        if (in_array($validated['tablename'], ['pm_objectives', 'pm_programmes', 'pm_projects'], true)) {
            $previous = (array)$this->DB->MQ("SELECT * FROM " . $this->model->get_table_name($validated['tablename'])
                                           . " WHERE id = ?", "one", [(int)$validated['id']]);
        }
        // The save and whatever has to follow it (activities under a moved
        // objective or programme) are one transaction.
        $this->DB->txBegin();
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->DB->txRollBack();
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            // An objective moved to another goal, or a programme to another
            // objective, takes its activities with it (library.php).
            children_follow_parent($this->DB, (string)$validated['tablename'], (int)$validated['id'], $previous);
            $this->DB->txCommit();
            record_filing_feedback($this->DB, $validated['tablename'], $this->query, $previous, (int)$validated['id'], $deliberate);
            $id_part = ($this->update_redirect=="db_edit") ? "/".$validated['id'] : "";
            redirect($this->L("core/".$this->update_redirect."/".$validated['tablename'].$id_part) . '?back=' . rawurlencode($this->backTo('core/db_list/' . $validated['tablename'], $back)));
        }
    }

    /**
     * The next code for a new objective / programme / activity, so the add
     * form can fill the abbreviation in as the parent is chosen.
     * GET core/next_code/<model>/<parent id>  ->  {"code": "16.1.6"}
     */
    public function next_code(){
        $this->checkMethod("GET");
        $this->mapRoute("model/parent");
        $model  = (string)($this->parts['model'] ?? '');
        $parent = (int)($this->parts['parent'] ?? 0);
        if (!in_array($model, ['pm_objectives', 'pm_programmes', 'pm_projects'], true)) {
            $this->setAnswer(404, "Unknown model.", [], "json");
            exit;
        }
        $row = ($model === 'pm_programmes') ? ['objective_id' => $parent] : (($model === 'pm_projects') ? ['programme_id' => $parent] : []);
        $this->setAnswer(200, "OK", ["code" => auto_wbs_code($this->DB, $model, $row)], "json");
    }

    /**
     * GET core/suggest_parent/<model>?text=...&exclude=<row id>
     *   -> {"candidates": [{pillar_id, objective_id, programme_id, label, score}, ...], "confident": true}
     * Where an item with these words belongs, best first; see
     * suggest_parent() in library.php. Read-only, hence a GET. The forms
     * call it as the name and description are typed.
     */
    public function suggest_parent(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $model = (string)($this->parts['model'] ?? '');
        if (!in_array($model, ['pm_objectives', 'pm_programmes', 'pm_projects'], true)) {
            $this->setAnswer(404, "Unknown model.", [], "json");
            exit;
        }
        $text    = mb_substr(trim((string)($this->query['text'] ?? '')), 0, 4000);
        $exclude = (int)($this->query['exclude'] ?? 0);
        $this->setAnswer(200, "OK", suggest_parent($this->DB, $model, $text, 3, $exclude), "json");
    }

    public function db_delete() {
        // POST only: as a GET, an <img src="/core/db_delete/pm_projects/5"> on
        // any page an administrator opened deleted the row.
        $this->checkMethod("POST");
        $this->mapRoute("model/id");
        $this->checkRequired(["model", "id"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $this->refuseSwitchedOff($validated['model'], true);
        // A goal, objective, programme, unit or reporting entity that still has
        // something under it, or a task with delivery records, is not deleted:
        // what hangs off it would point at nothing and drop out of every count.
        // The answer says what is still there, so it can be moved first.
        $blocker = ($validated['model'] === 'pm_projects_tasks') ? task_delete_blocker($this->DB, (int)$validated['id'])
                 : parent_delete_blocker($this->DB, (string)$validated['model'], (int)$validated['id']);
        if ($blocker !== '') { $this->setAnswer(409, $blocker, [], "json"); }
        if ($validated['model'] === 'pm_projects') {
            // An activity takes its tasks, delivery records, dates, milestones,
            // percentages and pending filing proposals with it (library.php,
            // activity_children_delete): deleting the row alone left them
            // behind. One transaction: a failure part-way leaves everything.
            if (!is_set($this->DB->MQ("SELECT id FROM pm_projects_tbl WHERE id = ?", "one", [(int)$validated['id']]))) {
                $this->setAnswer(404, "No such activity - it may have been deleted already.", [], "json");
            }
            $this->DB->txBegin();
            $gone = activity_children_delete($this->DB, (int)$validated['id']);
            $executed = $this->model->delete_data($validated['model'], $validated['id']);
            if (in_array('false', $executed, true) || !$executed) {
                $this->DB->txRollBack();
                $this->setAnswer(500, "Problem deleting the activity - nothing was removed.", [], "json");
            }
            $this->DB->txCommit();
            $this->setAnswer(200, "Deleted activity <b>" . (int)$validated['id'] . "</b> with " . $gone['tasks'] . " task(s) and " . $gone['deliveries'] . " delivery record(s).", $gone, "json");
        }
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function password_update(){
        $this->checkMethod("POST");
        // Was `debug($_REQUEST)`, which echoed the submitted password in
        // plaintext into the response body. The endpoint is not implemented;
        // answer honestly instead of leaking the request.
        $this->setAnswer(501, "Password update is not implemented.");
    }

	protected function _sanitizeNumericals() {
		foreach ($this->_numericalValues as $value) {
			if (isset($this->query[$value])) {
				$val = str_replace(',', '.', $this->query[$value]);
				$this->query[$value] = filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, array(
					'flags'=>FILTER_FLAG_ALLOW_FRACTION));
			}
		}
	}

	protected function _getCountryByID($id) {
		$countries = readJSONFile(_JSON_MODELS_PATH."countries_".$this->lang.".json");
		foreach ($countries as $country) {
			if ($country['id'] == $id) {
				return $country['name'];
			}
		}
		return '';
	}

	protected function _getCountryByShort($short) {
		$countries = readJSONFile(_JSON_MODELS_PATH."countries_".$this->lang.".json");
		foreach ($countries as $country) {
			if ($country['alpha2'] == $short) {
				return $country['name'];
			}
		}
		return '';
	}
}