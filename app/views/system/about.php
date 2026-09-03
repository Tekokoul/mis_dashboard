<?php
// About the Africa CDC DHIS Performance Monitor.
//
// Reachable at /system/about. The user-menu entry is hidden while _WHITELABEL
// is true (app/template/template_header.php:7 sets $about = ''), but the route
// stays live, so this page has to stand on its own as a product About page.
//
// The framework credit in the last card is attribution in body text, which is
// correct and should stay. Vendor branding - logo, marketing copy, outbound
// link, contact details - is what was removed and must not come back.
//
// system::about() renders with no $data, so nothing here may depend on it.
$esc = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$projectName    = defined('_PROJECT_NAME') ? _PROJECT_NAME : 'DHIS Performance Monitor';
$projectVersion = defined('_PROJECT_VERSION') ? (string)_PROJECT_VERSION : '';

// This page sits on a light ground, so it needs the coloured mark. The white
// mark (_WHITELABEL_LOGO_LIGHT / _WHITELABEL_LOGO_DARK) is for the dark header
// plate and the dark login panel, and would be invisible here.
$logoOnLight = defined('_WHITELABEL_LOGO_ON_LIGHT') ? _WHITELABEL_LOGO_ON_LIGHT : '';

// Goal > Objective > Programme > Project, in the same vocabulary the
// breadcrumbs use (settings.php: graphs.pillar_title ... graphs.project_title).
$levels = [
    ['Goal',      'A strategic goal, the top of the hierarchy: Internal (WBS 1), External (WBS 2), and any added since.'],
    ['Objective', 'A key deliverable under a goal: WBS 1.1 to 1.5, 2.1 to 2.5, and so on.'],
    ['Programme', 'A workstream grouping the activities that serve one key deliverable.'],
    ['Project',   'A single FY2026 Annual Workplan activity. 55 in total.'],
];
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">About</h2>
</header>

<div class="row">
    <div class="col-lg-8 mb-4">
        <section class="card card-light h-100">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-info-circle"></i>&nbsp;&nbsp;<?= $esc($projectName); ?></h2>
            </header>
            <div class="card-body">
                <?php if ($logoOnLight !== ''): ?>
                    <p><img src="<?= $esc($logoOnLight); ?>" class="img-fluid" width="220" alt="Africa CDC"></p>
                <?php endif; ?>

                <p class="afcdc-lens__label">Safeguarding Africa&rsquo;s Health</p>

                <p>This is the performance monitor for the Africa CDC Digital Health and Information
                    Systems (DHIS) division. It tracks the ten MIS key deliverables for
                    <b>August 2026 &ndash; January 2027</b>: five under the <b>Internal Lens</b> (WBS 1.1&ndash;1.5)
                    and five under the <b>External Lens</b> (WBS 2.1&ndash;2.5).</p>

                <p>Nothing is entered against a key deliverable directly. Each one is measured by the
                    FY2026 Annual Workplan activities that serve it &mdash; 55 activities in all &mdash; so the
                    figure shown for a deliverable, for a goal, and for the division overall is a roll-up
                    of work actually recorded further down.</p>
            </div>
        </section>
    </div>

    <div class="col-lg-4 mb-4">
        <section class="card card-light h-100">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-tag"></i>&nbsp;&nbsp;Release</h2>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm afcdc-table mb-0">
                        <tbody>
                            <tr>
                                <th scope="row">Product</th>
                                <td><?= $esc($projectName); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Version</th>
                                <td><?= ($projectVersion !== '') ? $esc($projectVersion) : '&mdash;'; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Period</th>
                                <td>August 2026 &ndash; January 2027</td>
                            </tr>
                            <tr>
                                <th scope="row">Deliverables</th>
                                <td>10 &mdash; 5 Internal Lens, 5 External Lens</td>
                            </tr>
                            <tr>
                                <th scope="row">Source plan</th>
                                <td>FY2026 Annual Workplan, 55 activities</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <section class="card card-light h-100">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-sitemap"></i>&nbsp;&nbsp;How the hierarchy works</h2>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm afcdc-table">
                        <thead>
                            <tr>
                                <th scope="col">Level</th>
                                <th scope="col">What it is here</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levels as $level): ?>
                            <tr>
                                <td><span class="afcdc-lens-tag"><?= $esc($level[0]); ?></span></td>
                                <td><?= $esc($level[1]); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="afcdc-note mb-0">
                    Percentages are computed from recorded activity results and roll upward:
                    activities into their programme, programmes into the key deliverable, the
                    deliverables into their goal, and both goals into the overall figure. A
                    deliverable with nothing recorded against it reports 0% because there is nothing
                    to measure yet &mdash; not because it has stalled.
                </p>
            </div>
        </section>
    </div>

    <div class="col-lg-4 mb-4">
        <section class="card card-light h-100">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-code"></i>&nbsp;&nbsp;Credits</h2>
            </header>
            <div class="card-body">
                <p>Built on CrystalEngine, a PHP model-view-controller framework.</p>

                <p>The data model, the reporting hierarchy, the Africa CDC theme and every view in
                    this monitor are specific to the DHIS division&rsquo;s MIS key deliverables.</p>

                <p class="afcdc-note mb-0">An entity of the African Union.</p>
            </div>
        </section>
    </div>
</div>
