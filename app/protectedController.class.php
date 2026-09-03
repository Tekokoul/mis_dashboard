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
    protected static $access = [
        'system/info'                   => [1],
        'system/*'                      => [1, 2, 3, 4, 5],
        'projects_graphs/*'             => [1, 2, 3, 4, 5],
        'projects_graphs_b/*'           => [1, 2, 3, 4, 5],
        'users/profile'                 => [1, 2, 3, 4, 5],
        'users/settings_update'         => [1, 2, 3, 4, 5],
        'users/password_update'         => [1, 2, 3, 4, 5],
        'users/logout'                  => [1, 2, 3, 4, 5],
        'users/sso_login'               => [1, 2, 3, 4, 5],
        'users/sso_callback'            => [1, 2, 3, 4, 5],
        // Recording delivery
        'projects/progress_list'        => [1, 2, 3],
        'projects/progress_edit'        => [1, 2, 3],
        'projects/progress_edit_update' => [1, 2, 3],
        'projects/task_progress_update' => [1, 2, 3],
        'projects/get_task_details'     => [1, 2, 3],
        'projects/get_tasks_details'    => [1, 2, 3],
        // Content editing
        'projects/*'                    => [1, 2],
        'core/*'                        => [1, 2],
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

        // The generic CRUD screens (core/db_*) serve content AND accounts.
        // Anything that names the core_users model is account administration,
        // whichever group may use those screens for content.
        if ($controller === 'core') {
            $parts = array_values((array)($this->R->url['parts'] ?? []));
            $query = (array)($this->R->url['query'] ?? []);
            $named = [$parts[0] ?? '', $query['tablename'] ?? '', $query['model'] ?? ''];
            if (in_array('core_users', $named, true)) {
                $allowed = [1];
            }
        }

        if (!in_array($group, $allowed, true)) {
            $this->setAnswer(403, "Your account does not have access to this page.");
        }
    }
}
