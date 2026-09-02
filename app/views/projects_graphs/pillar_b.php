<?php
//debug($data);
$val_all = ($data['pillar']['totals']>0) ? round(($data['pillar']['progress']/$data['pillar']['totals']*100), 2) : 0;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> > <?=$this->S['graphs']['pillar_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-md-4">
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$val_all?>"></canvas>
            <strong><?=$data['pillar']['name']?></strong>
            <label id="gaugeBasicTextfield"><?=pct($val_all);?>%</label>
        </div>
    </div>
    <div class="col-md-8">
        <?php
        foreach ($data['projects'] as $project){
            $prj_val = ($project['totals']>0) ? round(($project['progress']/$project['totals']*100), 2) : 0;
            ?>
                <div class="row">
                    <div class="col col-7"><a href="<?=$this->L("projects_graphs/project/".$project['id']);?>"><?=$project['name'];?></a></div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$prj_val;?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$prj_val;?>%;">
                                <?=($prj_val);?>%
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