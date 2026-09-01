<?php
//debug($data['data']);
$action = !empty($data['data']['id']) ? "Edit " : "Add ";
?>
<section class="card">
    <header class="card-header">
        <h2 class="card-title"><?=$action.$data['meta_name'];?></h2>
    </header>
    <form id="taskform" action="<?=$this->L("constituencies/ward_update");?>" method="post">
        <div class="card-body">
            <div class="row">
                <div>
                <input type="hidden" name="tablename" value="<?=$data['model_name'];?>">
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
        <footer class="card-footer">
            <div class="row">
                <div class="col-md-12 text-end">
                    <button class="btn btn-primary modal-confirm">Update</button>
                    <button class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </div>
        </footer>
    </form>
</section>