<?php
require_once _CONTROLLERS_PATH."core.php";

class projects_graphsController extends coreController{
    
    public function overview() {
        $query = "SELECT id, name, abbr FROM pm_pillars_tbl ORDER BY position";
        $pillars = $this->DB->MQ($query, "all");
    
        $data = [
            'totals' => 0,     // Total assignments across all levels
            'progress' => 0,   // Overall progress percentage
            'pillars' => []    // Goal-level details
        ];
    
        $allCompleted = 0;
        foreach ($pillars as $pillar) {
            $pillarTotal = 0;
            $pillarProgress = 0;
            $pillarCompleted = 0;
    
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
                $objectiveCompleted = 0;
    
                $objectiveData = $objective;
                $objectiveData['projects'] = [];
    
                $query = "SELECT id, name FROM pm_projects_tbl WHERE objective_id = " . $objective['id'] . " AND pillar_id = " . $pillar['id'];
                $projects = $this->DB->MQ($query, "all");
    
                foreach ($projects as $project) {
                    $projectTotal = 0;
                    $projectProgress = 0;
                    $projectCompleted = 0;
    
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
    
                            // Completed counts whole ("n of m completed"); progress also counts a
                            // task in progress by its share (25/50/75%) - the percentages use that.
                            list($doneHere, $shareHere) = $this->taskDelivery((int)$task['id'], (int)$project['id'], (array)$appliesTo);
                            $projectProgress += $shareHere;
                            $projectCompleted += $doneHere;
                        }
                    }
    
                    $projectData['totals'] = $projectTotal;
                    $projectData['progress'] = ($projectTotal > 0) 
                        ? round(($projectProgress / $projectTotal) * 100, 2) 
                        : 0;
    
                    $objectiveTotal += $projectTotal;
                    $objectiveProgress += $projectProgress;
                    $objectiveCompleted += $projectCompleted;
    
                    $objectiveData['projects'][$project['id']] = $projectData;
                }
    
                $objectiveData['totals'] = $objectiveTotal;
            $objectiveData['completed'] = $objectiveCompleted;   // raw count, for "n of m delivered"
                $objectiveData['progress'] = ($objectiveTotal > 0) 
                    ? round(($objectiveProgress / $objectiveTotal) * 100, 2) 
                    : 0;
    
                $pillarTotal += $objectiveTotal;
                $pillarProgress += $objectiveProgress;
                $pillarCompleted += $objectiveCompleted;
    
