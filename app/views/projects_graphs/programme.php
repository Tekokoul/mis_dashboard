<?php
// debug($data);
$val_all = ($data['programme']['totals']>0) ? round(($data['programme']['progress']/$data['programme']['totals']*100), 2) : 0;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <a href="<?= $this->L("projects_graphs/objective/".$data['programme']['objective_id']);?>"><?=$this->S['graphs']['objective_title']?></a> &rsaquo; <?=$this->S['graphs']['programme_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
        <div>
            <h2><?=$data['programme']['name']?></h2>
        </div>    
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$data['programme']['progress']?>"></canvas>
            <!-- <strong><?=$data['programme']['name']?></strong> -->
            <label id="gaugeBasicTextfield"><?=number_format((float)$data['programme']['progress'], 2, ',', '');?>%</label>
        </div>
        <div>
            <p><strong>Description:</strong><br><?=$data['programme']['description']?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
        <div>
            <h3 class="pb-4">Included projects</h3>
        </div>  
        <?php
        foreach ($data['projects'] as $project){
            $prj_val = ($project['totals']>0) ? round(($project['progress']/$project['totals']*100), 2) : 0;
            ?>
                <div class="row">
                    <div class="col col-7"><a href="<?=$this->L("projects_graphs/project/".$project['id']);?>"><?=$project['name'];?></a></div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$project['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$project['progress'];?>%;">
                                <?=($project['progress']);?>%
                            </div>
                        </div>
                    </div>
                    <hr>
                </div>
                <?php
        }
        ?>
    </div>
</div>
<script>
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>