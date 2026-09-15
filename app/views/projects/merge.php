<?php
// Merge activities (projectsController::merge): the ones chosen become one.
$acts     = (array)($data['activities'] ?? []);
$posted   = (array)($data['posted'] ?? []);
$sug      = (array)($data['suggested'] ?? []);
$problems = (array)($data['problems'] ?? []);
$errors   = (array)($data['form_errors'] ?? []);
$keep     = (int)($posted['keep'] ?? ($data['keep'] ?? 0));
$val = function ($k) use ($posted, $sug) {
    if (array_key_exists($k, $posted)) { return (string)$posted[$k]; }
    $v = $sug[$k] ?? '';
    if (is_float($v) || is_int($v)) { $v = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); }
    return (string)$v;
};
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L('projects/list'); ?>"><?= display($data['meta_name'] ?? 'Activities'); ?></a> &rsaquo; Merge activities</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= count($acts); ?> chosen</span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed afcdc-merge-form" action="<?= $this->L('projects/merge_update'); ?>" method="post">
    <input type="hidden" name="back" value="<?= display($data['back'] ?? ''); ?>">
    <input type="hidden" name="fingerprint" value="<?= display($data['fingerprint'] ?? ''); ?>">
    <?php foreach ($acts as $a): ?><input type="hidden" name="ids[]" value="<?= (int)$a['id']; ?>"><?php endforeach; ?>
    <div class="row mb-4">
        <div class="col-12 col-xl-7">
            <section class="card card-modern mb-4">
                <div class="card-body">
                    <?php if ($errors) { print '<div class="afcdc-form-errors" role="alert"><strong>Not merged.</strong> ' . display(implode(' ', $errors)) . '</div>'; } ?>
                    <?php if ($problems && !$errors) { print '<div class="afcdc-form-errors" role="alert"><strong>These cannot be merged.</strong> ' . display(implode(' ', $problems)) . '</div>'; } ?>
                    <h4 class="afcdc-merge__title">What is merged</h4>
                    <p class="afcdc-merge__lead">Choose the activity to keep. It keeps its code, its place and its page. The others' tasks and recorded deliveries move to it, and the others are removed. A task called "Delivered" takes the name of the activity it came from.</p>
                    <?php if (!empty($data['placements_differ'])): ?><div class="afcdc-gap-panel">These sit under different programmes. The merged activity stays under the programme of the one you keep.</div><?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-ecommerce-simple table-borderless table-striped mb-0 afcdc-merge__table">
                            <thead>
                                <tr><th scope="col">Keep</th><th scope="col">Code</th><th scope="col">Activity</th><th scope="col">Tasks after the merge</th><th scope="col">Deliveries</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($acts as $a): $id = (int)$a['id']; ?>
                                <tr>
                                    <td><input type="radio" name="keep" value="<?= $id; ?>" id="keep-<?= $id; ?>"<?= $id === $keep ? ' checked' : ''; ?> required aria-label="Keep <?= display(trim($a['abbr'] . ' ' . $a['name'])); ?>"></td>
                                    <td class="afcdc-merge__code"><label for="keep-<?= $id; ?>" class="mb-0"><?= display($a['abbr']); ?></label></td>
                                    <td>
                                        <label for="keep-<?= $id; ?>" class="mb-0"><strong><?= display($a['name']); ?></strong></label>
                                        <span class="afcdc-merge__was"><?= display(trim((string)($a['programme_abbr'] ?? '') . ' ' . (string)($a['programme_name'] ?? ''))); ?></span>
                                    </td>
                                    <td>
                                        <ul class="afcdc-merge__tasks">
                                        <?php foreach ((array)$a['tasks'] as $t): ?>
                                            <li><?= display($t['after']); ?><?php if ((string)$t['after'] !== trim((string)$t['name'])): ?><span class="afcdc-merge__was">now called "<?= display($t['name']); ?>"</span><?php endif; ?></li>
                                        <?php endforeach; ?>
                                        <?php if (!$a['tasks']): ?><li class="afcdc-merge__was">No task</li><?php endif; ?>
                                        </ul>
                                    </td>
                                    <td><?= (int)($a['deliveries'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card card-modern mb-4">
                <div class="card-body">
                    <h4 class="afcdc-merge__title">The merged activity</h4>
                    <div class="form-group pb-3">
                        <label for="merge-name" class="control-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-modern" id="merge-name" name="name" maxlength="255" required value="<?= display($val('name')); ?>">
                        <span class="afcdc-merge__hint">Proposed from the words the names share.</span>
                    </div>
                    <div class="form-group pb-3">
                        <label for="merge-description" class="control-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="merge-description" name="description" rows="6" required><?= display($val('description')); ?></textarea>
                    </div>
                    <div class="form-group pb-3">
                        <label for="merge-kpi" class="control-label">Expected task</label>
                        <input type="text" class="form-control form-control-modern" id="merge-kpi" name="kpi" maxlength="255" value="<?= display($val('kpi')); ?>">
                    </div>
                    <div class="row">
                        <div class="col-6 form-group pb-3">
                            <label for="merge-estimated" class="control-label">Estimated budget</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-modern" id="merge-estimated" name="estimated_budget" value="<?= display($val('estimated_budget')); ?>">
                        </div>
                        <div class="col-6 form-group pb-3">
                            <label for="merge-actual" class="control-label">Actual budget</label>
                            <input type="text" inputmode="decimal" class="form-control form-control-modern" id="merge-actual" name="actual_budget" value="<?= display($val('actual_budget')); ?>">
                        </div>
                    </div>
                    <span class="afcdc-merge__hint mt-0 mb-3">Budgets are the chosen activities' added together.</span>
                    <div class="form-group pb-3">
                        <label for="merge-notes" class="control-label">Notes</label>
                        <textarea class="form-control" id="merge-notes" name="notes" rows="3"><?= display($val('notes')); ?></textarea>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <div class="row action-buttons nopadding">
        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1"<?= $problems ? ' disabled' : ''; ?>>
                <i class="bx bx-git-merge text-4 me-2" aria-hidden="true"></i> Merge
            </button>
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?= display($data['back'] ?? $this->L('projects/list')); ?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1" data-afcdc-back title="Back to the list (Esc)">Back</a>
        </div>
    </div>
</form>
