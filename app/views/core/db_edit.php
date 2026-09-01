<?php
//debug($data['data']);
$columns = 0;
$columns += (isset($data['model']['common'])) ? 1 : 0;
$columns += (isset($data['model']['languages'])) ? 1 : 0;
$columns = ($columns==0) ? 1 : $columns;

if(isset($_SESSION['user']['settings']['editing_columns'])){
    $col_width = ($_SESSION['user']['settings']['editing_columns']==1) ? 12 : 12/$columns;
} else {
    $col_width = 12/$columns;
}
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("core/db_list/".$data['model_name'])?>" ><?= display($data['meta_name']); ?></a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Edit mode</span></li>
            <li><span>Entry: <?= display($data['data']['id']); ?></span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("core/db_edit_update")?>" method="post">
    <input type="hidden" name="tablename" value="<?= display($data['model_name']); ?>" >
    <div class="row mb-4">
        <?php
        if(isset($data['model']['common'])){
            ?>
            <div class="col col-lg-<?=$col_width;?> col-md-12">
                <section class="card card-modern mb-5">
                    <div class="card-body">
                        <div class="row">
                            <div>
                                <?php
                                $html = "";
                                foreach ($data['model']['common'] as $field=>$value) {
                                    $prepopulated = $data['data'][$field] ?? "";
                                    $html .= chooseElement($field, $value, $prepopulated);
                                }
                                print $html;
                                ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <?php
        }
        if(isset($data['model']['languages'])){
            ?>
            <div class="col col-lg-<?=$col_width;?> col-md-12">
                <div class="tabs">
                    <ul class="nav nav-tabs nav-justified" role="tablist">
                        <?php
                        $first_active = "active";
                        $selected = "true";
                        foreach ($this->R->languages as $language => $properties){
                            ?>
                            <li class="nav-item <?=$first_active;?>" role="presentation">
                                <a class="nav-link <?=$first_active;?>" data-bs-target="#<?=$language;?>_tab"
                                   href="#<?=$language;?>_tab"
                                   data-bs-toggle="tab"
                                    <?php if($selected=="false"){ print ' tabindex="-1" ';}?>
                                   aria-selected="<?=$selected?>" class="text-center"><?=$properties['language'];?></a>
                            </li>
                            <?php
                            $first_active = "";
                            $selected = "false";
                        }
                        ?>
                    </ul>
                    <div class="tab-content">
                        <?php
                        $first_active = "active";
                        foreach ($data['model']['languages'] as $language => $properties){
                            ?>
                            <div id="<?=$language;?>_tab" class="tab-pane <?=$first_active;?>" role="tabpanel">
                                <div class="row">
                                    <div>
                                        <?php
                                        $html = "";
                                        foreach ($properties as $field=>$value) {
                                            $prepopulated = $data['data']['languages'][$language][$field] ?? "";
                                            $html .= chooseElement("languages[" . $language . "][" . $field . "]", $value, $prepopulated);
                                        }
                                        print $html;
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $first_active = "";
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
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
                        $link = $this->model->dynamic_link($action['link'], $data['data']);
                        print '<a href="' . $link . '" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" target="' . $action['target'] . '">
                <i class="bx ' . $action['icon'] . ' text-4 me-2"></i> ' . $action['title'] . '</a>';
                    }
                }
            }
            ?>

<!--            <a href="#" class="delete-button btn btn-danger btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1">-->
<!--                <i class="bx bx-trash text-4 me-2"></i> Delete Product-->
<!--            </a>-->
        </div>

        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-save text-4 me-2"></i> Update
            </button>
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?=$this->GoBack();?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Back</a>
        </div>
    </div>
</form>