<?php
require_once _CONTROLLERS_PATH."core.php";

class projects_graphsController extends coreController{
    public function overview(){
        $query = "select * from pm_pillars_tbl order by position";
        $pillars = $this->DB->MQ($query, "all");
        $data = [];
        foreach ($pillars as $pillar){
            $pillar_count = 0;
            $pillar_progress = 0;

            $data[$pillar['id']] = $pillar;
            $data[$pillar['id']]['programmes'] = [];
            $query = "select  GROUP_CONCAT(id) as ids from pm_objectives_tbl where pillar_id = ".$pillar['id'];
            $obj = $this->DB->MQ($query, "one");
            if(!is_null($obj['ids'])){
                $query = "select * from pm_programmes_tbl where objective_id in (". $obj['ids'].")";
                $programmes = $this->DB->MQ($query, "all");
            } else {
                $programmes = [];
            }
            foreach ($programmes as $programme){
                $tasks_count = 0;
                $tasks_progress = 0;

                $data[$pillar['id']]['programmes'][$programme['id']] = ($programme);
                $query = "select * from pm_projects_tbl where programme_id=".$programme['id'];
                $projects = $this->DB->MQ($query, "all");
                foreach ($projects as $project){
                    $applies_to = [];
                    if(isset($project['applies_to'])) {
                        $applies_to = json_decode($project['applies_to']) ?? [];
                    }

                    $query = "SELECT JSON_LENGTH(tasks) AS tasks_count FROM pm_projects_tasks_tbl 
inner join pm_projects_tbl on pm_projects_tbl.id = pm_projects_tasks_tbl.project_id where project_id=".$project['id'];
                    $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;
                    $tasks_count += $totals*count($applies_to);

                    $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result = 1 and project_id = ".$project['id'];
                    $pgs = $this->DB->MQ($query, "one")['progress'];
                    $tasks_progress += $pgs;



//                    $tasks_count += (count($applies_to)*100);
//                    foreach ($progress_percentage as $p){
//                        $pillar_progress += $p['value'];
//                        $tasks_progress += $p['value'];
//                    }
                    $pillar_count += $tasks_count;
                    $pillar_progress += $tasks_progress;
                }
                $data[$pillar['id']]['programmes'][$programme['id']]['totals'] = $tasks_count;
                $data[$pillar['id']]['programmes'][$programme['id']]['progress'] = $tasks_progress;
            }
            $data[$pillar['id']]['totals'] = $pillar_count;
            $data[$pillar['id']]['progress'] = $pillar_progress;
        }
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }

    public function members(){
        $data = [];
        $pm_matrix = readJSONFile(_MODELS_SETTINGS_PATH."pm_type_to_progress_matrix.json");
        $query = "SELECT * FROM `pm_members_tbl` order by name asc";
        $members = $this->DB->MQ($query, "all");
        foreach ($members as $member){
            $data[$member['id']] = [
                'name' => $member['name'],
                'totals'=> 0,
                'progress' => 0
            ];

            $query = "SELECT JSON_LENGTH(tasks) AS tasks_count FROM pm_projects_tasks_tbl 
inner join pm_projects_tbl on pm_projects_tbl.id = pm_projects_tasks_tbl.project_id where  JSON_CONTAINS(applies_to, '\"".$member['id']."\"')";
            $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;
            $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result = 1 and member_id = ".$member['id'];
            $pgs = $this->DB->MQ($query, "one")['progress'];
            $data[$member['id']]['totals'] = ($data[$member['id']]['totals'] + $totals);
            $data[$member['id']]['progress'] = ($data[$member['id']]['progress'] + $pgs);

            $query = "SELECT count(*) as tasks_count from pm_projects_tbl where type ='pm_projects_percentages' and JSON_CONTAINS(applies_to, '\"".$member['id']."\"')";
            $totals = $this->DB->MQ($query, "one")['tasks_count'];

            $query = 'SELECT sum(`value`) as progress FROM `pm_progress_percentages_tbl` where member_id = '.$member['id'];
            $pgs = $this->DB->MQ($query, "one")['progress'];
            $data[$member['id']]['totals'] = $data[$member['id']]['totals'] + $totals*100;
                $data[$member['id']]['progress'] = $data[$member['id']]['progress'] + ((isset($pgs)) ? $pgs : 0);



//            $data[$member['id']] = $member;
//            $query = "select count(*) as total from pm_projects_tbl where JSON_CONTAINS(applies_to, '\"".$member['id']."\"') order by abbr";
//            $totals = $this->DB->MQ($query, "one");
//            $data[$member['id']]['total'] = $totals['total'];
//
//            $query = "select * from pm_projects_tbl where JSON_CONTAINS(applies_to, '\"".$member['id']."\"')";
//            $projects = $this->DB->MQ($query, "all");
//
////            $data[$member['id']]['completed'] ;
//            foreach ($projects as $project){
//                $query = "SELECT * FROM `".$project['type']."_tbl` where project_id=".$project['id'];
//                $prj = $this->DB->MQ($query, "one");
//                $query = "SELECT * FROM `".$pm_matrix[$project['type']]['progress_file']."_tbl` where member_id = ".$member['id']." and project_id=".$project['id'];
//                $pgs = $this->DB->MQ($query, "one");
//                $data[$member['id']]['projects'][] = [
//                    'project'=> $prj,
//                    'progress' => (is_set($pgs) ? $pgs : 0)
//                ];
//            }
        }
//        $this->AddCSS("/vendor/chartist/chartist.css");
        $this->AddJS("/vendor/raphael/raphael.js");
        $this->AddCSS("/vendor/morris/morris.css");
        $this->AddJS("/vendor/morris/morris.js");
        $this->AddCSS("/css/members_graphs.css");
//
//        $this->AddJS("/vendor/chartist/chartist.js");
        $this->AddJS("/js/members_graphs.js");

        $this->render($data);
    }
    public function projects(){
        $data = [];
        $temp = [];
        $query = "select * from pm_pillars_tbl";
        $pillars = $this->DB->MQ($query, "all");
        foreach ($pillars as $pillar){
            $query = "select * from pm_objectives_tbl where pillar_id = ".$pillar['id'];
            $objectives = $this->DB->MQ($query, "all");
            foreach ($objectives as $objective){
                $query = "select * from pm_programmes_tbl where objective_id=".$objective['id'];
                $programmes = $this->DB->MQ($query, "all");
                foreach ($programmes as $programme){
                    $query = "select * from pm_projects_tbl where programme_id=".$programme['id'];
                    $projects = $this->DB->MQ($query, "all");
                    foreach ($projects as $project){
                        $applies_to = [];
                        if(isset($project['applies_to'])) {
                            $applies_to = json_decode($project['applies_to']) ?? [];
                        }
                        $temp['project'] = $project;
                        $temp['project']['pillar'] = $pillar['name'];
                        foreach ($applies_to as $member){
                            $query = "SELECT * FROM `pm_members_tbl` where id = ".$member;
                            $m = $this->DB->MQ($query, "one");
                            $query = "SELECT * FROM `pm_progress_percentages_tbl` where member_id = ".$member." and project_id=".$project['id'];
                            $p = $this->DB->MQ($query, "one");
                            $temp['project']['members'][] = [
                                'member_state'=> $m,
                                'progress' => $p
                            ];
                        }
                        $data[] = $temp;
                    }
                }
            }
        }
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }

