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
        $this->render($data);
    }

    public function add(){
        $data['model_name'] = "pm_projects";
        $data["model"] = $this->model->get_table_fields("pm_projects");
        $data['meta_name'] = $this->model->get_meta_name("pm_projects");
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

        if ($validated['tablename'] === 'pm_projects') {
            $this->normaliseParents($this->query);
            if (trim((string)($this->query['abbr'] ?? '')) === '') { $this->query['abbr'] = auto_wbs_code($this->DB, 'pm_projects', $this->query); }
        }
        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            $new_id = $executed['common'];
            $id_part = "edit/".$new_id;
            foreach ($additional_tables as $add_tbl => $values){
                $values['project_id'] = $new_id;
                $executed = $this->model->add_data($add_tbl, $values);
            }
            if ($validated['tablename'] === 'pm_projects') { $this->ensureDefaultTask((int)$new_id); }
            redirect($this->L("projects/".$id_part));
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
        $this->AddJS("/js/pm_projects.js");
        $this->prepare_edit_mode();
        $this->render($data);
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
        if ($validated['tablename'] === 'pm_projects') {
            $this->normaliseParents($this->query);
            if (array_key_exists('abbr', $this->query) && trim((string)$this->query['abbr']) === '') { $this->query['abbr'] = auto_wbs_code($this->DB, 'pm_projects', $this->query); }
        }
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
            if ($validated['tablename'] === 'pm_projects') { $this->ensureDefaultTask((int)$validated['id']); }
            redirect($this->L("projects/".$id_part));
        }
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

    /**
     * An activity is reported through its tasks: with none it is missing from
     * Progress and counts for nothing on the overview. Every seeded activity
     * has exactly one task, "Delivered", applying to every reporting entity;
     * an activity added or saved through the form gets the same when it has
     * none.
     */
    private function ensureDefaultTask($projectId) {
        $projectId = (int)$projectId;
        if ($projectId <= 0) { return; }
        $project = $this->DB->MQ("SELECT id, abbr, name, type FROM pm_projects_tbl WHERE id = ?", "one", [$projectId]);
        if (!is_set($project)) { return; }
        $type = (string)($project['type'] ?? '');
        if ($type !== '' && $type !== 'pm_projects_tasks') { return; }
        if ($type === '') {
            // The add form leaves the type empty; every seeded activity is the
            // task-reported kind, and the progress pages pick their view by it.
            $this->DB->MQ("UPDATE pm_projects_tbl SET type = 'pm_projects_tasks' WHERE id = ?", false, [$projectId]);
        }
        $has = $this->DB->MQ("SELECT COUNT(*) AS n FROM pm_projects_tasks_tbl WHERE project_id = ?", "one", [$projectId]);
        if ((int)($has['n'] ?? 0) > 0) { return; }
        $ids = [];
        foreach ((array)$this->DB->MQ("SELECT id FROM pm_members_tbl WHERE active = 1", "all") as $m) { $ids[] = (string)(int)$m['id']; }
        if (!$ids) { return; }
        $this->DB->MQ("INSERT INTO pm_projects_tasks_tbl (project_id, name, description, applies_to) VALUES (?, 'Delivered', ?, ?)", false,
            [$projectId, trim((string)$project['abbr'] . ' ' . (string)$project['name']), json_encode($ids)]);
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
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["project_id"=>$validated['project_id'], "user_id"=>$_SESSION['user']['id']];
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
        $query = "select id, name, abbr from pm_objectives_tbl where pillar_id = ".$validated['id']." and active=1";
        $data = $this->DB->MQ($query, "all");
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
        $query = "select id, name, abbr from pm_programmes_tbl where objective_id = ".$validated['id']." and active=1";
        $data = $this->DB->MQ($query, "all");
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
            if($data['search']!=""){
                $where .= "AND ((name like ?) OR (description like ?)) ";
                $params[] = "%".$data['search']."%";
                $params[] = "%".$data['search']."%";
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
                   . " limit " . (int)$items_per_page . " offset " . (((int)$page - 1) * (int)$items_per_page);

            $data['data'] = $this->DB->MQ($query, "all", $params);

            $data['items'] = $items_per_page;
            $data['page'] = $page;
        } else {
            $data['data'] = [];
        }

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

            redirect($this->L("projects/".$id_part));
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
            redirect($this->L("projects/progress_edit/".(int)$validated['project_id']."?saved=1"));
        }
    }

}