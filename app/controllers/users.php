<?php

class usersController extends protectedController {

    protected $unprotected = ["login", "logout"];

    public function __construct(Registry $registry) {
        parent::__construct($registry);
        require_once _MODELS_PATH."core.php";
        $this->model = new coreModel($registry);
        $this->update_redirect = $this->update_redirect();
    }

    public function login() {
        // Make sure we accept only POST requests
        $this->checkMethod("POST");
        // Check for required parameters
        $this->checkRequired(["username", "password", "csrf"], $this->query);
        // Rules and validation of the QueryString
        $rules = [
            "username" => FILTER_SANITIZE_EMAIL,
            "password" => FILTER_UNSAFE_RAW,
            "step" => FILTER_UNSAFE_RAW,
            "csrf" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);

        $this->checkCSRF($validated['csrf']);

        // Throttle. Failed attempts are recorded in core_users_logs_tbl as
        // 'login_failed' against the account they named (0 when no such
        // account exists); more than five in fifteen minutes and the account
        // is refused until the window passes. No new table, so nothing to
        // migrate on the live database.
        $account = $this->DB->MQ("SELECT id FROM core_users_tbl WHERE username = ?", "one", [$validated['username']]);
        $account_id = (int)($account['id'] ?? 0);
        $recent = $this->DB->MQ(
            "SELECT COUNT(*) AS n FROM core_users_logs_tbl WHERE action = 'login_failed' AND core_users_id = ? AND log_date > (NOW() - INTERVAL 15 MINUTE)",
            "one", [$account_id]
        );
        if ((int)($recent['n'] ?? 0) >= 5) {
            $this->setAnswer(429, "Too many sign-in attempts. Wait fifteen minutes and try again.");
        }

        // Bound parameters. username is attacker-controlled and reaches this
        // query BEFORE any authentication; FILTER_SANITIZE_EMAIL is an input
        // filter, not an escaping mechanism, so it must never be interpolated.
        // The password is checked in PHP, not in the WHERE clause: hashes are
        // password_hash() values now (legacy md5(md5) is accepted once and
        // rewritten - see coreModel::check_password).
        $query = "SELECT *, core_users_tbl.id as user_id FROM core_users_tbl WHERE username = ?";
        $user = $this->DB->MQ($query, "one", [$validated['username']]);

        $needs_rehash = false;
        if (is_set($user) && !$this->model->check_password($validated['password'], $user['password'] ?? "", $needs_rehash)) {
            $user = false;
        }
        if (is_set($user) && $needs_rehash) {
            $this->DB->MQ("UPDATE core_users_tbl SET password = ? WHERE id = ?", false, [
                $this->model->create_password($validated['password']), (int)$user['id'],
            ]);
        }

        if (!is_set($user)) {
            $this->DB->MQ("INSERT INTO `core_users_logs_tbl` (`core_users_id`, `action`, `log_date`) VALUES (?, 'login_failed', ?)", false, [$account_id, date("Y-m-d H:i:s")]);
            // Same answer whether the account exists or not.
            usleep(300000);
        }

        if (is_set($user)){
            if($user['active']){
                // A fresh session id at the authentication boundary, so a
                // session id planted in the browser before login (fixation)
                // never becomes an authenticated one; and a fresh CSRF token
                // for the same reason.
                session_regenerate_id(true);
                $_SESSION['token'] = bin2hex(random_bytes(35));
                $_SESSION['token_expiry'] = time() + _CSRF_EXPIRY;
                $this->setUser($user);
                $query = "select * from core_groups_tbl where id = ?";
                $_SESSION['user']['group'] = $this->DB->MQ($query, "one", [(int)$user['group']]);

                if(file_exists(_USERS_SETTINGS_PATH."user_".$_SESSION['user']['id'].".json")){
                    $_SESSION['user']['settings'] = readJSONFile(_USERS_SETTINGS_PATH."user_".$_SESSION['user']['id'].".json");
                }
                $this->DB->MQ("INSERT INTO `core_users_logs_tbl` (`core_users_id`, `action`, `log_date`) VALUES (?, 'login', ?)", false, [(int)$user['id'], date("Y-m-d H:i:s")]);

                // Land where the user asked to (Profile > Settings), default the
                // overview - the page the monitor exists for - rather than the
                // near-empty "Hello" dashboard card. Allow-listed: the value is
                // read from a file the user's own settings form writes.
                $landing = (string)($_SESSION['user']['settings']['landing_page'] ?? '');
                $allowed = ['projects_graphs/overview', 'dashboard', 'projects/progress_list', 'projects_graphs/projects'];
                redirect($this->L(in_array($landing, $allowed, true) ? $landing : 'projects_graphs/overview'));
            } else {
                $this->setAnswer(401, "You do not have permission to login.");
            }
        } else {
            $this->setAnswer(401, "Invalid credentials.");
        }
    }

