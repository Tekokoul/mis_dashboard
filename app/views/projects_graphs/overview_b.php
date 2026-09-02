<?php
//debug($data);
$ttl = 0;
$pgs = 0;
foreach ($data as $pillar) {
    $ttl += $pillar['totals'];
    $pgs += $pillar['progress'];
}
if($ttl>0){
    $val_all = round(($pgs/$ttl*100), 2);

} else {
    $val_all = 0;
}
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Overview</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-md-12">
        <section class="card card-light">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-chart-line"></i>&nbsp;&nbsp;<?=$this->S['graphs']['overview_title']?></h2>
            </header>
            <div class="card-body">
                <div class="progress progress-xl progress-squared m-2">
                    <div class="progress-bar" role="progressbar" aria-valuenow="<?=$val_all;?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$val_all;?>%;">
                        <?=pct(($val_all));?>%
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <section class="card">
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    foreach ($data as $pillar){
                        if($pillar['totals']>0){
                            $value = ($pillar['progress']/$pillar['totals']*100);
                        } else {
                            $value = 0;
                        }
//$value = rand(0,100);
                    ?>
                    <div class="col-lg-4">
                        <div class="gauge-chart">
                            <canvas class="gaugeBasic" width="350" height="200" data-value="<?=$value?>"></canvas>
                            <strong><a href="<?=$this->L("projects_graphs/pillar/".$pillar['id']);?>"><?=$pillar['name']?></a></strong>
                            <label id="gaugeBasicTextfield"><?=pct($value);?>%</label>
                        </div>
                        <?php
                        foreach ($pillar['programmes'] as $programme){
                        if($programme['totals']>0) {
                            $obj_value = ($programme['progress']/$programme['totals']*100);
                        } else {
                            $obj_value = 0;

                        }
//                            $obj_value = rand(0,100);

                            ?>
                            <h5><?=$programme['abbr']." - ".$programme['name'];?></h5>
                        <div class="progress progress-lg progress-squared m-2">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?=$obj_value;?>" aria-valuemin="0" aria-valuemax="100" style="width: <?=$obj_value;?>%;">
                                <?=pct(($obj_value));?>%
                            </div>
                        </div>
                        <?php
                        }
                        ?>
                    </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    var graph_color = '<?=_PROJECT_COLOR;?>';
</script>