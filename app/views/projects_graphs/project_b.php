<?php
// debug($data);
//    $value = count($data);
$mem_val = 0;
$members = $data['project']['members'] ?? [];

foreach ($members as $member){
    $mem_val += $member['progress'];
}

if($data['project']['totals']>0){
    $value = round(($mem_val/($data['project']['totals']*count($data['project']['members']))*100), 2);
} else {
    $value = 0;
}
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> > <a href="<?=$this->L("projects_graphs/pillar/".$data['project']['pillar_id'])?>"><?=$this->S['graphs']['pillar_title']?></a> > Project</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-md-4">
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$value?>"></canvas>
            <strong><?=$data['project']['name']?></strong>
            <label id="gaugeBasicTextfield"><?=number_format((float)$value, 2, ',', '');?>%</label>
        </div>
    </div>
    <div class="col-md-8">
            <h4><?=$data['project']['abbr']. " - ".$data['project']['name']?></h4>
            <?php
            $members = $data['project']['members'] ?? [];
            foreach ($members as $member){
                if($data['project']['totals']>0){
                    $value = ($data['project']['totals']>0) ? round(($member['progress']/$data['project']['totals']*100),2) : 0;
                } else {
                    $value = 0;
                }
                ?>
                <div class="row">
                    <div class="col col-6"><?=$member['member_state']['name'];?></div>
                    <div class="col col-6"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$value;?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$value;?>%;">
                                <?=$value;?>%
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