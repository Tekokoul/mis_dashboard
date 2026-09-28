<?php
// MIS Key Deliverables overview.
//
// Two goals (pm_pillars_tbl), five key deliverables under each
// (pm_objectives_tbl), rolled up from the AWP activities beneath them. The
// data access is unchanged from the legacy build - only the presentation is
// Africa CDC. Progress is computed in projects_graphs::overview().
$lenses = $data['pillars'] ?? [];
$overall = (float)($data['progress'] ?? 0);
$overallDone = (int)($data['completed'] ?? 0);
$overallAll  = (int)($data['totals'] ?? 0);
$latest = $data['latest_delivery'] ?? null;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= htmlspecialchars($this->S['graphs']['overview_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?php if ($latest): ?>Latest recorded delivery: <time datetime="<?= date(DATE_ATOM, strtotime($latest)); ?>"><?= date('j M Y', strtotime($latest)); ?></time><?php else: ?>No deliveries recorded yet<?php endif; ?></span></li>
            <li><a href="#" class="afcdc-print" role="button"><i class="bx bx-printer" aria-hidden="true"></i> Print or save as PDF</a></li>
            <?php if (can_structure()): ?><li><a href="#" class="afcdc-reorder-start" role="button"><i class="bx bx-sort" aria-hidden="true"></i> Reorder objectives</a></li><?php endif; ?>
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
                        <?php if ($overall >= 8): ?><?= pct($overall); ?>%<?php endif; ?>
                    </div>
                </div>
                <div class="m-2 afcdc-progress-zero"><?php if ($overall < 8): ?><?= pct($overall); ?>% complete · <?php endif; ?><?= $overallDone; ?> of <?= $overallAll; ?> activities completed · <?= max(0, $overallAll - $overallDone); ?> remaining</div>
            </div>
        </section>
    </div>
</div>

<?php if (can_structure()): ?>
<?php // Shown while reordering (custom.js): drag within a goal, or the arrows on each row, then Save. ?>
<div class="afcdc-reorder-bar" data-url="<?= display($this->L('core/objective_order')); ?>" role="region" aria-label="Reorder objectives" hidden>
    <p class="afcdc-reorder-bar__text"><strong>Reorder objectives.</strong> Drag them into the order you want within each goal, or use the arrows on each row. The Overview and each goal's page show them in this order.</p>
    <div class="afcdc-reorder-bar__acts">
        <button type="button" class="btn btn-primary btn-sm" data-afcdc-reorder-save>Save order</button>
        <button type="button" class="btn btn-default btn-sm" data-afcdc-reorder-cancel>Cancel</button>
    </div>
    <span class="afcdc-reorder-bar__msg" role="status" aria-live="polite"></span>
</div>
<?php endif; ?>
<div class="row">
    <?php foreach ($lenses as $i => $lens):
        $lensName = htmlspecialchars($lens['name'], ENT_QUOTES, 'UTF-8');
        $lensAbbr = htmlspecialchars($lens['abbr'] ?? '', ENT_QUOTES, 'UTF-8');
        $lensPct  = (float)$lens['progress'];
        $lensAll  = (int)($lens['totals'] ?? 0);
        $lensDone = (int)($lens['completed'] ?? 0);
        $objectives = $lens['objectives'] ?? [];
    ?>
    <!-- col-lg-6: there are two goals, so they are peers on one row. The
         legacy build used col-lg-4 because it had six goals. -->
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
                                aria-label="<?= $lensName; ?>: <?= pct($lensPct); ?> percent complete<?= ($lensAll > 0) ? ", {$lensDone} of {$lensAll} activities completed" : ", no activities yet"; ?>"></canvas>
                        <label class="gaugeBasicTextfield"><?= pct($lensPct); ?>%</label>
                        <?php if ($lensAll > 0): ?><span class="afcdc-progress-zero d-block"><?= $lensDone; ?> of <?= $lensAll; ?> activities completed</span><?php endif; ?>
                    </div>

                    <div>
                        <?php
                        // In the order an administrator set ("Reorder objectives": pm_objectives_tbl.position), id breaking ties.
                        $roll = delivery_rollup($this->DB);
                        $pillarId = (int)$lens['id'];
                        $objStatusOf = function ($o) use ($roll, $pillarId) { return delivery_rollup_status($roll['objective'][$pillarId . ':' . (int)$o['id']] ?? null); };
                        ?>
                        <div class="afcdc-objectives" data-pillar-id="<?= $pillarId; ?>">
                        <?php foreach ((array)$objectives as $objective):
                            $objName = htmlspecialchars($objective['name'], ENT_QUOTES, 'UTF-8');
                            $objPct  = (float)$objective['progress'];
                            // abbr holds the WBS code (1.1, 2.3 ...) when it is seeded.
                            $wbs = trim((string)($objective['abbr'] ?? ''));
                            // One status word per row. A bare "0.00%" never said whether the
                            // deliverable had stalled or simply has no activity under it yet.
                            $objAll  = (int)($objective['totals'] ?? 0);
                            $objDone = (int)($objective['completed'] ?? 0);
                            $objStatus = $objStatusOf($objective);
                        ?>
                        <div class="afcdc-deliverable afcdc-drill<?= $objAll === 0 ? ' afcdc-deliverable--unfunded' : ''; ?>" data-objective-id="<?= (int)$objective['id']; ?>">
                            <span class="afcdc-deliverable__wbs"><?= htmlspecialchars($wbs !== '' ? $wbs : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="afcdc-deliverable__name">
                                <a class="stretched-link" href="<?= $this->L("projects_graphs/objective/" . (int)$objective['id']); ?>"><?= $objName; ?></a>
                            </span>
                            <div class="afcdc-deliverable__meta">
                                <div class="progress progress-lg progress-squared w-100">
                                    <div class="progress-bar" role="progressbar"
                                         aria-valuenow="<?= $objPct; ?>" aria-valuemin="0" aria-valuemax="100"
                                         aria-valuetext="<?= $objAll === 0 ? '0 activities' : $objDone.' of '.$objAll.' activities completed'; ?>"
                                         style="width: <?= $objPct; ?>%;">
                                        <?php if ($objPct >= 12): ?><?= pct($objPct); ?>%<?php endif; ?>
                                    </div>
                                </div>
                                <span class="afcdc-progress-zero">
                                    <?php if ($objPct < 12): ?><?= pct($objPct); ?>%<?php endif; ?>
                                    <?php if ($objAll > 0): ?> · <?= $objDone; ?> of <?= $objAll; ?> activities completed<?php endif; ?>
                                    <?php if ($objAll > 0): ?> <?= delivery_status_chip($objStatus, 'green'); ?><?php endif; ?>
                                </span>
                            </div>
                            <?php if (can_structure()): ?><span class="afcdc-reorder__ctl"><i class="bx bx-grid-vertical afcdc-reorder__grip" aria-hidden="true"></i><button type="button" class="afcdc-reorder__up" aria-label="Move <?= $objName; ?> up"><i class="bx bx-up-arrow-alt" aria-hidden="true"></i></button><button type="button" class="afcdc-reorder__down" aria-label="Move <?= $objName; ?> down"><i class="bx bx-down-arrow-alt" aria-hidden="true"></i></button></span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>

                        <?php if (empty($objectives)): ?>
                            <p class="afcdc-progress-zero mt-2 mb-0">No key deliverables are recorded under this goal yet.</p>
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
            Progress counts a delivery record marked <em>Completed</em> as one, one <em>In progress</em> as the share
            recorded for it (25, 50 or 75%; nothing when no share is recorded) and <em>Not started</em> as nothing;
            &ldquo;n of m completed&rdquo; beside it counts finished records only. Each activity counts once for every
            RCC or division user it applies to — today every activity is reported once, centrally, by DHIS HQ, so one
            record per activity. Staff record a delivery under <strong>Progress</strong> in the sidebar, on the
            activity's page, with <strong>Record delivery</strong>; every gauge recalculates on the next load.
            A deliverable with no activity count beside it has no Annual Workplan activity under it yet.
        </p>
    </div>
</div>

<script nonce="<?= csp_nonce(); ?>">
    var graph_color = '<?= _PROJECT_COLOR; ?>';
</script>
