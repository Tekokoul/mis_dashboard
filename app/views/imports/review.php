<?php
$batch = $data['batch']; $counts = $data['counts']; $cat = $data['cat']; $show = $data['show']; $canAct = !empty($data['can_act']);
$batchId = (int)$batch['id'];
$tab = function ($key, $label, $n) use ($show, $batchId) {
    $active = $show === $key;
    return '<a class="afcdc-import__tab' . ($active ? ' is-active' : '') . '" href="' . $this->L('imports/review/' . $batchId) . '?show=' . $key . '"' . ($active ? ' aria-current="page"' : '') . '>' . display($label) . ' <span class="afcdc-import__n">' . (int)$n . '</span></a>';
};
$rowClass = function (array $r) {
    $status = (string)$r['status']; $kind = (string)$r['kind']; $conf = (string)$r['confidence'];
    if ($status === 'accepted') { return 'afcdc-review afcdc-review--accepted'; }
    if ($status === 'skipped') { return 'afcdc-row--inactive'; }
    if ($status !== 'pending') { return ''; }
    // Red is for a question or a weak guess. A row matched by code or by name
    // was never guessed at - it carries no proposal and so keeps the default
    // confidence "none", which used to paint every plain update red and left
    // the band saying the opposite of the legend above it.
    if ($kind === 'unclear') { return 'afcdc-review afcdc-review--proposed afcdc-review--low'; }
    if ($kind === 'changed') { return 'afcdc-review afcdc-review--proposed afcdc-review--agreed'; }
    if (in_array($conf, ['split', 'low', 'none'], true)) { return 'afcdc-review afcdc-review--proposed afcdc-review--low'; }
    return 'afcdc-review afcdc-review--proposed afcdc-review--agreed';
};
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("imports/list"); ?>" data-afcdc-back><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= display($batch['filename']); ?></span></li>
            <li><span>sheet <?= display($batch['sheet']); ?>, read <?= display(substr((string)$batch['uploaded_at'], 0, 16)); ?></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <div class="datatable-header afcdc-sticky">
                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-lg">
                                <nav class="afcdc-import__tabs" aria-label="Rows by state">
                                    <?= $tab('pending', 'Pending', $counts['status']['pending']); ?>
                                    <?= $tab('accepted', 'Accepted', $counts['status']['accepted']); ?>
                                    <?= $tab('skipped', 'Skipped', $counts['status']['skipped']); ?>
                                    <?= $tab('unchanged', 'Already in the system', $counts['status']['unchanged']); ?>
                                    <?= $tab('all', 'All', $counts['total']); ?>
                                </nav>
                            </div>
                            <div class="col-12 col-lg-auto afcdc-import__tools">
                                <?php if ($canAct && $counts['agreed'] > 0 && empty($counts['incomplete'])) { ?>
                                    <a href="#" class="btn btn-sm btn-light border afcdc-review__all" data-import-action="accept_all" data-id="<?= $batchId; ?>">Accept all agreed (<?= (int)$counts['agreed']; ?>)</a>
                                <?php } ?>
                                <?php if ($canAct && $counts['status']['accepted'] === 0) { ?>
                                    <a href="#" class="btn btn-sm btn-light border" data-import-action="discard" data-id="<?= $batchId; ?>">Discard this import</a>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if (!empty($counts['incomplete'])) { ?>
                            <div class="afcdc-gap-panel" role="alert"><strong>This import did not finish reading.</strong>
                                The workbook offered <?= (int)$counts['expected']; ?> activities and only <?= (int)$counts['total']; ?> were recorded, so what you see below is part of a sheet.
                                Discard it and read the workbook again; nothing here can be accepted.</div>
                        <?php } ?>
                        <?php if (trim((string)$batch['note']) !== '') { ?>
                            <div class="afcdc-gap-panel"><strong>While reading the sheet:</strong> <?= nl2br(display($batch['note'])); ?></div>
                        <?php } ?>
                        <p class="afcdc-import__lead">
                            <?php if ($counts['status']['pending'] > 0 && empty($counts['incomplete'])) { ?>
                                Each pending row says what the catalogue knows about it and where a new one would go. Change the boxes if the proposal is wrong, then <strong>Accept</strong>; <strong>Skip</strong> leaves the catalogue alone. A yellow band is a proposal, a red one a question or a weak guess.
                            <?php } else { ?>
                                Nothing is waiting on this import.
                            <?php } ?>
                        </p>
                    </div>
                    <?php if (!$data['rows']) { ?>
                        <p class="afcdc-import__lead">No rows in this view.</p>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-ecommerce-simple table-borderless table-striped mb-0 afcdc-import__table" id="datatable-list" style="min-width: 760px;">
                            <thead>
                            <tr>
                                <th width="5%" class="afcdc-col-num">Row</th>
                                <th width="9%">Code</th>
                                <th width="44%">Activity</th>
                                <th width="30%">Placement</th>
                                <th width="12%" class="afcdc-col-actions">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['rows'] as $r) {
                                $rid = (int)$r['id']; $pending = (string)$r['status'] === 'pending'; $kind = (string)$r['kind'];
                                $needsPlace = $pending && empty($counts['incomplete']) && in_array($kind, ['new', 'unclear'], true);
                                $extra = (array)$r['extra_data'];
                                $trClass = $rowClass($r);
                                ?>
                                <tr<?= $trClass !== '' ? ' class="' . $trClass . '"' : ''; ?> data-import-row="<?= $rid; ?>">
                                    <td class="afcdc-col-num"><?= (int)$r['row_no']; ?></td>
                                    <td><code><?= display($r['code']); ?></code></td>
                                    <td class="afcdc-cell-name">
                                        <strong><?= display($r['name']); ?></strong>
                                        <?php
                                        // An activity cannot be saved without a description. When the
                                        // workbook gave none, one is put together from what it did give
                                        // and offered for approval - never used unless this row is
                                        // accepted, and never presented as the workbook's own words.
                                        $fields = import_row_fields($r);
                                        $desc = $fields['description'];
                                        if ($needsPlace && $canAct) { ?>
                                            <div class="afcdc-import__descedit<?= $desc['suggested'] ? ' afcdc-import__descedit--suggested' : ''; ?>">
                                                <label for="afcdc-desc-<?= $rid; ?>">
                                                    Description
                                                    <?php if ($desc['suggested'] && $desc['have'] !== '') { ?>
                                                        <span class="afcdc-import__needs">the workbook gave none &mdash; this was put together from its other columns, so read it before accepting</span>
                                                    <?php } elseif ($desc['suggested']) { ?>
                                                        <span class="afcdc-import__needs afcdc-import__needs--empty">the workbook gave none and there was nothing to compose one from &mdash; this row needs one written</span>
                                                    <?php } ?>
                                                </label>
                                                <textarea id="afcdc-desc-<?= $rid; ?>" class="form-control form-control-sm afcdc-import__desc-input" rows="2" maxlength="4000" data-id="<?= $rid; ?>" placeholder="What this activity is"><?= display($desc['have']); ?></textarea>
                                            </div>
                                        <?php } elseif (trim((string)$r['description']) !== '') { ?>
                                            <div class="afcdc-import__desc"><?= display(mb_substr((string)$r['description'], 0, 220)); ?><?= mb_strlen((string)$r['description']) > 220 ? '…' : ''; ?></div>
                                        <?php } ?>
                                        <div class="afcdc-import__facts">
                                            <?php
                                            $facts = [];
                                            if (trim((string)$r['kpi']) !== '') { $facts[] = 'Indicator ' . display($r['kpi']); }
                                            if ($r['budget'] !== null && $r['budget'] !== '') { $facts[] = 'Budget ' . number_format((float)$r['budget'], 0, '.', ','); }
                                            $when = display(trim((string)($extra['quarter'] ?? '')));
                                            if (($extra['start'] ?? '') !== '' || ($extra['finish'] ?? '') !== '') { $when = trim($when . ' ' . display($extra['start'] ?? '?') . ' to ' . display($extra['finish'] ?? '?')); }
                                            if ($when !== '') { $facts[] = $when; }
                                            if (($extra['owner'] ?? '') !== '') { $facts[] = 'Owner ' . display($extra['owner']); }
                                            if (($extra['wb_objective'] ?? '') !== '') { $facts[] = 'Under "' . display(trim((string)($extra['wb_wbs'] ?? '') . ' ' . (string)$extra['wb_objective'])) . '" in the workbook'; }
                                            print implode(' · ', $facts);
                                            ?>
                                        </div>
                                        <?= import_row_note($r, $cat, $canAct); ?>
                                    </td>
                                    <td>
                                        <?php if ($needsPlace && $canAct) {
                                            $selO = (int)$r['sug_objective_id']; $selP = (int)$r['sug_programme_id'];
                                            ?>
                                            <label class="afcdc-import__box"><span>Objective</span>
                                                <select class="form-select form-select-sm afcdc-import__obj" name="objective_id" data-id="<?= $rid; ?>" data-afcdc-was="<?= $selO; ?>">
                                                    <option value="">Choose…</option>
                                                    <?php foreach ($data['objectives'] as $o) { ?><option value="<?= (int)$o['id']; ?>"<?= (int)$o['id'] === $selO ? ' selected' : ''; ?>><?= display(trim((string)$o['abbr'] . ' ' . (string)$o['name'])); ?></option><?php } ?>
                                                </select>
                                            </label>
                                            <label class="afcdc-import__box"><span>Programme</span>
                                                <select class="form-select form-select-sm afcdc-import__prg" name="programme_id" data-id="<?= $rid; ?>" data-afcdc-was="<?= $selP; ?>">
                                                    <option value="">Choose…</option>
                                                    <?php foreach ((array)($data['programmes'][$selO] ?? []) as $p) { ?><option value="<?= (int)$p['id']; ?>"<?= (int)$p['id'] === $selP ? ' selected' : ''; ?>><?= display(trim((string)$p['abbr'] . ' ' . (string)$p['name'])); ?></option><?php } ?>
                                                </select>
                                            </label>
                                            <?php // The number the activity will carry, which follows the programme it goes under. ?>
                                            <div class="afcdc-import__code">Will be numbered <code class="afcdc-import__code-value" data-id="<?= $rid; ?>"><?= display((string)($data['codes'][$rid] ?? '—')); ?></code></div>
                                        <?php } elseif ($needsPlace) { ?>
                                            <?= display(import_place_label($cat, $r['sug_objective_id'], $r['sug_programme_id'])); ?>
                                        <?php } elseif ((int)$r['result_project_id'] > 0) { ?>
                                            <?php $x = $cat['activities'][(int)$r['result_project_id']] ?? null; ?>
                                            <?= $x ? display(import_place_label($cat, $x['objective_id'], $x['programme_id'])) : '—'; ?>
                                        <?php } elseif ((int)$r['match_project_id'] > 0) { ?>
                                            <?= display(import_place_label($cat, $r['match_objective_id'] ?? 0, $r['match_programme_id'] ?? 0)); ?>
                                        <?php } else { ?>
                                            —
                                        <?php } ?>
                                    </td>
                                    <td class="afcdc-col-actions">
                                        <?php if ($needsPlace && $canAct) { ?>
                                            <a href="#" class="btn btn-sm btn-primary afcdc-import__accept" data-import-action="accept" data-id="<?= $rid; ?>" title="Create the activity where the boxes say"><?= $kind === 'unclear' ? 'Accept as new' : 'Accept'; ?></a>
                                            <a href="#" class="btn btn-sm btn-light border" data-import-action="skip" data-id="<?= $rid; ?>" title="Leave the catalogue alone">Skip</a>
                                        <?php } elseif ((int)$r['result_project_id'] > 0) { ?>
                                            <a href="<?= $this->L('projects/edit/' . (int)$r['result_project_id']); ?>" class="btn btn-sm btn-light border" title="Open the activity"><i class="bx bx-edit" aria-hidden="true"></i></a>
                                        <?php } elseif ((int)$r['match_project_id'] > 0) { ?>
                                            <a href="<?= $this->L('projects/edit/' . (int)$r['match_project_id']); ?>" class="btn btn-sm btn-light border" title="Open the matching activity"><i class="bx bx-edit" aria-hidden="true"></i></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
