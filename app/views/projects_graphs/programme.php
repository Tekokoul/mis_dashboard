<?php
// debug($data);
$val_all = ($data['programme']['totals']>0) ? round(($data['programme']['progress']/$data['programme']['totals']*100), 2) : 0;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <a href="<?= $this->L("projects_graphs/objective/".(int)$data['programme']['objective_id']);?>"><?=$this->S['graphs']['objective_title']?></a> &rsaquo; <?=$this->S['graphs']['programme_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
        <div>
            <h2><?=display($data['programme']['name'])?></h2>
        </div>
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=(float)$data['programme']['progress']?>" role="img" aria-label="<?= display($data['programme']['name']); ?>: <?= pct($data['programme']['progress']); ?> percent complete"></canvas>
            <label class="gaugeBasicTextfield"><?=pct($data['programme']['progress']);?>%</label>
        </div>
        <?php $t = (int)($data['programme']['totals'] ?? 0); $c = (int)($data['programme']['completed'] ?? 0); if ($t > 0): ?><p class="afcdc-deliverable__meta mb-3"><?= $c; ?> of <?= $t; ?> activities delivered</p><?php endif; ?>
        <div>
            <p><strong>Description:</strong><br><?=nl2br(display($data['programme']['description']))?></p>
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
                <div class="row afcdc-drill">
                    <div class="col col-7"><a class="stretched-link" href="<?=$this->L("projects_graphs/project/".(int)$project['id']);?>"><?=display($project['name']);?></a>
                        <?php if (in_array((int)($_SESSION['user']['group']['id'] ?? 0), [1, 2, 3], true)): ?>
                            <a href="<?=$this->L("projects/progress_edit/".(int)$project['id']);?>" class="btn btn-xs btn-light border ms-2 afcdc-record-link"><i class="bx bx-edit"></i> Record delivery</a>
                        <?php endif; ?>
                    </div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=(float)$project['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$project['progress'];?>%;">
                                <?php if ((float)$project['progress'] >= 12): ?><?= pct($project['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php if ((float)$project['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($project['progress']); ?>%</span><?php endif; ?>
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