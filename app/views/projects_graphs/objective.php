<?php
// debug($data);
$val_all = ($data['objective']['totals']>0) ? round(($data['objective']['progress']/$data['objective']['totals']*100), 2) : 0;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <?=$this->S['graphs']['objective_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
        <div>
            <h2><?=display($data['objective']['name'])?></h2>
        </div>    
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=(float)$data['objective']['progress']?>" role="img" aria-label="<?= display($data['objective']['name']); ?>: <?= pct($data['objective']['progress']); ?> percent complete"></canvas>
            <label class="gaugeBasicTextfield"><?=pct($data['objective']['progress']);?>%</label>
        </div>
        <p class="afcdc-deliverable__meta mb-3"><?php $t = (int)($data['objective']['totals'] ?? 0); $c = (int)($data['objective']['completed'] ?? 0); echo $t > 0 ? "$c of $t activities delivered" : 'Nothing to measure yet'; ?></p>
        <div>
            <p><strong>Description:</strong><br><?=nl2br(display($data['objective']['description']))?><hr>
            <strong>Outcomes:</strong><br><?=nl2br(display($data['objective']['outcomes']))?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
        <div>
            <h3 class="pb-4">Included programmes</h3>
        </div>  
        <?php
        foreach ($data['programmes'] as $programme){
            $prj_val = ($programme['totals']>0) ? round(($programme['progress']/$programme['totals']*100), 2) : 0;
            ?>
                <div class="row afcdc-drill">
                    <div class="col col-7"><a class="stretched-link" href="<?=$this->L("projects_graphs/programme/".(int)$programme['id']);?>"><?=display($programme['name']);?></a></div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=(float)$programme['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$programme['progress'];?>%;">
                                <?php if ((float)$programme['progress'] >= 12): ?><?= pct($programme['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php if ((float)$programme['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($programme['progress']); ?>%</span><?php endif; ?></div>
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