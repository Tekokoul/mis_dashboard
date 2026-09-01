<?php
// debug($data['data']);
?>
<section class="card">
    <header class="card-header">
        <h2 class="card-title">Update Pro</h2>
    </header>
    <form id="taskform" action="<?=$this->L("polls/pros_update");?>" method="post">
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