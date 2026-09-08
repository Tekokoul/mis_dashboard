<?php
$columns = 2;
$col_width = 12/$columns;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("projects/list")?>" ><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Add mode</span></li>
            <li><span>New entry</span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("projects/add_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <input type="hidden" name="back" value="<?= display($data['back'] ?? ''); ?>">
    <?php if (!empty($data['filed_from_parent'])): ?><input type="hidden" name="filed_from_parent" value="1"><?php endif; ?>
    <div class="row mb-4">
        <div class="col col-lg-<?=$col_width;?> col-md-12">
            <section class="card card-modern mb-5">
                <div class="card-body">
                    <div class="row">
                        <div>
                            <?php
                            // Refused save: what was typed comes back with what is missing named.
                            if (!empty($data['form_errors'])) { print '<div class="afcdc-form-errors" role="alert"><strong>Not saved.</strong> Please fill in: ' . display(implode(', ', $data['form_errors'])) . '.</div>'; }
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
            // Tasks can be typed before the activity exists: they are created
            // with it (projectsController::add_update). With none, a single
            // task "Delivered" is created, as before.
            $newTasks = array_values(array_filter((array)($data['data']['new_tasks'] ?? []), function ($t) { return is_array($t) && trim((string)($t['name'] ?? '')) !== ''; }));
            ?>
            <div class="card card-modern" id="afcdc-new-tasks">
                <div class="card-body">
                    <p class="afcdc-new-tasks__lead">Tasks this activity is delivered through. Leave empty and a single task, <strong>Delivered</strong>, is created with it.</p>
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
                            <?php foreach ($newTasks as $i => $t): ?>
                            <tr class="afcdc-new-task">
                                <td class="afcdc-new-task__num"><?= $i + 1; ?></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][name]" value="<?= display($t['name']); ?>" placeholder="Task name" maxlength="250"></td>
                                <td><input type="text" class="form-control form-control-sm" name="new_tasks[<?= $i; ?>][description]" value="<?= display($t['description'] ?? ''); ?>" placeholder="What done looks like (optional)"></td>
                                <td><a href="#" data-remove-task aria-label="Remove"><i class="bx bx-trash text-3 me-2"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="afcdc-new-tasks__empty"<?= $newTasks ? ' hidden' : ''; ?>><td colspan="4" class="text-muted py-3">No task yet. Use the + above to add one; you can also add them after saving.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
    var project_id = 0;
    var project_type = 'pm_projects_milestones';
    // After a refused save the goal / objective / programme that were chosen
    // come back; the cascade in pm_projects.js keeps them when it reloads.
    <?php if (!empty($data['form_errors'])) { print 'window.afcdcPreselect = ' . json_encode(['objective_id' => (string)(int)($data['data']['objective_id'] ?? 0), 'programme_id' => (string)(int)($data['data']['programme_id'] ?? 0)]) . ';'; } ?>
</script>