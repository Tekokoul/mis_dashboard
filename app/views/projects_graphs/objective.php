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
        <?php $t = (int)($data['objective']['totals'] ?? 0); $c = (int)($data['objective']['completed'] ?? 0); if ($t > 0): ?><p class="afcdc-deliverable__meta mb-3"><?= $c; ?> of <?= $t; ?> activities completed</p><?php endif; ?>
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
        // Completed first, then In progress, then Not started; code order within each.
        $roll = delivery_rollup($this->DB);
        $objectiveId = (int)$data['objective']['id'];
        $prgStatus = function ($g) use ($roll, $objectiveId) { return delivery_rollup_status($roll['programme'][$objectiveId . ':' . (int)$g['id']] ?? null); };
        foreach (sort_by_delivery_status((array)$data['programmes'], $prgStatus) as $programme){
            $prj_val = ($programme['totals']>0) ? round(($programme['progress']/$programme['totals']*100), 2) : 0;
            $gStatus = $prgStatus($programme);
            ?>
                <div class="row afcdc-drill">
                    <div class="col col-7"><a class="stretched-link" href="<?=$this->L("projects_graphs/programme/".(int)$programme['id']);?>"><?=display($programme['name']);?></a> <?= delivery_status_chip($gStatus); ?></div>
                    <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar<?= delivery_status_bar($gStatus); ?>" role="progressbar" aria-valuenow="<?=(float)$programme['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$programme['progress'];?>%;">
                                <?php if ((float)$programme['progress'] >= 12): ?><?= pct($programme['progress']); ?>%<?php endif; ?>
                            </div>
                        </div>
                        <?php if ((float)$programme['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($programme['progress']); ?>%</span><?php endif; ?></div>
                        <hr>
                </div>
                <?php
        }
        if (!empty($data['other_projects'])):
            // Two kinds: activities with no programme yet (moved in to be filed,
            // each with a recommendation - tools/park-activities.php), and ones
            // filed under a programme of another objective.
            $strayGroups = [
                ['Not yet in a programme', 'They count towards this objective and wait to be filed. Each carries a recommended programme'
                    . (can_vet() ? ': see them on the <a href="' . display($this->L('projects/list') . '?' . http_build_query(['objective_id' => (int)$data['objective']['id'], 'review' => 'proposed'])) . '">Projects list</a> and press <strong>Move there</strong>, or choose another programme on the activity\'s form.' : '.'),
                    array_values(array_filter((array)$data['other_projects'], function ($p) { return (string)($p['programme_name'] ?? '') === ''; }))],
                ['Activities filed under programmes of other objectives', 'They count towards this objective on the overview but sit under a programme that belongs elsewhere. Open one to change its programme.',
                    array_values(array_filter((array)$data['other_projects'], function ($p) { return (string)($p['programme_name'] ?? '') !== ''; }))],
            ];
            foreach ($strayGroups as $sg): if (!$sg[2]) { continue; } ?>
        <div class="afcdc-other mt-4">
            <h3 class="pb-2"><?= display($sg[0]); ?></h3>
            <p class="text-muted mb-3"><?= $sg[1]; ?></p>
            <?php foreach (sort_by_delivery_status($sg[2], function ($p) use ($roll) { return delivery_rollup_status($roll['activity'][(int)$p['id']] ?? null); }) as $p): $oStatus = delivery_rollup_status($roll['activity'][(int)$p['id']] ?? null); ?>
            <div class="row afcdc-drill">
                <div class="col col-7">
                    <?= activity_flag($data['gaps'][(int)$p['id']] ?? []); ?><a class="stretched-link" href="<?=$this->L("projects_graphs/project/".(int)$p['id']);?>"><?= display(trim($p['abbr'] . ' ' . $p['name'])); ?></a> <?= delivery_status_chip($oStatus); ?><br>
                    <small class="text-muted"><?= $p['programme_name'] !== null && $p['programme_name'] !== '' ? display($p['programme_name']) : 'No programme'; ?></small>
                </div>
                <div class="col col-5"><div class="progress progress-lg progress-squared m-2">
                        <div class="progress-bar<?= delivery_status_bar($oStatus); ?>" role="progressbar" aria-valuenow="<?=(float)$p['progress'];?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=(float)$p['progress'];?>%;">
                            <?php if ((float)$p['progress'] >= 12): ?><?= pct($p['progress']); ?>%<?php endif; ?>
                        </div>
                    </div>
                    <?php if ((float)$p['progress'] < 12): ?><span class="afcdc-progress-zero"><?= pct($p['progress']); ?>%</span><?php endif; ?></div>
                    <hr>
            </div>
            <?php endforeach; ?>
        </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script nonce="<?= csp_nonce(); ?>">
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>