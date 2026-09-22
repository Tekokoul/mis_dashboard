<?php
$columns = 2;
$col_width = 12/$columns;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("projects/list")?>" ><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Edit mode</span></li>
            <li><span>Entry: <?= display($data['data']['id']); ?></span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("projects/edit_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <input type="hidden" name="back" value="<?= display($data['back'] ?? ''); ?>">
    <div class="row mb-4 afcdc-split" data-cols="<?= (int)$columns; ?>">
            <div class="col-12 afcdc-split__col">
                <section class="card card-modern mb-5">
                    <div class="card-body">
                        <div class="row">
                            <div>
                                <?php
                                // Refused save: what was typed comes back with what is missing named.
                                if (!empty($data['form_errors'])) { print '<div class="afcdc-form-errors" role="alert"><strong>Not saved.</strong> Please fill in: ' . display(implode(', ', $data['form_errors'])) . '.</div>'; }
                                // The AI's filing proposal, with Accept / Undo, and the unfinished flag - the same as in the list.
                                print allocation_review_panel($data['review'] ?? null);
                                if ((int)($_GET['merged_from'] ?? 0) > 0) { print '<div class="afcdc-review-panel afcdc-merge-panel" role="status"><div class="afcdc-review__note"><span class="afcdc-review__tag">Moved here</span> The activity you opened was merged into this one.</div></div>'; }
                                if (($_GET['unmerged'] ?? '') === '1') { print '<div class="afcdc-review-panel afcdc-merge-panel" role="status"><div class="afcdc-review__note"><span class="afcdc-review__tag">Merge undone</span> The merged activities are back, with their own codes, tasks and deliveries.</div></div>'; }
                                // Activities merged into this one, and the way back from the newest merge.
                                foreach ((array)($data['merges'] ?? []) as $mg) {
                                    $list = implode(', ', array_map(function ($x) { return '<code>' . display($x['abbr']) . '</code> ' . display($x['name']); }, $mg['merged']));
                                    print '<div class="afcdc-review-panel afcdc-merge-panel" role="' . ($mg['just'] ? 'status' : 'note') . '"><div class="afcdc-review__note">'
                                        . '<span class="afcdc-review__tag">' . ($mg['just'] ? 'Merged' : 'Merged here') . '</span> '
                                        . display(date('j M Y', strtotime($mg['merged_at']))) . ($mg['by'] !== '' ? ' by ' . display($mg['by']) : '') . ': '
                                        . $list . ' became part of this activity; their tasks and deliveries are listed with its own.'
                                        . (($mg['can_undo'] && can_vet()) ? ' <a href="#" class="afcdc-review__act" data-merge-undo="' . (int)$mg['id'] . '">Undo merge</a>' : '')
                                        . '</div></div>';
                                }
                                if (!empty($data['gaps']) && empty($data['form_errors'])) { print '<div class="afcdc-gap-panel">' . activity_gap_note($data['gaps']) . '</div>'; }
                                ?>
                                <?php
                                $html = "";
                                foreach ($data['model']['common'] as $field=>$value) {
                                    $prepopulated = $data['data'][$field] ?? "";
                                    $html .= chooseElement($field, $value, $prepopulated);
                                }
                                print $html;
                                ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-12 afcdc-split__col">
            <?php
            // An activity is reported through its tasks, so they are edited
            // HERE, in the form, and saved with everything else. They used to
            // sit in a panel beside the form that loaded separately and opened
            // a modal per task: three windows to change one word.
            $type = (string)($data['data']['type'] ?? '');
            $tasksHere = ($type === '' || $type === 'pm_projects_tasks');
            $tasks = $tasksHere ? (array)($data['tasks'] ?? []) : [];
            // Rows typed but not yet saved: only present when a refused save
            // brought the form back.
            // Anything typed comes back, name or description: keeping only the
            // named rows threw away the very row a refusal is about, leaving
            // "a name for every task" on screen with no row to name.
            $newTasks = array_values(array_filter((array)($data['data']['new_tasks'] ?? []), function ($t) { return is_array($t) && (trim((string)($t['name'] ?? '')) !== '' || trim((string)($t['description'] ?? '')) !== ''); }));
            // The Status column: for people who may record delivery, what
            // their own reporting entity says about each task, saved with the
            // form. The date, spend and comment behind it stay on Progress.
            $mayRecord = $tasksHere && !empty($data['may_record']);
            // In progress also says how far along (25, 50 or 75%) once the database carries it.
            $pctOn = $mayRecord && delivery_pct_available($this->DB);
            $statuses = [0 => 'Not started', 2 => 'In progress', 1 => 'Completed'];
            ?>
            <?php if ($tasksHere) { ?>
            <div class="card card-modern" id="afcdc-new-tasks">
                <div class="card-body">
                    <p class="afcdc-new-tasks__lead">Tasks this activity is delivered through. Change them here<?= $mayRecord ? ', set each one\'s <strong>Status</strong> as the work moves,' : ''; ?> and press <strong>Save</strong> with everything else. Remove them all and a single task, <strong>Task</strong>, is put back: an activity with no task cannot be reported on at all.</p>
                    <div class="table-responsive">
                        <table class="table table-ecommerce-simple table-borderless table-striped mb-0">
                            <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th width="38%">Name</th>
                                <th>Description</th>
                                <?php if ($mayRecord) { ?><th width="<?= $pctOn ? '25' : '16'; ?>%" data-afcdc-status-col>Status</th><?php } ?>
                                <th width="5%"><a href="#" data-add-task aria-label="Add a task" title="Add a task"><i class="bx bx-plus-medical text-3 me-2"></i></a></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $n = 0; foreach ($tasks as $t) {
                                $tid = (int)$t['id'];
                                // Any progress row locks the task, delivered or not: someone
                                // reported on it, and that record hangs off this id.
                                $reported = (int)($t['reports'] ?? 0);
                                $delivered = (int)($t['deliveries'] ?? 0);
                                $reportedNote = $reported === 1
                                    ? ($delivered === 1 ? 'A delivery has been recorded against this task, so it cannot be removed here.' : 'A progress report has been recorded against this task, so it cannot be removed here.')
                                    : $reported . ' progress reports have been recorded against this task' . ($delivered > 0 ? ' (' . $delivered . ' delivered)' : '') . ', so it cannot be removed here.';
                                $removed = !empty($t['remove']);
                                $n++; ?>
                            <tr class="afcdc-task<?= $removed ? ' afcdc-task--removed' : ''; ?>" data-task-id="<?= $tid; ?>">
                                <td class="afcdc-task__num"><?= $n; ?></td>
                                <td>
                                    <?php // data-afcdc-was, because a hidden input's defaultValue IS its value: the browser gives it no memory of what it started as, so the unsaved-changes check has nothing to compare against without this. ?>
                                    <input type="hidden" name="tasks[<?= $tid; ?>][remove]" value="<?= $removed ? '1' : '0'; ?>" data-afcdc-was="<?= $removed ? '1' : '0'; ?>">
                                    <input type="text" class="form-control form-control-sm" name="tasks[<?= $tid; ?>][name]" value="<?= display($t['name']); ?>" maxlength="250"<?= $removed ? ' readonly' : ' required'; ?>>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="tasks[<?= $tid; ?>][description]" value="<?= display($t['description'] ?? ''); ?>" placeholder="What done looks like (optional)"<?= $removed ? ' readonly' : ''; ?>></td>
                                <?php if ($mayRecord) {
                                    // NULL until this person's entity has recorded anything. "Not
                                    // recorded" is offered only then, so a record is never un-made here.
                                    $mine = $t['my_result'] ?? null;
                                    $sel = ($mine === null || $mine === '') ? '' : (string)(int)$mine;
                                    // How far along, for a task in progress. "How far?" is offered only
                                    // for a record that has no percentage yet, so none is ever un-made here.
                                    // Read as a percentage whatever the Status: after a refused save the box
                                    // keeps what was typed even if Status was moved off In progress meanwhile.
                                    $myPct = delivery_pct(2, $t['my_pct'] ?? null);
                                    $pctSel = $myPct === null ? '' : (string)$myPct; ?>
                                <td>
                                    <div class="afcdc-task__status-wrap">
                                    <select class="form-select form-select-sm afcdc-task__status" name="tasks[<?= $tid; ?>][result]" data-afcdc-was="<?= $sel; ?>" data-afcdc-status="<?= $sel; ?>" aria-label="Status of this task"<?= $removed ? ' disabled' : ''; ?>>
                                        <?php if ($sel === '') { ?><option value="" selected>Not recorded</option><?php } ?>
                                        <?php foreach ($statuses as $v => $label) { ?><option value="<?= $v; ?>"<?= $sel === (string)$v ? ' selected' : ''; ?>><?= $label; ?></option><?php } ?>
                                    </select>
                                    <?php if ($pctOn) { ?>
                                    <select class="form-select form-select-sm afcdc-task__pct" name="tasks[<?= $tid; ?>][pct]" data-afcdc-was="<?= $pctSel; ?>" aria-label="How far along this task is"<?= $sel === '2' ? '' : ' hidden'; ?><?= $removed ? ' disabled' : ''; ?>>
                                        <?php if ($pctSel === '') { ?><option value="" selected>How far?</option><?php } ?>
                                        <?php foreach (delivery_pct_values() as $v) { ?><option value="<?= $v; ?>"<?= $pctSel === (string)$v ? ' selected' : ''; ?>><?= $v; ?>%</option><?php } ?>
                                    </select>
                                    <?php } ?>
                                    </div>
                                </td>
                                <?php } ?>
                                <td>
                                    <?php if ($reported > 0) { ?>
                                        <span class="afcdc-task__kept" title="<?= $reportedNote; ?>"><i class="bx bx-lock-alt text-3 me-2" aria-hidden="true"></i><span class="sr-only"><?= $reportedNote; ?></span></span>
                                    <?php } else { ?>
                                        <?php // Rendered from the mark, so a row that comes back struck through after a refused save can still be un-marked. ?>
                                        <a href="#" data-remove-existing-task aria-label="Remove this task"<?= $removed ? ' hidden' : ''; ?>><i class="bx bx-trash text-3 me-2"></i></a>
                                        <a href="#" data-undo-remove-task aria-label="Keep this task"<?= $removed ? '' : ' hidden'; ?>>Undo</a>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($newTasks as $i => $t) { ?>
                            <tr class="afcdc-new-task">
                                <td class="afcdc-new-task__num"></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][name]" value="<?= display($t['name']); ?>" placeholder="Task name" maxlength="250"></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][description]" value="<?= display($t['description'] ?? ''); ?>" placeholder="What done looks like (optional)"></td>
                                <?php if ($mayRecord) { ?><td class="afcdc-task__status-later">after saving</td><?php } ?>
                                <td><a href="#" data-remove-task aria-label="Remove"><i class="bx bx-trash text-3 me-2"></i></a></td>
                            </tr>
                            <?php } ?>
                            <tr class="afcdc-new-tasks__empty"<?= ($tasks || $newTasks) ? ' hidden' : ''; ?>><td colspan="<?= $mayRecord ? 5 : 4; ?>" class="text-muted py-3">No task yet for this activity. Use the + above to add one.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="afcdc-new-tasks__lead mb-0"><?= $mayRecord ? 'A status set here is recorded for your reporting entity with today\'s date' . ($pctOn ? '; In progress also says how far along (25, 50 or 75%), and the overview counts the task by that share' : '') . '. The spend and comment behind it are entered on the Progress page. ' : ''; ?>A task that has been reported on is locked, so no record of somebody's work is thrown away by accident. Clear its entries on the Progress page first if it really has to go.</p>
                </div>
            </div>
            <?php } else { ?>
            <div id="project_details"></div>
            <?php } ?>
            </div>

    </div>
    <div class="row action-buttons nopadding">
        <div class="col-12 col-md-auto">
            <?php
            if(isset($data['meta_actions'])){
                foreach ($data['meta_actions'] as $action) {
                    $show_action = false;

                    if (isset($action['condition'])) {
                        if (ce_compare_values($data['data'][$action['condition']['field']], $action['condition']['operator'], $action['condition']['value'])) {
                            $show_action = true;
                        }
                    } else {
                        $show_action = true;
                    }
                    if ($show_action) {
                        print '<a href="' . $this->model->dynamic_link($action['link'], $data['data']) . '" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" target="' . $action['target'] . '">
                <i class="bx ' . $action['icon'] . ' text-4 me-2"></i> ' . $action['title'] . '
            </a>';
                    }
                }
            }
            ?>

            <?php // A sibling: the same goal, objective and programme, filled in already (projectsController::add).
                  // Only for an activity that really has a programme: an unfiled one has nothing to fill in from. ?>
            <?php if ((int)($data['data']['programme_id'] ?? 0) > 0 && !in_array('programme', array_map('strval', (array)($data['gaps'] ?? [])), true)): ?>
            <a href="<?= $this->L('projects/add'); ?>?<?= display(http_build_query(['programme_id' => (int)$data['data']['programme_id'], 'from' => 'parent', 'back' => (string)($data['back'] ?? '')])); ?>" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" title="A new activity under the same programme, with its goal, objective and programme already filled in">
                <i class="bx bx-plus-medical text-4 me-2"></i> Add another under this programme
            </a>
            <?php endif; ?>
            <?php // Administrators only (can_delete): the same confirm as the list's, but it leads back to the list afterwards. ?>
            <?php if (can_delete() && (int)($data['data']['id'] ?? 0) > 0): ?>
            <a href="#afcdc-delete-activity" class="afcdc-delete-activity btn btn-default btn-px-4 py-3 line-height-1 me-2 afcdc-btn-danger" data-id="<?= (int)$data['data']['id']; ?>" data-code="<?= display($data['data']['abbr'] ?? ''); ?>" data-afcdc-after="<?= display($data['back'] ?? $this->L('projects/list')); ?>" title="Delete this activity, its tasks and its delivery records">
                <i class="bx bx-trash text-4 me-2"></i> Delete
            </a>
            <?php endif; ?>
        </div>

        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-save text-4 me-2"></i> Update
            </button>
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?= display($data['back'] ?? $this->L('projects/list')); ?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1" data-afcdc-back title="Back to the list (Esc)">Back</a>
        </div>
    </div>
</form>
<?php if (can_delete() && (int)($data['data']['id'] ?? 0) > 0) {
    // Outside the form: the modal's buttons would otherwise submit it. What
    // goes with the activity is counted here so nobody confirms blind.
    $goneTasks = (int)($data['gone']['tasks'] ?? 0);
    $goneReports = (int)($data['gone']['deliveries'] ?? 0); ?>
<div id="afcdc-delete-activity" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <header class="card-header"><h2 class="card-title">Delete this activity?</h2></header>
        <div class="card-body">
            <div class="modal-wrapper">
                <div class="modal-icon"><i class="fas fa-question-circle"></i></div>
                <div class="modal-text">
                    <p class="mb-2"><strong><?= display(trim((string)($data['data']['abbr'] ?? '') . ' ' . (string)($data['data']['name'] ?? ''))); ?></strong></p>
                    <p class="mb-0">Its <?= $goneTasks; ?> task<?= $goneTasks === 1 ? '' : 's'; ?> and <?= $goneReports; ?> delivery record<?= $goneReports === 1 ? '' : 's'; ?> go with it. This cannot be undone.</p>
                </div>
            </div>
        </div>
        <footer class="card-footer">
            <div class="row"><div class="col-md-12 text-end">
                <button type="button" class="btn btn-danger modal-confirm">Delete</button>
                <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div></div>
        </footer>
    </section>
</div>
<?php } ?>

<script nonce="<?= csp_nonce(); ?>">
    var project_id = <?= (int)($data['data']['id'] ?? 0); ?>;
    // The add form never sets a type; every activity is the task-reported
    // kind, so an empty type still gets the Tasks panel (and the first save
    // writes the type - projectsController::ensureDefaultTask).
    var project_type = '<?= htmlspecialchars((string)(($data['data']['type'] ?? '') !== '' ? $data['data']['type'] : 'pm_projects_tasks'), ENT_QUOTES, 'UTF-8'); ?>';
</script>