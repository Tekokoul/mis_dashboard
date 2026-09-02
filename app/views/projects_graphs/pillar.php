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
            <h2><?=display($data['pillar']['name'])?></h2>
        </div>
        <div class="gauge-chart">
            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=(float)$data['pillar']['progress']?>" role="img" aria-label="<?= display($data['pillar']['name']); ?>: <?= pct($data['pillar']['progress']); ?> percent complete"></canvas>
            <label class="gaugeBasicTextfield"><?=pct($data['pillar']['progress']);?>%</label>
        </div>
        <p class="afcdc-deliverable__meta mb-3"><?php $t = (int)($data['pillar']['totals'] ?? 0); $c = (int)($data['pillar']['completed'] ?? 0); echo $t > 0 ? "$c of $t activities delivered" : 'Nothing to measure yet'; ?></p>
        <div>
            <p><strong>Description:</strong><br><?=nl2br(display($data['pillar']['description']))?></p>
        </div>
    </div>
    <div class="col-lg-7 col-md-12">
        <div>
            <h3 class="pb-4">Included objectives</h3>
        </div>  
        <?php
        foreach ($data['pillar']['objectives'] as $objective){
            ?>
                <div class="row afcdc-drill">
                    <div class="col col-7">
                        <span class="afcdc-deliverable__wbs"><?= htmlspecialchars(trim((string)($objective['abbr'] ?? '')) ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                        <a class="stretched-link" href="<?=$this->L("projects_graphs/objective/".(int)$objective['id']);?>"><?= display($objective['name']); ?></a>
                    </div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=(float)$objective['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$objective['progress'];?>%;">
                                <?php if ((float)$objective['progress'] >= 12): ?><?= pct($objective['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php
                        $oAll = (int)($objective['totals'] ?? 0); $oDone = (int)($objective['completed'] ?? 0); $oPct = (float)$objective['progress'];
                        if ($oAll === 0)      { $st = 'idle';   $stIcon = 'bx-minus-circle'; $stText = 'Nothing to measure yet'; }
                        elseif ($oPct >= 100) { $st = 'good';   $stIcon = 'bx-check-circle'; $stText = 'Delivered'; }
                        elseif ($oPct <= 0)   { $st = 'idle';   $stIcon = 'bx-time-five';    $stText = 'Not started'; }
                        else                  { $st = 'active'; $stIcon = 'bx-adjust';       $stText = 'In progress'; }
                        ?>
                        <span class="afcdc-progress-zero">
                            <?php if ($oAll > 0 && $oPct < 12): ?><?= pct($oPct); ?>% · <?php endif; ?>
                            <?php if ($oAll > 0): ?><?= $oDone; ?> of <?= $oAll; ?> activities delivered <?php endif; ?>
                            <span class="afcdc-status afcdc-status--<?= $st; ?>"><i class="bx <?= $stIcon; ?>" aria-hidden="true"></i> <?= $stText; ?></span>
                        </span>
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