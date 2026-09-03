<?php
//debug($data['monthly']);
$sorted_data = $data['progress'];
$progress = array_column($sorted_data, 'progress');
array_multisort($progress, SORT_DESC, $sorted_data);
?>
<script nonce="<?= csp_nonce(); ?>">
    var ms_sorted = [];
    var ms_chart = [];
    var ms_monthly = [];
    var member_keys = [];
    var member_labels = [];
</script>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?=$this->S['graphs']['members_title']?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col-lg-6 col-md-12 col-sm-12">
        <div class="card card-modern">
            <div class="card-header">
                <h2 class="card-title"><?=$this->S['graphs']['members_ranking']?></h2>
            </div>
            <div class="card-body">
                <script nonce="<?= csp_nonce(); ?>">
                <?php
                foreach ($sorted_data as $member){
                    if($member['totals']>0){
                        $total = round((100-$member['progress']/$member['totals']*100),2);
                        $completed = round((($member['progress']/$member['totals']*100)),2);
                    } else {
                        $total = 0;
                        $completed = 0;
                    }
                    ?>
ms_sorted.push({ country: <?=json_encode((string)$member['name'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);?>, total: <?=(float)$total;?>, completed: <?=(float)$completed;?>});
                    <?php
                }
                ?>
                </script>
                <div id="ChartistRanking" class="ct-chart-tasks ct-square"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-12 col-sm-12">
        <div class="card card-modern">
            <div class="card-header">
                <h2 class="card-title"><?=$this->S['graphs']['members_overall_tasks']?></h2>
            </div>
            <div class="card-body">
                <script nonce="<?= csp_nonce(); ?>">
                <?php
                foreach ($data['progress'] as $member){
                    ?>
ms_chart.push({ country: <?=json_encode((string)$member['name'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);?>, total: <?=(float)$member['totals'];?>, completed: <?=(float)$member['progress'];?>});
                        <?php
                }
                ?>
                </script>
                <div id="ChartistOverallTasks" class="ct-chart-tasks ct-square"></div>
        </div>
    </div>
    </div>
</div>
<div class="row">
    <div class="col-12 mt-3">
        <div class="card card-modern">
            <div class="card-header">
                <h2 class="card-title">Completed Projects / Month</h2>
            </div>
            <div class="card-body">
                <script nonce="<?= csp_nonce(); ?>">
                    <?php
                foreach ($data['monthly'] as $year => $months){
                    foreach ($months as $month => $values) {
                        print "ms_monthly.push({ year: '".$year."', month: '".$month."',";
                        $list = [];
                        foreach ($data['members'] as $member){
                            $list[] = "'".(int)$member['id']."': ".(int)($values[$member['id']]['tasks'] ?? 0);
                        }
                        print implode(",", $list)."});\n";
                    }
                }
                print "member_keys.push(";
                $list = [];
                foreach ($data['members'] as $member) {
                    $list[] = "'".(int)$member['id']."'";
                }
                print implode(",", $list).");\n";

                // Names go through json_encode with the HEX flags: addslashes
                // escapes quotes but not "</script>", so a division user named
                // `x</script><b>..` used to close the block and inject markup.
                print "member_labels.push(";
                $list = [];
                foreach ($data['members'] as $member) {
                    $list[] = json_encode((string)$member['name'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
                }
                print implode(",", $list).");\n";
                    ?>
                </script>
                <div id="ChartistMonthly" class="ct-chart-tasks ct-major-tenth"></div>
            </div>
    </div>
</div>
