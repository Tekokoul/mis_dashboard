<?php
// MIS Key Deliverables overview.
//
// Two lenses (pm_pillars_tbl), five key deliverables under each
// (pm_objectives_tbl), rolled up from the AWP activities beneath them. The
// data access is unchanged from the legacy build - only the presentation is
// Africa CDC. Progress is computed in projects_graphs::overview().
$lenses = $data['pillars'] ?? [];
$overall = (float)($data['progress'] ?? 0);
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= htmlspecialchars($this->S['graphs']['overview_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>

<div class="row">
    <div class="col-md-12">
        <section class="card card-light">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-chart-line"></i>&nbsp;&nbsp;Overall progress across all key deliverables</h2>
            </header>
            <div class="card-body">
                <div class="progress progress-xl progress-squared m-2">
                    <div class="progress-bar" role="progressbar"
                         aria-valuenow="<?= $overall; ?>" aria-valuemin="0" aria-valuemax="100"
                         style="width: <?= $overall; ?>%;">
                        <?php if ($overall >= 8): ?><?= number_format($overall, 2, ',', ''); ?>%<?php endif; ?>
                    </div>
                </div>
                <?php if ($overall < 8): ?>
                    <div class="m-2 afcdc-progress-zero"><?= number_format($overall, 2, ',', ''); ?>% complete</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<div class="row">
    <?php foreach ($lenses as $i => $lens):
        $lensName = htmlspecialchars($lens['name'], ENT_QUOTES, 'UTF-8');
        $lensAbbr = htmlspecialchars($lens['abbr'] ?? '', ENT_QUOTES, 'UTF-8');
        $lensPct  = (float)$lens['progress'];
        $objectives = $lens['objectives'] ?? [];
    ?>
    <!-- col-lg-6: there are two lenses, so they are peers on one row. The
         legacy build used col-lg-4 because it had six pillars. -->
    <div class="col-lg-6">
        <section class="card h-100">
            <div class="card-body">
                <div class="afcdc-lens">

                    <div>
                        <?php if ($lensAbbr !== ''): ?>
                            <div class="afcdc-lens__label"><?= $lensAbbr; ?></div>
                        <?php endif; ?>
                        <h3 class="afcdc-lens__name">
                            <a href="<?= $this->L("projects_graphs/pillar/" . (int)$lens['id']); ?>"><?= $lensName; ?></a>
                        </h3>
                    </div>

                    <div class="gauge-chart text-center">
                        <canvas class="gaugeBasic" width="300" height="150"
                                data-value="<?= $lensPct; ?>"
                                role="img"
                                aria-label="<?= $lensName; ?>: <?= number_format($lensPct, 2, ',', ''); ?> percent complete"></canvas>
                        <label class="gaugeBasicTextfield"><?= number_format($lensPct, 2, ',', ''); ?>%</label>
                    </div>

                    <div>
                        <?php foreach ($objectives as $objective):
                            $objName = htmlspecialchars($objective['name'], ENT_QUOTES, 'UTF-8');
                            $objPct  = (float)$objective['progress'];
                            // abbr holds the WBS code (1.1, 2.3 ...) when it is seeded.
                            $wbs = trim((string)($objective['abbr'] ?? ''));
                        ?>
                        <div class="afcdc-deliverable">
                            <span class="afcdc-deliverable__wbs"><?= htmlspecialchars($wbs !== '' ? $wbs : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="afcdc-deliverable__name">
                                <a href="<?= $this->L("projects_graphs/objective/" . (int)$objective['id']); ?>"><?= $objName; ?></a>
                            </span>
                            <div class="afcdc-deliverable__meta">
                                <div class="progress progress-lg progress-squared w-100">
                                    <div class="progress-bar" role="progressbar"
                                         aria-valuenow="<?= $objPct; ?>" aria-valuemin="0" aria-valuemax="100"
                                         style="width: <?= $objPct; ?>%;">
                                        <?php if ($objPct >= 12): ?><?= number_format($objPct, 2, ',', ''); ?>%<?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($objPct < 12): ?>
                                    <span class="afcdc-progress-zero"><?= number_format($objPct, 2, ',', ''); ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($objectives)): ?>
                            <p class="afcdc-progress-zero mt-2 mb-0">No key deliverables are recorded under this lens yet.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </section>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-md-12">
        <p class="afcdc-note">
            Percentages are computed from completed activity records only
            (<code>pm_progress_tasks_tbl</code>), against the number of RCCs and Member States each
            activity applies to. A deliverable with no activities recorded against it reports 0%
            because there is nothing to measure yet — not because it has stalled.
        </p>
    </div>
</div>

<script>
    var graph_color = '<?= _PROJECT_COLOR; ?>';
</script>
