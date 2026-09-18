<?php

class protectedController extends vanillaController {

    protected $unprotected = [];

    // Who may call what, by group id (core_groups_tbl: 1 System Administrators,
    // 2 Executive, 3 Power, 4 Custom, 5 Member State). Keys are
    // "controller/action" or "controller/*"; the specific entry wins. Anything
    // NOT listed is System Administrators only - deny by default, so a new
    // controller is private until someone decides otherwise here.
    //
    // This mirrors "active_for" in db/menus/ce_menu.json, which only ever hid
    // menu items: until this table existed the sole gate was "logged in", and
    // a Member State account could open /users/list, /admin/configuration or
    // POST group=1 to its own record.
    // Who may reach which route. 1 administrators, 2 executives, 3 power
    // users, 4 viewers (library.php: can_*). A route not listed is the
    // administrator's. The generic screens and the forms that name a table
    // are narrowed again by the model named (authorize, model_may).
    protected static $access = [
        'system/info'                   => [1],
        'system/*'                      => [1, 2, 3, 4],
        'projects_graphs/*'             => [1, 2, 3, 4],
        'projects_graphs_b/*'           => [1, 2, 3, 4],
        'users/profile'                 => [1, 2, 3, 4],
        'users/settings_update'         => [1, 2, 3, 4],
        'users/password_update'         => [1, 2, 3, 4],
        'users/logout'                  => [1, 2, 3, 4],
        'users/sso_login'               => [1, 2, 3, 4],
        'users/sso_callback'            => [1, 2, 3, 4],
        // Reading the plan
        'projects/list'                 => [1, 2, 3],
        'projects/search_suggest'       => [1, 2, 3],   // the users it suggests only for administrators (checked inside)
        'projects/programme_context'    => [1, 2, 3],
        'projects/get_details'          => [1, 2, 3],
        'projects/get_objectives'       => [1, 2, 3],
        'projects/get_programmes'       => [1, 2, 3],
        'core/db_list'                  => [1, 2, 3],
        'core/db_view'                  => [1, 2, 3],
        'imports/list'                  => [1, 2, 3],   // the controller also answers 404 unless IMPORT_ENABLED
        'imports/template'              => [1, 2, 3],
        'imports/review'                => [1, 2, 3],
        // Keeping the plan current: activities and their tasks, programmes
        // (core/db_* narrowed by model: goals, objectives and units stay the
        // administrator's), the work plan import
        'projects/add'                  => [1, 3],
        'projects/add_update'           => [1, 3],
        'projects/edit'                 => [1, 3],
        'projects/edit_update'          => [1, 3],
        'projects/task'                 => [1, 3],
        'projects/task_update'          => [1, 3],
        'projects/task_delete'          => [1, 3],
        'core/db_add'                   => [1, 3],
        'core/db_add_update'            => [1, 3],
        'core/db_edit'                  => [1, 3],
        'core/db_edit_update'           => [1, 3],
        'core/next_code'                => [1, 3],
        'core/suggest_parent'           => [1, 3],
        'imports/*'                     => [1, 3],
        // Recording delivery
        'projects/progress_list'        => [1, 3],
        'projects/progress_edit'        => [1, 3],
        'projects/progress_edit_update' => [1, 3],
        'projects/task_progress_update' => [1, 3],
        'projects/get_task_details'     => [1, 3],
        'projects/get_tasks_details'    => [1, 3],
        // Deciding: what the AI proposes, and merging
        'projects/allocation_accept'    => [1, 2],
        'projects/allocation_revert'    => [1, 2],
        'projects/allocation_accept_all'=> [1, 2],
        'projects/allocation_move'      => [1, 2],
        'projects/merge'                => [1, 2],
        'projects/merge_update'         => [1, 2],
        'projects/merge_undo'           => [1, 2],
        'projects/unit_move'            => [1, 2],
        'core/unit_accept'              => [1, 2],
        'core/unit_dismiss'             => [1, 2],
        'core/unit_accept_all'          => [1, 2],
        // Deleting, accounts, the json screens: the administrator's (the default)
        'core/db_delete'                => [1],
        'projects/*'                    => [1],
        'core/*'                        => [1],
    ];

    public function __construct(Registry $registry) {
        $this->R = $registry;
        if($this->isLoggedIn()){
            parent::__construct($registry);
            $this->authorize();
        } elseif ($this->allowed()) {
            parent::__construct($registry);
        } else {
            // A person following a bookmark or an expired session gets the
            // sign-in form; scripts (AJAX, POST) still get a plain 401.
            $isXhr  = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
            $isGet  = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
            $wantsHtml = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false;
            if ($isGet && !$isXhr && $wantsHtml && !headers_sent()) {
                header("Location: " . $this->L("login"), true, 302);
                exit;
            }
            $this->setAnswer(401, "You do not have permission to view this.");
        }
    }

    protected function allowed(){
        return in_array($this->R->url['action'],$this->unprotected);
    }

    protected function authorize(){
        $controller = (string)($this->R->url['controller'] ?? '');
        $action     = (string)($this->R->url['action'] ?? '');
        $group      = (int)($_SESSION['user']['group']['id'] ?? 0);

        $allowed = static::$access[$controller.'/'.$action]
                ?? static::$access[$controller.'/*']
                ?? [1];

        if (!in_array($group, $allowed, true)) {
            $this->setAnswer(403, "Your account does not have access to this page.");
        }

        // The generic screens (core/db_*) serve goals, objectives, units,
        // programmes AND accounts, and the activity forms name the table they
        // save to. Every model a request names must be one this group may
        // read, write or delete (library.php model_may) - whichever group may
        // use the screen. This is what stops an activity save from reaching
        // core_users_tbl, or a Power User from filing an objective through the
        // form meant for programmes.
        $parts = array_values((array)($this->R->url['parts'] ?? []));
        $query = (array)($this->R->url['query'] ?? []);
        $named = [];
        if ($controller === 'core' || ($controller === 'projects' && $action === 'get_details')) { $named[] = (string)($parts[0] ?? ''); }
        foreach (['tablename', 'model'] as $k) { if (isset($query[$k]) && is_string($query[$k])) { $named[] = $query[$k]; } }
        foreach (array_keys((array)($query['additional_tables'] ?? [])) as $k) { $named[] = (string)$k; }
        $named = array_values(array_filter(array_unique($named), 'strlen'));
        if ($named) {
            $op = in_array($action, ['db_list', 'db_view', 'get_details'], true) ? 'read' : ($action === 'db_delete' ? 'delete' : 'write');
            foreach ($named as $m) {
                if (!model_may($m, $op)) { $this->setAnswer(403, "Your account does not have access to this page."); }
            }
        }
    }
}