    public function logout(){
        $this->DB->MQ("INSERT INTO `core_users_logs_tbl` (`core_users_id`, `action`, `log_date`) VALUES (?, 'logout', ?)", false, [(int)($_SESSION['user']['id'] ?? 0), date("Y-m-d H:i:s")]);
        unset($_SESSION['user']);
        session_start();
        session_destroy();
        clear_cache();
        redirect($this->L(""));
    }

    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $validated['model'] = "core_users";
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);

        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        // Key is a model-defined column; the VALUE is raw
                        // request input, so it travels as a bound value.
                        $filters[] = ['sql' => "AND `".$filter['key']."` = ?", 'value' => $this->query[$filter['key']] ?? ""];
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
        $this->checkMethod("GET");
        $validated['model'] = "core_users";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
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
        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            $id_part = ($this->update_redirect=="db_edit") ? "/".$executed['common'] : "";
            redirect($this->L("users/edit".$id_part));
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
        $validated['model'] = "core_users";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = $this->model->get_data($validated['model'], $validated['id']);
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
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $id_part = ($this->update_redirect=="db_edit") ? "/".$validated['id'] : "";
            redirect($this->L("users/edit".$id_part));
        }
    }

    public function profile(){
        $model_file_common = _JSON_MODELS_PATH."table.user_settings.json";
        $data_file = _USERS_SETTINGS_PATH."user_".$_SESSION['user']['id'].".json";

        $model = [];

        if(file_exists($model_file_common)) {
            $model["common"] = readJSONFile($model_file_common);
        }

        $data['model'] = $model;
        $data['data'] = readJSONFile($data_file);
        $this->prepare_edit_mode();
        $data['user']['username'] = $_SESSION['user']['username'];
        $data['user']['name'] = $_SESSION['user']['givenname']." ".$_SESSION['user']['sn'];
        $data['user']['group'] = $_SESSION['user']['group']['name'];
        $data['logs'] = $this->DB->MQ("select action, log_date from core_users_logs_tbl where log_date between '".date("Y-m-d H:i:s", strtotime("-5 days"))."'
and '".date("Y-m-d H:i:s")."' and core_users_id=".$_SESSION['user']['id']." order by log_date desc", "all");
        $this->render($data);
    }

    public function settings_update(){
        $this->checkMethod("POST");

        $values = $this->R->url['query'];
        $_SESSION['user']['settings'] = $values;

        $json_table = fopen(_USERS_SETTINGS_PATH."user_".$_SESSION['user']['id'].".json", "w") or die(__FILE__." Unable to open file for saving");
        fwrite($json_table, json_encode($values));
        fclose($json_table);
        redirect($this->L("users/profile"));
    }

    public function password_update(){
        $this->checkMethod("POST");
        $rules = [
            "password" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        if($validated['password']!=""){
            // The target is always the signed-in user - never an id from the
            // request - and the hash goes through create_password so a later
            // change of hashing scheme lands here too.
            $query = "update core_users_tbl set password = ? where id = ?";
            $this->DB->MQ($query, false, [
                $this->model->create_password($validated['password']),
                (int)$_SESSION['user']['id'],
            ]);
        }
        redirect($this->L("users/profile"));
    }

}