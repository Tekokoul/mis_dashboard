<?php
require_once _CONTROLLERS_PATH."core.php";

class casesController extends coreController{
    public function list(){
        $this->checkMethod("GET");
        $this->mapRoute("page");
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "folders" => FILTER_UNSAFE_RAW,
            "filter-by" => FILTER_SANITIZE_NUMBER_INT,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);

        $validated['model'] = 'youspeak_cases';

        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);
//        $data['meta_filters'][] = [
//            "key" => "idConstituency",
//            "title" => "Constituency",
//            "type" => "dropdown",
//            "values_from" => "db",
//            "link_to_table" => "youspeak_constituencies_tbl",
//            "link_to_field" => "name",
//            "order_by" => "`name` asc",
//            "where_clause" => "and active=1",
//            "add_zero_value" => true
//        ];
        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        if($filter['key']=='idIssues_1'){
                            $filters[] = "AND (idIssues_1='".($this->query[$filter['key']] ?? "")."' OR idIssues_2='".($this->query[$filter['key']] ?? "")."')";
                        } else {
                            $filters[] = "AND ".$filter['key']."='".($this->query[$filter['key']] ?? "")."'";
                        }
                    }
                }
                $data['filter_data'][$filter['key']] = $this->query[$filter['key']] ?? "";
            }
        }
        if(array_key_exists('idConstituency', $this->query)){
            if ($this->query['idConstituency'] != '%') {
                $filters[] = "AND idConstituency='" . ($this->query['idConstituency'] ?? "") . "'";
                $data['filter_data']['idConstituency'] = $this->query['idConstituency'] ?? "";
            }
        }
        switch ($_SESSION['user']['group']['cases_type']){
            case 0:
                if($_SESSION['user']['constituencies']!="") {
                    $filters[] = "AND idConstituency in (".$_SESSION['user']['constituencies'].")";
                }
                break;
            case 1:
                $query = "select * from youspeak_cases_to_users_tbl where user_id = ".$_SESSION['user']['id'];
                $assigned_cases = $this->DB->MQ($query, "all");
                if(is_set($assigned_cases)){
                    $filters[] = "AND id in (".implode(",", array_column($assigned_cases, "case_id")).")";
                }
                break;
        }
        if(is_set($data['fields'])){
//            $results = ;
            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??"", $filters));
        } else {
            $data['data'] = [];
        }
        if($_SESSION['user']['constituencies']!=""){
            $query = "select * from youspeak_constituencies_tbl where id in (".$_SESSION['user']['constituencies'].") order by name";
        } else {
            $query = "select * from youspeak_constituencies_tbl order by name";
        }

        $data['available_constituencies'] = $this->DB->MQ($query, "all");

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function edit(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = 'youspeak_cases';

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = $this->model->get_data($validated['model'], $validated['id']);

        $data['data']['details'] = [];
        $data['data']['details'][] = [
            "title" => "Case created",
            "type" => "change",
            "event_date" => $data['data']['dateCreated']
        ];

        $query = "select *, 'reply' as type from ".$this->model->get_table_name("youspeak_cases_replies"). " where case_id = ".$validated['id'];
        $replies = $this->DB->MQ($query, "all");
        $data['data']['details'] = array_merge($data['data']['details'], $replies);

        $query = "select *, 'change' as type, date_created as event_date from ".$this->model->get_table_name("youspeak_cases_journal"). " where case_id = ".$validated['id'];
        $changes = $this->DB->MQ($query, "all");
        foreach ($changes as &$change){
            $query = "select title from ".$this->model->get_table_name("youspeak_cases_status")." where id=".$change['value'];
            $result = $this->DB->MQ($query, "one");
            $change['title'] = "Status changed to ".$result['title'];
        }
        $data['data']['details'] = array_merge($data['data']['details'], $changes);

        $query = "select *, 'action' as type from ".$this->model->get_table_name("youspeak_cases_actions"). " where case_id = ".$validated['id'];
        $actions = $this->DB->MQ($query, "all");
        foreach ($actions as &$action){
            $query = "select title as method_title from ".$this->model->get_table_name("youspeak_options_responses")." where id=".$action['method'];
            $result = $this->DB->MQ($query, "one");
            $action['method_title'] = $result['method_title'];
        }
        $data['data']['details'] = array_merge($data['data']['details'], $actions);

        $query = "select *, 'redirect' as type from ".$this->model->get_table_name("youspeak_cases_redirects"). " where case_id = ".$validated['id'];
        $redirects = $this->DB->MQ($query, "all");
        foreach ($redirects as &$redirect){
            $query = "select title as method_title from ".$this->model->get_table_name("youspeak_options_responses")." where id=".$redirect['method'];
            $result = $this->DB->MQ($query, "one");
            $redirect['method_title'] = $result['method_title'] ?? 'N/A';
            $query = "select title as referral_title from ".$this->model->get_table_name("youspeak_options_referrals")." where id=".$redirect['redirect_to_user'];
            $result = $this->DB->MQ($query, "one");
            $redirect['referral_title'] = $result['referral_title'] ?? 'N/A';
        }
        $data['data']['details'] = array_merge($data['data']['details'], $redirects);

        $query = "select *, 'comment' as type from ".$this->model->get_table_name("youspeak_cases_comments"). " where case_id = ".$validated['id'];
        $comments = $this->DB->MQ($query, "all");
        $data['data']['details'] = array_merge($data['data']['details'], $comments);

        $query = "select * from ".$this->model->get_table_name("youspeak_issues");
        $data['issues'] = $this->DB->MQ($query, "all");

        $query = "select * from ".$this->model->get_table_name("youspeak_constituencies");
        $data['constituencies'] = $this->DB->MQ($query, "all");

        $query = "select * from ".$this->model->get_table_name("youspeak_wards")." where constituencies_id=".$data['data']['userConstituency'];
        $data['wards'] = $this->DB->MQ($query, "all");

        $dates = array_column($data['data']['details'], 'event_date');
        array_multisort($dates, SORT_DESC, $data['data']['details']);

        $query = "SELECT CONCAT( givenname, ' ', sn ) as name, username FROM core_users_tbl WHERE ((FIND_IN_SET(".$data['data']['idConstituency'].", constituencies) > 0) and core_users_tbl.GROUP not in (5,6)) or (core_users_tbl.GROUP=1 and constituencies is NULL)";
        $data['data']['access_users'] = $this->DB->MQ($query, "all");
        
        $query = "select COALESCE(CONCAT(givenname, ' ', sn), title) AS `name`, username from youspeak_cases_to_users_tbl 
left join core_users_tbl on youspeak_cases_to_users_tbl.user_id = core_users_tbl.id
left join youspeak_options_referrals_tbl on youspeak_options_referrals_tbl.id=youspeak_cases_to_users_tbl.unregistered_user_id
where case_id=".$validated['id'];
        $data['data']['access_users'] = array_merge($data['data']['access_users'], $this->DB->MQ($query, "all"));
//
//        debug(_MEDIA_PATH."cases".DS.$data['data']['id']);
//        $data['files'] = scandir(_MEDIA_PATH."cases".DS.$data['data']['id']); // get all file names

        $files = scandir(_ROOT_PATH."..".DS."public".DS."media".DS."cases".DS.$data['data']['id']);
        if(!empty($files)){
            $data['files'] = array_values(array_diff($files, ['..', '.'])); // get all file names
        } else {
            $data['files'] = [];
        }

        $this->AddCSS("/assets/youspeak/css/timeline.css");
        $this->AddJS("/assets/youspeak/js/timeline.js");
        $this->AddJS("/assets/youspeak/js/storyjs-embed.js");
        $this->AddJS("/assets/youspeak/js/custom-timeline.js");
        $this->AddJS("/assets/youspeak/js/custom-issue.js");
        $this->AddJS("/js/youspeak.js");
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
            $redirect_part = ($this->update_redirect=="db_edit") ? "edit/".$validated['id'] : "list";
            redirect($this->L("cases/".$redirect_part));
        }
    }

    public function reply(){
        $this->checkMethod("GET");
        $this->mapRoute("case_id/id");
        $this->checkRequired(["case_id","id"], $this->parts);
        $rules = [
            "case_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_cases_replies";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["case_id"=>$validated['case_id'], "user_id"=>$_SESSION['user']['id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function redirect(){
        $this->checkMethod("GET");
        $this->mapRoute("case_id/id");
        $this->checkRequired(["case_id","id"], $this->parts);
        $rules = [
            "case_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_cases_redirects";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["case_id"=>$validated['case_id'], "user_id"=>$_SESSION['user']['id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function action(){
        $this->checkMethod("GET");
        $this->mapRoute("case_id/id");
        $this->checkRequired(["case_id","id"], $this->parts);
        $rules = [
            "case_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_cases_actions";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["case_id"=>$validated['case_id'], "user_id"=>$_SESSION['user']['id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function comment(){
        $this->checkMethod("GET");
        $this->mapRoute("case_id/id");
        $this->checkRequired(["case_id","id"], $this->parts);
        $rules = [
            "case_id" => FILTER_SANITIZE_NUMBER_INT,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $validated['model'] = "youspeak_cases_comments";

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = ($validated['id']!="new") ? $this->model->get_data($validated['model'], $validated['id']) : ["case_id"=>$validated['case_id'], "user_id"=>$_SESSION['user']['id']];
        $this->prepare_edit_mode();
        $this->partial_render($data, "html", "popup");
    }

    public function event_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->query, $rules);
        if($validated['id']!=""){
            $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        } else {
            $executed = $this->model->add_data($validated['tablename'], $this->query);
        }

        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            redirect($this->L("cases/edit/".$this->query['case_id']));
        }
    }

    public function event_delete(){
        $this->checkMethod("GET");
        $this->mapRoute("type/id");
        $this->checkRequired(["type", "id"], $this->parts);
        $rules = [
            "type" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        switch ($validated['type']){
            case "reply":
                $validated['model'] = "youspeak_cases_replies";
                break;
            case "redirect":
                $validated['model'] = "youspeak_cases_redirects";
                break;
            case "action":
                $validated['model'] = "youspeak_cases_actions";
                break;
            case "comment":
                $validated['model'] = "youspeak_cases_comments";
                break;
        }
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function change_status(){
        $this->checkMethod("GET");
        $this->mapRoute("case_id/status");
        $this->checkRequired(["case_id","status"], $this->parts);
        $rules = [
            "case_id" => FILTER_SANITIZE_NUMBER_INT,
            "status" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $query = "UPDATE ".$this->model->get_table_name("youspeak_cases")." SET `idStatus` = '".$validated['status']."' WHERE `id` = '".$validated['case_id']."'";
        $this->DB->MQ($query);
        $query = "INSERT INTO `youspeak_cases_journal_tbl` ( `case_id`, `user_id`, `property`, `value`, `date_created`) VALUES ( '".$validated['case_id']."', '".$_SESSION['user']['id']."', 'Status change', '".$validated['status']."', '".date("Y-m-d H:i:s")."' );";
        $this->DB->MQ($query);

        $api_class = _PROJECT_HELPER_CLASS;
        // The per-tenant helper class is optional and absent in this build.
        if ($api_class === "" || !class_exists($api_class)) { return; }
        $projectClass = new $api_class($this->R);
        $result = $projectClass->case_update($validated);

        redirect($this->L("cases/edit/".$validated['case_id']));
    }

    public function list_redirects(){

    }

}