    public function project(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $data = [];
        $temp = [];

        $query = "select * from pm_projects_tbl where id=".$validated['id'];
        $project = $this->DB->MQ($query, "one");

        $query = "SELECT JSON_LENGTH(tasks) AS tasks_count FROM pm_projects_tasks_tbl where project_id=".$project['id'];
        $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;

        $applies_to = [];
        if(isset($project['applies_to'])) {
            $applies_to = json_decode($project['applies_to']) ?? [];
        }
        $temp['project'] = $project;
        $temp['project']['totals'] = $totals;
        foreach ($applies_to as $member){
            $query = "SELECT * FROM `pm_members_tbl` where id = ".$member;
            $m = $this->DB->MQ($query, "one");
            $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result=1 and member_id = ".$member." and project_id=".$project['id'];
            $p = $this->DB->MQ($query, "one");
            $temp['project']['members'][] = [
                'member_state'=> $m,
                'progress' => $p['progress']
            ];
        }
        $data = $temp;

        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }

    public function pillar(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $query = "select * from pm_pillars_tbl where id =".$validated['id'];
        $pillar = $this->DB->MQ($query, "one");
        $data['pillar'] = $pillar;

        $pillar_count = 0;
        $pillar_progress = 0;
        $query = "select * from pm_projects_tbl where pillar_id=".$pillar['id'];
        $projects = $this->DB->MQ($query, "all");
        foreach ($projects as $project){
            $tasks_count = 0;
            $tasks_progress = 0;
            $applies_to = [];
            if(isset($project['applies_to'])) {
                $applies_to = json_decode($project['applies_to']) ?? [];
            }

            $query = "SELECT JSON_LENGTH(tasks) AS tasks_count FROM pm_projects_tasks_tbl 
inner join pm_projects_tbl on pm_projects_tbl.id = pm_projects_tasks_tbl.project_id where project_id=".$project['id'];
            $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;
            $tasks_count += $totals*count($applies_to);

            $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result = 1 and project_id = ".$project['id'];
            $pgs = $this->DB->MQ($query, "one")['progress'];
            $tasks_progress += $pgs;



//                    $tasks_count += (count($applies_to)*100);
//                    foreach ($progress_percentage as $p){
//                        $pillar_progress += $p['value'];
//                        $tasks_progress += $p['value'];
//                    }
            $data['projects'][$project['id']] = [
                "id" => $project['id'],
                "name" => $project['name'],
                "totals" => $tasks_count,
                "progress" => $tasks_progress
            ];

            $pillar_count += $tasks_count;
            $pillar_progress += $tasks_progress;
        }
        $data['pillar']['totals'] = $pillar_count;
        $data['pillar']['progress'] = $pillar_progress;
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }

}
