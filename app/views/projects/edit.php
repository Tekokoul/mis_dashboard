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
    <div class="row mb-4">
            <div class="col col-lg-<?=$col_width;?> col-md-12">
                <section class="card card-modern mb-5">
                    <div class="card-body">
                        <div class="row">
                            <div>
                                <?php
                                // Refused save: what was typed comes back with what is missing named.
                                if (!empty($data['form_errors'])) { print '<div class="afcdc-form-errors" role="alert"><strong>Not saved.</strong> Please fill in: ' . display(implode(', ', $data['form_errors'])) . '.</div>'; }
                                // The AI's filing proposal, with Accept / Undo, and the unfinished flag - the same as in the list.
                                print allocation_review_panel($data['review'] ?? null);
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
            <div class="col col-lg-<?=$col_width;?> col-md-12">
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
            $newTasks = array_values(array_filter((array)($data['data']['new_tasks'] ?? []), function ($t) { return is_array($t) && trim((string)($t['name'] ?? '')) !== ''; }));
            ?>
            <?php if ($tasksHere) { ?>
            <div class="card card-modern" id="afcdc-new-tasks">
                <div class="card-body">
                    <p class="afcdc-new-tasks__lead">Tasks this activity is delivered through. Change them here and press <strong>Save</strong> with everything else.</p>
                    <div class="table-responsive">
                        <table class="table table-ecommerce-simple table-borderless table-striped mb-0">
                            <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th width="38%">Name</th>
                                <th>Description</th>
                                <th width="5%"><a href="#" data-add-task aria-label="Add a task" title="Add a task"><i class="bx bx-plus-medical text-3 me-2"></i></a></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $n = 0; foreach ($tasks as $t) { $tid = (int)$t['id']; $delivered = (int)($t['deliveries'] ?? 0); $removed = !empty($t['remove']); $n++; ?>
                            <tr class="afcdc-task<?= $removed ? ' afcdc-task--removed' : ''; ?>" data-task-id="<?= $tid; ?>">
                                <td class="afcdc-task__num"><?= $n; ?></td>
                                <td>
                                    <input type="hidden" name="tasks[<?= $tid; ?>][remove]" value="<?= $removed ? '1' : '0'; ?>">
                                    <input type="text" class="form-control form-control-sm" name="tasks[<?= $tid; ?>][name]" value="<?= display($t['name']); ?>" maxlength="250"<?= $removed ? '' : ' required'; ?>>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="tasks[<?= $tid; ?>][description]" value="<?= display($t['description'] ?? ''); ?>" placeholder="What done looks like (optional)"></td>
                                <td>
                                    <?php if ($delivered > 0) { ?>
                                        <span class="afcdc-task__kept" title="<?= $delivered; ?> deliver<?= $delivered === 1 ? 'y has' : 'ies have'; ?> been recorded against this task, so it cannot be removed here."><i class="bx bx-lock-alt text-3 me-2" aria-hidden="true"></i><span class="sr-only">Kept: <?= $delivered; ?> recorded</span></span>
                                    <?php } else { ?>
                                        <a href="#" data-remove-existing-task aria-label="Remove this task"><i class="bx bx-trash text-3 me-2"></i></a>
                                        <a href="#" data-undo-remove-task aria-label="Keep this task" hidden>Undo</a>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                            <?php foreach ($newTasks as $i => $t) { ?>
                            <tr class="afcdc-new-task">
                                <td class="afcdc-new-task__num"></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][name]" value="<?= display($t['name']); ?>" placeholder="Task name" maxlength="250"></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][description]" value="<?= display($t['description'] ?? ''); ?>" placeholder="What done looks like (optional)"></td>
                                <td><a href="#" data-remove-task aria-label="Remove"><i class="bx bx-trash text-3 me-2"></i></a></td>
                            </tr>
                            <?php } ?>
                            <tr class="afcdc-new-tasks__empty"<?= ($tasks || $newTasks) ? ' hidden' : ''; ?>><td colspan="4" class="text-muted py-3">No task yet for this activity. Use the + above to add one.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="afcdc-new-tasks__lead mb-0">A task with a recorded delivery is locked, so nothing that has been reported can be thrown away by accident. Remove its deliveries first if it really has to go.</p>
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

            <!--            <a href="#" class="delete-button btn btn-danger btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1">-->
            <!--                <i class="bx bx-trash text-4 me-2"></i> Delete Product-->
            <!--            </a>-->
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

<script nonce="<?= csp_nonce(); ?>">
    var project_id = <?= (int)($data['data']['id'] ?? 0); ?>;
    // The add form never sets a type; every activity is the task-reported
    // kind, so an empty type still gets the Tasks panel (and the first save
    // writes the type - projectsController::ensureDefaultTask).
    var project_type = '<?= htmlspecialchars((string)(($data['data']['type'] ?? '') !== '' ? $data['data']['type'] : 'pm_projects_tasks'), ENT_QUOTES, 'UTF-8'); ?>';
</script>