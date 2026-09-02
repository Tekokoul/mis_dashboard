<?php
//debug($data);
$columns = 2;
$col_width = 12/$columns;
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("projects/progress_list")?>" >Progress</a> &rsaquo; Record delivery</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Edit mode</span></li>
            <li><span>Entry: <?= display($data['data']['id']); ?></span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("projects/progress_edit_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <div class="row mb-4">
            <div class="col col-lg-<?=$col_width;?> col-md-12">
                <section class="card card-modern mb-5">
                    <div class="card-body">
                        <div class="row">
                            <div>
                                <?php
                                $html = "";
                                foreach ($data['model']['common'] as $field=>$value) {
                                    $prepopulated = $data['data'][$field] ?? "";

//                                    debug($field." -> ".$prepopulated);
                                    $html .= chooseElement($field, $value, $prepopulated);
                                }
                                print $html;
                                ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col col-lg-<?=$col_width;?> col-md-12">
            <?php if (($_GET['saved'] ?? '') === '1'): ?>
            <div id="afcdc-saved" class="alert alert-success py-2 mb-3" role="status" tabindex="-1">
                <i class="bx bx-check-circle" aria-hidden="true"></i> <strong>Delivery recorded.</strong> Every gauge on the overview now reflects it.
                <a href="<?= $this->L('projects_graphs/overview'); ?>" class="alert-link">View overview</a>
            </div>
            <?php endif; ?>
            <p class="afcdc-note mb-3">To mark this activity as done, click <strong>Record delivery</strong> on its KPI,
                set <strong>Delivery status</strong> to <strong>Delivered</strong> and press <strong>Save delivery</strong>.
                Every gauge on the dashboard recalculates immediately.</p>
            <div id="project_details" aria-busy="true">
                <div class="card card-modern"><div class="card-body"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span role="status">Loading KPIs…</span></div></div>
            </div>
            </div>

    </div>
    <div class="row action-buttons nopadding">
        <div class="col-12 col-md-auto">
            <?php
            if(isset($data['meta_actions'])){
                foreach ($data['meta_actions'] as $action) {
                    $show_action = false;

                    if (isset($action['condition'])) {
                        if (ce_compare_values($data['data'][$action['condition']['field']], $action['condition']['operator'], $action['condition']['value'])) {
                            $show_action = true;
                        }
                    } else {
                        $show_action = true;
                    }
                    if ($show_action) {
                        print '<a href="' . $this->model->dynamic_link($action['link'], $data['data']) . '" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" target="' . $action['target'] . '">
                <i class="bx ' . $action['icon'] . ' text-4 me-2"></i> ' . $action['title'] . '
            </a>';
                    }
                }
            }
            ?>

            <!--            <a href="#" class="delete-button btn btn-danger btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1">-->
            <!--                <i class="bx bx-trash text-4 me-2"></i> Delete Product-->
            <!--            </a>-->
        </div>

        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?=$this->L("projects/progress_list");?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Back</a>
        </div>
    </div>
</form>

<script>
    var project_id = <?=(int)$data['data']['id'];?>;
    var project_type = <?=json_encode((string)($data['data']['type'] ?? ''), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);?>;
    // The "Delivery recorded" strip: focus is what gets it announced on a
    // fresh load; dropping ?saved=1 from the URL stops F5 re-announcing it.
    (function () {
        var s = document.getElementById('afcdc-saved');
        if (s) { s.focus(); if (history.replaceState) { history.replaceState(null, '', location.pathname); } }
    })();
</script>