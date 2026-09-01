<?php
// Every FY2026 Annual Workplan activity, flat, with the lens and key
// deliverable it rolls up to. Sorting, search and paging come from DataTables
// (initialised in /js/page_projects_graphs_projects.js).
$activities = $data['activities'] ?? [];
$filters    = $data['filters'] ?? ['lens' => [], 'wbs' => [], 'indicator' => []];
$esc = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">
        <?= $esc($this->S['graphs']['project_title'] ?? 'AWP Activity'); ?>
        <span class="afcdc-count"><?= count($activities); ?> activities</span>
    </h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']); ?>">Overview</a></li>
        </ol>
    </div>
</header>

<div class="row">
    <div class="col-md-12">
        <section class="card">
            <div class="card-body">

                <!-- Filters sit in one row above the table, as a group. -->
                <div class="afcdc-filters" role="group" aria-label="Filter activities">
                    <label class="afcdc-filter">
                        <span>Lens</span>
                        <select class="form-select form-select-sm" data-afcdc-filter="1">
                            <option value="">All lenses</option>
                            <?php foreach ($filters['lens'] as $v): ?>
                                <option value="<?= $esc($v); ?>"><?= $esc($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="afcdc-filter">
                        <span>Key deliverable</span>
                        <select class="form-select form-select-sm" data-afcdc-filter="2">
                            <option value="">All deliverables</option>
                            <?php foreach ($filters['wbs'] as $v): ?>
                                <option value="<?= $esc($v); ?>"><?= $esc($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="afcdc-filter">
                        <span>Indicator</span>
                        <select class="form-select form-select-sm" data-afcdc-filter="6">
                            <option value="">All indicators</option>
                            <?php foreach ($filters['indicator'] as $v): ?>
                                <option value="<?= $esc($v); ?>"><?= $esc($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="button" class="btn btn-sm btn-light border" id="afcdc-filters-reset">Clear filters</button>
                </div>

                <div class="table-responsive afcdc-scroll">
                    <table class="table table-borderless table-striped mb-0 afcdc-table" id="datatable-activities">
                        <thead>
                        <tr>
                            <th class="afcdc-col-num" data-orderable="false">#</th>
                            <th>Lens</th>
                            <th>WBS</th>
                            <th>Key deliverable</th>
                            <th>AWP code</th>
                            <th>Activity</th>
                            <th>Indicator</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activities as $i => $a): ?>
                            <tr>
                                <td class="afcdc-col-num"><?= $i + 1; ?></td>
                                <td><span class="afcdc-lens-tag"><?= $esc($a['lens_abbr'] ?: '—'); ?></span></td>
                                <td class="afcdc-deliverable__wbs"><?= $esc($a['wbs'] ?: '—'); ?></td>
                                <td><?= $esc($a['deliverable'] ?: '—'); ?></td>
                                <td class="afcdc-awp"><?= $esc($a['awp_code'] ?: '—'); ?></td>
                                <td>
                                    <a href="<?= $this->L("projects_graphs/project/".(int)$a['id']); ?>"><?= $esc($a['name']); ?></a>
                                </td>
                                <td><?= $esc($a['indicator'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($activities)): ?>
                    <p class="afcdc-progress-zero mt-3 mb-0">No activities are recorded yet.</p>
                <?php endif; ?>

            </div>
        </section>
    </div>
</div>

<script>
    var graph_color = '<?= _PROJECT_COLOR; ?>';
</script>
