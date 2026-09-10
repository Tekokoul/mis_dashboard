<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= display($data['meta_name']); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= count($data['batches']) > 0 ? count($data['batches']) . ' import' . (count($data['batches']) === 1 ? '' : 's') . ' so far' : 'No imports yet'; ?></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-12 col-xl-5">
        <section class="card card-modern mb-4">
            <div class="card-body">
                <h4 class="afcdc-import__title">Read a workbook</h4>
                <p class="afcdc-import__lead">Upload the work plan as an .xlsx file. Its activities are compared with the catalogue and listed for you to look at: which are new, which already exist and differ, and where each new one belongs. Nothing is written until you accept a row.</p>
                <?php if (!empty($data['error'])) { print '<div class="afcdc-form-errors" role="alert"><strong>Not read.</strong> ' . display($data['error']) . '</div>'; } ?>
                <?php if (!empty($data['notice'])) { print '<div class="afcdc-review-panel afcdc-review--accepted">' . display($data['notice']) . '</div>'; } ?>
                <form class="afcdc-import__form" action="<?= $this->L("imports/upload"); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group mb-3">
                        <label for="workbook" class="control-label">Workbook (.xlsx)</label>
                        <input type="file" class="form-control" id="workbook" name="workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="sheet" class="control-label">Sheet <span class="afcdc-import__hint">optional; the schedule sheet is found on its own</span></label>
                        <input type="text" class="form-control" id="sheet" name="sheet" maxlength="64" placeholder="Schedule">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-import" aria-hidden="true"></i> Read the workbook</button>
                </form>
                <div class="afcdc-import__how">
                    <p><strong>What the sheet needs.</strong> A header row with a column for the activity (Task, Activity or Name) and, ideally, its code (AWP Code), indicator, budget and notes. A row with a WBS number and no code is a heading: "1.1 Connect the RCCs as one organisation" files the activities under it, and that heading is matched to an objective here.</p>
                    <p><strong>How a row is judged.</strong> By the code an earlier import or the re-filing recorded, then by name, then by wording. A name that only resembles an existing activity is a question for you, not a guess. Where a new activity goes is proposed from its wording and the workbook heading together; when they disagree both are shown.</p>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-7">
        <section class="card card-modern mb-4">
            <div class="card-body">
                <h4 class="afcdc-import__title">Imports so far</h4>
                <?php if (!$data['batches']) { ?>
                    <p class="afcdc-import__lead">None yet. The first workbook you read appears here with what it found.</p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-ecommerce-simple table-borderless table-striped mb-0 afcdc-import__batches">
                        <thead>
                            <tr><th>#</th><th>Workbook</th><th>Read</th><th>Rows</th><th>Found</th><th>Decided</th><th class="afcdc-col-actions">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['batches'] as $b) { $c = $b['counts']; ?>
                            <tr>
                                <td><?= (int)$b['id']; ?></td>
                                <td><a href="<?= $this->L('imports/review/' . (int)$b['id']); ?>"><strong><?= display($b['filename']); ?></strong></a><div class="afcdc-import__meta">sheet <?= display($b['sheet']); ?><?= trim((string)$b['note']) !== '' ? ' · <span title="' . display($b['note']) . '">warnings</span>' : ''; ?></div></td>
                                <td><?= display(substr((string)$b['uploaded_at'], 0, 16)); ?><div class="afcdc-import__meta"><?= display($b['uploaded_by_name'] ?? ((int)$b['uploaded_by'] === 0 ? 'command line' : '#' . (int)$b['uploaded_by'])); ?></div></td>
                                <td><?= (int)$c['total']; ?></td>
                                <td><?= (int)$c['kind']['new']; ?> new · <?= (int)$c['kind']['changed']; ?> changed · <?= (int)$c['kind']['unclear']; ?> to settle · <?= (int)$c['kind']['same']; ?> already in</td>
                                <td><?= (int)$c['status']['accepted']; ?> accepted · <?= (int)$c['status']['skipped']; ?> skipped<?= (int)$c['status']['pending'] > 0 ? ' · <strong>' . (int)$c['status']['pending'] . ' pending</strong>' : ''; ?></td>
                                <td class="afcdc-col-actions">
                                    <?php // Bare icons, as every other list in the dashboard does its row actions. ?>
                                    <a href="<?= $this->L('imports/review/' . (int)$b['id']); ?>" title="Review this import" aria-label="Review this import"><i class="bx bx-list-check bx-sm" aria-hidden="true"></i></a>
                                    <?php if ($c['status']['accepted'] === 0) { ?><a href="#" data-import-action="discard" data-id="<?= (int)$b['id']; ?>" title="Discard this import" aria-label="Discard this import"><i class="bx bx-trash bx-sm" aria-hidden="true"></i></a><?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </section>
    </div>
</div>
