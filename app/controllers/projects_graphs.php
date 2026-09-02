<?php
require_once _CONTROLLERS_PATH."core.php";

class projects_graphsController extends coreController{
    
    public function overview() {
        $query = "SELECT id, name, abbr FROM pm_pillars_tbl ORDER BY position";
        $pillars = $this->DB->MQ($query, "all");
    
        $data = [
            'totals' => 0,     // Total assignments across all levels
            'progress' => 0,   // Overall progress percentage
            'pillars' => []    // Pillar-level details
        ];
    
        foreach ($pillars as $pillar) {
            $pillarTotal = 0;
            $pillarProgress = 0;
    
            $pillarData = $pillar;
            $pillarData['objectives'] = [];
    
            // ORDER BY position keeps the five deliverables in WBS order (1.1 -> 1.5).
            // Without it MySQL returns them in whatever order it likes; the column is
            // added by db/for_upload/africacdc_dhis_seed.sql.
            $query = "SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = " . $pillar['id'] . " ORDER BY position, id";
            $objectives = $this->DB->MQ($query, "all");
    
            foreach ($objectives as $objective) {
                $objectiveTotal = 0;
                $objectiveProgress = 0;
    
                $objectiveData = $objective;
                $objectiveData['projects'] = [];
    
                $query = "SELECT id, name FROM pm_projects_tbl WHERE objective_id = " . $objective['id'] . " AND pillar_id = " . $pillar['id'];
                $projects = $this->DB->MQ($query, "all");
    
                foreach ($projects as $project) {
                    $projectTotal = 0;
                    $projectProgress = 0;
    
                    $projectData = $project;
    
                    // Fetch all tasks for the project
                    $query = "SELECT id, applies_to FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
                    $tasks = $this->DB->MQ($query, "all");
    
                    foreach ($tasks as $task) {
                        // applies_to is a JSON list of member ids stored by the task form.
                        // It is imploded into IN(...) below, so it is reduced to positive
                        // integers here - a stored value like `1) UNION SELECT ...` used
                        // to execute for every viewer of this page.
                        $appliesTo = array_values(array_filter(array_map('intval', (array)json_decode((string)$task['applies_to'], true)), fn($v) => $v > 0));
    
                        if (is_array($appliesTo) && count($appliesTo) > 0) {
                            $taskAssignments = count($appliesTo);
                            $projectTotal += $taskAssignments;
    
                            $queryPart = implode(",", $appliesTo);
                            $query = "SELECT COUNT(*) as completed 
                                      FROM pm_progress_tasks_tbl 
                                      WHERE result = 1 
                                      AND task_id = " . $task['id'] . " 
                                      AND project_id = " . $project['id'] . " 
                                      AND member_id IN (" . $queryPart . ")";
                            $completed = $this->DB->MQ($query, "one")['completed'] ?? 0;
    
                            $projectProgress += $completed;
                        }
                    }
    
                    $projectData['totals'] = $projectTotal;
                    $projectData['progress'] = ($projectTotal > 0) 
                        ? round(($projectProgress / $projectTotal) * 100, 2) 
                        : 0;
    
                    $objectiveTotal += $projectTotal;
                    $objectiveProgress += $projectProgress;
    
                    $objectiveData['projects'][$project['id']] = $projectData;
                }
    
                $objectiveData['totals'] = $objectiveTotal;
            $objectiveData['completed'] = $objectiveProgress;   // raw count, for "n of m delivered"
                $objectiveData['progress'] = ($objectiveTotal > 0) 
                    ? round(($objectiveProgress / $objectiveTotal) * 100, 2) 
                    : 0;
    
                $pillarTotal += $objectiveTotal;
                $pillarProgress += $objectiveProgress;
    
                $pillarData['objectives'][$objective['id']] = $objectiveData;
            }
    
            $pillarData['totals'] = $pillarTotal;
            $pillarData['completed'] = $pillarProgress;
            $pillarData['progress'] = ($pillarTotal > 0) 
                ? round(($pillarProgress / $pillarTotal) * 100, 2) 
                : 0;
    
            // Add pillar-level data to `pillars` array
            $data['pillars'][$pillar['id']] = $pillarData;
    
            // Accumulate totals and progress for the entire data structure
            $data['totals'] += $pillarTotal;
            $data['progress'] += $pillarProgress;
        }
    
        // Keep the raw count before it becomes a percentage: the view prints
        // "n of m delivered" next to every bar, which a bare 0.00% never said.
        $data['completed'] = (int)$data['progress'];

        // Calculate overall progress as a percentage of totals
        $data['progress'] = ($data['totals'] > 0)
            ? round(($data['progress'] / $data['totals']) * 100, 2)
            : 0;

        // Latest user-entered delivery date - the Date field of the
        // Record-delivery form - so the header can say when the figures were
        // last moved. Zero dates are skipped; strtotime() cannot parse them.
        $latest = $this->DB->MQ("SELECT MAX(progress_date) AS latest FROM pm_progress_tasks_tbl WHERE result = 1 AND progress_date > '1000-01-01'", "one");
        $data['latest_delivery'] = $latest['latest'] ?? null;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
     
    public function pillar() {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
    
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
    
        // Fetch the pillar details
        $query = "SELECT * FROM pm_pillars_tbl WHERE id = " . (int)$validated['id'];
        $pillar = $this->DB->MQ($query, "one");
    
        if (!$pillar) {
            $this->render(["error" => "Pillar not found"], 404);
            return;
        }
    
        $data = [
            'pillar' => [
                'id' => $pillar['id'],
                'name' => $pillar['name'],
                'abbr' => $pillar['abbr'],
                'description' => $pillar['description'],
                'totals' => 0,       // Total assignments at the pillar level
                'progress' => 0,     // Progress percentage at the pillar level
                'objectives' => []   // Nested objectives
            ]
        ];
    
        $pillarTotal = 0;
        $pillarProgress = 0;
    
        // Fetch objectives linked to the pillar
        // Same WBS ordering as overview() - see the note there.
        $query = "SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = " . $pillar['id'] . " ORDER BY position, id";
        $objectives = $this->DB->MQ($query, "all");
    
        foreach ($objectives as $objective) {
            $objectiveTotal = 0;
            $objectiveProgress = 0;
    
            $objectiveData = [
                'id' => $objective['id'],
                'name' => $objective['name'],
                'abbr' => $objective['abbr'],
                'totals' => 0,
                'progress' => 0,
                'projects' => [] // Nested projects
            ];
    
            // Fetch projects linked to the objective
            $query = "SELECT id, name FROM pm_projects_tbl WHERE objective_id = " . $objective['id'];
            $projects = $this->DB->MQ($query, "all");
    
            foreach ($projects as $project) {
                $projectTotal = 0;
                $projectProgress = 0;
    
                // Fetch tasks linked to the project
                $query = "SELECT id, applies_to FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
                $tasks = $this->DB->MQ($query, "all");
    
                foreach ($tasks as $task) {
                    $appliesTo = json_decode($task['applies_to'], true);
    
                    if (is_array($appliesTo) && count($appliesTo) > 0) {
                        $taskAssignments = count($appliesTo);
                        $projectTotal += $taskAssignments;
    
                        $queryPart = implode(",", $appliesTo);
                        $query = "SELECT COUNT(*) as progress 
                                  FROM pm_progress_tasks_tbl 
                                  WHERE result = 1 
                                  AND task_id = " . $task['id'] . " 
                                  AND project_id = " . $project['id'] . " 
                                  AND member_id IN (" . $queryPart . ")";
                        $taskProgress = $this->DB->MQ($query, "one")['progress'] ?? 0;
    
                        $projectProgress += $taskProgress;
                    }
                }
    
                // Add project data
                $objectiveData['projects'][$project['id']] = [
                    'id' => $project['id'],
                    'name' => $project['name'],
                    'totals' => $projectTotal,
                    'progress' => ($projectTotal > 0) 
                        ? round(($projectProgress / $projectTotal) * 100, 2) 
                        : 0
                ];
    
                $objectiveTotal += $projectTotal;
                $objectiveProgress += $projectProgress;
            }
    
            // Add objective data
            $objectiveData['totals'] = $objectiveTotal;
            $objectiveData['completed'] = $objectiveProgress;   // raw count, for "n of m delivered"
            $objectiveData['progress'] = ($objectiveTotal > 0) 
                ? round(($objectiveProgress / $objectiveTotal) * 100, 2) 
                : 0;
    
            $data['pillar']['objectives'][$objective['id']] = $objectiveData;
    
            $pillarTotal += $objectiveTotal;
            $pillarProgress += $objectiveProgress;
        }
    
        // Finalize pillar data
        $data['pillar']['totals'] = $pillarTotal;
        $data['pillar']['completed'] = $pillarProgress;
        $data['pillar']['progress'] = ($pillarTotal > 0) 
            ? round(($pillarProgress / $pillarTotal) * 100, 2) 
            : 0;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
  
    public function objective() {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
    
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
    
        // Fetch the objective details
        $query = "SELECT * FROM pm_objectives_tbl WHERE id = " . (int)$validated['id'];
        $objective = $this->DB->MQ($query, "one");
        $data['objective'] = $objective;
    
        $objectiveTotal = 0;
        $objectiveProgress = 0;
    
        // Fetch programmes linked to the objective
        $query = "SELECT id, name, abbr FROM pm_programmes_tbl WHERE objective_id = " . $objective['id'];
        $programmes = $this->DB->MQ($query, "all");
        $data['programmes'] = [];
    
        foreach ($programmes as $programme) {
            $programmeTotal = 0;
            $programmeProgress = 0;
    
            // Fetch projects linked to the programme
            $query = "SELECT id, name, abbr FROM pm_projects_tbl WHERE programme_id = " . $programme['id'] . " AND objective_id = " . $objective['id'];
            $projects = $this->DB->MQ($query, "all");
            $data['programmes'][$programme['id']] = [
                "id" => $programme['id'],
                "name" => $programme['name'],
                "projects" => []
            ];
    
            foreach ($projects as $project) {
                $projectTotal = 0;
                $projectProgress = 0;
    
                // Fetch tasks linked to the project
                $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
                $tasks = $this->DB->MQ($query, "all");
    
                foreach ($tasks as $task) {
                    $appliesTo = json_decode($task['applies_to'], true);
    
                    if (is_array($appliesTo) && count($appliesTo) > 0) {
                        $taskAssignments = count($appliesTo);
                        $projectTotal += $taskAssignments;
    
                        // Create a query to get progress per member
                        $queryPart = implode(",", $appliesTo);
                        $query = "SELECT COUNT(*) as progress 
                                  FROM pm_progress_tasks_tbl 
                                  WHERE result = 1 
                                  AND task_id = " . $task['id'] . " 
                                  AND project_id = " . $project['id'] . " 
                                  AND member_id IN (" . $queryPart . ")";
                        $taskProgress = $this->DB->MQ($query, "one")['progress'] ?? 0;
    
                        $projectProgress += $taskProgress;
                    }
                }
    
                // Calculate project progress as a percentage
                $projectProgressPercentage = ($projectTotal > 0) ? round(($projectProgress / $projectTotal) * 100, 2) : 0;
    
                // Add project data to the programme
                $data['programmes'][$programme['id']]['projects'][$project['id']] = [
                    "id" => $project['id'],
                    "name" => $project['name'],
                    "totals" => $projectTotal,
                    "progress" => $projectProgressPercentage
                ];
    
                $programmeTotal += $projectTotal;
                $programmeProgress += $projectProgress;
            }
    
            // Calculate programme progress as a percentage
            $programmeProgressPercentage = ($programmeTotal > 0) ? round(($programmeProgress / $programmeTotal) * 100, 2) : 0;
    
            // Add programme data to the objective
            $data['programmes'][$programme['id']]['totals'] = $programmeTotal;
            $data['programmes'][$programme['id']]['progress'] = $programmeProgressPercentage;
    
            $objectiveTotal += $programmeTotal;
            $objectiveProgress += $programmeProgress;
        }
    
        // Calculate objective progress as a percentage
        $objectiveProgressPercentage = ($objectiveTotal > 0) ? round(($objectiveProgress / $objectiveTotal) * 100, 2) : 0;
    
        // Finalize objective data
        $data['objective']['totals'] = $objectiveTotal;
        $data['objective']['completed'] = $objectiveProgress;
        $data['objective']['progress'] = $objectiveProgressPercentage;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
  
    public function programme() {
        $this->checkMethod("GET");
        $this->mapRoute("id");
        $this->checkRequired(["id"], $this->parts);
    
        $rules = [
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
    
        // Fetch the programme details
        $query = "SELECT * FROM pm_programmes_tbl WHERE id = " . (int)$validated['id'];
        $programme = $this->DB->MQ($query, "one");
        $data['programme'] = $programme;
    
        $programmeTotal = 0;
        $programmeProgress = 0;
    
        // Fetch projects linked to the programme
        $query = "SELECT id, name, abbr FROM pm_projects_tbl WHERE programme_id = " . (int)$validated['id'] . " AND objective_id = " . $programme['objective_id'];
        $projects = $this->DB->MQ($query, "all");
        $data['projects'] = [];
    
        foreach ($projects as $project) {
            $projectTotal = 0;
            $projectProgress = 0;
    
            // Fetch tasks linked to the project
            $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
            $tasks = $this->DB->MQ($query, "all");
    
            foreach ($tasks as $task) {
                $appliesTo = json_decode($task['applies_to'], true);
    
                if (is_array($appliesTo) && count($appliesTo) > 0) {
                    $taskAssignments = count($appliesTo);
                    $projectTotal += $taskAssignments;
    
                    // Create a query to get progress per member
                    $queryPart = implode(",", $appliesTo);
                    $query = "SELECT COUNT(*) as progress 
                              FROM pm_progress_tasks_tbl 
                              WHERE result = 1 
                              AND task_id = " . $task['id'] . " 
                              AND project_id = " . $project['id'] . " 
                              AND member_id IN (" . $queryPart . ")";
                    $taskProgress = $this->DB->MQ($query, "one")['progress'] ?? 0;
    
                    $projectProgress += $taskProgress;
                }
            }
    
            // Calculate project progress as a percentage
            $projectProgressPercentage = ($projectTotal > 0) ? round(($projectProgress / $projectTotal) * 100, 2) : 0;
    
            // Add project data to the programme
            $data['projects'][$project['id']] = [
                "id" => $project['id'],
                "name" => $project['name'],
                "totals" => $projectTotal,
                "progress" => $projectProgressPercentage
            ];
    
            $programmeTotal += $projectTotal;
            $programmeProgress += $projectProgress;
        }
    
        // Calculate programme progress as a percentage
        $programmeProgressPercentage = ($programmeTotal > 0) ? round(($programmeProgress / $programmeTotal) * 100, 2) : 0;
    
        // Finalize programme data
        $data['programme']['totals'] = $programmeTotal;
        $data['programme']['completed'] = $programmeProgress;
        $data['programme']['progress'] = $programmeProgressPercentage;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
    
    public function project()
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
        $query = "SELECT * FROM pm_projects_tbl WHERE id=" . (int)$validated['id'];
        $project = $this->DB->MQ($query, "one");
    
        // Fetch all tasks for the project
        $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id=" . $project['id'];
        $tasks = $this->DB->MQ($query, "all");
    
        $temp['project'] = $project;
        $temp['project']['tasks'] = $tasks;
        $temp['project']['totals'] = 0; // Total tasks * assignees
        $temp['project']['progress'] = 0; // Overall project progress
        $temp['project']['members'] = []; // Members progress and budget details
    
        $memberProgress = []; // To track progress per member across tasks
        $totalAssignments = 0; // Total assignments (tasks × assignees)
        $completedAssignments = 0; // Total completed assignments (for project progress)
    
        // Loop through tasks and process their `applies_to`
        foreach ($tasks as $task) {
            // Same reduction to positive integers as the other views: `$member`
            // below is interpolated into three queries.
            $applies_to = array_values(array_filter(array_map('intval', (array)json_decode((string)($task['applies_to'] ?? "[]"), true)), fn($v) => $v > 0));
            $taskAssignments = count($applies_to);
            $temp['project']['totals'] += $taskAssignments; // Add to total assignments
    
            // For each member in `applies_to`
            foreach ($applies_to as $member) {
                // Fetch member details if not already fetched
                if (!isset($memberProgress[$member])) {
                    $query = "SELECT * FROM pm_members_tbl WHERE id = " . $member;
                    $m = $this->DB->MQ($query, "one");
    
                    // Initialize member details
                    $memberProgress[$member] = [
                        'member_state' => $m,
                        'assigned_tasks' => 0,
                        'completed_tasks' => 0,
                        'progress' => 0, // Member progress percentage
                        'budget' => 0
                    ];
                }
    
                // Increment the member's assigned task count
                $memberProgress[$member]['assigned_tasks']++;
    
                // Check progress for this specific task and member
                $query = "SELECT COUNT(*) as progress 
                          FROM pm_progress_tasks_tbl 
                          WHERE result = 1 AND member_id = " . $member . " AND project_id = " . $project['id'] . " AND task_id = " . $task['id'];
                $progress = $this->DB->MQ($query, "one")['progress'] ?? 0;
    
                if ($progress > 0) {
                    $memberProgress[$member]['completed_tasks']++;
                    $completedAssignments++;
                }
    
                // Fetch budget for the member for this task
                $query = "SELECT SUM(actual_budget) as budget 
                          FROM pm_progress_tasks_tbl 
                          WHERE member_id = " . $member . " AND project_id = " . $project['id'] . " AND task_id = " . $task['id'];
                $budget = $this->DB->MQ($query, "one")['budget'] ?? 0;
                $memberProgress[$member]['budget'] += $budget;
            }
        }
    
        // Calculate individual member progress percentages
        foreach ($memberProgress as $member => $details) {
            $assignedTasks = $details['assigned_tasks'];
            $completedTasks = $details['completed_tasks'];
            $memberProgress[$member]['progress'] = ($assignedTasks > 0) ? ($completedTasks / $assignedTasks) * 100 : 0;
        }
    
        // Calculate overall project progress
        $temp['project']['progress'] = ($temp['project']['totals'] > 0) 
        ? ($completedAssignments / $temp['project']['totals']) * 100 
        : 0;
        
        // Add members with their states and progress to the response
        $temp['project']['completed'] = $completedAssignments;
        $temp['project']['members'] = array_values($memberProgress);
    
        $data = $temp;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
    
    public function members() {
        $data = [];
        
        // Query members from the database
        $query = "SELECT * FROM `pm_members_tbl` ORDER BY name ASC";
        $members = $this->DB->MQ($query, "all");
        
        foreach ($members as $member) {
            $data['progress'][$member['id']] = [
                'name' => $member['name'],
                'totals' => 0,
                'progress' => 0
            ];
    
            $query = "SELECT COUNT(*) AS tasks_count FROM pm_projects_tasks_tbl WHERE JSON_CONTAINS(applies_to, '\"{$member['id']}\"')";
            $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;
            
            $query = "SELECT COUNT(*) AS progress FROM `pm_progress_tasks_tbl` WHERE result = 1 AND member_id = " . $member['id'];
            $pgs = $this->DB->MQ($query, "one")['progress'];
    
            $data['progress'][$member['id']]['totals'] += $totals;
            $data['progress'][$member['id']]['progress'] += $pgs;
        }
    
        // Modified query to group tasks by month instead of year
        $query = "SELECT 
            pm_members_tbl.id AS member_id, 
            pm_members_tbl.name AS member_name, 
            YEAR(progress_date) AS year,
            MONTH(progress_date) AS month, 
            COUNT(*) AS tasks
        FROM pm_progress_tasks_tbl 
        RIGHT JOIN pm_members_tbl ON pm_progress_tasks_tbl.member_id = pm_members_tbl.id
        WHERE result = 1
        GROUP BY pm_members_tbl.id, YEAR(progress_date), MONTH(progress_date)";
    
        $tasks = $this->DB->MQ($query, "all");
        foreach ($tasks as $task) {
            $data['monthly'][$task['year']][$task['month']][$task['member_id']] = [
                "id" => $task['member_id'],
                "name" => $task['member_name'],
                "tasks" => $task['tasks']
            ];
        }
    
        // Add all members to the data structure
        $query = "SELECT * FROM pm_members_tbl";
        $data['members'] = $this->DB->MQ($query, "all");
    
        // Add required CSS and JS files
        $this->AddJS("/vendor/raphael/raphael.js");
        $this->AddCSS("/vendor/morris/morris.css");
        $this->AddJS("/vendor/morris/morris.js");
        $this->AddCSS("/css/members_graphs.css");
        $this->AddJS("/js/members_graphs.js");
    
        // Render the data
        $this->render($data);
    }
    
    public function projects(){
        // One flat, ordered query instead of the previous four nested loops
        // (pillars -> objectives -> programmes -> projects, plus a query per
        // member per project). On 55 activities the old version issued well
        // over a hundred round trips to build the same table.
        $query = "SELECT
                      p.id            AS id,
                      p.name          AS name,
                      p.abbr          AS awp_code,
                      p.kpi           AS indicator,
                      l.name          AS lens,
                      l.abbr          AS lens_abbr,
                      o.abbr          AS wbs,
                      o.name          AS deliverable,
                      g.name          AS workstream
                  FROM pm_projects_tbl p
                  LEFT JOIN pm_pillars_tbl     l ON l.id = p.pillar_id
                  LEFT JOIN pm_objectives_tbl  o ON o.id = p.objective_id
                  LEFT JOIN pm_programmes_tbl  g ON g.id = p.programme_id
                  ORDER BY l.position, o.position, o.id, p.abbr, p.id";
        $data['activities'] = $this->DB->MQ($query, "all") ?: [];

        // Distinct values for the filter selects, taken from what is actually
        // on the page so a filter can never offer an empty result.
        $data['filters'] = ['lens' => [], 'wbs' => [], 'indicator' => []];
        foreach ($data['activities'] as $row) {
            foreach (['lens' => 'lens_abbr', 'wbs' => 'wbs', 'indicator' => 'indicator'] as $key => $col) {
                $v = trim((string)($row[$col] ?? ''));
                if ($v !== '' && !in_array($v, $data['filters'][$key], true)) {
                    $data['filters'][$key][] = $v;
                }
            }
        }
        sort($data['filters']['wbs']);
        sort($data['filters']['indicator']);

        // DataTables gives this table its search box, per-column sorting and
        // paging. It is already vendored and used by every core/db_list page.
        // /js/page_projects_graphs_projects.js is picked up automatically by
        // template.php's page_<controller>_<action>.js convention.
        $this->AddCSS("/vendor/datatables/media/css/dataTables.bootstrap5.css");
        $this->AddJS("/vendor/datatables/media/js/jquery.dataTables.min.js");
        $this->AddJS("/vendor/datatables/media/js/dataTables.bootstrap5.min.js");
        $this->render($data);
    }
}
