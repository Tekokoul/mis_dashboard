<?php
$columns = 2;
$col_width = 12/$columns;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("projects/list")?>" ><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Add mode</span></li>
            <li><span><?= !empty($data['saved']) ? 'Another one, after ' . display($data['saved']['abbr']) : 'New entry'; ?></span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("projects/add_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <input type="hidden" name="back" value="<?= display($data['back'] ?? ''); ?>">
    <?php if (!empty($data['filed_from_parent'])): ?><input type="hidden" name="filed_from_parent" value="1"><?php endif; ?>
    <?php if (!empty($data['saved'])): ?>
    <?php // "Save and add another" landed here: what was saved, and what this form already knows. ?>
    <div id="afcdc-saved" class="alert alert-success py-2 mb-3" role="status">Saved <strong><?= display($data['saved']['abbr']); ?></strong> <?= display($data['saved']['name']); ?> (<a href="<?= $this->L('projects/edit/' . (int)$data['saved']['id']); ?>?back=<?= rawurlencode((string)($data['back'] ?? '')); ?>">open it</a>). <span data-afcdc-prefill>Goal, objective, programme and the next code are filled in from it; change them if this one belongs elsewhere.</span></div>
    <?php endif; ?>
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
            // task "Task" is created.
            // Anything typed comes back, name or description: keeping only the
            // named rows threw away the very row a refusal is about, leaving
            // "a name for every task" on screen with no row to name.
            $newTasks = array_values(array_filter((array)($data['data']['new_tasks'] ?? []), function ($t) { return is_array($t) && (trim((string)($t['name'] ?? '')) !== '' || trim((string)($t['description'] ?? '')) !== ''); }));
            ?>
            <div class="card card-modern" id="afcdc-new-tasks">
                <div class="card-body">
                    <p class="afcdc-new-tasks__lead">Tasks this activity is delivered through. Leave empty and a single task, <strong>Task</strong>, is created with it.</p>
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
            <?php // Update stays the form's FIRST submit button: Enter in a box presses the first one, and Enter must still mean a plain save. ?>
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-save text-4 me-2"></i> Update
            </button>
        </div>
        <div class="col-12 col-md-auto mt-3 mt-md-0">
            <?php // Saves this one, then the same form again under the same programme (projectsController::add_update). ?>
            <button type="submit" name="after_save" value="another" class="submit-button btn btn-default btn-px-4 py-3 d-flex align-items-center line-height-1" data-loading-text="Loading..." title="Save this activity, then start another under the same programme">
                <i class="bx bx-plus-medical text-4 me-2"></i> Save and add another
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
    // After "Save and add another": the cursor goes to Name, with the strip
    // read out alongside it (a status present at load is not announced on
    // its own), and ?saved= leaves the address so a reload does not announce
    // the same save twice. The rest of the address stays - it is what
    // pre-fills the form. No jQuery here: this runs before the libraries
    // load; what needs them (the strip following a moved box) is in
    // pm_projects.js.
    (function () {
        if (!document.getElementById('afcdc-saved')) { return; }
        var name = document.querySelector('form.ecommerce-form input[name="name"]');
        if (name) { name.setAttribute('aria-describedby', 'afcdc-saved'); name.focus(); }
        if (!history.replaceState) { return; }
        var q = location.search.replace(/[?&]saved=\d+(?=&|$)/, '').replace(/^&/, '?');
        history.replaceState(null, '', location.pathname + q);
    })();
</script>