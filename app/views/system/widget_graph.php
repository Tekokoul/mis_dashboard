<?php
$graph_data = [];
foreach ($data['graph'] as $datum) {
    $graph_data[] = [
        "x" => $datum[$data['settings']['graph']['x_axis']],
        "y" => $datum[$data['settings']['graph']['y_axis']]
    ];
}
?>
<div class="col-lg-12 col-xl-8 pt-2 pt-xl-0 mt-4 mt-xl-0">
    <div class="row">
        <div class="col">
            <div class="card card-modern">
                <div class="card-header">
                    <h2 class="card-title"><?=$data['settings']['graph']['title']?></h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        foreach ($data['settings']['graph']['sums'] as $sum){
                            $values = array_sum(array_column($data['graph'], $sum['values']));
                            ?>
                            <div class="col-auto">
                                <strong class="text-color-dark text-6"><?=$sum['function']($values)?></strong>
                                <h3 class="text-4 mt-0 mb-2"><?=$sum['title']?></h3>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="row">
                        <div class="col">
                            <!-- Morris: Area -->
                            <div class="chart chart-md chart-bar-stacked-sm my-3" id="revenueChart" style="height: 409px;"></div>
                            <script type="text/javascript">
                                var graph_color = '<?=_WHITELABEL ? _PROJECT_COLOR : '#5f78ea';?>';
                                var revenueChartData = <?= json_encode($graph_data);?> ;
                                var x_axis_label = <?="'".$data['settings']['graph']['x_axis_label']."'"?> ;
                                var y_axis_label = <?="'".$data['settings']['graph']['y_axis_label']."'";?> ;
                                // See: js/examples/examples.charts.js for more settings.
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>