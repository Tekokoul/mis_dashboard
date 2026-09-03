<?php
require_once _CONTROLLERS_PATH."core.php";

class projects_graphs_bController extends coreController{
    public function overview_b(){
        $query = "select id, name, abbr from pm_pillars_tbl order by position";
        $pillars = $this->DB->MQ($query, "all");
        $data = [];
        foreach ($pillars as $pillar){
            $pillar_count = 0;
            $pillar_progress = 0;

            $data[$pillar['id']] = $pillar;
            $data[$pillar['id']]['programmes'] = [];
            $query = "select id, name, abbr from pm_programmes_tbl where objective_id in (select id from pm_objectives_tbl where pillar_id =".$pillar['id'].")";;
            $programmes = $this->DB->MQ($query, "all");
            foreach ($programmes as $programme){
                $tasks_count = 0;
                $tasks_progress = 0;

                $data[$pillar['id']]['programmes'][$programme['id']] = $programme;
                $query = "select id, name, abbr from pm_projects_tbl where programme_id=".$programme['id']." and pillar_id = ".$pillar['id'];
                $projects = $this->DB->MQ($query, "all");
                foreach ($projects as $project){
                    $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id = ".$project['id'];
                    $tasks_result = $this->DB->MQ($query, "all");

                    foreach ($tasks_result as $each_task){
                        $applies_to = json_decode($each_task['applies_to']);

                        if(is_array($applies_to)) {
                            $tasks_count += count($applies_to);
                            $query_part = implode(",", $applies_to);
                        } else {
                            $query_part = 'NULL';
                        }

                        $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result = 1 and task_id = ".$each_task['id']." and project_id = ".$project['id']." and member_id in (".$query_part .")";
                        $tasks_progress += $this->DB->MQ($query, "one")['progress'];
                    }
                }
                $data[$pillar['id']]['programmes'][$programme['id']]['totals'] = $tasks_count;
                $data[$pillar['id']]['programmes'][$programme['id']]['progress'] = $tasks_progress;
                $pillar_count += $tasks_count;
                $pillar_progress += $tasks_progress;

            }
            $data[$pillar['id']]['totals'] = $pillar_count;
            $data[$pillar['id']]['progress'] = $pillar_progress;
        }
//        debug($data);
//        exit();
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }

    public function project_b()
    {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
    
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
    
        $data = [];
        $temp = [];
    
        // Fetch project details
        $query = "SELECT * FROM pm_projects_tbl WHERE id=" . $validated['id'];
        $project = $this->DB->MQ($query, "one");
    
        // Fetch all tasks for the project
        $query = "SELECT id, applies_to FROM pm_projects_tasks_tbl WHERE project_id=" . $project['id'];
        $tasks = $this->DB->MQ($query, "all");
    
        $totals = count($tasks);
        $temp['project'] = $project;
        $temp['project']['totals'] = 0; // To calculate later
        $temp['project']['members'] = [];
    
        $memberProgress = []; // To track progress per member across tasks
    
        // Loop through tasks and process their `applies_to`
        foreach ($tasks as $task) {
            $applies_to = json_decode($task['applies_to'] ?? "[]", true);
    
            // For each member in `applies_to`, update their progress
            foreach ($applies_to as $member) {
                // Fetch member details if not already fetched
                if (!isset($memberProgress[$member])) {
                    $query = "SELECT * FROM pm_members_tbl WHERE id = " . $member;
                    $m = $this->DB->MQ($query, "one");
    
                    // Initialize member details
                    $memberProgress[$member] = [
                        'member_state' => $m,
                        'progress' => 0 // To be calculated
                    ];
                }
    
                // Fetch progress for the member specific to this task and project
                $query = "SELECT COUNT(*) as progress 
                          FROM pm_progress_tasks_tbl 
                          WHERE result = 1 AND member_id = " . $member . " AND project_id = " . $project['id'] . " AND task_id = " . $task['id'];
                $progress = $this->DB->MQ($query, "one")['progress'] ?? 0;
    
                // Add progress to the member's total
                $memberProgress[$member]['progress'] += $progress;
            }
        }
    
        // Calculate the overall total
        $temp['project']['totals'] = $totals * count($memberProgress);
    
        // Add division users with their states and progress to the response
        $temp['project']['members'] = array_values($memberProgress);
    
        $data = $temp;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
    

    public function pillar_b(){
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $query = "select id, name, abbr from pm_pillars_tbl where id =".$validated['id'];
        $pillar = $this->DB->MQ($query, "one");
        $data['pillar'] = $pillar;

        $pillar_count = 0;
        $pillar_progress = 0;
        $query = "select id, name, abbr from pm_projects_tbl where pillar_id=".$pillar['id'];
        $projects = $this->DB->MQ($query, "all");
        $data['projects'] = [];
        foreach ($projects as $project){
            $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id = ".$project['id'];
            $tasks_result = $this->DB->MQ($query, "all");
            $tasks_count = 0;
            $tasks_progress = 0;
            foreach ($tasks_result as $each_task){
                $applies_to = json_decode($each_task['applies_to']);

                if(is_array($applies_to)) {
                    $tasks_count += count($applies_to);
                    $query_part = implode(",", $applies_to);
                } else {
                    $query_part = 'NULL';
                }

                $query = "SELECT count(*) as progress FROM `pm_progress_tasks_tbl` where result = 1 and task_id=".$each_task['id']." and project_id = ".$project['id']." and member_id in (".$query_part .")";
                $tasks_progress += $this->DB->MQ($query, "one")['progress'];
            }

//debug($tasks_count);

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