<?php
// Files attached to an activity: PDF, Word, Excel (library.php, "Files
// attached to activities"). Included by projects/edit.php and
// projects_graphs/project.php with $attachProjectId set. Reading is for the
// levels that read the lists (can_browse), adding for those who edit
// activities (can_edit), removing for administrators (can_delete).
if (!can_browse() || !attachments_available($this->DB) || (int)($attachProjectId ?? 0) <= 0) { return; }
$attachItems = array_map(function ($r) { return attachment_item($r, function ($p) { return $this->L($p); }); }, (array)($data['attachments'] ?? []));
$attachMayAdd = can_edit();
$attachMayRemove = can_delete();
?>
<section class="card card-modern afcdc-attach" id="afcdc-attachments"
         data-upload-url="<?= display($this->L('projects/attachment_upload/' . (int)$attachProjectId)); ?>"
         data-delete-url="<?= display($this->L('projects/attachment_delete')); ?>"
         data-max-bytes="<?= (int)attachment_max_bytes(); ?>">
    <div class="card-body">
        <div class="afcdc-attach__head">
            <h3 class="afcdc-attach__title"><i class="bx bx-paperclip" aria-hidden="true"></i> Files <span class="afcdc-attach__count">(<?= count($attachItems); ?>)</span></h3>
            <?php if ($attachMayAdd): ?>
            <?php // No name on the input: it sits inside the activity form, and the file goes up on its own (custom.js), not with Save. ?>
            <label class="btn btn-sm btn-light border mb-0 afcdc-attach__add">
                <i class="bx bx-upload" aria-hidden="true"></i> Attach files
                <input type="file" class="visually-hidden" data-afcdc-attach-input multiple
                       accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
            </label>
            <?php endif; ?>
        </div>
        <p class="afcdc-attach__status" role="status" aria-live="polite" hidden></p>
        <ul class="afcdc-attach__list">
            <?php foreach ($attachItems as $it): ?>
            <li class="afcdc-attach__item" data-id="<?= (int)$it['id']; ?>">
                <i class="bx <?= display($it['icon']); ?> afcdc-attach__icon afcdc-attach__icon--<?= display($it['kind']); ?>" aria-hidden="true"></i>
                <div class="afcdc-attach__main">
                    <a class="afcdc-attach__name" href="<?= display($it['view_url'] !== '' ? $it['view_url'] : $it['download_url']); ?>"<?= $it['view_url'] !== '' ? ' target="_blank" rel="noopener"' : ''; ?>><?= display($it['name']); ?></a>
                    <span class="afcdc-attach__meta"><?= display($it['meta']); ?></span>
                </div>
                <span class="afcdc-attach__acts">
                    <?php if ($it['view_url'] !== ''): ?><a class="btn btn-xs btn-light border" href="<?= display($it['view_url']); ?>" target="_blank" rel="noopener"><i class="bx bx-show" aria-hidden="true"></i> View</a><?php endif; ?>
                    <a class="btn btn-xs btn-light border" href="<?= display($it['download_url']); ?>"><i class="bx bx-download" aria-hidden="true"></i> Download</a>
                    <?php if ($attachMayRemove): ?><button type="button" class="btn btn-xs btn-light border afcdc-btn-danger" data-afcdc-attach-delete="<?= (int)$it['id']; ?>" aria-label="Delete <?= display($it['name']); ?>"><i class="bx bx-trash" aria-hidden="true"></i></button><?php endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="afcdc-attach__empty text-muted"<?= $attachItems ? ' hidden' : ''; ?>>No files attached yet.<?= $attachMayAdd ? ' PDF, Word and Excel files, up to ' . display(attachment_max_label()) . ' each.' : ''; ?></p>
    </div>
    <?php // A row as custom.js adds it after an upload - the same markup as above. ?>
    <template id="afcdc-attach-row">
        <li class="afcdc-attach__item">
            <i class="bx afcdc-attach__icon" data-slot="icon" aria-hidden="true"></i>
            <div class="afcdc-attach__main">
                <a class="afcdc-attach__name" data-slot="name" href="#"></a>
                <span class="afcdc-attach__meta" data-slot="meta"></span>
            </div>
            <span class="afcdc-attach__acts">
                <a class="btn btn-xs btn-light border" data-slot="view" href="#" target="_blank" rel="noopener"><i class="bx bx-show" aria-hidden="true"></i> View</a>
                <a class="btn btn-xs btn-light border" data-slot="download" href="#"><i class="bx bx-download" aria-hidden="true"></i> Download</a>
                <?php if ($attachMayRemove): ?><button type="button" class="btn btn-xs btn-light border afcdc-btn-danger" data-slot="delete"><i class="bx bx-trash" aria-hidden="true"></i></button><?php endif; ?>
            </span>
        </li>
    </template>
</section>