                $pillarData['objectives'][$objective['id']] = $objectiveData;
            }
    
            $pillarData['totals'] = $pillarTotal;
            $pillarData['completed'] = $pillarCompleted;
            $pillarData['progress'] = ($pillarTotal > 0) 
                ? round(($pillarProgress / $pillarTotal) * 100, 2) 
                : 0;
    
            // Add goal-level data to `pillars` array
            $data['pillars'][$pillar['id']] = $pillarData;
    
            // Accumulate totals and progress for the entire data structure
            $data['totals'] += $pillarTotal;
            $data['progress'] += $pillarProgress;
            $allCompleted += $pillarCompleted;
        }
    
        // The count of finished deliveries: the view prints "n of m delivered"
        // next to every bar, which a bare 0.00% never said. The percentage
        // below also counts work in progress by its share.
        $data['completed'] = (int)$allCompleted;

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
    
        // Fetch the goal details
        $query = "SELECT * FROM pm_pillars_tbl WHERE id = " . (int)$validated['id'];
        $pillar = $this->DB->MQ($query, "one");
    
        if (!$pillar) {
            // render() has no 404 mode: the old call produced an empty 200.
            $this->setAnswer(404, "There is no goal with that id.");
            exit;
        }
    
        $data = [
            'pillar' => [
                'id' => $pillar['id'],
                'name' => $pillar['name'],
                'abbr' => $pillar['abbr'],
                'description' => $pillar['description'],
                'totals' => 0,       // Total assignments at the goal level
                'progress' => 0,     // Progress percentage at the goal level
                'objectives' => []   // Nested objectives
            ]
        ];
    
        $pillarTotal = 0;
        $pillarProgress = 0;
        $pillarCompleted = 0;
    
        // Fetch objectives linked to the goal
        // Same WBS ordering as overview() - see the note there.
        $query = "SELECT id, name, abbr FROM pm_objectives_tbl WHERE pillar_id = " . $pillar['id'] . " ORDER BY position, id";
        $objectives = $this->DB->MQ($query, "all");
    
        foreach ($objectives as $objective) {
            $objectiveTotal = 0;
            $objectiveProgress = 0;
            $objectiveCompleted = 0;
    
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
                $projectCompleted = 0;
    
                // Fetch tasks linked to the project
                $query = "SELECT id, applies_to FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
                $tasks = $this->DB->MQ($query, "all");
    
                foreach ($tasks as $task) {
                    $appliesTo = json_decode($task['applies_to'], true);
    
                    if (is_array($appliesTo) && count($appliesTo) > 0) {
                        $taskAssignments = count($appliesTo);
                        $projectTotal += $taskAssignments;
    
                        // Completed counts whole ("n of m completed"); progress also counts a
                        // task in progress by its share (25/50/75%) - the percentages use that.
                        list($doneHere, $shareHere) = $this->taskDelivery((int)$task['id'], (int)$project['id'], (array)$appliesTo);
                        $projectProgress += $shareHere;
                        $projectCompleted += $doneHere;
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
                $objectiveCompleted += $projectCompleted;
            }
    
            // Add objective data
            $objectiveData['totals'] = $objectiveTotal;
            $objectiveData['completed'] = $objectiveCompleted;   // raw count, for "n of m delivered"
            $objectiveData['progress'] = ($objectiveTotal > 0) 
                ? round(($objectiveProgress / $objectiveTotal) * 100, 2) 
                : 0;
    
            $data['pillar']['objectives'][$objective['id']] = $objectiveData;
    
            $pillarTotal += $objectiveTotal;
            $pillarProgress += $objectiveProgress;
            $pillarCompleted += $objectiveCompleted;
        }
    
        // Finalize goal data
        $data['pillar']['totals'] = $pillarTotal;
        $data['pillar']['completed'] = $pillarCompleted;
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
        if (!$objective) {
            // Without this an empty id was interpolated below and the page
            // showed "Database unavailable" for any unknown objective.
            $this->setAnswer(404, "There is no deliverable with that id.");
            exit;
        }
        $data['objective'] = $objective;
    
        $objectiveTotal = 0;
        $objectiveProgress = 0;
        $objectiveCompleted = 0;
    
        // Fetch programmes linked to the objective
        $query = "SELECT id, name, abbr FROM pm_programmes_tbl WHERE objective_id = " . (int)$objective['id'] . " ORDER BY " . coreModel::natural_order_sql('abbr');
        $programmes = $this->DB->MQ($query, "all");
        $data['programmes'] = [];
    
        foreach ($programmes as $programme) {
            $programmeTotal = 0;
            $programmeProgress = 0;
            $programmeCompleted = 0;
    
            // Fetch projects linked to the programme
            $query = "SELECT id, name, abbr FROM pm_projects_tbl WHERE programme_id = " . (int)$programme['id'] . " AND objective_id = " . (int)$objective['id'] . " ORDER BY " . coreModel::natural_order_sql('abbr');
            $projects = $this->DB->MQ($query, "all");
            $data['programmes'][$programme['id']] = [
                "id" => $programme['id'],
                "name" => $programme['name'],
                "projects" => []
            ];
    
            foreach ($projects as $project) {
                list($projectTotal, $projectProgress, $projectCompleted) = $this->activityProgress((int)$project['id']);
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
                $programmeCompleted += $projectCompleted;
            }
    
            // Calculate programme progress as a percentage
            $programmeProgressPercentage = ($programmeTotal > 0) ? round(($programmeProgress / $programmeTotal) * 100, 2) : 0;
    
            // Add programme data to the objective
            $data['programmes'][$programme['id']]['totals'] = $programmeTotal;
            $data['programmes'][$programme['id']]['progress'] = $programmeProgressPercentage;
    
            $objectiveTotal += $programmeTotal;
            $objectiveProgress += $programmeProgress;
            $objectiveCompleted += $programmeCompleted;
        }

        // Activities of this objective filed under a programme that belongs
        // to ANOTHER objective (or under none). The overview counts them by
        // objective id, so this page must too - otherwise "Data Centre" reads
        // 44% on the overview and 0% here, with its nine activities unreachable.
        $known = [];
        foreach ((array)$programmes as $programme) { $known[] = (int)$programme['id']; }
        $query = "SELECT p.id, p.name, p.abbr, g.name AS programme_name FROM pm_projects_tbl p"
               . " LEFT JOIN pm_programmes_tbl g ON g.id = p.programme_id"
               . " WHERE p.objective_id = " . (int)$objective['id']
               . ($known ? " AND (p.programme_id IS NULL OR p.programme_id NOT IN (" . implode(",", $known) . "))" : "")
               . " ORDER BY " . coreModel::natural_order_sql('p.abbr');
        $otherTotal = 0; $otherProgress = 0; $otherCompleted = 0; $data['other_projects'] = [];
        foreach ((array)$this->DB->MQ($query, "all") as $project) {
            list($t, $d, $dc) = $this->activityProgress((int)$project['id']);
            $data['other_projects'][] = [
                "id" => (int)$project['id'], "name" => $project['name'], "abbr" => $project['abbr'],
                "programme_name" => $project['programme_name'],
                "totals" => $t, "completed" => $dc, "progress" => ($t > 0) ? round(($d / $t) * 100, 2) : 0,
            ];
            $otherTotal += $t; $otherProgress += $d; $otherCompleted += $dc;
        }
        $data['other'] = ["totals" => $otherTotal, "completed" => $otherCompleted, "progress" => ($otherTotal > 0) ? round(($otherProgress / $otherTotal) * 100, 2) : 0];
        $data['gaps'] = activity_gaps_for($this->DB, array_column($data['other_projects'], 'id'));
        $objectiveTotal += $otherTotal;
        $objectiveProgress += $otherProgress;
        $objectiveCompleted += $otherCompleted;
    
        // Calculate objective progress as a percentage
        $objectiveProgressPercentage = ($objectiveTotal > 0) ? round(($objectiveProgress / $objectiveTotal) * 100, 2) : 0;
    
        // Finalize objective data
        $data['objective']['totals'] = $objectiveTotal;
        $data['objective']['completed'] = $objectiveCompleted;
        $data['objective']['progress'] = $objectiveProgressPercentage;
    
        $this->AddJS("/vendor/gauge/gauge.js");
        $this->AddJS("/js/graphs.js");
        $this->render($data);
    }
  
    /**
     * [assignments, progress, completed] for one activity: each task counts
     * once per reporting entity it applies to; completed = finished
     * deliveries, progress = the same plus work in progress by its share
     * (taskDelivery).
     */
    private function activityProgress($projectId) {
        $total = 0; $done = 0.0; $completed = 0;
        $tasks = $this->DB->MQ("SELECT id, applies_to FROM pm_projects_tasks_tbl WHERE project_id = " . (int)$projectId, "all");
        foreach ((array)$tasks as $task) {
            $appliesTo = json_decode((string)$task['applies_to'], true);
            if (!is_array($appliesTo) || count($appliesTo) === 0) { continue; }
            $members = array_map('intval', $appliesTo);
            $total += count($members);
            list($c, $share) = $this->taskDelivery((int)$task['id'], (int)$projectId, $members);
            $done += $share; $completed += $c;
        }
        return [$total, $done, $completed];
    }

    /**
     * [completed, progress] for one task and the reporting entities it applies
     * to. completed counts finished deliveries (for "n of m completed");
     * progress also counts a task in progress by its share - 25, 50 or 75% -
     * and is what every percentage and bar on these pages is made of
     * (library: delivery_weight_sql). The entity ids are reduced to positive
     * integers here, whatever the caller passed.
     */
    private function taskDelivery($taskId, $projectId, array $members) {
        $members = array_values(array_filter(array_map('intval', $members), function ($v) { return $v > 0; }));
        if (!$members) { return [0, 0.0]; }
        $r = $this->DB->MQ("SELECT IFNULL(SUM(`result` = 1), 0) AS completed, IFNULL(SUM(" . delivery_weight_sql($this->DB) . "), 0) AS progress
                              FROM pm_progress_tasks_tbl WHERE task_id = ? AND project_id = ? AND member_id IN (" . implode(',', $members) . ")", "one", [(int)$taskId, (int)$projectId]);
        return [(int)($r['completed'] ?? 0), (float)($r['progress'] ?? 0)];
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
        if (!$programme) {
            $this->setAnswer(404, "There is no workstream with that id.");
            exit;
        }
        $data['programme'] = $programme;
    
        $programmeTotal = 0;
        $programmeProgress = 0;
        $programmeCompleted = 0;
    
        // Fetch projects linked to the programme
        $query = "SELECT id, name, abbr FROM pm_projects_tbl WHERE programme_id = " . (int)$validated['id'] . " AND objective_id = " . (int)$programme['objective_id'] . " ORDER BY " . coreModel::natural_order_sql('abbr');
        $projects = $this->DB->MQ($query, "all");
        $data['projects'] = [];
        $data['gaps'] = activity_gaps_for($this->DB, array_column((array)$projects, 'id'));
    
        foreach ($projects as $project) {
            $projectTotal = 0;
            $projectProgress = 0;
            $projectCompleted = 0;
    
            // Fetch tasks linked to the project
            $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id = " . $project['id'];
            $tasks = $this->DB->MQ($query, "all");
    
            foreach ($tasks as $task) {
                $appliesTo = json_decode($task['applies_to'], true);
    
                if (is_array($appliesTo) && count($appliesTo) > 0) {
                    $taskAssignments = count($appliesTo);
                    $projectTotal += $taskAssignments;
    
                    // Create a query to get progress per division user
                    // Completed counts whole ("n of m completed"); progress also counts a
                    // task in progress by its share (25/50/75%) - the percentages use that.
                    list($doneHere, $shareHere) = $this->taskDelivery((int)$task['id'], (int)$project['id'], (array)$appliesTo);
                    $projectProgress += $shareHere;
                    $projectCompleted += $doneHere;
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
            $programmeCompleted += $projectCompleted;
        }
    
        // Calculate programme progress as a percentage
        $programmeProgressPercentage = ($programmeTotal > 0) ? round(($programmeProgress / $programmeTotal) * 100, 2) : 0;
    
        // Finalize programme data
        $data['programme']['totals'] = $programmeTotal;
        $data['programme']['completed'] = $programmeCompleted;
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
        if (!$project) {
            // Merged into another activity: open that one instead.
            if (($to = merge_forwarding($this->DB, (int)$validated['id'])) > 0) { redirect($this->L("projects_graphs/project/" . $to)); }
            $this->setAnswer(404, "There is no activity with that id.");
            exit;
        }

        // Fetch all tasks for the project
        $query = "SELECT * FROM pm_projects_tasks_tbl WHERE project_id=" . (int)$project['id'];
        $tasks = $this->DB->MQ($query, "all");
    
        $temp['project'] = $project;
        $temp['project']['tasks'] = $tasks;
        $temp['project']['totals'] = 0; // Total tasks * assignees
        $temp['project']['progress'] = 0; // Overall project progress
        $temp['project']['members'] = []; // Members progress and budget details
    
        $memberProgress = []; // To track progress per member across tasks
        $totalAssignments = 0; // Total assignments (tasks × assignees)
        $completedAssignments = 0; // Total completed assignments (for "n of m completed")
        $weightedAssignments = 0;  // The same, with work in progress counted by its share (for the percentages)
        // Each task with its own count, so the page can show what is left to
        // deliver task by task rather than only per reporting entity.
        $taskRows = [];
    
        // Loop through tasks and process their `applies_to`
        foreach ($tasks as $task) {
            $taskDone = 0;
            $taskShare = 0;
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
                        'share' => 0,       // completed + in progress by its share
                        'progress' => 0, // Division user progress percentage
                        'budget' => 0
                    ];
                }
    
                // Increment the member's assigned task count
                $memberProgress[$member]['assigned_tasks']++;
    
                // This entity's record for the task: completed counts whole, in progress
                // by its share (25/50/75%) for the percentages; one assignment counts at most once.
                list($doneHere, $shareHere) = $this->taskDelivery((int)$task['id'], (int)$project['id'], [$member]);
                $shareHere = min(1.0, $shareHere);
                $memberProgress[$member]['share'] += $shareHere;
                $weightedAssignments += $shareHere;
                $taskShare += $shareHere;
                if ($doneHere > 0) {
                    $memberProgress[$member]['completed_tasks']++;
                    $completedAssignments++;
                    $taskDone++;
                }
    
                // Fetch budget for the member for this task
                $query = "SELECT SUM(actual_budget) as budget 
                          FROM pm_progress_tasks_tbl 
                          WHERE member_id = " . $member . " AND project_id = " . $project['id'] . " AND task_id = " . $task['id'];
                $budget = $this->DB->MQ($query, "one")['budget'] ?? 0;
                $memberProgress[$member]['budget'] += $budget;
            }
            $taskRows[] = [
                'id'          => (int)$task['id'],
                'name'        => (string)$task['name'],
                'description' => (string)($task['description'] ?? ''),
                'assignments' => $taskAssignments,
                'completed'   => $taskDone,
                'progress'    => $taskAssignments > 0 ? round($taskShare / $taskAssignments * 100, 2) : 0,
            ];
        }
        $temp['project']['tasks'] = $taskRows;
    
        // Calculate individual member progress percentages
        foreach ($memberProgress as $member => $details) {
            $assignedTasks = $details['assigned_tasks'];
            $completedTasks = $details['completed_tasks'];
            $memberProgress[$member]['progress'] = ($assignedTasks > 0) ? ($details['share'] / $assignedTasks) * 100 : 0;
        }
    
        // Calculate overall project progress
        $temp['project']['progress'] = ($temp['project']['totals'] > 0) 
        ? ($weightedAssignments / $temp['project']['totals']) * 100 
        : 0;
        
        // Add division users with their states and progress to the response
        $temp['project']['completed'] = $completedAssignments;
        $temp['project']['members'] = array_values($memberProgress);
    
        $data = $temp;
        // Merges into this activity, with Undo for those who decide merges:
        // Executives land here after a merge (they may not open the edit form).
        $data['merges'] = merge_history($this->DB, (int)$project['id'], (int)($this->query['merged'] ?? 0));
    
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
                'progress' => 0,
                'completed' => 0   // finished only, for the "Completed" bar; progress also counts work in progress by its share
            ];
    
            $query = "SELECT COUNT(*) AS tasks_count FROM pm_projects_tasks_tbl WHERE JSON_CONTAINS(applies_to, '\"{$member['id']}\"')";
            $totals = $this->DB->MQ($query, "one")['tasks_count'] ?? 0;
            
            // Finished deliveries, plus work in progress by its share (25/50/75%).
            $query = "SELECT IFNULL(SUM(" . delivery_weight_sql($this->DB) . "), 0) AS progress, IFNULL(SUM(`result` = 1), 0) AS completed FROM `pm_progress_tasks_tbl` WHERE member_id = " . (int)$member['id'];
            $row = $this->DB->MQ($query, "one");
            $pgs = (float)($row['progress'] ?? 0);
            $data['progress'][$member['id']]['completed'] += (int)($row['completed'] ?? 0);
    
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
        // (goals -> objectives -> programmes -> projects, plus a query per
        // division user per project). On 55 activities the old version issued well
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
                  ORDER BY l.position, o.position, o.id, " . coreModel::natural_order_sql('p.abbr') . ", p.id";
        $data['activities'] = $this->DB->MQ($query, "all") ?: [];
        $data['gaps'] = activity_gaps_for($this->DB, array_column($data['activities'], 'id'));

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
