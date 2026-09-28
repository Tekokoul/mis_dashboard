<?php
// A readable preview of an attached Word or Excel file, made on this server
// (library.php attachment_preview). The original is one click away.
$a = $data['attachment'];
$p = $data['project'] ?? null;
$prev = (array)($data['preview'] ?? []);
$download = $this->L('projects/attachment_download/' . (int)$a['id']);
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= display($a['original_name']); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= display(($data['kind']['label'] ?? '') . ' · ' . attachment_size_label($a['size'])); ?></span></li>
        </ol>
    </div>
</header>
<section class="card card-modern afcdc-preview">
    <div class="card-body">
        <p class="afcdc-preview__lead">
            <?php if ($p): ?>
                <span>Attached to <a href="<?= display($this->L((can_edit() ? 'projects/edit/' : 'projects_graphs/project/') . (int)$p['id'])); ?>"><?= display(trim($p['abbr'] . ' ' . $p['name'])); ?></a>.</span>
            <?php endif; ?>
            <a class="btn btn-sm btn-primary ms-2" href="<?= display($download); ?>"><i class="bx bx-download" aria-hidden="true"></i> Download the original</a>
        </p>
        <?php if (!empty($prev['error'])): ?>
            <p class="afcdc-preview__error"><?= display($prev['error']); ?></p>
        <?php else: ?>
            <?php if (!empty($prev['note'])): ?><p class="afcdc-preview__note"><?= display($prev['note']); ?></p><?php endif; ?>
            <div class="afcdc-preview__doc"><?= $prev['html'] ?? ''; ?></div>
        <?php endif; ?>
    </div>
</section>
