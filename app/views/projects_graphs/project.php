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
            <?php if (can_record()): ?>
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
        $roll = delivery_rollup($this->DB);
        $st = delivery_rollup_status($roll['activity'][(int)$data['project']['id']] ?? null);
        $stLine = $st === 'completed' ? ($t === 1 ? 'Completed' : "Completed by all $t reporting entities")
                : ($c > 0 ? "Completed by $c of $t reporting entities" : '');
        // Actual budget: the figure entered on the activity itself; failing
        // that, the spend recorded with its delivery records.
        $actual_shown = ($data['project']['actual_budget'] ?? null) !== null && (float)$data['project']['actual_budget'] > 0
            ? (float)$data['project']['actual_budget'] : $actual_budget;
        ?>
        <?php // Only movement is worth a line: nothing is said for an activity that has not been delivered yet. ?>
        <?php if ($c > 0 && $stLine !== ''): ?><p class="afcdc-deliverable__meta mb-3"><?= display($stLine); ?></p><?php endif; ?>
        <div>
            <p><strong>Description:</strong><br><?=nl2br(display($data['project']['description']))?><hr>
            <strong>Status:</strong><br>
            <?= delivery_status_chip($st); ?><hr>
            <strong>Tasks:</strong><br><?=display($data['project']['kpi'])?><hr>
            <strong>Estimated budget:</strong><br>USD <?=(display_price($data['project']['estimated_budget'] ?? 0,2,".",",")??'N/A');?><hr>
            <strong>Actual budget:</strong><br>USD <?=(display_price($actual_shown,2,".",",")??'N/A');?><hr>
            <strong>Notes:</strong><br><?=nl2br(display($data['project']['notes']))?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
            <h3 class="pb-4">Tasks</h3>
            <?php
            // What this activity is delivered through, each with what has been
            // recorded against it and a way to record the rest - the same
            // button the programme page puts beside every activity.
            $tasks = $data['project']['tasks'] ?? [];
            $mayRecord = can_record();
            if (!$tasks) {
                print '<p class="text-muted">No task on this activity yet, so there is nothing to deliver against it.</p>';
            }
            // Completed first, then In progress, then Not started.
            $taskStatus = function ($task) use ($roll) { return delivery_rollup_status($roll['task'][(int)$task['id']] ?? null); };
            foreach (sort_by_delivery_status((array)$tasks, $taskStatus) as $task){
                $tStatus = $taskStatus($task);
                ?>
                <div class="row afcdc-drill afcdc-drill--flat">
                    <div class="col col-7">
                        <?= display($task['name']); ?> <?= delivery_status_chip($tStatus); ?>
                        <?php if ($mayRecord): ?>
                            <a href="<?=$this->L("projects/progress_edit/".(int)$data['project']['id']);?>" class="btn btn-xs btn-light border ms-2 afcdc-record-link"><i class="bx bx-edit"></i> Record delivery</a>
                        <?php endif; ?>
                        <br><span class="afcdc-deliverable__meta"><?= (int)$task['completed']; ?> of <?= (int)$task['assignments']; ?> completed</span>
                    </div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar<?= delivery_status_bar($tStatus); ?>" role="progressbar" aria-valuenow="<?=(float)$task['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$task['progress'];?>%;">
                                <?php if ((float)$task['progress'] >= 12): ?><?= pct($task['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php if ((float)$task['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($task['progress']); ?>%</span><?php endif; ?>
                    </div>
                    <hr>
                </div>
                <?php
            }
        ?>
    </div>
</div>
<script nonce="<?= csp_nonce(); ?>">
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>