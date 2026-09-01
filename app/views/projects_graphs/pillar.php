<?php
// debug($data);
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?= $this->L("projects_graphs/".$this->S['graphs']['overview_link']);?>">Overview</a> &rsaquo; <?=$this->S['graphs']['pillar_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-5 col-md-12">
        <div>
            <h2><?=$data['pillar']['name']?></h2>
        </div>   
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$data['pillar']['progress']?>"></canvas>
            <label id="gaugeBasicTextfield"><?=number_format((float)$data['pillar']['progress'], 2, ',', '');?>%</label>
        </div>
        <div>
            <p><strong>Description:</strong><br><?=$data['pillar']['description']?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
        <div>
            <h3 class="pb-4">Included objectives</h3>
        </div>  
        <?php
        foreach ($data['pillar']['objectives'] as $objective){
            ?>
                <div class="row">
                    <div class="col col-7">
                        <span class="afcdc-deliverable__wbs"><?= htmlspecialchars(trim((string)($objective['abbr'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                        <a href="<?=$this->L("projects_graphs/objective/".$objective['id']);?>"><?= htmlspecialchars($objective['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$objective['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$objective['progress'];?>%;">
                                <?=($objective['progress']);?>%
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