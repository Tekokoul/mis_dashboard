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
<h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <a href="<?= $this->L("projects_graphs/objective/".$data['project']['objective_id']);?>"><?=$this->S['graphs']['objective_title']?></a> &rsaquo; <a href="<?= $this->L("projects_graphs/programme/".$data['project']['programme_id']);?>"><?=$this->S['graphs']['programme_title']?></a> &rsaquo; <?=$this->S['graphs']['project_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
    <div>
            <h2><?=$data['project']['name']?></h2>
        </div>   
    <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$data['project']['progress']?>"></canvas>
            <label id="gaugeBasicTextfield"><?=number_format((float)$data['project']['progress'], 2, ',', '');?>%</label>
        </div>
        <div>
            <p><strong>Description:</strong><br><?=$data['project']['description']?><hr>
            <strong>Tasks:</strong><br>
            <?php
            $tasks = $data['project']['tasks'] ?? [];
            if(count($tasks)>0){
                ?>
                <ul>
                <?php
                foreach ($tasks as $task){
                    ?>
                        <li class="col col-6"><?=$task['name'];?></li>
                    <?php
                }
                ?>
                </ul>
                <?php
            }
            ?><hr>
            <strong>KPIs:</strong><br><?=$data['project']['kpi']?><hr>
            <strong>Estimated Budget:</strong><br>USD <?=(display_price($data['project']['estimated_budget'],2,".",",")??'N/A');?><hr>
            <strong>Actual Budget:</strong><br>USD <?=(display_price($actual_budget,2,".",",")??'N/A');?><hr>
            <strong>Notes:</strong><br><?=$data['project']['notes']?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
            <h3 class="pb-4">Assignees</h3>
            <?php
            $members = $data['project']['members'] ?? [];
            foreach ($members as $member){
                ?>
                <div class="row">
                    <div class="col col-6"><?=$member['member_state']['name']." (".$member['completed_tasks']."/".$member['assigned_tasks']." tasks)";?></div>
                    <div class="col col-6"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$member['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$member['progress'];?>%;">
                                <?=number_format((float)$member['progress'],2);?>%
                            </div>
                        </div></div>
                </div>
                <?php
            }
        ?>
    </div>
</div>
<script>
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>