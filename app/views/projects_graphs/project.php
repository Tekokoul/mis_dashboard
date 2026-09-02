<?php
// debug($data);
//    $value = count($data);
$members = $data['project']['members'] ?? [];
$actual_budget = 0;

foreach ($members as $member){
    $actual_budget += $member['budget'];
}

?>
<header class="page-header page-header-left-inline-breadcrumb">
<h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <a href="<?= $this->L("projects_graphs/objective/".(int)$data['project']['objective_id']);?>"><?=$this->S['graphs']['objective_title']?></a> &rsaquo; <a href="<?= $this->L("projects_graphs/programme/".(int)$data['project']['programme_id']);?>"><?=$this->S['graphs']['programme_title']?></a> &rsaquo; <?=$this->S['graphs']['project_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
    <div>
            <h2><?=display($data['project']['name'])?></h2>
            <?php if (in_array((int)($_SESSION['user']['group']['id'] ?? 0), [1, 2, 3], true)): ?>
                <a href="<?=$this->L("projects/progress_edit/".(int)$data['project']['id']);?>" class="btn btn-primary btn-sm mb-3"><i class="bx bx-edit"></i> Record delivery</a>
            <?php endif; ?>
        </div>
    <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=(float)$data['project']['progress']?>" role="img" aria-label="<?= display($data['project']['name']); ?>: <?= pct($data['project']['progress']); ?> percent complete"></canvas>
            <label class="gaugeBasicTextfield"><?=pct($data['project']['progress']);?>%</label>
        </div>
        <?php
        // An activity is delivered once per reporting entity it applies to
        // (one entity today: DHIS HQ). Say that in words; the task list used
        // to print the tickable record's NAME, which the seed calls
        // "Delivered", so an undelivered activity read "Tasks: Delivered".
        $t = (int)($data['project']['totals'] ?? 0);
        $c = (int)($data['project']['completed'] ?? 0);
        if ($t === 0)      { $st = 'idle';   $stIcon = 'bx-minus-circle'; $stText = 'Nothing to measure yet'; $stLine = 'Nothing to measure yet'; }
        elseif ($c >= $t)  { $st = 'good';   $stIcon = 'bx-check-circle'; $stText = 'Delivered';              $stLine = $t === 1 ? 'Delivered' : "Delivered by all $t reporting entities"; }
        elseif ($c > 0)    { $st = 'active'; $stIcon = 'bx-adjust';       $stText = 'Partly delivered';       $stLine = "Delivered by $c of $t reporting entities"; }
        else               { $st = 'idle';   $stIcon = 'bx-time-five';    $stText = 'Not delivered';          $stLine = $t === 1 ? 'Not yet delivered' : "Not yet delivered by any of $t reporting entities"; }
        // Actual budget: the figure entered on the activity itself; failing
        // that, the spend recorded with its delivery records.
        $actual_shown = ($data['project']['actual_budget'] ?? null) !== null && (float)$data['project']['actual_budget'] > 0
            ? (float)$data['project']['actual_budget'] : $actual_budget;
        ?>
        <p class="afcdc-deliverable__meta mb-3"><?= display($stLine); ?></p>
        <div>
            <p><strong>Description:</strong><br><?=nl2br(display($data['project']['description']))?><hr>
            <strong>Delivery status:</strong><br>
            <span class="afcdc-status afcdc-status--<?= $st; ?>"><i class="bx <?= $stIcon; ?>" aria-hidden="true"></i> <?= $stText; ?></span><hr>
            <strong>KPIs:</strong><br><?=display($data['project']['kpi'])?><hr>
            <strong>Estimated budget:</strong><br>USD <?=(display_price($data['project']['estimated_budget'] ?? 0,2,".",",")??'N/A');?><hr>
            <strong>Actual budget:</strong><br>USD <?=(display_price($actual_shown,2,".",",")??'N/A');?><hr>
            <strong>Notes:</strong><br><?=nl2br(display($data['project']['notes']))?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
            <h3 class="pb-4">Assignees</h3>
            <?php
            $members = $data['project']['members'] ?? [];
            foreach ($members as $member){
                ?>
                <div class="row">
                    <div class="col col-6">
                        <?= htmlspecialchars((string)$member['member_state']['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="afcdc-deliverable__meta"><?= (int)$member['completed_tasks']; ?> of <?= (int)$member['assigned_tasks']; ?> delivered</span>
                    </div>
                    <div class="col col-6"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=(float)$member['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$member['progress'];?>%;">
                                <?php if ((float)$member['progress'] >= 12): ?><?= pct($member['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php if ((float)$member['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($member['progress']); ?>%</span><?php endif; ?>
                    </div>
                </div>
                <?php
            }
        ?>
    </div>
</div>
<script>
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>