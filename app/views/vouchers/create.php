<?php
//debug($data);
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><a href="<?=$this->L("orders/edit/".$data['data']['order_reference'])?>" >Create Voucher</a></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span>Add mode</span></li>
            <li><span>New Entry</span></li>
        </ol>
    </div>
</header>
<form class="ecommerce-form action-buttons-fixed" action="<?=$this->L("vouchers/create_update")?>" method="post">
<!--    <input type="hidden" name="order_reference" value="--><?php //= display($data['data']['order_reference']); ?><!--" >-->
    <input type="hidden" name="courier" value="<?= display($data['courier']); ?>" >
    <div class="row">
        <?php
        if(isset($data['model']['common'])){
            ?>
            <div class="col col-lg-12 col-md-12">
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
        ?>
    </div>
    <div class="row action-buttons">
        <div class="col-12 col-md-auto">
            <?php
            if(isset($data['meta_actions'])){
                foreach ($data['meta_actions'] as $action){
                    print '<a href="'.$this->model->dynamic_link($action['link'], $data['data']).'" class="btn btn-default btn-px-4 py-3 line-height-1 me-2" target="'.$action['target'].'">
                <i class="bx '.$action['icon'].' text-4 me-2"></i> '.$action['title'].'
            </a>';
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
            <a href="<?=$_SERVER['HTTP_REFERER'];?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Back</a>
        </div>
    </div>
</form>
